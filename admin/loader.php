<?php
/**
 * HCLOU-LICENSE — Download loader per-game endpoint.
 * /admin/loader.php?game=<game_id> — admin auth.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/loader_builder.php';

@session_start();

if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Not authorized. <a href="index.php">Login</a>');
}
$_SESSION['admin_last'] = time();

$gameId = (int)($_GET['game'] ?? 0);
if (!$gameId) {
    http_response_code(400);
    die('Missing game id');
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
$stmt->execute([$gameId]);
$game = $stmt->fetch();
if (!$game) {
    http_response_code(404);
    die('Game not found');
}

$content = buildLoaderForGame($game);
if ($content === '') {
    http_response_code(500);
    die('Build loader failed (template missing?)');
}

$filename = 'HCLOU_Loader_' . preg_replace('/[^A-Za-z0-9]/', '_', $game['slug']) . '.lua';

header('Content-Type: text/x-lua; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
echo $content;
