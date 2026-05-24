<?php
/**
 * HCLOU-LICENSE — Download endpoint per-key loader.
 * /admin/loader.php?id=<key_id> — only admin authenticated.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/loader_builder.php';

@session_start();

// Auth check
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Not authorized. <a href="index.php">Login</a>');
}
$_SESSION['admin_last'] = time();

$keyId = (int)($_GET['id'] ?? 0);
if (!$keyId) {
    http_response_code(400);
    die('Missing id');
}

$db = getDB();
$stmt = $db->prepare("SELECT k.*, g.id AS g_id, g.package_id, g.name AS g_name FROM license_keys k JOIN games g ON k.game_id = g.id WHERE k.id = ?");
$stmt->execute([$keyId]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    die('Key not found');
}

$key = [
    'key_code' => $row['key_code'],
];
$game = [
    'package_id' => $row['package_id'],
    'name'       => $row['g_name'],
];

$content = buildLoaderForKey($key, $game);
if ($content === '') {
    http_response_code(500);
    die('Build loader failed (template missing?)');
}

$filename = 'HCLOU_Loader_' . preg_replace('/[^A-Za-z0-9]/', '_', $row['key_code']) . '.lua';

header('Content-Type: text/x-lua; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $content;
