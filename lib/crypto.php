<?php
/**
 * HCLOU-LICENSE — Crypto helpers (HMAC sign, XOR body encrypt, nonce store).
 */

/**
 * Verify HMAC sign từ client.
 * sign = hex(hmac_sha256(HMAC_SECRET, game|user_key|serial|nonce|timestamp))
 */
function hmacVerify(string $game, string $userKey, string $serial, string $nonce, string $ts, string $sign): bool {
    $payload = "{$game}|{$userKey}|{$serial}|{$nonce}|{$ts}";
    $expected = hash_hmac('sha256', $payload, HMAC_SECRET);
    return hash_equals($expected, strtolower($sign));
}

/**
 * Per-key derive — phải KHỚP với app C++ (Main.cpp:201,226).
 *   perHmac   = hmac_sha256(HMAC_SECRET, "hmac:"   + user_key)
 *   perStatic = hmac_sha256(STATIC_WORD, "static:" + user_key)
 */
function deriveHmac(string $userKey): string {
    return hash_hmac('sha256', 'hmac:' . $userKey, HMAC_SECRET);
}
function deriveStaticWord(string $userKey): string {
    return hash_hmac('sha256', 'static:' . $userKey, STATIC_WORD);
}

/**
 * Derive XOR key cho body: hash(user_key + serial + BODY_XOR_BASE).
 * Mỗi (user_key, serial) cặp khác nhau → key khác → 2 user share body không decrypt được.
 */
function deriveBodyKey(string $userKey, string $serial): string {
    return hash('sha256', $userKey . '|' . $serial . '|' . BODY_XOR_BASE, true); // 32 bytes binary
}

/**
 * XOR encrypt/decrypt body. Stream với key 32 bytes lặp lại.
 * Return base64 string để JSON-safe.
 */
function xorEncode(string $plaintext, string $keyBin): string {
    $klen = strlen($keyBin);
    $out = '';
    $len = strlen($plaintext);
    for ($i = 0; $i < $len; $i++) {
        $out .= $plaintext[$i] ^ $keyBin[$i % $klen];
    }
    return base64_encode($out);
}

/**
 * Nonce storage (file-based). Mỗi nonce file tồn tại NONCE_TTL giây thì xoá.
 * Trả true nếu nonce CHƯA dùng (đã lưu), false nếu đã dùng (replay).
 */
function nonceClaim(string $nonce): bool {
    $dir = APP_ROOT . '/data/nonces';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    // GC: dọn file > 5 phút (chạy ngẫu nhiên 1/50 request để giảm IO)
    if (random_int(1, 50) === 1) {
        $cutoff = time() - 300;
        foreach (glob($dir . '/*') as $f) {
            if (@filemtime($f) < $cutoff) @unlink($f);
        }
    }
    $safe = preg_replace('/[^a-zA-Z0-9]/', '', $nonce);
    if (strlen($safe) < 8) return false;
    $file = $dir . '/' . substr($safe, 0, 64);
    if (file_exists($file)) return false; // replay
    @file_put_contents($file, '1', LOCK_EX);
    return true;
}

/**
 * Rate limit per key. Trả true nếu chưa vượt, false nếu vượt.
 */
function rateLimitKey(int $keyId): bool {
    $dir = APP_ROOT . '/data/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/key_' . $keyId . '.json';
    $now = time();
    $win = (int)RATE_LIMIT_WINDOW;
    $max = (int)RATE_LIMIT_PER_KEY;
    $data = ['start' => $now, 'count' => 0];
    if (is_file($file)) {
        $old = json_decode((string)@file_get_contents($file), true);
        if (is_array($old) && !empty($old['start']) && ($now - (int)$old['start']) < $win) $data = $old;
    }
    if (($now - (int)$data['start']) >= $win) $data = ['start' => $now, 'count' => 0];
    $data['count'] = (int)$data['count'] + 1;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return $data['count'] <= $max;
}
