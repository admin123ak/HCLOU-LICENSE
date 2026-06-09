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

    /** Tạo mới / làm mới token */
    public function generate()
    {
        $token = bin2hex(random_bytes(24)); // 48 ký tự hex
        $this->userModel->update($this->user->id_users, [
            'api_token'   => $token,
            'api_enabled' => 1,
        ]);
        return redirect()->to('settings/api')->with('msgSuccess', 'Đã tạo API token mới.');
    }

    /** Bật / tắt API */
    public function toggle()
    {
        $new = empty($this->user->api_enabled) ? 1 : 0;
        $this->userModel->update($this->user->id_users, ['api_enabled' => $new]);
        return redirect()->to('settings/api')->with('msgSuccess', $new ? 'Đã BẬT API.' : 'Đã TẮT API.');
    }

    /** Admin: bật/tắt bán API cho 1 game */
    public function toggleGame($id = false)
    {
        if ((int)$this->user->level !== 1) {
            return redirect()->to('settings/api')->with('msgDanger', 'Chỉ admin.');
        }
        $gm = new GameModel();
        $g = $gm->find((int)$id);
        if ($g) {
            $new = empty($g['api_sale']) ? 1 : 0;
            $gm->update((int)$id, ['api_sale' => $new]);
            return redirect()->to('settings/api')->with('msgSuccess', $new ? 'Đã MỞ bán API game ' . $g['name'] : 'Đã TẮT bán API game ' . $g['name']);
        }
        return redirect()->to('settings/api')->with('msgDanger', 'Game không tồn tại.');
    }
}
