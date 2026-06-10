<?php

namespace App\Controllers;

use App\Models\GameModel;
use App\Models\UserModel;

class Games extends BaseController
{
    protected $model, $user;

    public function __construct()
    {
        $this->model = new GameModel();
        $this->user = (new UserModel())->getUser();
    }

    public function index()
    {
        // Nếu bảng games chưa tồn tại -> báo rõ thay vì trang trắng
        if (!db_connect()->tableExists('games')) {
            return view('Admin/games', [
                'title' => 'Games',
                'user' => $this->user,
                'games' => [],
                'noTable' => true,
            ]);
        }
        $data = [
            'title' => 'Games',
            'user' => $this->user,
            'games' => $this->model->getAllGames(),
            'noTable' => false,
        ];
        return view('Admin/games', $data);
    }

    /** Thêm mới hoặc cập nhật game */
    public function save()
    {
        $id        = (int) $this->request->getPost('id_game');
        $name      = trim((string) $this->request->getPost('name'));
        $code      = strtoupper(trim((string) $this->request->getPost('game_code')));
        $status    = (int) $this->request->getPost('status');
        $sort      = (int) $this->request->getPost('sort_order');
        $hoursArr  = $this->request->getPost('d_hours') ?: [];
        $priceArr  = $this->request->getPost('d_price') ?: [];

        // Validate cơ bản
        if ($name === '' || $code === '' || !preg_match('/^[A-Z0-9_]{2,32}$/', $code)) {
            return redirect()->to('admin/games')->with('msgDanger', 'Invalid game name / code (code: UPPERCASE, digits, _ ; 2-32 chars).');
        }

        // Build durations JSON từ các dòng gói
        $durations = [];
        foreach ($hoursArr as $i => $h) {
            $h = (int) $h;
            $p = isset($priceArr[$i]) ? (float) $priceArr[$i] : 0;
            if ($h > 0 && $p >= 0) {
                $durations[] = ['hours' => $h, 'price' => $p];
            }
        }
        if (empty($durations)) {
            return redirect()->to('admin/games')->with('msgDanger', 'At least 1 package required (hours + price).');
        }

        $payload = [
            'name'       => $name,
            'game_code'  => $code,
            'durations'  => json_encode(array_values($durations)),
            'status'     => $status ? 1 : 0,
            'sort_order' => $sort,
        ];

        try {
            if ($id > 0) {
                // Sửa — không cho trùng game_code với game khác
                $dup = $this->model->where('game_code', $code)->where('id_game !=', $id)->first();
                if ($dup) return redirect()->to('admin/games')->with('msgDanger', 'Game code already exists.');
                $this->model->update($id, $payload);
                $msg = 'Game "' . $name . '" updated.';
            } else {
                $dup = $this->model->where('game_code', $code)->first();
                if ($dup) return redirect()->to('admin/games')->with('msgDanger', 'Game code already exists.');
                $this->model->insert($payload);
                $msg = 'Game "' . $name . '" added.';
            }
        } catch (\Throwable $e) {
            return redirect()->to('admin/games')->with('msgDanger', 'DB error: ' . $e->getMessage());
        }

        return redirect()->to('admin/games')->with('msgSuccess', $msg);
    }

    public function delete()
    {
        $id = (int) $this->request->getPost('id_game');
        if ($id > 0) {
            $this->model->delete($id);
            return redirect()->to('admin/games')->with('msgSuccess', 'Game deleted.');
        }
        return redirect()->to('admin/games')->with('msgDanger', 'Game not found.');
    }
}
