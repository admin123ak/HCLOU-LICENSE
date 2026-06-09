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

        $u = $this->userModel->where('api_token', $token)->first();
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
            $this->log('buy', $game, $duration, 0, null, 'DURATION_NOT_FOUND');
            return $this->json(['status'=>false,'reason'=>'DURATION_NOT_FOUND'], 404);
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
            // Không có pool -> sinh key mới
            if ($license === null) {
                $license = random_string('alnum', 16);
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
