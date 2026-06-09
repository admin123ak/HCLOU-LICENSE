<?php

namespace App\Models;

use CodeIgniter\Model;

class GameModel extends Model
{
    protected $table      = 'games';
    protected $primaryKey = 'id_game';
    protected $allowedFields = ['name', 'game_code', 'durations', 'status', 'sort_order'];
    protected $useTimestamps = true;

    /** Tất cả game đang bật, sắp xếp theo sort_order */
    public function getActive()
    {
        return $this->where('status', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id_game', 'ASC')
            ->findAll();
    }

    /** Tất cả game (cả tắt) — cho admin quản lý */
    public function getAllGames()
    {
        return $this->orderBy('sort_order', 'ASC')->orderBy('id_game', 'ASC')->findAll();
    }

    /** Lấy 1 game theo id */
    public function getGame($id)
    {
        return $this->find($id);
    }

    /**
     * Trả về list game cho dropdown: ['PUBG' => 'PUBG Mobile', ...]
     * key = game_code (lưu vào keys_code.game), value = tên hiển thị
     */
    public function dropdownList()
    {
        $rows = $this->getActive();
        $out = [];
        foreach ($rows as $g) {
            $out[$g['game_code']] = $g['name'];
        }
        return $out;
    }

    /** Parse durations JSON -> mảng [ ['hours'=>1,'price'=>10], ... ] */
    public static function parseDurations($json)
    {
        $arr = json_decode((string) $json, true);
        if (!is_array($arr)) return [];
        $clean = [];
        foreach ($arr as $d) {
            $h = isset($d['hours']) ? (int) $d['hours'] : 0;
            $p = isset($d['price']) ? (float) $d['price'] : 0;
            if ($h > 0) $clean[] = ['hours' => $h, 'price' => $p];
        }
        return $clean;
    }

    /** Map duration => label "1 Hours — $10/Device" cho 1 game */
    public static function durationLabels($json)
    {
        $out = [];
        foreach (self::parseDurations($json) as $d) {
            $h = $d['hours'];
            if ($h % 720 === 0)      $txt = ($h / 720) . ' Months';
            elseif ($h % 24 === 0)   $txt = ($h / 24) . ' Days';
            else                     $txt = $h . ' Hours';
            $out[$h] = $txt . ' &mdash; $' . rtrim(rtrim(number_format($d['price'], 2, '.', ''), '0'), '.') . '/Device';
        }
        return $out;
    }

    /** Map duration => price cho 1 game */
    public static function priceMap($json)
    {
        $out = [];
        foreach (self::parseDurations($json) as $d) {
            $out[$d['hours']] = $d['price'];
        }
        return $out;
    }
}
