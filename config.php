<?php
/**
 * HCLOU-LICENSE — License server cho Lua/GameGuardian script.
 * Tách biệt khỏi HCLOU shop. Pattern PHP vanilla + PDO.
 */

// =============================================
// DB CONNECTION
// =============================================
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'hclou_license');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// =============================================
// APP CORE
// =============================================
if (!defined('APP_ROOT'))      define('APP_ROOT', __DIR__);
if (!defined('SITE_URL'))      define('SITE_URL', 'https://license.example.com');
if (!defined('SITE_NAME'))     define('SITE_NAME', 'HCLOU License');
if (!defined('APP_TIMEZONE'))  define('APP_TIMEZONE', 'Asia/Ho_Chi_Minh');

// =============================================
// CRYPTO SECRETS — đổi NGAY khi deploy, rotate định kỳ
// =============================================
// Static word cho client tự verify response token md5(game-key-serial-staticWord).
// LEAK = mất toàn bộ verify chain. KHÔNG commit secret thực tế vào git.
if (!defined('STATIC_WORD'))      define('STATIC_WORD', 'CHANGE_ME_RANDOM_32_CHARS_HERE');
// HMAC secret cho request sign (anti-tamper + anti-replay).
if (!defined('HMAC_SECRET'))      define('HMAC_SECRET', 'CHANGE_ME_RANDOM_64_CHARS_HERE');
// Body XOR key derive base (combined với user_key + device_fp).
if (!defined('BODY_XOR_BASE'))    define('BODY_XOR_BASE', 'CHANGE_ME_BASE_KEY');

// =============================================
// ADMIN AUTH
// =============================================
if (!defined('ADMIN_USERNAME'))   define('ADMIN_USERNAME', 'admin');
if (!defined('ADMIN_PASS_HASH'))  define('ADMIN_PASS_HASH', '$2y$10$CHANGE_ME_BCRYPT_HASH');

// =============================================
// ANTI-CRACK SETTINGS
// =============================================
if (!defined('RATE_LIMIT_PER_KEY'))     define('RATE_LIMIT_PER_KEY', 5);       // req/phút/key
if (!defined('RATE_LIMIT_WINDOW'))      define('RATE_LIMIT_WINDOW', 60);       // giây
if (!defined('NONCE_TTL'))              define('NONCE_TTL', 60);               // giây — replay window
if (!defined('TOKEN_VALIDITY'))         define('TOKEN_VALIDITY', 30);          // giây — client verify rng window
if (!defined('KEY_PREFIX'))             define('KEY_PREFIX', 'HCLOU-');        // format: HCLOU-XXXXXXXXXXXX
if (!defined('KEY_RANDOM_LEN'))         define('KEY_RANDOM_LEN', 12);          // alphanum sau prefix

// =============================================
// BOOTSTRAP
// =============================================
date_default_timezone_set(APP_TIMEZONE);
@session_start();

if (!is_dir(APP_ROOT . '/data')) @mkdir(APP_ROOT . '/data', 0700, true);

// =============================================
// DB CONNECTION (PDO singleton)
// =============================================
function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['status' => false, 'reason' => 'DB_ERROR']));
        }
    }
    return $db;
}

// =============================================
// HELPERS
// =============================================
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function logConnect(int $keyId, string $serial, string $ip, string $action, string $reason = ''): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO connect_logs (key_id, device_serial, ip, action, reason) VALUES (?, ?, ?, ?, ?)")
           ->execute([$keyId, $serial, $ip, $action, $reason]);
    } catch (Exception $e) {
        // Log fail không break flow
    }
}

function genKeyCode(): string {
    // HCLOU-XXXXXXXXXXXX (prefix + 12 alphanum uppercase, exclude confusing chars)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // bỏ I,O,0,1
    $out = '';
    $len = (int)KEY_RANDOM_LEN;
    $clen = strlen($chars);
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $clen - 1)];
    }
    return KEY_PREFIX . $out;
}

function clientIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
