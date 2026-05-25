<?php
/**
 * HCLOU-LICENSE — API endpoint /api/connect
 *
 * Method: POST
 * Body params:
 *   game       — package_id của game (vd com.tencent.ig)
 *   user_key   — key code (HCLOU-XXXXXXXXXXXX)
 *   serial     — device fingerprint (hash UUID từ android_id + model + brand)
 *   nonce      — random unique string ≥ 8 chars (chống replay)
 *   timestamp  — unix timestamp request (window 60s)
 *   hmac       — hex sha256_hmac(HMAC_SECRET, game|user_key|serial|nonce|timestamp)
 *
 * Response (success):
 *   { status: true, data: { script_body (base64 XOR), token (md5), version, EXP, max_devices, rng } }
 *
 * Response (fail):
 *   { status: false, reason: "..." }
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/crypto.php';
require_once __DIR__ . '/../lib/loader_builder.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => false,
        'service' => SITE_NAME,
        'version' => '1.0.0',
        'reason'  => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

$db = getDB();
$ip = clientIP();

// =============================================
// 0. MAINTENANCE CHECK
// =============================================
try {
    $mt = (int)($db->query("SELECT v FROM settings WHERE k='maintenance'")->fetchColumn() ?: 0);
    if ($mt === 1) {
        $msg = (string)($db->query("SELECT v FROM settings WHERE k='maintenance_message'")->fetchColumn() ?: 'Server bảo trì');
        jsonResponse(['status' => false, 'reason' => 'MAINTENANCE', 'message' => $msg]);
    }
} catch (Exception $e) {
    jsonResponse(['status' => false, 'reason' => 'SERVER_ERROR'], 500);
}

// =============================================
// 1. INPUT VALIDATION
// =============================================
$game    = trim($_POST['game']      ?? '');
$userKey = trim($_POST['user_key']  ?? '');
$serial  = trim($_POST['serial']    ?? '');
$nonce   = trim($_POST['nonce']     ?? '');
$ts      = trim($_POST['timestamp'] ?? '');
$hmac    = trim($_POST['hmac']      ?? '');

if (!$game || !$userKey || !$serial || !$nonce || !$ts || !$hmac) {
    logConnect(0, $serial, $ip, 'rejected', 'INVALID_PARAMETER');
    jsonResponse(['status' => false, 'reason' => 'INVALID_PARAMETER']);
}

if (strlen($serial) < 8 || strlen($serial) > 80
    || strlen($userKey) < 8 || strlen($userKey) > 40
    || strlen($nonce) < 8 || strlen($nonce) > 64
    || !ctype_digit($ts) || strlen($hmac) !== 64) {
    logConnect(0, $serial, $ip, 'rejected', 'MALFORMED_INPUT');
    jsonResponse([
        'status' => false,
        'reason' => 'MALFORMED_INPUT',
        'debug' => [
            'game_len'     => strlen($game),
            'userKey_len'  => strlen($userKey),
            'serial_len'   => strlen($serial),
            'nonce_len'    => strlen($nonce),
            'ts_isdigit'   => ctype_digit($ts) ? 'yes' : 'no',
            'ts_value'     => substr($ts, 0, 20),
            'hmac_len'     => strlen($hmac),
            'hmac_sample'  => substr($hmac, 0, 12),
        ],
    ]);
}

// =============================================
// 2. TIMESTAMP WINDOW (chống replay cũ)
// =============================================
$now = time();
$tsInt = (int)$ts;
if (abs($now - $tsInt) > (int)NONCE_TTL) {
    logConnect(0, $serial, $ip, 'rejected', 'TIMESTAMP_OUT_OF_WINDOW');
    jsonResponse(['status' => false, 'reason' => 'TIMESTAMP_OUT_OF_WINDOW']);
}

// =============================================
// 3. HMAC VERIFY (chống tamper) — dùng derived secret per-key
// =============================================
$perKeyHmac = deriveHmac($userKey);
$payload = "{$game}|{$userKey}|{$serial}|{$nonce}|{$ts}";
$expectedHmac = hash_hmac('sha256', $payload, $perKeyHmac);
if (!hash_equals($expectedHmac, strtolower($hmac))) {
    logConnect(0, $serial, $ip, 'rejected', 'HMAC_INVALID');
    jsonResponse(['status' => false, 'reason' => 'HMAC_INVALID']);
}

// =============================================
// 4. NONCE CLAIM (chống replay)
// =============================================
if (!nonceClaim($nonce)) {
    logConnect(0, $serial, $ip, 'rejected', 'NONCE_REUSED');
    jsonResponse(['status' => false, 'reason' => 'NONCE_REUSED']);
}

// =============================================
// 5. BANNED DEVICE CHECK
// =============================================
try {
    $banned = $db->prepare("SELECT COUNT(*) FROM banned_devices WHERE device_serial = ?");
    $banned->execute([$serial]);
    if ((int)$banned->fetchColumn() > 0) {
        logConnect(0, $serial, $ip, 'rejected', 'DEVICE_BANNED');
        jsonResponse(['status' => false, 'reason' => 'DEVICE_BANNED']);
    }
} catch (Exception $e) {
    jsonResponse(['status' => false, 'reason' => 'SERVER_ERROR'], 500);
}

// =============================================
// 6. FIND GAME by package_id
// =============================================
try {
    $g = $db->prepare("SELECT * FROM games WHERE package_id = ? AND is_active = 1");
    $g->execute([$game]);
    $gameRow = $g->fetch();
    if (!$gameRow) {
        logConnect(0, $serial, $ip, 'rejected', 'GAME_NOT_FOUND');
        jsonResponse(['status' => false, 'reason' => 'GAME_NOT_FOUND']);
    }
} catch (Exception $e) {
    jsonResponse(['status' => false, 'reason' => 'SERVER_ERROR'], 500);
}

// =============================================
// 7. FIND KEY by code + game match
// =============================================
try {
    $k = $db->prepare("SELECT * FROM license_keys WHERE key_code = ?");
    $k->execute([$userKey]);
    $key = $k->fetch();
    if (!$key) {
        logConnect(0, $serial, $ip, 'rejected', 'KEY_NOT_FOUND');
        jsonResponse(['status' => false, 'reason' => 'KEY_NOT_FOUND']);
    }
    if ((int)$key['game_id'] !== (int)$gameRow['id']) {
        logConnect((int)$key['id'], $serial, $ip, 'rejected', 'KEY_GAME_MISMATCH');
        jsonResponse(['status' => false, 'reason' => 'KEY_NOT_VALID_FOR_THIS_GAME']);
    }
} catch (Exception $e) {
    jsonResponse(['status' => false, 'reason' => 'SERVER_ERROR'], 500);
}

$keyId = (int)$key['id'];

// =============================================
// 8. RATE LIMIT per key
// =============================================
if (!rateLimitKey($keyId)) {
    logConnect($keyId, $serial, $ip, 'rate_limited', 'PER_KEY_LIMIT');
    jsonResponse(['status' => false, 'reason' => 'RATE_LIMITED'], 429);
}

// =============================================
// 9. STATUS CHECK
// =============================================
if ($key['status'] === 'banned') {
    logConnect($keyId, $serial, $ip, 'rejected', 'KEY_BANNED');
    jsonResponse(['status' => false, 'reason' => 'KEY_BANNED']);
}

// =============================================
// 10. ACTIVATE FIRST TIME / EXPIRE CHECK
// =============================================
$duration = (int)$key['duration_hours'];
$expireAt = $key['expired_at'];

if ($key['status'] === 'unused' || !$expireAt) {
    // Activate
    $newExp = date('Y-m-d H:i:s', $now + $duration * 3600);
    $db->prepare("UPDATE license_keys SET status='active', activated_at=NOW(), expired_at=? WHERE id=?")
       ->execute([$newExp, $keyId]);
    $expireAt = $newExp;
    logConnect($keyId, $serial, $ip, 'activate', 'FIRST_TIME');
} else {
    // Check expire
    if (strtotime($expireAt) < $now) {
        $db->prepare("UPDATE license_keys SET status='expired' WHERE id=?")->execute([$keyId]);
        logConnect($keyId, $serial, $ip, 'rejected', 'EXPIRED');
        jsonResponse(['status' => false, 'reason' => 'EXPIRED_KEY']);
    }
}

// =============================================
// 11. DEVICE BINDING
// =============================================
$devicesStr = (string)($key['devices'] ?? '');
$devices = array_values(array_filter(array_unique(explode(',', $devicesStr))));
$maxDev  = max(1, (int)$key['max_devices']);

if (!in_array($serial, $devices, true)) {
    if (count($devices) >= $maxDev) {
        logConnect($keyId, $serial, $ip, 'rejected', 'MAX_DEVICE_REACHED');
        jsonResponse(['status' => false, 'reason' => 'MAX_DEVICE_REACHED']);
    }
    $devices[] = $serial;
    $db->prepare("UPDATE license_keys SET devices = ? WHERE id = ?")
       ->execute([implode(',', $devices), $keyId]);
}

// =============================================
// 12. GET LATEST ACTIVE SCRIPT for this game
// =============================================
$s = $db->prepare("SELECT * FROM scripts WHERE game_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
$s->execute([(int)$gameRow['id']]);
$script = $s->fetch();
if (!$script) {
    logConnect($keyId, $serial, $ip, 'rejected', 'NO_SCRIPT_AVAILABLE');
    jsonResponse(['status' => false, 'reason' => 'NO_SCRIPT_AVAILABLE']);
}

// =============================================
// 13. ENCRYPT BODY + BUILD RESPONSE
// Per-key derived secrets cho token + XOR base
// =============================================
$perKeyXorBase = deriveXorBase($userKey);
$bodyKeyMaterial = $userKey . '|' . $serial . '|' . $perKeyXorBase;
$bodyKey = hash('sha256', $bodyKeyMaterial, true); // 32 bytes binary
$bodyEnc = xorEncode((string)$script['body'], $bodyKey);

$perKeyStatic = deriveStaticWord($userKey);
$real  = "{$game}-{$userKey}-{$serial}-" . $perKeyStatic;
$token = md5($real);

logConnect($keyId, $serial, $ip, 'verify_ok', 'v=' . $script['version']);

jsonResponse([
    'status' => true,
    'data' => [
        'script_body' => $bodyEnc,
        'version'     => (string)$script['version'],
        'token'       => $token,
        'EXP'         => $expireAt,
        'max_devices' => $maxDev,
        'rng'         => $now,
    ],
]);
