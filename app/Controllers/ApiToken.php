<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\GameModel;

/**
 * Quản lý API Token của user (cho bot bán key bên ngoài).
 */
class ApiToken extends BaseController
{
    protected $userModel, $user;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->user = $this->userModel->getUser();
    }

    public function index()
    {
        $db = \Config\Database::connect();
        if (!$db->fieldExists('api_token', 'users')) {
            return view('User/api_token', [
                'title' => 'API Token',
                'user'  => $this->user,
                'noCol' => true,
                'games' => [],
            ]);
        }

        // Game của user này (admin = tất cả) để bật/tắt bán API — ở đây bật/tắt theo game (toàn cục, admin only)
        $games = [];
        if ((int)$this->user->level === 1) {
            $games = (new GameModel())->getAllGames();
        }

        return view('User/api_token', [
            'title' => 'API Token',
            'user'  => $this->user,
            'noCol' => false,
            'games' => $games,
            'baseApiUrl' => rtrim(base_url('api/sell'), '/'),
        ]);
    }

    /**
     * Trang SDK chống crack: hiển thị public key RSA + code Android dán-là-chạy.
     * ?gen=1 -> tạo cặp khoá mới (admin tự dán private vào .env).
     */
    public function sdk()
    {
        if ((int)$this->user->level !== 1) return redirect()->to('dashboard');

        // Tạo cặp khoá mới khi bấm nút
        $generated = null;
        if ($this->request->getGet('gen') === '1' && function_exists('openssl_pkey_new')) {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($res) {
                openssl_pkey_export($res, $priv);
                $det = openssl_pkey_get_details($res);
                $generated = ['private_b64' => base64_encode($priv), 'public' => $det['key']];
            }
        }

        // Public key hiện hành (suy từ private trong .env)
        $currentPublic = null;
        $b64 = env('connect.rsaPrivate');
        if ($b64 && function_exists('openssl_pkey_get_private')) {
            $pem = base64_decode($b64, true);
            $pk  = $pem ? openssl_pkey_get_private($pem) : false;
            if ($pk) { $d = openssl_pkey_get_details($pk); if ($d) $currentPublic = $d['key']; }
        }

        return view('User/sdk', [
            'title'         => 'License SDK',
            'user'          => $this->user,
            'generated'     => $generated,
            'currentPublic' => $currentPublic,
            'connectUrl'    => rtrim(base_url('connect'), '/'),
        ]);
    }

    /** Tạo mới / làm mới token */
    public function generate()
    {
        $token = bin2hex(random_bytes(24)); // 48 ký tự hex
        $this->userModel->update($this->user->id_users, [
            'api_token'   => $token,
            'api_enabled' => 1,
        ]);
        return redirect()->to('settings/api')->with('msgSuccess', 'New API token created.');
    }

    /** Bật / tắt API */
    public function toggle()
    {
        $new = empty($this->user->api_enabled) ? 1 : 0;
        $this->userModel->update($this->user->id_users, ['api_enabled' => $new]);
        return redirect()->to('settings/api')->with('msgSuccess', $new ? 'API enabled.' : 'API disabled.');
    }

    /** Admin: bật/tắt bán API cho 1 game */
    public function toggleGame($id = false)
    {
        if ((int)$this->user->level !== 1) {
            return redirect()->to('settings/api')->with('msgDanger', 'Admin only.');
        }
        $gm = new GameModel();
        $g = $gm->find((int)$id);
        if ($g) {
            $new = empty($g['api_sale']) ? 1 : 0;
            $gm->update((int)$id, ['api_sale' => $new]);
            return redirect()->to('settings/api')->with('msgSuccess', $new ? 'API sale enabled for ' . $g['name'] : 'API sale disabled for ' . $g['name']);
        }
        return redirect()->to('settings/api')->with('msgDanger', 'Game not found.');
    }
}
