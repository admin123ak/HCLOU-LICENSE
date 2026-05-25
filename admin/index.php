<?php
/**
 * HCLOU-LICENSE — Admin panel SPA-like.
 * Login bcrypt + CSRF + 6 tabs: dashboard, games, scripts, keys, bindings, logs, settings.
 */

require_once __DIR__ . '/../config.php';

// =============================================
// SESSION + AUTH
// =============================================
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

function isAdmin(): bool {
    if (empty($_SESSION['admin_logged'])) return false;
    if (!empty($_SESSION['admin_last']) && (time() - $_SESSION['admin_last']) > 1800) {
        // 30 phút idle → logout
        $_SESSION = [];
        return false;
    }
    $_SESSION['admin_last'] = time();
    return true;
}

// LOGIN POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if ($u === ADMIN_USERNAME && password_verify($p, ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_last']   = time();
        header('Location: ?tab=dashboard'); exit;
    } else {
        $loginErr = 'Sai username hoặc password';
    }
}

// LOGOUT
if (($_GET['action'] ?? '') === 'logout') {
    $_SESSION = [];
    header('Location: index.php'); exit;
}

// =============================================
// LOGIN UI (nếu chưa login)
// =============================================
if (!isAdmin()) {
    ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — <?= h(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,'Segoe UI',sans-serif;background:#0d1117;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#161b27;border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:36px 28px;width:100%;max-width:380px;box-shadow:0 24px 60px rgba(0,0,0,.5)}
.head{text-align:center;margin-bottom:24px}
.head h1{font-size:22px;font-weight:800;background:linear-gradient(135deg,#7c6fe0,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.head p{color:#7a8499;font-size:13px;margin-top:4px}
.field{margin-bottom:14px}
.field label{display:block;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.field input{width:100%;padding:12px 14px;background:#1e2535;border:1px solid rgba(255,255,255,.08);border-radius:10px;color:#e2e8f0;font-size:14px;font-family:inherit;outline:none}
.field input:focus{border-color:rgba(124,111,224,.5);box-shadow:0 0 0 3px rgba(124,111,224,.18)}
.btn{width:100%;padding:13px;border-radius:12px;border:none;background:linear-gradient(135deg,#7c6fe0,#5b52c4);color:#fff;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;margin-top:8px;box-shadow:0 6px 16px rgba(100,80,220,.3);transition:opacity .2s,transform .15s}
.btn:hover{opacity:.9;transform:translateY(-1px)}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:10px 12px;color:#fca5a5;font-size:13px;margin-bottom:14px}
</style>
</head>
<body>
<div class="card">
  <div class="head"><h1><?= h(SITE_NAME) ?></h1><p>Admin Panel</p></div>
  <?php if (!empty($loginErr)): ?><div class="err">⚠ <?= h($loginErr) ?></div><?php endif ?>
  <form method="POST">
    <input type="hidden" name="action" value="login">
    <div class="field"><label>Username</label><input name="username" required autofocus></div>
    <div class="field"><label>Password</label><input type="password" name="password" required></div>
    <button class="btn" type="submit">Đăng nhập</button>
  </form>
</div>
</body>
</html>
    <?php
    exit;
}

// =============================================
// === LOGGED IN AREA ===
// =============================================
$db = getDB();
$tab = $_GET['tab'] ?? 'dashboard';
$msg = '';
$err = '';

function csrfCheck(string $csrf) {
    if (($_POST['csrf'] ?? '') !== $csrf) {
        http_response_code(400);
        die('CSRF invalid. <a href="javascript:history.back()">Quay lại</a>');
    }
}

// =============================================
// ACTION HANDLERS (POST)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrfCheck($csrf);
    $act = $_POST['action'];

    try {
        // ----- GAMES -----
        if ($act === 'add_game') {
            $db->prepare("INSERT INTO games (name, package_id, slug) VALUES (?, ?, ?)")
               ->execute([trim($_POST['name']), trim($_POST['package_id']), trim($_POST['slug'])]);
            $msg = 'Đã thêm game';
        }
        if ($act === 'toggle_game') {
            $db->prepare("UPDATE games SET is_active = 1 - is_active WHERE id = ?")
               ->execute([(int)$_POST['id']]);
            $msg = 'Đã đổi trạng thái game';
        }
        if ($act === 'delete_game') {
            $db->prepare("DELETE FROM games WHERE id = ?")->execute([(int)$_POST['id']]);
            $msg = 'Đã xoá game';
        }

        // ----- SCRIPTS -----
        if ($act === 'add_script') {
            $db->prepare("INSERT INTO scripts (game_id, version, body, notes) VALUES (?, ?, ?, ?)")
               ->execute([(int)$_POST['game_id'], trim($_POST['version']), $_POST['body'], trim($_POST['notes'] ?? '')]);
            $msg = 'Đã thêm script';
        }
        if ($act === 'edit_script') {
            $db->prepare("UPDATE scripts SET version = ?, body = ?, notes = ? WHERE id = ?")
               ->execute([trim($_POST['version']), $_POST['body'], trim($_POST['notes'] ?? ''), (int)$_POST['id']]);
            $msg = 'Đã cập nhật script';
        }
        if ($act === 'toggle_script') {
            $db->prepare("UPDATE scripts SET is_active = 1 - is_active WHERE id = ?")
               ->execute([(int)$_POST['id']]);
            $msg = 'Đã đổi trạng thái script';
        }
        if ($act === 'delete_script') {
            $db->prepare("DELETE FROM scripts WHERE id = ?")->execute([(int)$_POST['id']]);
            $msg = 'Đã xoá script';
        }

        // ----- KEYS -----
        if ($act === 'gen_single_key') {
            $code = genKeyCode();
            $db->prepare("INSERT INTO license_keys (key_code, game_id, duration_hours, max_devices, notes) VALUES (?, ?, ?, ?, ?)")
               ->execute([$code, (int)$_POST['game_id'], (int)$_POST['duration_hours'], (int)$_POST['max_devices'], trim($_POST['notes'] ?? '')]);
            $msg = 'Đã tạo key: ' . $code;
        }
        if ($act === 'gen_bulk_keys') {
            $cnt = max(1, min(1000, (int)$_POST['count']));
            $batch = 'BATCH-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $stmt = $db->prepare("INSERT INTO license_keys (key_code, game_id, duration_hours, max_devices, batch_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $gameId = (int)$_POST['game_id'];
            $dur = (int)$_POST['duration_hours'];
            $maxd = (int)$_POST['max_devices'];
            $notes = trim($_POST['notes'] ?? '');
            $created = 0;
            for ($i = 0; $i < $cnt; $i++) {
                try {
                    $stmt->execute([genKeyCode(), $gameId, $dur, $maxd, $batch, $notes]);
                    $created++;
                } catch (Exception $e) { /* duplicate key, retry next */ }
            }
            $msg = "Đã tạo $created/$cnt key (batch $batch)";
        }
        if ($act === 'edit_key') {
            $db->prepare("UPDATE license_keys SET game_id = ?, duration_hours = ?, max_devices = ?, status = ?, notes = ? WHERE id = ?")
               ->execute([(int)$_POST['game_id'], (int)$_POST['duration_hours'], (int)$_POST['max_devices'], $_POST['status'], trim($_POST['notes'] ?? ''), (int)$_POST['id']]);
            $msg = 'Đã cập nhật key';
        }
        if ($act === 'delete_key') {
            $db->prepare("DELETE FROM license_keys WHERE id = ?")->execute([(int)$_POST['id']]);
            $msg = 'Đã xoá key';
        }
        if ($act === 'reset_binding') {
            $db->prepare("UPDATE license_keys SET devices = NULL WHERE id = ?")->execute([(int)$_POST['id']]);
            $msg = 'Đã reset device binding';
        }

        // ----- SETTINGS -----
        if ($act === 'toggle_maintenance') {
            $cur = (int)($db->query("SELECT v FROM settings WHERE k='maintenance'")->fetchColumn() ?: 0);
            $new = $cur ? 0 : 1;
            $db->prepare("INSERT INTO settings (k,v) VALUES ('maintenance', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
               ->execute([(string)$new]);
            $msg = 'Maintenance: ' . ($new ? 'ON' : 'OFF');
        }
        if ($act === 'set_maintenance_msg') {
            $db->prepare("INSERT INTO settings (k,v) VALUES ('maintenance_message', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
               ->execute([trim($_POST['msg'])]);
            $msg = 'Đã cập nhật message';
        }

        // ----- BAN DEVICE -----
        if ($act === 'ban_device') {
            $db->prepare("INSERT IGNORE INTO banned_devices (device_serial, reason) VALUES (?, ?)")
               ->execute([trim($_POST['serial']), trim($_POST['reason'] ?? '')]);
            $msg = 'Đã ban device';
        }
        if ($act === 'unban_device') {
            $db->prepare("DELETE FROM banned_devices WHERE id = ?")->execute([(int)$_POST['id']]);
            $msg = 'Đã unban';
        }

    } catch (Exception $e) {
        $err = 'Lỗi: ' . $e->getMessage();
    }
}

// =============================================
// EXPORT CSV (handle trước HTML)
// =============================================
if (($_GET['action'] ?? '') === 'export_batch' && !empty($_GET['batch'])) {
    $stmt = $db->prepare("SELECT key_code, duration_hours, max_devices, status, created_at FROM license_keys WHERE batch_id = ? ORDER BY id ASC");
    $stmt->execute([$_GET['batch']]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $_GET['batch']) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['key_code','duration_hours','max_devices','status','created_at']);
    while ($r = $stmt->fetch()) fputcsv($out, $r);
    fclose($out); exit;
}

// =============================================
// HTML LAYOUT
// =============================================
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — <?= h(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,'Segoe UI',sans-serif;background:#0d1117;color:#e2e8f0;min-height:100vh}
.topbar{background:#161b27;border-bottom:1px solid rgba(255,255,255,.07);padding:12px 18px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;position:sticky;top:0;z-index:10}
.topbar .brand{font-size:15px;font-weight:800;background:linear-gradient(135deg,#7c6fe0,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;white-space:nowrap}
.topbar .sep{color:#5a6478;margin:0 2px}
.topbar a{color:#94a3b8;text-decoration:none;font-size:12.5px;font-weight:600;padding:6px 10px;border-radius:8px;transition:background .15s;white-space:nowrap}
.topbar a:hover{background:rgba(124,111,224,.12);color:#e2e8f0}
.topbar a.on{background:rgba(124,111,224,.18);color:#a78bfa}
.topbar .right{margin-left:auto;font-size:12px;color:#5a6478;white-space:nowrap}
.topbar .right a{color:#fca5a5;font-weight:600}
@media(max-width:640px){.topbar{gap:4px}.topbar a{padding:5px 8px;font-size:12px}.topbar .sep{display:none}.topbar .right{margin-left:0;width:100%;text-align:right;margin-top:4px}}
main{padding:24px 22px;max-width:1280px;margin:0 auto}
h1{font-size:22px;font-weight:800;margin-bottom:18px;color:#fff}
h2{font-size:15px;font-weight:700;margin:18px 0 10px;color:#fff}
.card{background:#161b27;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 8px 24px rgba(0,0,0,.3);overflow-x:auto;-webkit-overflow-scrolling:touch}
.toast{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-size:13px;font-weight:500}
.toast.ok{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:#6ee7b7}
.toast.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
table{width:100%;border-collapse:collapse;font-size:13px;table-layout:auto}
table th{text-align:left;padding:10px 12px;background:#1e2535;font-weight:600;color:#94a3b8;border-bottom:1px solid rgba(255,255,255,.05);font-size:11px;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap}
table td{padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle;word-break:break-word;max-width:320px}
table td code{display:inline-block;max-width:100%;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
table tr:hover td{background:rgba(124,111,224,.03)}
.field{margin-bottom:12px}
.field label{display:block;font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.field input,.field select,.field textarea{width:100%;max-width:100%;padding:10px 12px;background:#1e2535;border:1px solid rgba(255,255,255,.08);border-radius:8px;color:#e2e8f0;font-size:13px;font-family:inherit;outline:none}
.field textarea{min-height:120px;max-height:60vh;font-family:'SF Mono','JetBrains Mono',monospace;font-size:12px;line-height:1.5;resize:vertical;word-break:break-all}
.field input:focus,.field select:focus,.field textarea:focus{border-color:rgba(124,111,224,.5);box-shadow:0 0 0 2px rgba(124,111,224,.15)}
.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.btn{display:inline-block;padding:8px 14px;border-radius:8px;border:none;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;text-decoration:none;color:#fff;transition:opacity .15s,transform .1s}
.btn:active{transform:scale(.97)}
.btn.primary{background:linear-gradient(135deg,#7c6fe0,#5b52c4)}
.btn.danger{background:#dc2626}
.btn.ghost{background:transparent;border:1px solid rgba(255,255,255,.1);color:#94a3b8}
.btn.ghost:hover{border-color:rgba(124,111,224,.4);color:#a78bfa}
.btn.tiny{padding:5px 10px;font-size:11px}
code{background:rgba(124,111,224,.1);padding:2px 6px;border-radius:4px;color:#a78bfa;font-family:'SF Mono',monospace;font-size:12px}
.badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.badge.green{background:rgba(52,211,153,.15);color:#6ee7b7}
.badge.gray{background:rgba(148,163,184,.15);color:#94a3b8}
.badge.orange{background:rgba(251,146,60,.15);color:#fdba74}
.badge.red{background:rgba(239,68,68,.15);color:#fca5a5}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:18px}
.stat{background:#161b27;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:18px}
.stat .lbl{font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.stat .val{font-size:24px;font-weight:800;color:#fff}
.stat .val.purple{color:#a78bfa}
.stat .val.green{color:#34d399}
.stat .val.orange{color:#fdba74}
.actions{display:flex;gap:6px;flex-wrap:wrap}
</style>
</head>
<body>

<div class="topbar">
  <span class="brand"><?= h(SITE_NAME) ?></span>
  <span class="sep">·</span>
  <a href="?tab=dashboard" class="<?= $tab==='dashboard'?'on':'' ?>">Dashboard</a>
  <a href="?tab=games" class="<?= $tab==='games'?'on':'' ?>">Games</a>
  <a href="?tab=scripts" class="<?= $tab==='scripts'?'on':'' ?>">Scripts</a>
  <a href="?tab=keys" class="<?= $tab==='keys'?'on':'' ?>">Keys</a>
  <a href="?tab=bindings" class="<?= $tab==='bindings'?'on':'' ?>">Bindings</a>
  <a href="?tab=logs" class="<?= $tab==='logs'?'on':'' ?>">Logs</a>
  <a href="?tab=settings" class="<?= $tab==='settings'?'on':'' ?>">Settings</a>
  <div class="right">admin · <a href="?action=logout">Logout</a></div>
</div>

<main>

<?php if ($msg): ?><div class="toast ok">✓ <?= h($msg) ?></div><?php endif ?>
<?php if ($err): ?><div class="toast err">⚠ <?= h($err) ?></div><?php endif ?>

<?php if ($tab === 'dashboard'): ?>
<h1>Dashboard</h1>
<?php
$totalKeys   = (int)$db->query("SELECT COUNT(*) FROM license_keys")->fetchColumn();
$activeKeys  = (int)$db->query("SELECT COUNT(*) FROM license_keys WHERE status='active'")->fetchColumn();
$unusedKeys  = (int)$db->query("SELECT COUNT(*) FROM license_keys WHERE status='unused'")->fetchColumn();
$expiredKeys = (int)$db->query("SELECT COUNT(*) FROM license_keys WHERE status='expired' OR (expired_at IS NOT NULL AND expired_at < NOW())")->fetchColumn();
$bannedKeys  = (int)$db->query("SELECT COUNT(*) FROM license_keys WHERE status='banned'")->fetchColumn();
$totalGames  = (int)$db->query("SELECT COUNT(*) FROM games WHERE is_active=1")->fetchColumn();
$totalScripts = (int)$db->query("SELECT COUNT(*) FROM scripts WHERE is_active=1")->fetchColumn();
$logsToday   = (int)$db->query("SELECT COUNT(*) FROM connect_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
?>
<div class="stat-grid">
  <div class="stat"><div class="lbl">Total keys</div><div class="val"><?= $totalKeys ?></div></div>
  <div class="stat"><div class="lbl">Unused (in pool)</div><div class="val purple"><?= $unusedKeys ?></div></div>
  <div class="stat"><div class="lbl">Active</div><div class="val green"><?= $activeKeys ?></div></div>
  <div class="stat"><div class="lbl">Expired</div><div class="val orange"><?= $expiredKeys ?></div></div>
  <div class="stat"><div class="lbl">Banned</div><div class="val" style="color:#fca5a5"><?= $bannedKeys ?></div></div>
  <div class="stat"><div class="lbl">Games active</div><div class="val"><?= $totalGames ?></div></div>
  <div class="stat"><div class="lbl">Scripts active</div><div class="val"><?= $totalScripts ?></div></div>
  <div class="stat"><div class="lbl">Logs hôm nay</div><div class="val"><?= $logsToday ?></div></div>
</div>

<div class="card">
  <h2>10 connect log gần nhất</h2>
  <?php $rows = $db->query("SELECT l.*, k.key_code FROM connect_logs l LEFT JOIN license_keys k ON l.key_id=k.id ORDER BY l.id DESC LIMIT 10")->fetchAll(); ?>
  <?php if (!$rows): ?><p style="color:#5a6478;font-size:13px">Chưa có log.</p>
  <?php else: ?>
  <table><tr><th>Time</th><th>Key</th><th>Device</th><th>IP</th><th>Action</th><th>Reason</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr><td><?= h($r['created_at']) ?></td><td><code><?= h($r['key_code'] ?: '—') ?></code></td><td style="font-size:11px;color:#7a8499"><?= h(substr($r['device_serial'] ?? '', 0, 12)) ?>…</td><td><?= h($r['ip']) ?></td><td><span class="badge <?= $r['action']==='verify_ok'?'green':($r['action']==='rejected'?'red':'gray') ?>"><?= h($r['action']) ?></span></td><td style="font-size:12px;color:#7a8499"><?= h($r['reason']) ?></td></tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<?php elseif ($tab === 'games'): ?>
<h1>Games</h1>
<div class="card">
  <h2>Thêm game mới</h2>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="add_game">
    <div class="row">
      <div class="field"><label>Tên</label><input name="name" required placeholder="PUBG Mobile"></div>
      <div class="field"><label>Package ID</label><input name="package_id" required placeholder="com.tencent.ig"></div>
      <div class="field"><label>Slug</label><input name="slug" required pattern="[a-z0-9_-]+" placeholder="pubg-mobile"></div>
    </div>
    <button class="btn primary" type="submit">+ Thêm game</button>
  </form>
</div>

<div class="card">
  <h2>Danh sách</h2>
  <?php $games = $db->query("SELECT g.*,(SELECT COUNT(*) FROM scripts s WHERE s.game_id=g.id) sc_count,(SELECT COUNT(*) FROM license_keys k WHERE k.game_id=g.id) k_count FROM games g ORDER BY g.id DESC")->fetchAll(); ?>
  <?php if (!$games): ?><p style="color:#5a6478;font-size:13px">Chưa có game nào.</p>
  <?php else: ?>
  <table><tr><th>ID</th><th>Tên</th><th>Package</th><th>Slug</th><th>Scripts</th><th>Keys</th><th>TT</th><th>Action</th></tr>
  <?php foreach ($games as $g): ?>
  <tr>
    <td>#<?= $g['id'] ?></td>
    <td><?= h($g['name']) ?></td>
    <td><code><?= h($g['package_id']) ?></code></td>
    <td><code><?= h($g['slug']) ?></code></td>
    <td><?= $g['sc_count'] ?></td>
    <td><?= $g['k_count'] ?></td>
    <td><span class="badge <?= $g['is_active']?'green':'gray' ?>"><?= $g['is_active']?'ON':'OFF' ?></span></td>
    <td class="actions">
      <a class="btn tiny primary" href="loader.php?game=<?= $g['id'] ?>" target="_blank" title="Download loader .lua chung cho game này">📥 Loader</a>
      <form method="POST" style="display:inline"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="toggle_game"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn tiny ghost" type="submit"><?= $g['is_active']?'Tắt':'Bật' ?></button></form>
      <form method="POST" style="display:inline" onsubmit="return confirm('Xoá game này?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="delete_game"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="btn tiny danger" type="submit">Xoá</button></form>
    </td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<?php elseif ($tab === 'scripts'): ?>
<h1>Scripts (Lua body per game)</h1>
<?php $games = $db->query("SELECT * FROM games WHERE is_active=1 ORDER BY id DESC")->fetchAll(); ?>

<?php if (!empty($_GET['edit'])):
    $sid = (int)$_GET['edit'];
    $s = $db->prepare("SELECT * FROM scripts WHERE id=?"); $s->execute([$sid]); $script = $s->fetch();
    if ($script): ?>
<div class="card">
  <h2>Sửa script #<?= $sid ?></h2>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="edit_script"><input type="hidden" name="id" value="<?= $sid ?>">
    <div class="row">
      <div class="field"><label>Version</label><input name="version" required value="<?= h($script['version']) ?>"></div>
      <div class="field"><label>Notes</label><input name="notes" value="<?= h($script['notes']) ?>"></div>
    </div>
    <div class="field"><label>Body Lua</label><textarea name="body" required><?= h($script['body']) ?></textarea></div>
    <button class="btn primary" type="submit">Lưu</button>
    <a class="btn ghost" href="?tab=scripts">Huỷ</a>
  </form>
</div>
<?php endif; endif ?>

<div class="card">
  <h2>Thêm script mới</h2>
  <?php if (!$games): ?><p style="color:#fca5a5;font-size:13px">⚠ Chưa có game. <a href="?tab=games" style="color:#a78bfa">Thêm game trước</a>.</p>
  <?php else: ?>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="add_script">
    <div class="row">
      <div class="field"><label>Game</label><select name="game_id" required><?php foreach ($games as $g): ?><option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label>Version</label><input name="version" required value="1.0.0"></div>
      <div class="field"><label>Notes</label><input name="notes" placeholder="Mô tả ngắn"></div>
    </div>
    <div class="field"><label>Body Lua (paste toàn bộ script)</label><textarea name="body" required placeholder="-- Lua script body sẽ được trả về client sau khi key verify."></textarea></div>
    <button class="btn primary" type="submit">+ Thêm script</button>
  </form>
  <?php endif ?>
</div>

<div class="card">
  <h2>Danh sách</h2>
  <?php $scripts = $db->query("SELECT s.*, g.name game_name FROM scripts s JOIN games g ON s.game_id=g.id ORDER BY s.id DESC")->fetchAll(); ?>
  <?php if (!$scripts): ?><p style="color:#5a6478;font-size:13px">Chưa có script.</p>
  <?php else: ?>
  <table><tr><th>ID</th><th>Game</th><th>Version</th><th>Size</th><th>Notes</th><th>Updated</th><th>TT</th><th>Action</th></tr>
  <?php foreach ($scripts as $s): ?>
  <tr>
    <td>#<?= $s['id'] ?></td>
    <td><?= h($s['game_name']) ?></td>
    <td><code><?= h($s['version']) ?></code></td>
    <td><?= number_format(strlen($s['body'])) ?> B</td>
    <td style="font-size:12px;color:#7a8499"><?= h(mb_strimwidth($s['notes'] ?? '', 0, 40, '…')) ?></td>
    <td style="font-size:11px;color:#7a8499"><?= h($s['updated_at']) ?></td>
    <td><span class="badge <?= $s['is_active']?'green':'gray' ?>"><?= $s['is_active']?'ON':'OFF' ?></span></td>
    <td class="actions">
      <a class="btn tiny ghost" href="?tab=scripts&edit=<?= $s['id'] ?>">Sửa</a>
      <form method="POST" style="display:inline"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="toggle_script"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn tiny ghost" type="submit"><?= $s['is_active']?'Tắt':'Bật' ?></button></form>
      <form method="POST" style="display:inline" onsubmit="return confirm('Xoá script này?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="delete_script"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn tiny danger" type="submit">Xoá</button></form>
    </td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<?php elseif ($tab === 'keys'): ?>
<h1>License Keys</h1>
<?php $games = $db->query("SELECT * FROM games WHERE is_active=1 ORDER BY id DESC")->fetchAll(); ?>

<?php if (!empty($_GET['edit'])):
    $kid = (int)$_GET['edit'];
    $k = $db->prepare("SELECT * FROM license_keys WHERE id=?"); $k->execute([$kid]); $key = $k->fetch();
    if ($key): ?>
<div class="card">
  <h2>Sửa key <code><?= h($key['key_code']) ?></code></h2>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="edit_key"><input type="hidden" name="id" value="<?= $kid ?>">
    <div class="row">
      <div class="field"><label>Game</label><select name="game_id" required><?php foreach ($games as $g): ?><option value="<?= $g['id'] ?>" <?= $g['id']==$key['game_id']?'selected':'' ?>><?= h($g['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label>Duration (hours)</label><input type="number" name="duration_hours" required min="1" value="<?= (int)$key['duration_hours'] ?>"></div>
      <div class="field"><label>Max devices</label><input type="number" name="max_devices" required min="1" max="10" value="<?= (int)$key['max_devices'] ?>"></div>
      <div class="field"><label>Status</label><select name="status"><?php foreach (['unused','active','expired','banned'] as $st): ?><option value="<?= $st ?>" <?= $st===$key['status']?'selected':'' ?>><?= $st ?></option><?php endforeach ?></select></div>
    </div>
    <div class="field"><label>Notes</label><input name="notes" value="<?= h($key['notes']) ?>"></div>
    <div style="font-size:12px;color:#7a8499;margin:8px 0">Devices đã bind: <code><?= h($key['devices'] ?: 'chưa có') ?></code> · Activated: <?= h($key['activated_at'] ?: '—') ?> · Expired: <?= h($key['expired_at'] ?: '—') ?></div>
    <button class="btn primary" type="submit">Lưu</button>
    <a class="btn ghost" href="?tab=keys">Huỷ</a>
  </form>
</div>
<?php endif; endif ?>

<div class="card">
  <h2>Gen key</h2>
  <?php if (!$games): ?><p style="color:#fca5a5;font-size:13px">⚠ Chưa có game. <a href="?tab=games" style="color:#a78bfa">Thêm game trước</a>.</p>
  <?php else: ?>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="gen_bulk_keys">
    <div class="row">
      <div class="field"><label>Game</label><select name="game_id" required><?php foreach ($games as $g): ?><option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label>Số lượng</label><input type="number" name="count" required min="1" max="1000" value="10"></div>
      <div class="field"><label>Duration (hours)</label><input type="number" name="duration_hours" required min="1" value="720"></div>
      <div class="field"><label>Max devices</label><input type="number" name="max_devices" required min="1" max="10" value="1"></div>
      <div class="field"><label>Notes (batch)</label><input name="notes" placeholder="Mô tả lô"></div>
    </div>
    <button class="btn primary" type="submit">⚡ Gen bulk</button>
    <span style="margin-left:10px;font-size:12px;color:#5a6478">Tối đa 1000 key/lần. Sau gen → tab Keys list xem + export CSV.</span>
  </form>
  <?php endif ?>
</div>

<div class="card">
  <h2>Danh sách 100 key mới nhất</h2>
  <?php $keys = $db->query("SELECT k.*, g.name game_name FROM license_keys k JOIN games g ON k.game_id=g.id ORDER BY k.id DESC LIMIT 100")->fetchAll(); ?>
  <?php if (!$keys): ?><p style="color:#5a6478;font-size:13px">Chưa có key.</p>
  <?php else: ?>
  <table><tr><th>Key</th><th>Game</th><th>Duration</th><th>Dev</th><th>Status</th><th>Activated</th><th>Expired</th><th>Batch</th><th>Action</th></tr>
  <?php foreach ($keys as $k): ?>
  <tr>
    <td><code style="font-size:11px"><?= h($k['key_code']) ?></code></td>
    <td><?= h($k['game_name']) ?></td>
    <td><?= (int)$k['duration_hours'] ?>h</td>
    <td><?= (int)$k['max_devices'] ?> · <?= $k['devices'] ? count(explode(',', $k['devices'])) : 0 ?> bound</td>
    <td><span class="badge <?= $k['status']==='active'?'green':($k['status']==='banned'?'red':($k['status']==='expired'?'orange':'gray')) ?>"><?= $k['status'] ?></span></td>
    <td style="font-size:11px;color:#7a8499"><?= h($k['activated_at'] ?: '—') ?></td>
    <td style="font-size:11px;color:#7a8499"><?= h($k['expired_at'] ?: '—') ?></td>
    <td style="font-size:11px"><?php if ($k['batch_id']): ?><a href="?action=export_batch&batch=<?= urlencode($k['batch_id']) ?>" style="color:#a78bfa;text-decoration:none">📥 <?= h(substr($k['batch_id'], -8)) ?></a><?php else: ?>—<?php endif ?></td>
    <td class="actions">
      <a class="btn tiny ghost" href="?tab=keys&edit=<?= $k['id'] ?>">Sửa</a>
      <?php if ($k['devices']): ?><form method="POST" style="display:inline" onsubmit="return confirm('Reset device binding?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="reset_binding"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="btn tiny ghost" type="submit">Reset</button></form><?php endif ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Xoá key?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="delete_key"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="btn tiny danger" type="submit">Xoá</button></form>
    </td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<?php elseif ($tab === 'bindings'): ?>
<h1>Device Bindings</h1>
<div class="card">
  <h2>Key có device bound</h2>
  <?php $rows = $db->query("SELECT k.*, g.name game_name FROM license_keys k JOIN games g ON k.game_id=g.id WHERE k.devices IS NOT NULL AND k.devices != '' ORDER BY k.activated_at DESC LIMIT 100")->fetchAll(); ?>
  <?php if (!$rows): ?><p style="color:#5a6478;font-size:13px">Chưa có key nào bound device.</p>
  <?php else: ?>
  <table><tr><th>Key</th><th>Game</th><th>Devices</th><th>Activated</th><th>Expired</th><th>Action</th></tr>
  <?php foreach ($rows as $k):
      $devices = array_filter(explode(',', $k['devices'])); ?>
  <tr>
    <td><code style="font-size:11px"><?= h($k['key_code']) ?></code></td>
    <td><?= h($k['game_name']) ?></td>
    <td style="font-size:11px;color:#7a8499"><?= count($devices) ?>/<?= (int)$k['max_devices'] ?>: <?php foreach ($devices as $d): ?><code style="font-size:10px;display:inline-block;margin-right:4px"><?= h(substr($d, 0, 12)) ?>…</code><?php endforeach ?></td>
    <td style="font-size:11px;color:#7a8499"><?= h($k['activated_at']) ?></td>
    <td style="font-size:11px;color:#7a8499"><?= h($k['expired_at']) ?></td>
    <td class="actions">
      <form method="POST" style="display:inline" onsubmit="return confirm('Reset binding cho key này?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="reset_binding"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="btn tiny ghost" type="submit">Reset</button></form>
    </td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<div class="card">
  <h2>Banned devices</h2>
  <form method="POST" style="margin-bottom:14px">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="ban_device">
    <div class="row">
      <div class="field"><label>Device Serial</label><input name="serial" required placeholder="hash UUID..."></div>
      <div class="field"><label>Reason</label><input name="reason" placeholder="Crack attempt"></div>
    </div>
    <button class="btn danger" type="submit">+ Ban device</button>
  </form>
  <?php $banned = $db->query("SELECT * FROM banned_devices ORDER BY id DESC LIMIT 50")->fetchAll(); ?>
  <?php if (!$banned): ?><p style="color:#5a6478;font-size:13px">Chưa ban device nào.</p>
  <?php else: ?>
  <table><tr><th>Serial</th><th>Reason</th><th>Banned at</th><th>Action</th></tr>
  <?php foreach ($banned as $b): ?>
  <tr>
    <td><code style="font-size:11px"><?= h(substr($b['device_serial'], 0, 24)) ?>…</code></td>
    <td style="font-size:12px"><?= h($b['reason']) ?></td>
    <td style="font-size:11px;color:#7a8499"><?= h($b['created_at']) ?></td>
    <td><form method="POST" style="display:inline"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="unban_device"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="btn tiny ghost" type="submit">Unban</button></form></td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<?php elseif ($tab === 'logs'): ?>
<h1>Connect Logs</h1>
<div class="card">
  <?php
  $filterAct = $_GET['filter'] ?? '';
  $where = '';
  if ($filterAct) $where = "WHERE action = " . $db->quote($filterAct);
  $logs = $db->query("SELECT l.*, k.key_code FROM connect_logs l LEFT JOIN license_keys k ON l.key_id=k.id $where ORDER BY l.id DESC LIMIT 200")->fetchAll();
  ?>
  <div style="margin-bottom:12px">
    <a class="btn tiny <?= !$filterAct?'primary':'ghost' ?>" href="?tab=logs">Tất cả</a>
    <a class="btn tiny <?= $filterAct==='verify_ok'?'primary':'ghost' ?>" href="?tab=logs&filter=verify_ok">OK</a>
    <a class="btn tiny <?= $filterAct==='rejected'?'primary':'ghost' ?>" href="?tab=logs&filter=rejected">Rejected</a>
    <a class="btn tiny <?= $filterAct==='rate_limited'?'primary':'ghost' ?>" href="?tab=logs&filter=rate_limited">Rate limited</a>
    <a class="btn tiny <?= $filterAct==='activate'?'primary':'ghost' ?>" href="?tab=logs&filter=activate">Activate</a>
  </div>
  <?php if (!$logs): ?><p style="color:#5a6478;font-size:13px">Chưa có log.</p>
  <?php else: ?>
  <table><tr><th>Time</th><th>Key</th><th>Device</th><th>IP</th><th>Action</th><th>Reason</th></tr>
  <?php foreach ($logs as $l): ?>
  <tr>
    <td style="font-size:11px;color:#7a8499"><?= h($l['created_at']) ?></td>
    <td><code style="font-size:11px"><?= h($l['key_code'] ?: '—') ?></code></td>
    <td style="font-size:11px;color:#7a8499"><?= h(substr($l['device_serial'] ?? '', 0, 16)) ?>…</td>
    <td style="font-size:11px"><?= h($l['ip']) ?></td>
    <td><span class="badge <?= $l['action']==='verify_ok'?'green':($l['action']==='rejected'?'red':($l['action']==='rate_limited'?'orange':'gray')) ?>"><?= h($l['action']) ?></span></td>
    <td style="font-size:12px;color:#7a8499"><?= h($l['reason']) ?></td>
  </tr>
  <?php endforeach ?>
  </table>
  <?php endif ?>
</div>

<?php elseif ($tab === 'settings'): ?>
<h1>Settings</h1>
<?php
$maintenance = (int)($db->query("SELECT v FROM settings WHERE k='maintenance'")->fetchColumn() ?: 0);
$mtMsg = (string)($db->query("SELECT v FROM settings WHERE k='maintenance_message'")->fetchColumn() ?: '');
?>
<div class="card">
  <h2>Maintenance</h2>
  <form method="POST" style="display:inline-block;margin-right:14px"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="toggle_maintenance"><button class="btn <?= $maintenance?'danger':'primary' ?>" type="submit"><?= $maintenance?'⛔ ĐANG BẢO TRÌ — Click để tắt':'✓ Online — Click để bật bảo trì' ?></button></form>
  <form method="POST" style="margin-top:14px">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="set_maintenance_msg">
    <div class="field"><label>Maintenance message</label><input name="msg" value="<?= h($mtMsg) ?>" placeholder="Server bảo trì..."></div>
    <button class="btn primary" type="submit">Lưu message</button>
  </form>
</div>

<div class="card">
  <h2>Secrets (mask)</h2>
  <table>
    <tr><th>Key</th><th>Value (mask)</th><th>Note</th></tr>
    <tr><td>STATIC_WORD</td><td><code><?= h(substr(STATIC_WORD, 0, 4)) ?>…<?= h(substr(STATIC_WORD, -4)) ?></code></td><td style="font-size:12px;color:#7a8499">Client md5 verify token</td></tr>
    <tr><td>HMAC_SECRET</td><td><code><?= h(substr(HMAC_SECRET, 0, 4)) ?>…<?= h(substr(HMAC_SECRET, -4)) ?></code></td><td style="font-size:12px;color:#7a8499">Request HMAC sign</td></tr>
    <tr><td>BODY_XOR_BASE</td><td><code><?= h(substr(BODY_XOR_BASE, 0, 4)) ?>…<?= h(substr(BODY_XOR_BASE, -4)) ?></code></td><td style="font-size:12px;color:#7a8499">Body XOR key derive base</td></tr>
  </table>
  <p style="font-size:12px;color:#7a8499;margin-top:10px">Rotate bằng cách sửa <code>config.local.php</code> trên server. Sau rotate buộc tất cả client phải request lại + redownload script body.</p>
</div>

<?php endif ?>

</main>
</body>
</html>
