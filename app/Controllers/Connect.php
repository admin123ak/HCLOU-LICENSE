<?php

namespace App\Controllers;

use App\Models\KeysModel;

class Connect extends BaseController
{
    protected $model, $game, $uKey, $sDev;
    protected $encCtx = null; // context mã hoá response (nếu request đến dạng mã hoá)

    public function __construct()
    {
        $this->model = new KeysModel();
        $this->maintenance = false;
        // Bí mật ký token: ưu tiên đọc từ .env (connect.secret). Fallback giá trị cũ
        // để không phá app đang chạy. NÊN đặt giá trị MỚI trong .env + để repo PRIVATE.
        $this->staticWords = env('connect.secret') ?: "Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E";
    }

    /** Kiểm tra bảng games đã tồn tại chưa (tương thích panel chưa import) */
    private function gamesTableExists()
    {
        try {
            return db_connect()->tableExists('games');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Lấy private key RSA (PEM) từ .env (connect.rsaPrivate = base64 của PEM).
     * Để repo có public mà private vẫn KHÔNG nằm trong source.
     */
    private function rsaPrivatePem()
    {
        $b64 = env('connect.rsaPrivate');
        if (!$b64) return null;
        $pem = base64_decode($b64, true);
        return $pem ?: null;
    }

    /** Ký payload bằng RSA private key (SHA256). Trả base64, hoặc null nếu chưa cấu hình. */
    private function signRSA($payload)
    {
        if (!function_exists('openssl_sign')) return null;
        $pem = $this->rsaPrivatePem();
        if (!$pem) return null;
        $pkey = openssl_pkey_get_private($pem);
        if (!$pkey) return null;
        $sig = '';
        if (!openssl_sign($payload, $sig, $pkey, OPENSSL_ALGO_SHA256)) return null;
        return base64_encode($sig);
    }

    /** Bí mật mã hoá request (đối xứng). Đặt connect.apiSecret trong .env. */
    private function apiSecret()
    {
        return env('connect.apiSecret') ?: $this->staticWords;
    }

    /** Khoá AES phiên (32 byte) suy từ secret + timestamp. */
    private function sessionKey($ts)
    {
        return hash('sha256', $this->apiSecret() . '|' . $ts, true);
    }

    /** Khoá HMAC suy từ secret + nonce. */
    private function macKey($nonce)
    {
        return hash('sha256', $this->apiSecret() . '|' . $nonce . '|mac', true);
    }

    /**
     * Giải mã request mã hoá. Trả ['game','key','hwid'] hoặc null (set $err).
     * Client gửi: d (base64 iv+ciphertext AES-256-CBC), t (ts), n (nonce), m (HMAC base64).
     */
    private function decryptRequest(&$err)
    {
        $req = $this->request;
        $d = (string) $req->getPost('d');
        $t = (string) $req->getPost('t');
        $n = (string) $req->getPost('n');
        $m = (string) $req->getPost('m');
        if ($d === '' || $t === '' || $n === '' || $m === '') { $err = 'BAD_ENC_FORMAT'; return null; }

        // Chống replay: timestamp lệch tối đa 300s
        if (abs(time() - (int) $t) > 300) { $err = 'REQUEST_EXPIRED'; return null; }

        // Verify HMAC (chống sửa request)
        $expectMac = base64_encode(hash_hmac('sha256', $d . '|' . $t . '|' . $n, $this->macKey($n), true));
        if (!hash_equals($expectMac, $m)) { $err = 'BAD_MAC'; return null; }

        // Giải mã AES-256-CBC
        $raw = base64_decode($d, true);
        if ($raw === false || strlen($raw) <= 16) { $err = 'BAD_CIPHER'; return null; }
        $iv = substr($raw, 0, 16);
        $ct = substr($raw, 16);
        $plain = openssl_decrypt($ct, 'aes-256-cbc', $this->sessionKey($t), OPENSSL_RAW_DATA, $iv);
        if ($plain === false) { $err = 'DECRYPT_FAIL'; return null; }

        $obj = json_decode($plain, true);
        if (!is_array($obj)) { $err = 'BAD_JSON'; return null; }

        // Lưu context để MÃ HOÁ response (2 chiều như Kuro)
        $this->encCtx = ['t' => $t, 'n' => $n, 'sk' => $this->sessionKey($t)];
        return $obj;
    }

    /**
     * Trả response. Nếu request đến dạng mã hoá -> mã hoá luôn response (AES+HMAC),
     * ngược lại trả JSON thường (app cũ). Phần data vẫn chứa chữ ký RSA bên trong.
     */
    private function reply($data)
    {
        if (!$this->encCtx) {
            return $this->response->setJSON($data);
        }
        $ts = $this->encCtx['t']; $nonce = $this->encCtx['n']; $sk = $this->encCtx['sk'];
        $iv = random_bytes(16);
        $ct = openssl_encrypt(json_encode($data), 'aes-256-cbc', $sk, OPENSSL_RAW_DATA, $iv);
        $d  = base64_encode($iv . $ct);
        $mac = base64_encode(hash_hmac('sha256', $d . '|' . $ts . '|' . $nonce, $this->macKey($nonce), true));
        $wrap = base64_encode(json_encode(['x' => $d, 't' => $ts, 'n' => $nonce, 'm' => $mac]));
        return $this->response->setContentType('text/plain')->setBody($wrap);
    }

    /** Chặn xem endpoint qua trình duyệt (cần header X-API-Client). */
    private function isBrowserDirect()
    {
        $ua = $this->request->getServer('HTTP_USER_AGENT') ?? '';
        $hasClient = $this->request->getHeaderLine('X-API-Client') !== '';
        return (preg_match('/(Mozilla|Chrome|Safari|Firefox|Edge|Opera)/i', $ua) && !$hasClient);
    }

    public function index()
    {
        // Chặn truy cập trực tiếp bằng trình duyệt (như Kuro)
        if ($this->isBrowserDirect()) {
            return $this->response->setStatusCode(403)->setBody(
                '<!doctype html><html><head><title>Access Denied</title><style>body{background:#0d1117;color:#c9d1d9;font-family:sans-serif;display:flex;height:100vh;align-items:center;justify-content:center;margin:0}div{text-align:center}</style></head><body><div><h1>403</h1><p>This endpoint is for the app client only.</p></div></body></html>'
            );
        }

        if ($this->request->getPost()) {
            return $this->index_post();
        }
        // GET: KHÔNG lộ license/version/author. Chỉ trả tối thiểu.
        return $this->response->setStatusCode(405)->setJSON([
            'status' => false,
            'reason' => 'METHOD_NOT_ALLOWED'
        ]);
    }

    public function index_post()
    {
        // Rate limit chống dò/brute key: tối đa 60 lần/phút theo IP
        $throttler = \Config\Services::throttler();
        if ($throttler->check(md5('connect_' . $this->request->getIPAddress()), 60, MINUTE) === false) {
            return $this->response->setStatusCode(429)->setJSON([
                'status' => false,
                'reason' => 'TOO MANY REQUESTS'
            ]);
        }

        $isMT = $this->maintenance;

        // === Request MÃ HOÁ (AES-256 + HMAC + timestamp + nonce) — như Kuro ===
        // App mới gửi field 'd' (ciphertext). App cũ vẫn gửi game/user_key/serial plaintext.
        $encField = $this->request->getPost('d');
        if ($encField !== null && $encField !== '') {
            $dec = $this->decryptRequest($encErr);
            if ($dec === null) {
                return $this->response->setJSON(['status' => false, 'reason' => $encErr]);
            }
            $game = $dec['game'] ?? null;
            $uKey = $dec['key']  ?? null;
            $sDev = $dec['hwid'] ?? null;
        } else {
            $game = $this->request->getPost('game');
            $uKey = $this->request->getPost('user_key');
            $sDev = $this->request->getPost('serial'); // serial = HWID
        }

        // Validate thủ công (áp dụng cho cả 2 path)
        if (!$game || !$uKey || !$sDev
            || !preg_match('/^[A-Za-z0-9_-]{1,32}$/', (string)$game)
            || !preg_match('/^[A-Za-z0-9]{1,36}$/', (string)$uKey)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/', (string)$sDev)) {
            return $this->reply(['status' => false, 'reason' => 'Bad Parameter']);
        }

        if ($isMT) {
            $data = [
                'status' => false,
                'reason' => 'MAINTENANCE'
            ];
        } else {
            if (!$game or !$uKey or !$sDev) {
                $data = [
                    'status' => false,
                    'reason' => 'INVALID PARAMETER'
                ];
            } else {
                $time = new \CodeIgniter\I18n\Time;
                $model = $this->model;

                // 1) Game phải tồn tại + đang bật trong hệ thống
                $gameRow = (new \App\Models\GameModel())
                    ->where('game_code', $game)->where('status', 1)->first();
                // Nếu chưa import bảng games -> bỏ qua check này (tương thích cũ)
                $gamesTableExists = $this->gamesTableExists();
                if ($gamesTableExists && !$gameRow) {
                    return $this->reply([
                        'status' => false,
                        'reason' => 'GAME NOT FOUND'
                    ]);
                }

                // 2) Tìm key CHỈ theo user_key (để phân biệt sai key vs sai game)
                $anyKey = $model->getKeys($uKey, 'user_key');
                if (!$anyKey) {
                    return $this->reply([
                        'status' => false,
                        'reason' => 'INVALID KEY'
                    ]);
                }
                // 3) Key tồn tại nhưng KHÔNG dành cho game app đang gửi
                if ($anyKey->game !== $game) {
                    return $this->reply([
                        'status' => false,
                        'reason' => 'WRONG GAME',
                        'key_for' => $anyKey->game
                    ]);
                }

                $findKey = $anyKey; // key đúng game

                if ($findKey) {
                    if ($findKey->status != 1) {
                        $data = [
                            'status' => false,
                            'reason' => 'USER BLOCKED'
                        ];
                    } else {
                        $id_keys = $findKey->id_keys;
                        $duration = $findKey->duration;
                        $expired = $findKey->expired_date;
                        $max_dev = $findKey->max_devices;
                        $devices = $findKey->devices;
    
                        function checkDevicesAdd($serial, $devices, $max_dev)
                        {
                            $lsDevice = explode(",", $devices);
                            $cDevices = isset($devices) ? count($lsDevice) : 0;
                            $serialOn = in_array($serial, $lsDevice);
    
                            if ($serialOn) {
                                return true;
                            } else {
                                if ($cDevices < $max_dev) {
                                    array_push($lsDevice, $serial);
                                    $setDevice = reduce_multiples(implode(",", $lsDevice), ",", true);
                                    return ['devices' => $setDevice];
                                } else {
                                    // ! false - devices max
                                    return false;
                                }
                            }
                        }
    
                        if (!$expired) {
                            $setExpired = $time::now()->addHours($duration);
                            $model->update($id_keys, ['expired_date' => $setExpired]);
                            $data['status'] = true;
                        } else {
                            if ($time::now()->isBefore($expired)) {
                                $data['status'] = true;
                            } else {
                                $data = [
                                    'status' => false,
                                    'reason' => 'EXPIRED KEY'
                                ];
                            }
                        }
    
                        if ($data['status']) {
                            $devicesAdd = checkDevicesAdd($sDev, $devices, $max_dev);
                            if ($devicesAdd) {
                                if (is_array($devicesAdd)) {
                                    $model->update($id_keys, $devicesAdd);
                                }
                                // ? game-user_key-serial-word di line 15
                                $ts = $time->getTimestamp();
                                $real = "$game-$uKey-$sDev-$this->staticWords";
                                // payload để app verify bằng RSA public key (chống giả mạo token)
                                $payload = "$game|$uKey|$sDev|$ts";
                                $data = [
                                    'status' => true,
                                    'data' => [
                                        // token md5 CŨ: giữ tạm cho app cũ. BỎ sau khi app dùng RSA.
                                        'token' => md5($real),
                                        // === RSA (mới) — app verify chữ ký bằng PUBLIC key ===
                                        'payload' => $payload,
                                        'sig'     => $this->signRSA($payload),
                                        'rng' => $ts
                                    ],
                                ];
                            } else {
                                $data = [
                                    'status' => false,
                                    'reason' => 'MAX DEVICE REACHED'
                                ];
                            }
                        }
                    }
                } else {
                    $data = [
                        'status' => false,
                        'reason' => 'USER OR GAME NOT REGISTERED'
                    ];
                }
            }
        }
        return $this->reply($data);
    }
}
