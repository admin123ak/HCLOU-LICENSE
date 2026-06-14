<?php
/* =========================================================================
 *  FIX DB CONFIG — đổi TẤT CẢ file kết nối DB của panel về 1 database.
 *  Upload file này lên thư mục GỐC panel, mở trên trình duyệt, nhập DB cPanel.
 *  Sau khi chạy xong -> XOÁ file này.
 * ========================================================================= */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
$ROOT = __DIR__;
$done = []; $msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['host'] ?? 'localhost');
    $db   = trim($_POST['db']   ?? '');
    $user = trim($_POST['user'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');
    if ($db === '' || $user === '') { $msg = '❌ Nhập đủ DB name + username'; }
    else {
        // các database cũ (của seller khác) cần thay
        $oldVals = ['sxladoro_sannso','ffdetect_main','vipteams_rohit','fallenxt_jangrashab','darkesp1_db','darkesp1_user'];

        $fix = function($path) use ($host,$db,$user,$pass,$oldVals,&$done) {
            if (!is_file($path)) return;
            $c = file_get_contents($path); $orig = $c;
            // 1) conn.php kiểu mysqli: $servername/$username/$password/$dbname
            $c = preg_replace('/(\$servername\s*=\s*)"[^"]*"/', '$1"'.$host.'"', $c);
            $c = preg_replace('/(\$username\s*=\s*)"[^"]*"/',   '$1"'.$user.'"', $c);
            $c = preg_replace('/(\$password\s*=\s*)"[^"]*"/',   '$1"'.$pass.'"', $c);
            $c = preg_replace('/(\$dbname\s*=\s*)"[^"]*"/',     '$1"'.$db.'"',   $c);
            // 2) .env CodeIgniter
            $c = preg_replace('/^(database\.default\.hostname\s*=\s*).*$/m', '${1}'.$host, $c);
            $c = preg_replace('/^(database\.default\.database\s*=\s*).*$/m', '${1}'.$db,   $c);
            $c = preg_replace('/^(database\.default\.username\s*=\s*).*$/m', '${1}'.$user, $c);
            $c = preg_replace('/^(database\.default\.password\s*=\s*).*$/m', '${1}'.$pass, $c);
            // 3) define('DB_*')
            $c = preg_replace("/(define\('DB_SERVER',\s*)'[^']*'/",   "$1'".$host."'", $c);
            $c = preg_replace("/(define\('DB_USERNAME',\s*)'[^']*'/", "$1'".$user."'", $c);
            $c = preg_replace("/(define\('DB_PASSWORD',\s*)'[^']*'/", "$1'".$pass."'", $c);
            $c = preg_replace("/(define\('DB_NAME',\s*)'[^']*'/",     "$1'".$db."'",   $c);
            // 4) Database.php (CI4) — chỉ thay đúng giá trị seller cũ (không đụng block tests)
            foreach ($oldVals as $ov) {
                $c = preg_replace("/('username'\s*=>\s*)'".preg_quote($ov,'/')."'/", "$1'".$user."'", $c);
                $c = preg_replace("/('password'\s*=>\s*)'".preg_quote($ov,'/')."'/", "$1'".$pass."'", $c);
                $c = preg_replace("/('database'\s*=>\s*)'".preg_quote($ov,'/')."'/", "$1'".$db."'",   $c);
                $c = preg_replace("/('hostname'\s*=>\s*)'".preg_quote($ov,'/')."'/", "$1'".$host."'", $c);
            }
            if ($c !== $orig) { file_put_contents($path, $c); $done[] = str_replace($GLOBALS['ROOT'].'/','',$path); }
        };

        // Quét toàn bộ panel (bỏ vendor)
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = $f->getPathname();
            if (strpos($p, '/vendor/') !== false) continue;
            $bn = $f->getFilename();
            if ($bn === '.env' || $bn === 'conn.php' || $bn === 'db_config.php' || $bn === 'Database.php' || $bn === 'keys_reset.php' || $bn === 'DB.php') {
                $fix($p);
            }
        }
        $msg = '✅ Đã sửa '.count($done).' file về DB: '.htmlspecialchars($db);
    }
}
?><!doctype html><html><head><meta charset="utf-8"><title>Fix DB Config</title>
<style>body{font-family:sans-serif;background:#0d1117;color:#c9d1d9;max-width:560px;margin:40px auto;padding:20px}
input{width:100%;padding:9px;margin:6px 0 14px;background:#161b22;border:1px solid #30363d;color:#fff;border-radius:6px}
button{background:#238636;color:#fff;border:0;padding:10px 18px;border-radius:6px;cursor:pointer}
.box{background:#161b22;border:1px solid #30363d;border-radius:10px;padding:18px}code{color:#7db3ff}</style></head><body>
<h2>🔧 Fix DB Config (đổi hết file về 1 database)</h2>
<?php if($msg):?><div class="box" style="border-color:#238636;margin-bottom:16px"><?=$msg?>
<?php if($done):?><br><br><b>File đã sửa:</b><br><?php foreach($done as $d) echo "<code>".htmlspecialchars($d)."</code><br>";?><?php endif;?>
<br><b style="color:#fbbf24">⚠️ Nhớ XOÁ file fix_dbconfig.php sau khi xong!</b></div><?php endif;?>
<div class="box"><form method="post">
<label>DB Host</label><input name="host" value="localhost">
<label>Database name (cPanel)</label><input name="db" placeholder="vd: clouzy_panel" required>
<label>DB Username</label><input name="user" placeholder="vd: clouzy_admin" required>
<label>DB Password</label><input name="pass" type="text" placeholder="mật khẩu DB">
<button type="submit">🔧 Sửa hết file DB config</button>
</form></div></body></html>
