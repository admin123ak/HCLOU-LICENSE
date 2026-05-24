<?php
/**
 * HCLOU-LICENSE — Loader builder + server-side obfuscation.
 *
 * Build file Lua loader per-key:
 * - Embed key sẵn (user không phải nhập).
 * - Secrets derive riêng per-key (leak 1 loader = lộ 1 key, master vẫn an toàn).
 * - URL encrypted XOR + base64 (attacker reverse thấy garbage).
 * - String constants encrypted (junk vs real).
 * - Variable rename random.
 * - Junk code injection.
 */

require_once __DIR__ . '/crypto.php';

/**
 * Derive secrets per-key từ master.
 */
function deriveHmac(string $keyCode): string {
    return hash_hmac('sha256', 'hmac:' . $keyCode, HMAC_SECRET);
}
function deriveStaticWord(string $keyCode): string {
    return hash_hmac('sha256', 'static:' . $keyCode, STATIC_WORD);
}
function deriveXorBase(string $keyCode): string {
    return hash_hmac('sha256', 'xor:' . $keyCode, BODY_XOR_BASE);
}

/**
 * Encrypt URL với XOR + base64. Return Lua snippet decrypt runtime.
 */
function obfuscateString(string $plain): array {
    $keyBin = random_bytes(8);
    $keyHex = bin2hex($keyBin);
    $klen = strlen($keyBin);
    $enc = '';
    for ($i = 0, $n = strlen($plain); $i < $n; $i++) {
        $enc .= chr(ord($plain[$i]) ^ ord($keyBin[$i % $klen]));
    }
    return [
        'key_hex' => $keyHex,
        'enc_b64' => base64_encode($enc),
    ];
}

/**
 * Random Lua variable name (avoid Lua keywords).
 */
function randVarName(int $len = 6): string {
    static $used = [];
    $kw = ['and','break','do','else','elseif','end','false','for','function','goto','if','in','local','nil','not','or','repeat','return','then','true','until','while','load','math','string','table','io','os'];
    do {
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $name = '_';
        for ($i = 0; $i < $len; $i++) {
            $name .= $chars[random_int(0, 25)];
        }
    } while (in_array($name, $kw, true) || isset($used[$name]));
    $used[$name] = true;
    return $name;
}

/**
 * Inject junk code dummy: vài variable declaration vô nghĩa, đỡ pattern match.
 */
function junkLines(int $n = 5): string {
    $out = '';
    for ($i = 0; $i < $n; $i++) {
        $v = randVarName(7);
        $val = bin2hex(random_bytes(random_int(4, 12)));
        $out .= "local {$v} = \"{$val}\"\n";
    }
    return $out;
}

/**
 * Build loader Lua content for a specific key.
 * Return: string Lua file content (chưa minify).
 */
