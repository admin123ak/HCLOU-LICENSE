<?php
/**
 * HCLOU-LICENSE — Loader builder per-game (1 loader chung cho cả game).
 *
 * Loader output:
 * - Embed MASTER secrets (URL + HMAC + STATIC + XOR_BASE) encrypted.
 * - KHÔNG embed key — user prompt nhập trong GG.
 * - Loader derive per-key secrets runtime (match server PHP derive*).
 * - Encrypted constants + random var names + junk lines.
 */

require_once __DIR__ . '/crypto.php';

function deriveHmac(string $keyCode): string {
    return hash_hmac('sha256', 'hmac:' . $keyCode, HMAC_SECRET);
}
function deriveStaticWord(string $keyCode): string {
    return hash_hmac('sha256', 'static:' . $keyCode, STATIC_WORD);
}
function deriveXorBase(string $keyCode): string {
    return hash_hmac('sha256', 'xor:' . $keyCode, BODY_XOR_BASE);
}

function obfuscateString(string $plain): array {
    $keyBin = random_bytes(8);
    $keyHex = bin2hex($keyBin);
    $klen = strlen($keyBin);
    $enc = '';
    for ($i = 0, $n = strlen($plain); $i < $n; $i++) {
        $enc .= chr(ord($plain[$i]) ^ ord($keyBin[$i % $klen]));
    }
    return ['key_hex' => $keyHex, 'enc_b64' => base64_encode($enc)];
}

function randVarName(int $len = 6): string {
    static $used = [];
    $kw = ['and','break','do','else','elseif','end','false','for','function','goto','if','in','local','nil','not','or','repeat','return','then','true','until','while','load','math','string','table','io','os'];
    do {
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $name = '_';
        for ($i = 0; $i < $len; $i++) $name .= $chars[random_int(0, 25)];
    } while (in_array($name, $kw, true) || isset($used[$name]));
    $used[$name] = true;
    return $name;
}

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
 * Build loader Lua content per-game (1 loader chung cho tất cả key của game này).
 * User prompt nhập key trong GG.
 */
function buildLoaderForGame(array $game): string {
    $apiUrl = rtrim(SITE_URL, '/') . '/api/connect.php';

    // Embed MASTER secrets encrypted (loader derive per-key sau khi user nhập key).
    $obfUrl    = obfuscateString($apiUrl);
    $obfHmac   = obfuscateString(HMAC_SECRET);
    $obfStatic = obfuscateString(STATIC_WORD);
    $obfXor    = obfuscateString(BODY_XOR_BASE);

    $template = file_get_contents(__DIR__ . '/../client/HCLOU_Loader.lua');
    if ($template === false) return '';

    $vDecStr = randVarName(10);

    $header = "-- HCLOU Loader (auto-generated " . date('Y-m-d H:i') . " for " . $game['name'] . ")\n";
    $header .= junkLines(8);

    $decryptHelper = "
local function {$vDecStr}(kHex, encB64)
    local kb = \"\"
    for i = 1, #kHex, 2 do kb = kb .. string.char(tonumber(kHex:sub(i, i+1), 16)) end
    local s = base64_decode(encB64)
    local out = {}
    for i = 1, #s do out[i] = string.char(s:byte(i) ~ kb:byte(((i-1) % #kb) + 1)) end
    return table.concat(out)
end
";

    $constants = "
local _u_k = \"{$obfUrl['key_hex']}\";    local _u_e = \"{$obfUrl['enc_b64']}\"
local _h_k = \"{$obfHmac['key_hex']}\";   local _h_e = \"{$obfHmac['enc_b64']}\"
local _s_k = \"{$obfStatic['key_hex']}\"; local _s_e = \"{$obfStatic['enc_b64']}\"
local _x_k = \"{$obfXor['key_hex']}\";    local _x_e = \"{$obfXor['enc_b64']}\"
" . junkLines(6);

    $injectVars = "
local API_URL       = {$vDecStr}(_u_k, _u_e)
local HMAC_SECRET   = {$vDecStr}(_h_k, _h_e)
local STATIC_WORD   = {$vDecStr}(_s_k, _s_e)
local BODY_XOR_BASE = {$vDecStr}(_x_k, _x_e)
";

    $injectBlock = "\n-- ============================================================================\n-- (auto-injected: encrypted master secrets + decrypt helper)\n-- ============================================================================\n"
                 . $decryptHelper . $constants . $injectVars;

    // Replace placeholders trong template (placeholder = giá trị mặc định trong template)
    $template = preg_replace('/local\s+API_URL\s*=\s*"<<API_URL>>"\s*--[^\n]*/', '-- (URL injected below)', $template);
    $template = preg_replace('/local\s+HMAC_SECRET\s*=\s*"<<HMAC_SECRET>>"\s*--[^\n]*/', '-- (HMAC injected below)', $template);
    $template = preg_replace('/local\s+STATIC_WORD\s*=\s*"<<STATIC_WORD>>"\s*--[^\n]*/', '-- (STATIC injected below)', $template);
    $template = preg_replace('/local\s+BODY_XOR_BASE\s*=\s*"<<BODY_XOR_BASE>>"\s*--[^\n]*/', '-- (XOR_BASE injected below)', $template);

    // Insert injectBlock TRƯỚC main flow (sau khi base64_decode đã define)
    $template = preg_replace(
        '/-- =+\nlocal info = gg\.getTargetInfo\(\)/',
        $injectBlock . "\n-- ============================================================================\nlocal info = gg.getTargetInfo()",
        $template
    );

    return $header . $template;
}
