# HCLOU-LICENSE — API Documentation

Endpoint cho **Android C++ mod menu** verify license + lấy mod config.

**Base URL:** `https://teamcrack.linkpc.net`

---

## POST `/api/connect.php`

Verify license key + bind device + trả mod config.

### Request Headers

```
Content-Type: application/x-www-form-urlencoded
User-Agent: <your-app-name>/<version>
```

### Request Parameters (form-urlencoded body)

| Param | Type | Required | Description |
|---|---|---|---|
| `game` | string | yes | Package ID của game (vd `com.tencent.ig`). Lấy từ `context.getPackageName()` |
| `user_key` | string | yes | License key user nhập (`HCLOU-XXXXXXXXXXXX`) |
| `serial` | string | yes | Device fingerprint (8-80 chars). Đề xuất sha256(android_id + Build.MODEL + Build.BRAND), cắt 40 hex |
| `nonce` | string | yes | Random 8-64 chars unique per request (chống replay) |
| `timestamp` | integer | yes | Unix timestamp khi gửi request (server check window 60s) |
| `hmac` | string | yes | hex sha256_hmac(per_hmac, "{game}\|{user_key}\|{serial}\|{nonce}\|{timestamp}") (64 chars lowercase) |

`per_hmac` derive bên client từ master `HMAC_SECRET` embedded:

```
per_hmac = hex(hmac_sha256(HMAC_SECRET, "hmac:" + user_key))
```

### Sample Request

```
POST https://teamcrack.linkpc.net/api/connect.php
Content-Type: application/x-www-form-urlencoded

game=com.tencent.ig
&user_key=HCLOU-A8X3K9P2MN7Q
&serial=19b25856e1c150ca834cffc8b59b23adbd0ec038
&nonce=abcDEFghij12KLmnop34QRst
&timestamp=1779682216
&hmac=9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08
```

### Success Response (200)

```json
{
  "status": true,
  "data": {
    "modname": "NGO TRAN MODS",
    "mod_status": "on",
    "credit": "Powered by HCLOU",
    "version": "1.0.0",
    "token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "EXP": "2026-06-25 02:30:00",
    "device": 1,
    "rng": 1779682216
  }
}
```

| Field | Description |
|---|---|
| `modname` | Tên mod menu hiện trên UI |
| `mod_status` | `"on"` / `"off"` — bật/tắt mod toàn cục |
| `credit` | Text credit hiện trong menu |
| `version` | Phiên bản mod config |
| `token` | `md5("{game}-{user_key}-{serial}-{per_static}")` — client tự verify chống fake server |
| `EXP` | Datetime hết hạn key (`YYYY-MM-DD HH:MM:SS`) |
| `device` | Max devices key này được phép bind |
| `rng` | Unix timestamp server lúc trả response — token valid 30s |

### Error Responses

```json
{ "status": false, "reason": "INVALID_PARAMETER" }
{ "status": false, "reason": "MALFORMED_INPUT", "debug": {...} }
{ "status": false, "reason": "TIMESTAMP_OUT_OF_WINDOW" }
{ "status": false, "reason": "HMAC_INVALID" }
{ "status": false, "reason": "NONCE_REUSED" }
{ "status": false, "reason": "DEVICE_BANNED" }
{ "status": false, "reason": "GAME_NOT_FOUND" }
{ "status": false, "reason": "KEY_NOT_FOUND" }
{ "status": false, "reason": "KEY_NOT_VALID_FOR_THIS_GAME" }
{ "status": false, "reason": "KEY_BANNED" }
{ "status": false, "reason": "EXPIRED_KEY" }
{ "status": false, "reason": "MAX_DEVICE_REACHED" }
{ "status": false, "reason": "RATE_LIMITED" }        (HTTP 429)
{ "status": false, "reason": "NO_MOD_CONFIG" }
{ "status": false, "reason": "MAINTENANCE", "message": "..." }
```

---

## Token Verification (client side)

Sau khi nhận response, **MUST verify token** để chống fake server (man-in-the-middle hoặc DNS poison):

