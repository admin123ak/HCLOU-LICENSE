# HCLOU Loader — Hướng dẫn build & deploy

## File `HCLOU_Loader.lua`

Template thin loader chạy trong GameGuardian. Trước khi giao cho user:

### Bước 1 — Thay placeholder secrets

Mở `HCLOU_Loader.lua`, replace 4 placeholder với giá trị thực từ `config.local.php` trên server:

```lua
local API_URL        = "<<API_URL>>"          -- → https://yourdomain.com/api/connect.php
local HMAC_SECRET    = "<<HMAC_SECRET>>"      -- → giá trị define('HMAC_SECRET', '...')
local STATIC_WORD    = "<<STATIC_WORD>>"      -- → giá trị define('STATIC_WORD', '...')
local BODY_XOR_BASE  = "<<BODY_XOR_BASE>>"    -- → giá trị define('BODY_XOR_BASE', '...')
```

### Bước 2 — Obfuscate (BẮT BUỘC, không skip)

Vì secrets embed trong loader, leak file = leak secrets = bypass anti-crack. Obfuscate dày:

**Option A — luaobfuscator.com (web, free tier):**
1. Upload `HCLOU_Loader.lua`.
2. Bật full mode: String Encryption + Control Flow Flattening + Constant Encryption.
3. Download file `.lua` obfuscated.

**Option B — Prometheus (CLI, hardcore):**
```bash
git clone https://github.com/prometheus-lua/prometheus
cd prometheus
lua cli.lua --preset Strong --output HCLOU_Loader_obf.lua HCLOU_Loader.lua
```

### Bước 3 — Giao cho user

User mua key trên HCLOU shop → nhận key code `HCLOU-XXXXXXXXXXXX` + link tải `HCLOU_Loader_obf.lua`.

User flow:
1. Cài GameGuardian (root device).
2. Mở game cần dùng (vd PUBG Mobile).
3. Mở GG menu → run script → chọn `HCLOU_Loader_obf.lua`.
4. Nhập key.
5. Loader verify → tải script body từ server → chạy in-memory.

### Bước 4 — Rotate secrets định kỳ

Mỗi 7-14 ngày:
1. Sửa `config.local.php` server: gen mới `HMAC_SECRET`, `STATIC_WORD`, `BODY_XOR_BASE`.
2. Re-obfuscate loader với secrets mới.
3. Bump version (1.0.0 → 1.0.1).
4. Replace loader cũ trên link tải.
5. User cũ phải tải loader mới (loader cũ HMAC sai → server reject).

## Anti-crack mechanism in loader

| Layer | Implement | Crack difficulty |
|---|---|---|
| HMAC-SHA256 sign request | pure Lua, key embed | Cần extract key sau obfuscate |
| Nonce random 24 chars | `math.random` seeded | Không replay được |
| Timestamp 60s window | `os.time()` | Không replay cũ |
| Token MD5 client verify | server fake → loader reject | Chống fake server |
| Body XOR decrypt | key = sha256(user_key+serial+XOR_BASE) | Cần user_key + serial mới decrypt |
| Wipe vars sau load | `var = nil; collectgarbage()` | Đỡ memory dump |
| `load()` in-memory | không lưu file | Không thấy script body trên disk |

## Caveats

- 100% chống crack KHÔNG tồn tại. Attacker memory dump moment `fn()` chạy = lấy được code.
- Reasonable goal: crack tốn 1-2 tuần dev senior → ROI âm cho attacker (vs key 50k/tháng).
- Tăng difficulty: rotate secrets weekly + mutation per-request (server-side, future).

## Device serial — hạn chế hiện tại

`get_serial()` hash từ `packageName + label + processName` — KHÔNG unique đủ (mọi user cùng game cùng phone model = same serial trùng → key share dễ).

**Cải thiện Phase 2:**
- Yêu cầu user nhập 1 lần "device ID" (IMEI/Android ID) → lưu trong loader local file → check next time.
- Hoặc dùng GG bridge JNI để get Android Settings.Secure.ANDROID_ID.

## TODO

- [ ] Server endpoint `/admin/gen_loader` — tự sinh loader file với secrets embedded, download zip.
- [ ] Auto-obfuscate trong panel (call API luaobfuscator).
- [ ] Loader version negotiate: server từ chối loader cũ.
- [ ] Mutation per-request script body.