function buildLoaderForKey(array $key, array $game): string {
    $keyCode = $key['key_code'];
    $apiUrl  = rtrim(SITE_URL, '/') . '/api/connect.php';

    $perKeyHmac    = deriveHmac($keyCode);
    $perKeyStatic  = deriveStaticWord($keyCode);
    $perKeyXorBase = deriveXorBase($keyCode);

    // Obfuscate 4 strings: URL + HMAC + STATIC + XOR_BASE + KEY_CODE
    $obfUrl    = obfuscateString($apiUrl);
    $obfHmac   = obfuscateString($perKeyHmac);
    $obfStatic = obfuscateString($perKeyStatic);
    $obfXor    = obfuscateString($perKeyXorBase);
    $obfKey    = obfuscateString($keyCode);
    $obfGame   = obfuscateString($game['package_id']);

    // Read template
    $template = file_get_contents(__DIR__ . '/../client/HCLOU_Loader.lua');
    if ($template === false) return '';

    // Vars random — base names → random  (chống grep pattern)
    $vSha256Bin  = randVarName(8);
    $vSha256Hex  = randVarName(8);
    $vHmacSha256 = randVarName(8);
    $vMd5Hex     = randVarName(8);
    $vB64Dec     = randVarName(8);
    $vXorStream  = randVarName(8);
    $vTohex      = randVarName(8);
    $vGetSerial  = randVarName(8);
    $vGenNonce   = randVarName(8);
    $vUrlencode  = randVarName(8);
    $vJsonExtr   = randVarName(8);
    $vJsonBool   = randVarName(8);
    $vDecStr     = randVarName(10);   // decrypt string helper

    // Build header section — replace cả block placeholder + main flow
    $header = "-- HCLOU Loader (auto-generated " . date('Y-m-d H:i') . ", key " . substr($keyCode, 0, 12) . "***)\n";
    $header .= junkLines(8);

    // Helper decrypt string (XOR + base64)
    $decryptHelper = "
local function {$vDecStr}(kHex, encB64)
    local kb = \"\"
    for i = 1, #kHex, 2 do kb = kb .. string.char(tonumber(kHex:sub(i, i+1), 16)) end
    local s = ({_b64_})(encB64)
    local out = {}
    for i = 1, #s do out[i] = string.char(s:byte(i) ~ kb:byte(((i-1) % #kb) + 1)) end
    return table.concat(out)
end
";

    // Encrypted constants block
    $constants = "
local _u_k = \"{$obfUrl['key_hex']}\";    local _u_e = \"{$obfUrl['enc_b64']}\"
local _h_k = \"{$obfHmac['key_hex']}\";   local _h_e = \"{$obfHmac['enc_b64']}\"
local _s_k = \"{$obfStatic['key_hex']}\"; local _s_e = \"{$obfStatic['enc_b64']}\"
local _x_k = \"{$obfXor['key_hex']}\";    local _x_e = \"{$obfXor['enc_b64']}\"
local _k_k = \"{$obfKey['key_hex']}\";    local _k_e = \"{$obfKey['enc_b64']}\"
local _g_k = \"{$obfGame['key_hex']}\";   local _g_e = \"{$obfGame['enc_b64']}\"
" . junkLines(6);

    // Find main flow trong template + thay placeholder + main
    // Template chứa block "local API_URL = ..." + "local HMAC_SECRET = ..." + "local STATIC_WORD = ..." + "local BODY_XOR_BASE = ..."
    // → Đổi thành: API_URL = decrypt(_u_k, _u_e), etc.
    $template = preg_replace(
        '/local\s+API_URL\s*=\s*"<<API_URL>>"\s*--[^\n]*/',
        "-- (URL embedded encrypted, decrypted later)",
        $template
    );
    $template = preg_replace(
        '/local\s+HMAC_SECRET\s*=\s*"<<HMAC_SECRET>>"\s*--[^\n]*/',
        "-- (HMAC embedded encrypted)",
        $template
    );
    $template = preg_replace(
        '/local\s+STATIC_WORD\s*=\s*"<<STATIC_WORD>>"\s*--[^\n]*/',
        "-- (STATIC embedded encrypted)",
        $template
    );
    $template = preg_replace(
        '/local\s+BODY_XOR_BASE\s*=\s*"<<BODY_XOR_BASE>>"\s*--[^\n]*/',
        "-- (XOR_BASE embedded encrypted)",
        $template
    );

    // Replace prompt + key input → skip (auto fill embedded key)
    $template = preg_replace(
        '/-- Prompt key.*?if not user_key:find\("\^HCLOU%-"\) then.*?os\.exit\(\)\s*end/s',
        "-- (key embedded — no prompt)\nlocal user_key = nil",
        $template
    );

    // Sau khi decrypt vars có sẵn, set vào local biến chính của loader
    $injectVars = "
local API_URL       = {$vDecStr}(_u_k, _u_e)
local HMAC_SECRET   = {$vDecStr}(_h_k, _h_e)
local STATIC_WORD   = {$vDecStr}(_s_k, _s_e)
local BODY_XOR_BASE = {$vDecStr}(_x_k, _x_e)
user_key            = {$vDecStr}(_k_k, _k_e)
local PACKAGE_EMBED = {$vDecStr}(_g_k, _g_e)
";

    // Insert vào trước \"local info = gg.getTargetInfo()\"
    $template = preg_replace(
        '/-- =+\nlocal info = gg\.getTargetInfo\(\)/',
        $injectVars . "\n-- ============================================================================\nlocal info = gg.getTargetInfo()",
        $template
    );

    // Wipe sensitive sau khi build payload — bỏ embed key
    // Build final loader: header(junk) + decryptHelper + constants + template main
    $final = $header . $decryptHelper . $constants . $template;

    // Replace placeholder cho b64decode trong decryptHelper
    // Cần tìm base64_decode trong template + rename
    // Đơn giản: dùng tên hàm base64_decode trong template thẳng
    $final = str_replace('{_b64_}', 'base64_decode', $final);

    return $final;
}