```
per_static = hex(hmac_sha256(STATIC_WORD, "static:" + user_key))
expected_token = md5(game + "-" + user_key + "-" + serial + "-" + per_static)

if expected_token != response.data.token → reject
if rng + 30 < current_time → reject (expired token)
```

`STATIC_WORD` master embed trong client app, GIỐNG giá trị trên server `config.local.php`.

---

## Secrets embedded trong client app

Client cần embed 3 master secrets (đồng bộ với server `config.local.php`):

```cpp
const std::string HMAC_SECRET   = "...";  // 64 hex từ config.local.php
const std::string STATIC_WORD   = "...";  // 32 hex
const std::string BODY_XOR_BASE = "...";  // 48 hex (chưa dùng ở C++, reserved)
```

**Bảo vệ secrets:**
- Encrypt bằng XOR + decrypt runtime (chống `strings` grep binary).
- Obfuscate symbol names trong native lib.
- Rotate trên server + push update app version mỗi 1-2 tuần.
- Strip debug symbols khỏi `.so`.

---

## Device ID Generation (sample C++ JNI)

```cpp
#include <jni.h>
#include <openssl/sha.h>
#include <string>

std::string getDeviceSerial(JNIEnv* env, jobject context) {
    // 1. Android ID
    jclass settingsCls = env->FindClass("android/provider/Settings$Secure");
    jmethodID getStringMid = env->GetStaticMethodID(settingsCls, "getString",
        "(Landroid/content/ContentResolver;Ljava/lang/String;)Ljava/lang/String;");
    jclass ctxCls = env->GetObjectClass(context);
    jmethodID getCrMid = env->GetMethodID(ctxCls, "getContentResolver",
        "()Landroid/content/ContentResolver;");
    jobject cr = env->CallObjectMethod(context, getCrMid);
    jstring keyStr = env->NewStringUTF("android_id");
    jstring androidIdJ = (jstring)env->CallStaticObjectMethod(settingsCls, getStringMid, cr, keyStr);
    const char* androidId = env->GetStringUTFChars(androidIdJ, nullptr);

    // 2. Build.MODEL + Build.BRAND
    jclass buildCls = env->FindClass("android/os/Build");
    jfieldID modelFid = env->GetStaticFieldID(buildCls, "MODEL", "Ljava/lang/String;");
    jstring modelJ = (jstring)env->GetStaticObjectField(buildCls, modelFid);
    const char* model = env->GetStringUTFChars(modelJ, nullptr);
    jfieldID brandFid = env->GetStaticFieldID(buildCls, "BRAND", "Ljava/lang/String;");
    jstring brandJ = (jstring)env->GetStaticObjectField(buildCls, brandFid);
    const char* brand = env->GetStringUTFChars(brandJ, nullptr);

    // 3. Concat + SHA256
    std::string seed = std::string(androidId) + "|" + model + "|" + brand;
    unsigned char hash[SHA256_DIGEST_LENGTH];
    SHA256((const unsigned char*)seed.data(), seed.size(), hash);
    char hex[65];
    for (int i = 0; i < 32; i++) sprintf(hex + i * 2, "%02x", hash[i]);
    hex[64] = 0;

    // 4. Cleanup JNI refs
    env->ReleaseStringUTFChars(androidIdJ, androidId);
    env->ReleaseStringUTFChars(modelJ, model);
    env->ReleaseStringUTFChars(brandJ, brand);

    return std::string(hex).substr(0, 40);  // 40 hex chars
}
```

---

## Sample integration code

Xem file [`client/cpp/hclou_connect.cpp`](client/cpp/hclou_connect.cpp) trong repo này.

## Package IDs phổ biến

| Game | Package ID |
|---|---|
| PUBG Mobile Global | `com.tencent.ig` |
| PUBG Mobile KR | `com.pubg.krmobile` |
| PUBG Mobile VN | `com.vng.pubgmobile` |
| Free Fire | `com.dts.freefireth` |
| Mobile Legends | `com.mobile.legends` |
| Call of Duty Mobile | `com.activision.callofduty.shooter` |

Lấy package thực tế từ `context.getPackageName()`.
