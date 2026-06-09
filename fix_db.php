<?php
/**
 * ============================================================
 *  HCLOU LICENSE — FIX DATABASE
 *  Tự tạo/sửa các bảng cần thiết (games...) cho panel.
 *  Đọc thông tin DB từ file .env (cùng thư mục).
 *
 *  CÁCH DÙNG: mở https://your-domain/fix_db.php trên trình duyệt.
 *  ⚠️ XOÁ FILE NÀY sau khi chạy xong để bảo mật.
 * ============================================================
 */

header('Content-Type: text/html; charset=utf-8');

// ---- 1. Đọc DB config từ .env ----
function envGet($keys, $default = '') {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) return $default;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ((array)$keys as $key) {
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#') continue;
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.*)$/', $l, $m)) {
                return trim($m[1], " '\"");
            }
        }
    }
    return $default;
}

$DB_HOST = envGet(['database.default.hostname']) ?: 'localhost';
$DB_NAME = envGet(['database.default.database']);
$DB_USER = envGet(['database.default.username']);
$DB_PASS = envGet(['database.default.password']);
$DB_PORT = envGet(['database.default.port']) ?: 3306;

$out = [];
$ok = true;

echo '<!doctype html><html><head><meta charset="utf-8"><title>Fix DB</title>';
echo '<style>body{font-family:system-ui,Segoe UI,sans-serif;background:#0a0f1c;color:#eef3fb;max-width:760px;margin:40px auto;padding:24px;line-height:1.6}'
   . 'h1{font-size:22px}.ok{color:#6ee7b7}.err{color:#fca5a5}.box{background:#141c2e;border:1px solid #26354f;border-radius:12px;padding:16px 20px;margin:14px 0}'
   . 'code{background:#0a0f1c;padding:2px 7px;border-radius:5px;color:#7db3ff}.warn{background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.35);color:#fde68a;padding:12px 16px;border-radius:10px}</style></head><body>';
echo '<h1>🔧 HCLOU License — Fix Database</h1>';

if (!$DB_NAME || !$DB_USER) {
    echo '<div class="box err">❌ Không đọc được DB config từ <code>.env</code>. Kiểm tra file .env có dòng <code>database.default.database</code>, <code>database.default.username</code>, <code>database.default.password</code>.</div></body></html>';
    exit;
}

// ---- 2. Kết nối ----
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<div class="box ok">✅ Kết nối DB <code>' . htmlspecialchars($DB_NAME) . '</code> thành công.</div>';
} catch (Throwable $e) {
    echo '<div class="box err">❌ Lỗi kết nối DB: ' . htmlspecialchars($e->getMessage()) . '</div></body></html>';
    exit;
}

// ---- 3. Các migration cần chạy (idempotent) ----
$migrations = [
    'Tạo bảng games' => "
        CREATE TABLE IF NOT EXISTS `games` (
          `id_game` INT(11) NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(64) NOT NULL,
          `game_code` VARCHAR(32) NOT NULL,
          `durations` TEXT DEFAULT NULL,
          `status` TINYINT(1) DEFAULT 1,
          `sort_order` INT(11) DEFAULT 0,
          `created_at` DATETIME DEFAULT NULL,
          `updated_at` DATETIME DEFAULT NULL,
          PRIMARY KEY (`id_game`),
          UNIQUE KEY `uniq_game_code` (`game_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ",
    'Seed game PUBG (nếu chưa có)' => "
        INSERT INTO `games` (`name`,`game_code`,`durations`,`status`,`sort_order`,`created_at`,`updated_at`)
        SELECT 'PUBG Mobile','PUBG',
          '[{\"hours\":1,\"price\":10},{\"hours\":5,\"price\":20},{\"hours\":24,\"price\":40},{\"hours\":72,\"price\":100},{\"hours\":168,\"price\":170},{\"hours\":336,\"price\":300},{\"hours\":720,\"price\":500},{\"hours\":1440,\"price\":800}]',
          1,0,NOW(),NOW()
        WHERE NOT EXISTS (SELECT 1 FROM `games` WHERE `game_code`='PUBG');
    ",
];

echo '<div class="box">';
foreach ($migrations as $label => $sql) {
    try {
        $affected = $pdo->exec($sql);
        echo '<div class="ok">✅ ' . htmlspecialchars($label)
           . ($affected !== false && $affected > 0 ? " ($affected dòng)" : '') . '</div>';
    } catch (Throwable $e) {
        $ok = false;
        echo '<div class="err">❌ ' . htmlspecialchars($label) . ' — ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
echo '</div>';

// ---- 4. Tổng kết ----
if ($ok) {
    echo '<div class="box ok"><b>🎉 Hoàn tất!</b> Database đã sẵn sàng. Vào panel → <b>Admin → Games</b> để thêm game.</div>';
}
echo '<div class="warn">⚠️ <b>QUAN TRỌNG:</b> Xoá file <code>fix_db.php</code> này ngay sau khi chạy xong để tránh lộ thông tin.</div>';
echo '</body></html>';
