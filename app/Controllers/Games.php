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
        $data = [
            'title' => 'Games',
            'user' => $this->user,
            'games' => $this->model->getAllGames(),
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
            return redirect()->to('admin/games')->with('msgDanger', 'Tên game / mã game không hợp lệ (mã: CHỮ HOA, số, _ ; 2-32 ký tự).');
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
            return redirect()->to('admin/games')->with('msgDanger', 'Phải có ít nhất 1 gói (số giờ + giá).');
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
                if ($dup) return redirect()->to('admin/games')->with('msgDanger', 'Mã game đã tồn tại.');
                $this->model->update($id, $payload);
                $msg = 'Đã cập nhật game "' . $name . '".';
            } else {
                $dup = $this->model->where('game_code', $code)->first();
                if ($dup) return redirect()->to('admin/games')->with('msgDanger', 'Mã game đã tồn tại.');
                $this->model->insert($payload);
                $msg = 'Đã thêm game "' . $name . '".';
            }
        } catch (\Throwable $e) {
            return redirect()->to('admin/games')->with('msgDanger', 'Lỗi DB: ' . $e->getMessage());
        }

        return redirect()->to('admin/games')->with('msgSuccess', $msg);
    }

    public function delete()
    {
        $id = (int) $this->request->getPost('id_game');
        if ($id > 0) {
            $this->model->delete($id);
            return redirect()->to('admin/games')->with('msgSuccess', 'Đã xoá game.');
        }
        return redirect()->to('admin/games')->with('msgDanger', 'Không tìm thấy game.');
    }
}
