<?php

namespace App\Controllers;

use App\Models\KeysModel;

class Connect extends BaseController
{
    protected $model, $game, $uKey, $sDev;

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

    public function index()
    {
        if ($this->request->getPost()) {
            return $this->index_post();
        } else {
            $nata = [
                "web_info" => [
                    "_client" => BASE_NAME,
                    "license" => "Qp5KSGTquetnUkjX6UVBAURH8hTkZuLM",
                    "version" => "1.0.0",
                ],
                "web__dev" => [
                    "author" => "DhuvadHeet",
                ],
            ];

            return $this->response->setJSON($nata);
        }
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
        $game = $this->request->getPost('game');
        $uKey = $this->request->getPost('user_key');
        $sDev = $this->request->getPost('serial');

        $form_rules = [
            'game' => 'required|alpha_dash',
            'user_key' => 'required|alpha_numeric|min_length[1]|max_length[36]',
            'serial' => 'required|alpha_dash'
        ];

        if (!$this->validate($form_rules)) {
            $data = [
                'status' => false,
                'reason' => "Bad Parameter",
            ];
            return $this->response->setJSON($data);
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
                    return $this->response->setJSON([
                        'status' => false,
                        'reason' => 'GAME NOT FOUND'
                    ]);
                }

                // 2) Tìm key CHỈ theo user_key (để phân biệt sai key vs sai game)
                $anyKey = $model->getKeys($uKey, 'user_key');
                if (!$anyKey) {
                    return $this->response->setJSON([
                        'status' => false,
                        'reason' => 'INVALID KEY'
                    ]);
                }
                // 3) Key tồn tại nhưng KHÔNG dành cho game app đang gửi
                if ($anyKey->game !== $game) {
                    return $this->response->setJSON([
                        'status' => false,
                        'reason' => 'WRONG GAME',
                        'key_for' => $anyKey->game   // app biết key này thuộc game nào
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
        return $this->response->setJSON($data);
    }
}
