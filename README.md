# HCLOU-LICENSE

License server cho Lua/GameGuardian script. Tách biệt khỏi HCLOU shop.

## Vai trò

- Gen key bulk format `HCLOU-XXXXXXXXXXXX` → export CSV → import vào HCLOU shop pool để bán.
- Verify key + device binding + expire qua endpoint `/api/connect`.
- Lưu script Lua body per game, trả về encrypted khi client request hợp lệ.
- Admin panel: keys, scripts, bindings, logs, settings.

## Stack

- PHP 7.4+ vanilla (không framework)
- MySQL/MariaDB
- Deploy shared cPanel-friendly (no CLI required)

## Cấu trúc

```
/
├── config.php          DB + secrets + helpers (clone HCLOU pattern)
├── database.sql        Schema 6 tables
├── install.php         Wizard cài đặt 5 bước qua web
├── index.php           API info JSON
├── admin/              Admin panel SPA-like
├── api/                Endpoint /api/connect + auxiliary
├── lib/                Helpers (keys, crypto, device fp)
├── client/             HCLOU_Loader.lua template
└── data/               Runtime (logs, rate-limit state)
```

## Flow

```
Admin → Bulk gen 100 key (status=unused) → Export CSV
       ↓
HCLOU shop → Import pool → User mua → User nhận key code
       ↓
User chạy HCLOU_Loader.lua trong GameGuardian
       ↓
Loader: prompt key + serial = hash(android_id+model+brand)
       ↓
POST /api/connect {game, user_key, serial, nonce, timestamp, hmac}
       ↓
Server verify (key+game+expire+rate+hmac+nonce) → bind device → return script_body XOR encoded
       ↓
Loader decrypt → load()() chạy trong memory
```

## Endpoint `/api/connect`

Pattern tham khảo demo PUBG mod menu, mở rộng cho Lua use case:

| Field | Old (demo C++) | New (Lua) |
|---|---|---|
| Request | game, user_key, serial | + nonce, timestamp, hmac |
| Response | modname, mod_status, credit, token | + script_body (XOR encoded) |
| Token | md5(game+key+serial+staticWord) | giữ — client verify |
| Device bind | comma-separated serial trong cột devices | giữ pattern |
| Max devices | per-key column | giữ |

## Anti-crack stack

- ✓ Device binding (max_devices per key)
- ✓ Expire check
- ✓ Status block
- ✓ Token MD5 client verify (replay 30s)
- ✓ HMAC sign request (chống tamper)
- ✓ Nonce + timestamp 60s (chống replay)
- ✓ Rate limit per key (5 req/60s)
- ✓ Banned devices blacklist
- ✓ Script body XOR encoded (key derive từ user_key + device)
- Future: mutation per-request, watermark per-buyer

## Deploy

1. Upload toàn bộ lên host (cPanel/shared OK).
2. Tạo DB MySQL trong cPanel/phpMyAdmin.
3. Truy cập `/install.php` qua browser → wizard 5 bước (system check, DB config, import schema, admin account, done).
4. Sau khi xong → xoá hoặc rename `install.php`, mở `/admin/` để vận hành.

## Format key

`HCLOU-` + 12 ký tự alphanum uppercase (loại I/O/0/1).
Ví dụ: `HCLOU-A8X3K9P2MN7Q`.

## TODO

- [x] Commit 1: skeleton + DB schema + install.php
- [ ] Commit 2: admin panel (auth + tabs: keys, scripts, bindings, logs, settings)
- [ ] Commit 3: API `/api/connect` + crypto + rate limit + HMAC
- [ ] Commit 4: `HCLOU_Loader.lua` template + obfuscation guide
