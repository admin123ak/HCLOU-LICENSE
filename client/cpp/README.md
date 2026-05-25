# HCLOU-LICENSE — C++ Client Sample

Sample C++ integration cho Android native mod menu.

## File

- `hclou_connect.h` — interface (LoginResult, ModConfig, login function)
- `hclou_connect.cpp` — implementation (HMAC, MD5, curl POST, JSON parse, token verify)

## Phụ thuộc

| Lib | Mục đích |
|---|---|
| libcurl | HTTPS POST `/api/connect.php` |
| openssl (libssl + libcrypto) | SHA256, HMAC-SHA256, MD5 |

## Build (Android NDK)

`CMakeLists.txt`:

```cmake
add_library(hclou_connect STATIC
    hclou_connect.cpp
)

find_package(CURL REQUIRED)
find_package(OpenSSL REQUIRED)

target_link_libraries(hclou_connect
    PRIVATE CURL::libcurl
    PRIVATE OpenSSL::SSL
    PRIVATE OpenSSL::Crypto
)
```

## Usage trong app C++ chính

```cpp
#include "hclou_connect.h"
#include <android/log.h>

// 1. Lấy serial qua JNI (xem API_DOCUMENTATION.md mục Device ID Generation)
std::string serial = getDeviceSerial(env, context);

// 2. Lấy package id
std::string packageId = "com.tencent.ig";  // hoặc context.getPackageName()

// 3. User input key qua UI EditText
std::string userKey = "HCLOU-A8X3K9P2MN7Q";

// 4. Verify license
auto r = hclou::login(packageId, userKey, serial);
if (!r.success) {
    __android_log_print(ANDROID_LOG_ERROR, "HCLOU", "Login fail: %s", r.reason.c_str());
    // Show error toast cho user
    return;
}

// 5. Login OK → mod config có sẵn trong hclou::modConfig
std::string title  = hclou::modConfig.modname;     // hiện title menu
std::string credit = hclou::modConfig.credit;      // hiện credit text
bool modOn = (hclou::modConfig.modStatus == "on"); // bật/tắt mod toàn cục
std::string exp = hclou::modConfig.expireDate;     // hiện ngày hết hạn

if (modOn) {
    startBypassThread();   // bắt đầu thread mod (Bypass400, AimBot, etc.)
}
```

## Bảo mật secrets

Master `HMAC_SECRET` + `STATIC_WORD` đang **plain text** trong `hclou_connect.cpp` (line `static const std::string`). Trước khi ship release:

1. **XOR encode constants**:
   ```cpp
   // Decode runtime
   static std::string decodeXor(const unsigned char* data, size_t len, const std::string& key) {
       std::string out;
       for (size_t i = 0; i < len; i++) out += (char)(data[i] ^ key[i % key.size()]);
       return out;
   }
   ```

2. **Strip symbols** khỏi `.so`:
   ```
   strip --strip-all libhclou_connect.so
   ```

3. **Obfuscate symbols** bằng llvm-obfuscator hoặc ollvm.

4. **Detect debugger / Frida**:
   ```cpp
   bool isDebugged() {
       FILE* f = fopen("/proc/self/status", "r");
       char line[256];
       while (fgets(line, sizeof(line), f)) {
           if (strstr(line, "TracerPid:") && !strstr(line, "0")) { fclose(f); return true; }
       }
       fclose(f);
       return false;
   }
   ```

5. **Rotate trên server + bump app version** mỗi 1-2 tuần.

## Anti-crack tổng

- Server HMAC + nonce + timestamp 60s → chống tamper + replay request.
- Server device binding → 1 key chạy max_devices máy.
- Server rate limit 5 req/60s/key → chống brute force.
- Token MD5 client verify → chống fake server.
- Master secrets XOR-encoded → chống `strings` grep binary.
- Rotate secrets weekly → forces app re-release, đẩy attacker phải re-crack.

## Endpoint reference

Xem [`API_DOCUMENTATION.md`](../../API_DOCUMENTATION.md) ở root repo.
