-- ============================================================================
-- HCLOU_Loader.lua — Template thin loader cho GameGuardian
-- ============================================================================
-- Trước khi giao cho user:
--   1. Thay <<API_URL>>, <<HMAC_SECRET>>, <<STATIC_WORD>>, <<BODY_XOR_BASE>>
--      bằng giá trị thật từ config.local.php (server).
--   2. Obfuscate bằng luaobfuscator.com hoặc prometheus-obfuscator
--      (string encode + control flow flatten).
--   3. Rotate secrets định kỳ → bump version → loader cũ chết.
-- ============================================================================

local API_URL        = "<<API_URL>>"          -- vd https://license.example.com/api/connect.php
local HMAC_SECRET    = "<<HMAC_SECRET>>"      -- 64 hex chars
local STATIC_WORD    = "<<STATIC_WORD>>"      -- 32 hex chars
local BODY_XOR_BASE  = "<<BODY_XOR_BASE>>"    -- 48 hex chars

-- ============================================================================
-- HEX
-- ============================================================================
local function tohex(bin)
    return (bin:gsub('.', function(c) return string.format('%02x', c:byte()) end))
end
local function fromhex(hex)
    return (hex:gsub('..', function(b) return string.char(tonumber(b, 16)) end))
end

-- ============================================================================
-- SHA256 (pure Lua, RFC 6234)
-- ============================================================================
local band, bor, bxor, bnot, lshift, rshift, rrotate =
    function(a,b) return a & b end,
    function(a,b) return a | b end,
    function(a,b) return a ~ b end,
    function(a) return ~a end,
    function(a,n) return (a << n) & 0xFFFFFFFF end,
    function(a,n) return a >> n end,
    function(a,n) return ((a >> n) | (a << (32 - n))) & 0xFFFFFFFF end

local K256 = {
    0x428a2f98,0x71374491,0xb5c0fbcf,0xe9b5dba5,0x3956c25b,0x59f111f1,0x923f82a4,0xab1c5ed5,
    0xd807aa98,0x12835b01,0x243185be,0x550c7dc3,0x72be5d74,0x80deb1fe,0x9bdc06a7,0xc19bf174,
    0xe49b69c1,0xefbe4786,0x0fc19dc6,0x240ca1cc,0x2de92c6f,0x4a7484aa,0x5cb0a9dc,0x76f988da,
    0x983e5152,0xa831c66d,0xb00327c8,0xbf597fc7,0xc6e00bf3,0xd5a79147,0x06ca6351,0x14292967,
    0x27b70a85,0x2e1b2138,0x4d2c6dfc,0x53380d13,0x650a7354,0x766a0abb,0x81c2c92e,0x92722c85,
    0xa2bfe8a1,0xa81a664b,0xc24b8b70,0xc76c51a3,0xd192e819,0xd6990624,0xf40e3585,0x106aa070,
    0x19a4c116,0x1e376c08,0x2748774c,0x34b0bcb5,0x391c0cb3,0x4ed8aa4a,0x5b9cca4f,0x682e6ff3,
    0x748f82ee,0x78a5636f,0x84c87814,0x8cc70208,0x90befffa,0xa4506ceb,0xbef9a3f7,0xc67178f2
}

