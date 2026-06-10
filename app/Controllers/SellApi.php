<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\GameModel;
use App\Models\KeysModel;
use CodeIgniter\Controller;

/**
 * ============================================================
 *  SELL API — endpoint cho BOT bán key bên ngoài.
 *  Xác thực bằng api_token của reseller/user trong panel.
 *
 *  Base: /api/sell/...
 *    POST/GET  products   -> list gói game đang mở bán
 *    POST      buy        -> mua 1 key (trừ tiền, trả key)
 *    GET       balance    -> số dư hiện tại
 *
 *  Token gửi qua: header "Authorization: Bearer <token>"
 *                hoặc tham số ?token= / POST token
 * ============================================================
 */
class SellApi extends Controller
{
    protected $user = null;
    protected $userModel;
    protected $gameModel;
    protected $keysModel;
    protected $db;

    public function __construct()
    {
        // SellApi extends Controller thẳng (không qua BaseController) nên phải tự
        // load text helper -> random_string() dùng trong buy() mới hoạt động.
        helper('text');
        $this->userModel = new UserModel();
        $this->gameModel = new GameModel();
        $this->keysModel = new KeysModel();
        $this->db = \Config\Database::connect();
    }

    /** Lấy token từ request (header Bearer / token param) */
    private function getToken()
    {
        $req = service('request');
        $auth = $req->getHeaderLine('Authorization');
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) return trim($m[1]);
        return trim((string) ($req->getPost('token') ?? $req->getGet('token') ?? ''));
    }

    /** Xác thực token -> set $this->user; trả null nếu fail */
    private function auth(&$err)
    {
        $token = $this->getToken();
        if ($token === '') { $err = 'MISSING_TOKEN'; return false; }
        if (!$this->db->tableExists('users')) { $err = 'DB_ERROR'; return false; }

        // LƯU Ý: Model mặc định returnType='array' -> phải lấy OBJECT (get()->getFirstRow())
        // giống getUser(), nếu không $u->api_enabled trên array = null -> API_DISABLED nhầm.
        $u = $this->userModel->where('api_token', $token)->get()->getFirstRow();
        if (!$u) { $err = 'INVALID_TOKEN'; return false; }
        if (empty($u->api_enabled)) { $err = 'API_DISABLED'; return false; }
        if (isset($u->status) && (int)$u->status !== 1) { $err = 'ACCOUNT_BLOCKED'; return false; }

        $this->user = $u;
        return true;
    }

    private function json($arr, $code = 200)
    {
        return $this->response->setStatusCode($code)->setJSON($arr);
    }

    /** Ghi log giao dịch API (best effort) */
    private function log($action, $game = null, $dur = null, $price = 0, $key = null, $result = 'ok')
    {
        try {
            if (!$this->db->tableExists('api_logs')) return;
            $this->db->table('api_logs')->insert([
                'user_id'   => $this->user->id_users ?? null,
                'action'    => $action,
                'game_code' => $game,
                'duration'  => $dur,
                'price'     => $price,
                'user_key'  => $key,
                'ip'        => service('request')->getIPAddress(),
                'result'    => $result,
                'created_at'=> date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) { /* ignore */ }
    }

    // =========================================================
    //  GET/POST /api/sell/products  -> danh sách gói game mở bán
    // =========================================================
    public function products()
    {
        if (!$this->auth($err)) return $this->json(['status' => false, 'reason' => $err], 401);

        $hasApiSale = $this->db->fieldExists('api_sale', 'games');
        $games = $this->gameModel->where('status', 1);
        if ($hasApiSale) $games->where('api_sale', 1);
        $games = $games->orderBy('sort_order', 'ASC')->findAll();

        $out = [];
        foreach ($games as $g) {
            $durs = GameModel::parseDurations($g['durations']);
            $packs = [];
            foreach ($durs as $d) {
                $packs[] = [
                    'duration' => $d['hours'],
                    'label'    => $this->durLabel($d['hours']),
                    'price'    => (float) $d['price'],
                ];
            }
            $out[] = [
                'game'      => $g['game_code'],
                'name'      => $g['name'],
                'packages'  => $packs,
            ];
        }

        return $this->json([
            'status'  => true,
            'balance' => (float) $this->user->saldo,
            'games'   => $out,
        ]);
    }

    private function durLabel($h)
    {
        $h = (int) $h;
        if ($h % 720 === 0) return ($h / 720) . ' Months';
        if ($h % 24 === 0)  return ($h / 24) . ' Days';
        return $h . ' Hours';
    }

    // =========================================================
    //  POST /api/sell/keyinfo  -> trạng thái thật của key (cho web bán đồng bộ)
    //  params: keys = "key1,key2,..." (hoặc 1 key)
    //  Trả devices/max/expired/status thật từ panel theo từng user_key.
    // =========================================================
    public function keyinfo()
    {
        if (!$this->auth($err)) return $this->json(['status' => false, 'reason' => $err], 401);

        $raw = service('request')->getPost('keys');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out = [];
        $now = new \CodeIgniter\I18n\Time('now');
        foreach ($list as $uk) {
            $uk = trim((string) $uk);
            if ($uk === '') continue;
            // Chỉ key do chính token-user tạo (bảo mật)
            $k = $this->keysModel->where('user_key', $uk)
                ->where('registrator', $this->user->username)->get()->getRowArray();
            if (!$k) { $out[$uk] = null; continue; }

            // Đếm thiết bị từ chuỗi devices (CSV serial)
            $devCount = 0;
            if (!empty($k['devices'])) {
                $devCount = count(array_filter(explode(',', $k['devices']), fn($s) => trim($s) !== ''));
            }
            $exp = $k['expired_date'] ?? null;
            $isExpired = false;
            if (!empty($exp)) {
                try { $isExpired = (new \CodeIgniter\I18n\Time($exp))->isBefore($now); } catch (\Throwable $e) {}
            }
            $out[$uk] = [
                'devices'      => $devCount,
                'max_devices'  => (int) $k['max_devices'],
                'duration'     => (int) $k['duration'],
                'expired_date' => $exp,                 // NULL = chưa kích hoạt (chưa login)
                'activated'    => !empty($exp),
                'locked'       => ((int) $k['status']) !== 1, // status 0 = khoá
                'expired'      => $isExpired,
            ];
        }
        return $this->json(['status' => true, 'keys' => $out]);
    }

    // =========================================================
    //  POST /api/sell/resetkey  -> reset thiết bị của 1 key (xoá devices)
    //  params: key = user_key
    // =========================================================
    public function resetkey()
    {
        if (!$this->auth($err)) return $this->json(['status' => false, 'reason' => $err], 401);

        $uk = trim((string) service('request')->getPost('key'));
        if ($uk === '') return $this->json(['status' => false, 'reason' => 'MISSING_KEY'], 400);

        $k = $this->keysModel->where('user_key', $uk)
            ->where('registrator', $this->user->username)->get()->getRowArray();
        if (!$k) { $this->log('reset', null, null, 0, $uk, 'KEY_NOT_FOUND'); return $this->json(['status' => false, 'reason' => 'KEY_NOT_FOUND'], 404); }

        // Xoá danh sách thiết bị -> số thiết bị về 0, khách đổi máy login lại được
        $this->keysModel->update($k['id_keys'], ['devices' => null]);
        $this->log('reset', $k['game'] ?? null, (int)($k['duration'] ?? 0), 0, $uk, 'ok');
        return $this->json(['status' => true, 'key' => $uk]);
    }

    // =========================================================
    //  GET /api/sell/balance  -> số dư
    // =========================================================
    public function balance()
    {
        if (!$this->auth($err)) return $this->json(['status' => false, 'reason' => $err], 401);
        return $this->json([
            'status'   => true,
            'username' => $this->user->username,
            'balance'  => (float) $this->user->saldo,
        ]);
    }

    // =========================================================
    //  POST /api/sell/buy  -> mua key (trừ tiền, trả key)
    //  params: game, duration, max_devices(optional=1)
    // =========================================================
    public function buy()
    {
        if (!$this->auth($err)) return $this->json(['status' => false, 'reason' => $err], 401);

        $req     = service('request');
        $game    = strtoupper(trim((string) $req->getPost('game')));
        $duration= (int) $req->getPost('duration');
        $maxDev  = (int) ($req->getPost('max_devices') ?: 1);
        if ($maxDev < 1) $maxDev = 1;

        if ($game === '' || $duration < 1) {
            return $this->json(['status' => false, 'reason' => 'INVALID_PARAMETER'], 400);
        }

        // 1) Game tồn tại + đang mở bán API
        $g = $this->gameModel->where('game_code', $game)->where('status', 1)->first();
        if (!$g) { $this->log('buy', $game, $duration, 0, null, 'GAME_NOT_FOUND'); return $this->json(['status'=>false,'reason'=>'GAME_NOT_FOUND'], 404); }
        if ($this->db->fieldExists('api_sale', 'games') && empty($g['api_sale'])) {
            $this->log('buy', $game, $duration, 0, null, 'GAME_SALE_OFF');
            return $this->json(['status'=>false,'reason'=>'GAME_SALE_DISABLED'], 403);
        }

        // 2) Gói (duration) phải tồn tại trong game -> lấy giá
        $priceMap = GameModel::priceMap($g['durations']);
        if (!isset($priceMap[$duration])) {
            $this->log('buy', $game, $duration, 0, null, 'DUR_404');
            return $this->json([
                'status'    => false,
                'reason'    => 'DURATION_NOT_FOUND',
                'sent'      => $duration,
                'available' => array_keys($priceMap), // các duration (giờ) hợp lệ của game này
                'hint'      => 'Fix package: api_duration must be one of these (hours) values. 1 day=24, 1 month=720.',
            ], 404);
        }
        $price = (float) $priceMap[$duration] * $maxDev;

        // 3) Đủ tiền?
        if ((float) $this->user->saldo < $price) {
            $this->log('buy', $game, $duration, $price, null, 'INSUFFICIENT');
            return $this->json([
                'status' => false,
                'reason' => 'INSUFFICIENT_BALANCE',
                'need'   => $price,
                'balance'=> (float) $this->user->saldo,
            ], 402);
        }

        // 4) Tạo key + trừ tiền (transaction)
        $this->db->transStart();
        try {
            // Ưu tiên lấy từ POOL nếu có key sẵn (đúng game + duration + chưa bán)
            $license = null;
            if ($this->db->tableExists('keys_pool')) {
                $poolRow = $this->db->table('keys_pool')
                    ->where('game_code', $game)->where('duration', $duration)
                    ->where('is_sold', 0)->where('status', 1)
                    ->orderBy('id_pool', 'ASC')->get(1)->getRowArray();
                if ($poolRow) {
                    $license = $poolRow['user_key'];
                    $this->db->table('keys_pool')->where('id_pool', $poolRow['id_pool'])->update(['is_sold' => 1]);
                }
            }
            // Không có pool -> sinh key mới.
            // Dùng random_bytes (PHP gốc) thay random_string (cần text helper) -> không phụ thuộc helper.
            if ($license === null) {
                $license = bin2hex(random_bytes(8)); // 16 ký tự hex
            }

            // Insert vào keys_code (key chính, app verify từ đây)
            $this->keysModel->insert([
                'game'        => $game,
                'user_key'    => $license,
                'duration'    => $duration,
                'max_devices' => $maxDev,
                'status'      => 1,
                'registrator' => $this->user->username,
            ]);

            // Trừ tiền
            $newSaldo = (float) $this->user->saldo - $price;
            $this->userModel->update($this->user->id_users, ['saldo' => $newSaldo]);

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new \Exception('TRANSACTION_FAILED');
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', '[SELLAPI_BUY] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->log('buy', $game, $duration, $price, null, 'ERROR');
            return $this->json(['status'=>false,'reason'=>'SERVER_ERROR','detail'=>$e->getMessage()], 500);
        }

        $this->log('buy', $game, $duration, $price, $license, 'ok');
        return $this->json([
            'status'      => true,
            'game'        => $game,
            'duration'    => $duration,
            'max_devices' => $maxDev,
            'price'       => $price,
            'key'         => $license,
            'balance'     => $newSaldo,
        ]);
    }
}