local function sha256_bin(msg)
    local h = {0x6a09e667,0xbb67ae85,0x3c6ef372,0xa54ff53a,0x510e527f,0x9b05688c,0x1f83d9ab,0x5be0cd19}
    local bits = #msg * 8
    msg = msg .. '\128'
    while (#msg % 64) ~= 56 do msg = msg .. '\0' end
    msg = msg .. string.pack('>I8', bits)

    for chunk = 1, #msg, 64 do
        local w = {}
        for i = 0, 15 do
            w[i+1] = string.unpack('>I4', msg, chunk + i * 4)
        end
        for i = 17, 64 do
            local s0 = bxor(bxor(rrotate(w[i-15], 7), rrotate(w[i-15], 18)), rshift(w[i-15], 3))
            local s1 = bxor(bxor(rrotate(w[i-2], 17), rrotate(w[i-2], 19)), rshift(w[i-2], 10))
            w[i] = (w[i-16] + s0 + w[i-7] + s1) & 0xFFFFFFFF
        end
        local a,b,c,d,e,f,g,hh = h[1],h[2],h[3],h[4],h[5],h[6],h[7],h[8]
        for i = 1, 64 do
            local S1 = bxor(bxor(rrotate(e, 6), rrotate(e, 11)), rrotate(e, 25))
            local ch = bxor(band(e, f), band(bnot(e) & 0xFFFFFFFF, g))
            local t1 = (hh + S1 + ch + K256[i] + w[i]) & 0xFFFFFFFF
            local S0 = bxor(bxor(rrotate(a, 2), rrotate(a, 13)), rrotate(a, 22))
            local mj = bxor(bxor(band(a, b), band(a, c)), band(b, c))
            local t2 = (S0 + mj) & 0xFFFFFFFF
            hh = g; g = f; f = e; e = (d + t1) & 0xFFFFFFFF
            d = c; c = b; b = a; a = (t1 + t2) & 0xFFFFFFFF
        end
        h[1]=(h[1]+a)&0xFFFFFFFF; h[2]=(h[2]+b)&0xFFFFFFFF
        h[3]=(h[3]+c)&0xFFFFFFFF; h[4]=(h[4]+d)&0xFFFFFFFF
        h[5]=(h[5]+e)&0xFFFFFFFF; h[6]=(h[6]+f)&0xFFFFFFFF
        h[7]=(h[7]+g)&0xFFFFFFFF; h[8]=(h[8]+hh)&0xFFFFFFFF
    end
    return string.pack('>I4I4I4I4I4I4I4I4', h[1],h[2],h[3],h[4],h[5],h[6],h[7],h[8])
end

local function sha256_hex(msg) return tohex(sha256_bin(msg)) end

-- ============================================================================
-- HMAC-SHA256
-- ============================================================================
local function hmac_sha256(key, msg)
    if #key > 64 then key = sha256_bin(key) end
    if #key < 64 then key = key .. string.rep('\0', 64 - #key) end
    local opad, ipad = '', ''
    for i = 1, 64 do
        local b = key:byte(i)
        opad = opad .. string.char(bxor(b, 0x5c))
        ipad = ipad .. string.char(bxor(b, 0x36))
    end
    return tohex(sha256_bin(opad .. sha256_bin(ipad .. msg)))
end

-- ============================================================================
-- MD5 (pure Lua compact, dùng cho client verify token)
-- ============================================================================
local function md5_hex(msg)
    local R = {7,12,17,22,7,12,17,22,7,12,17,22,7,12,17,22,
               5,9,14,20,5,9,14,20,5,9,14,20,5,9,14,20,
               4,11,16,23,4,11,16,23,4,11,16,23,4,11,16,23,
               6,10,15,21,6,10,15,21,6,10,15,21,6,10,15,21}
    local K = {
        0xd76aa478,0xe8c7b756,0x242070db,0xc1bdceee,0xf57c0faf,0x4787c62a,0xa8304613,0xfd469501,
        0x698098d8,0x8b44f7af,0xffff5bb1,0x895cd7be,0x6b901122,0xfd987193,0xa679438e,0x49b40821,
        0xf61e2562,0xc040b340,0x265e5a51,0xe9b6c7aa,0xd62f105d,0x02441453,0xd8a1e681,0xe7d3fbc8,
        0x21e1cde6,0xc33707d6,0xf4d50d87,0x455a14ed,0xa9e3e905,0xfcefa3f8,0x676f02d9,0x8d2a4c8a,
        0xfffa3942,0x8771f681,0x6d9d6122,0xfde5380c,0xa4beea44,0x4bdecfa9,0xf6bb4b60,0xbebfbc70,
        0x289b7ec6,0xeaa127fa,0xd4ef3085,0x04881d05,0xd9d4d039,0xe6db99e5,0x1fa27cf8,0xc4ac5665,
        0xf4292244,0x432aff97,0xab9423a7,0xfc93a039,0x655b59c3,0x8f0ccc92,0xffeff47d,0x85845dd1,
        0x6fa87e4f,0xfe2ce6e0,0xa3014314,0x4e0811a1,0xf7537e82,0xbd3af235,0x2ad7d2bb,0xeb86d391
    }
    local bits = #msg * 8
    msg = msg .. '\128'
    while (#msg % 64) ~= 56 do msg = msg .. '\0' end
    msg = msg .. string.pack('<I8', bits)

    local a0,b0,c0,d0 = 0x67452301,0xefcdab89,0x98badcfe,0x10325476
    for chunk = 1, #msg, 64 do
        local m = {}
        for i = 0, 15 do m[i] = string.unpack('<I4', msg, chunk + i * 4) end
        local A,B,C,D = a0,b0,c0,d0
        for i = 0, 63 do
            local F, g
            if i < 16 then
                F = bxor(D, band(B, bxor(C, D))); g = i
            elseif i < 32 then
                F = bxor(C, band(D, bxor(B, C))); g = (5 * i + 1) % 16
            elseif i < 48 then
                F = bxor(bxor(B, C), D); g = (3 * i + 5) % 16
            else
                F = bxor(C, bor(B, bxor(D, 0xFFFFFFFF))); g = (7 * i) % 16
            end
            local Ft = (F + A + K[i+1] + m[g]) & 0xFFFFFFFF
            A = D; D = C; C = B
            local r = R[i+1]
            B = (B + (((Ft << r) | (Ft >> (32 - r))) & 0xFFFFFFFF)) & 0xFFFFFFFF
        end
        a0 = (a0 + A) & 0xFFFFFFFF
        b0 = (b0 + B) & 0xFFFFFFFF
        c0 = (c0 + C) & 0xFFFFFFFF
        d0 = (d0 + D) & 0xFFFFFFFF
    end
    return string.format('%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x',
        a0 & 0xFF, (a0 >> 8) & 0xFF, (a0 >> 16) & 0xFF, (a0 >> 24) & 0xFF,
        b0 & 0xFF, (b0 >> 8) & 0xFF, (b0 >> 16) & 0xFF, (b0 >> 24) & 0xFF,
        c0 & 0xFF, (c0 >> 8) & 0xFF, (c0 >> 16) & 0xFF, (c0 >> 24) & 0xFF,
        d0 & 0xFF, (d0 >> 8) & 0xFF, (d0 >> 16) & 0xFF, (d0 >> 24) & 0xFF)
end

-- ============================================================================
-- BASE64 DECODE
-- ============================================================================
local b64chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/"
local function base64_decode(s)
    s = s:gsub('[^A-Za-z0-9+/=]', '')
    local out = {}
    local n = 0
    local buf = 0
    for i = 1, #s do
        local c = s:sub(i, i)
        if c == '=' then break end
        local v = b64chars:find(c, 1, true)
        if v then
            buf = (buf << 6) | (v - 1)
            n = n + 6
            if n >= 8 then
                n = n - 8
                out[#out+1] = string.char((buf >> n) & 0xFF)
                buf = buf & ((1 << n) - 1)
            end
        end
    end
    return table.concat(out)
end

-- ============================================================================
-- XOR STREAM (key binary, repeat)
-- ============================================================================
local function xor_stream(data, key_bin)
    local klen = #key_bin
    local out = {}
    for i = 1, #data do
        local d = data:byte(i)
        local k = key_bin:byte(((i - 1) % klen) + 1)
        out[i] = string.char(bxor(d, k))
    end
    return table.concat(out)
end

-- ============================================================================
-- JSON parse minimal (chỉ extract field cần)
-- ============================================================================
local function json_extract(json, key)
    -- pattern: "key" : "value" hoặc "key": "value" hoặc number
    local p = '"' .. key .. '"%s*:%s*"([^"]*)"'
    local v = json:match(p)
    if v then return v end
    p = '"' .. key .. '"%s*:%s*([%-%d%.]+)'
    return json:match(p)
end
local function json_bool(json, key)
    local p = '"' .. key .. '"%s*:%s*(%a+)'
    local v = json:match(p)
    return v == 'true'
end

-- ============================================================================
-- DEVICE SERIAL (hash từ thông tin device qua GG)
-- ============================================================================
local function get_serial()
    local info = gg.getTargetInfo() or {}
    local pkg = info.packageName or "unknown"
    local label = info.label or ""
    -- GG không expose Android ID trực tiếp. Combo package + label + processName tạo fingerprint.
    -- Để mạnh hơn: yêu cầu user nhập 1 device ID lần đầu (chưa làm phase này).
    local seed = pkg .. "|" .. label .. "|" .. (info.processName or "")
    return sha256_hex(seed):sub(1, 40)
end

-- ============================================================================
-- NONCE
-- ============================================================================
local function gen_nonce()
    math.randomseed(os.time() + math.floor(os.clock() * 1000))
    local chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
    local out = {}
    for i = 1, 24 do out[i] = chars:sub(math.random(1, #chars), math.random(1, #chars)) end
    return table.concat(out)
end

-- ============================================================================
-- URL encode (form-urlencoded)
-- ============================================================================
local function urlencode(s)
    return (s:gsub('([^%w%-%._~])', function(c) return string.format('%%%02X', c:byte()) end))
end

-- ============================================================================
-- MAIN FLOW
-- ============================================================================
local info = gg.getTargetInfo()
if not info or not info.packageName then
    gg.alert("Vui lòng mở game trước khi chạy script!")
    os.exit()
end

local PACKAGE_ID = info.packageName

-- Prompt key
local p = gg.prompt(
    {"License Key:"},
    {""},
    {"text"}
)
if not p or not p[1] or p[1] == "" then os.exit() end
local user_key = p[1]:upper():gsub("%s", "")

if not user_key:find("^HCLOU%-") then
    gg.alert("Key phải bắt đầu HCLOU-")
    os.exit()
end

-- Build request
local serial = get_serial()
local nonce  = gen_nonce()
local ts     = tostring(os.time())
local payload = PACKAGE_ID .. "|" .. user_key .. "|" .. serial .. "|" .. nonce .. "|" .. ts
local hmac    = hmac_sha256(HMAC_SECRET, payload)

local body = "game="      .. urlencode(PACKAGE_ID)
          .. "&user_key=" .. urlencode(user_key)
          .. "&serial="   .. urlencode(serial)
          .. "&nonce="    .. urlencode(nonce)
          .. "&timestamp=".. urlencode(ts)
          .. "&hmac="     .. urlencode(hmac)

gg.toast("Đang xác thực...")
local res = gg.makeRequest(API_URL, {
    ["Content-Type"] = "application/x-www-form-urlencoded",
    ["User-Agent"]   = "HCLOU-Loader/1.0"
}, body)

if not res or not res.content then
    gg.alert("Lỗi kết nối server. Check internet hoặc thử lại sau.")
    os.exit()
end

local json = res.content

if not json_bool(json, "status") then
    local reason = json_extract(json, "reason") or "UNKNOWN"
    gg.alert("❌ Xác thực thất bại\n\nLý do: " .. reason)
    os.exit()
end

-- Extract response fields
local enc_body = json_extract(json, "script_body")
local token    = json_extract(json, "token")
local version  = json_extract(json, "version") or "?"
local exp      = json_extract(json, "EXP") or "?"
local rng      = tonumber(json_extract(json, "rng") or "0") or 0

if not enc_body or not token then
    gg.alert("Response thiếu field. Server lỗi?")
    os.exit()
end

-- Verify token md5(game-key-serial-staticWord)
local expected_token = md5_hex(PACKAGE_ID .. "-" .. user_key .. "-" .. serial .. "-" .. STATIC_WORD)
if expected_token ~= token then
    gg.alert("Token invalid — server bị fake?\nExpected: " .. expected_token:sub(1, 8) .. "...\nGot: " .. token:sub(1, 8) .. "...")
    os.exit()
end

-- Token validity window (30s)
if rng + 30 < os.time() then
    gg.alert("Token expired. Restart script.")
    os.exit()
end

-- Decrypt body XOR (key = sha256(user_key|serial|BODY_XOR_BASE) — 32 bytes binary)
local body_key = sha256_bin(user_key .. "|" .. serial .. "|" .. BODY_XOR_BASE)
local script_body = xor_stream(base64_decode(enc_body), body_key)

if #script_body < 10 then
    gg.alert("Decrypt fail (body quá ngắn).")
    os.exit()
end

-- Load + run in-memory
local fn, err = load(script_body, "HCLOU_Script", "t")
if not fn then
    gg.alert("Load script lỗi: " .. tostring(err))
    os.exit()
end

gg.toast("✓ Đã xác thực (v" .. version .. ", hết hạn " .. exp .. ")")

-- Wipe sensitive vars khỏi memory
HMAC_SECRET = nil
STATIC_WORD = nil
BODY_XOR_BASE = nil
body_key = nil
script_body = nil
collectgarbage("collect")

-- Run script body
fn()
