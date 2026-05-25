# HCLOU FF Loader — Android NDK App

App loader root cho **Free Fire** (`com.dts.freefireth`).
Login HCLOU-LICENSE + Floating menu Bypass Login 400.

## Build

1. Mở folder này trong Android Studio Hedgehog+
2. SDK Manager cài: Android SDK 34, NDK 25+, CMake 3.22
3. **Add prebuilt curl + openssl cho Android** (xem `app/src/main/cpp/CMakeLists.txt`):
   - Download từ https://github.com/leenjewel/openssl_for_ios_and_android
   - Copy vào `app/src/main/cpp/prebuilt/curl/` và `prebuilt/openssl/`
   - Uncomment phần target_link_libraries trong CMakeLists.txt
4. Build → Build APK(s)
5. Sign APK + ship

## Flow user

1. Mở app → form login (UI lavender dark).
2. Nhập key `HCLOU-XXXXXXXXXXXX` → bấm VÀO GAME.
3. Native verify qua HCLOU-LICENSE (HMAC + per_static derive + token MD5).
4. Login OK → hiện modname/credit/EXP → button START MOD enable.
5. Bấm START MOD → xin quyền SYSTEM_ALERT_WINDOW → service start floating menu.
6. App ẩn vào background, user mở Free Fire.
7. Floating menu hiện trên màn hình FF → bấm "Bypass Login 400" toggle ON.
8. Native lib tìm `libil2cpp.so` base trong `/proc/self/maps` (yêu cầu lib inject hoặc Substrate hook).
9. Bypass400 thread loop ghi memory codes 0x0001007B / 0x0002007C vào offset cố định.

## Architecture

```
app/
├── build.gradle              NDK arm64/armv7, C++17, ProGuard release
└── src/main/
    ├── AndroidManifest.xml   INTERNET + SYSTEM_ALERT_WINDOW + FOREGROUND_SERVICE
    ├── java/com/hclou/ffloader/
    │   ├── MainActivity.java login form + JNI verify
    │   └── ModService.java   floating menu service drag-to-move
    ├── cpp/
    │   ├── CMakeLists.txt    build libhclouff.so (cần prebuilt curl + openssl)
    │   ├── native-lib.cpp    HMAC + curl + Bypass400 thread + JNI bindings
    │   └── Bypass_Login.h    code Bypass400 (in-process adapter inline)
    └── res/                  layouts + drawables (lavender theme)
```

## Bảo mật

`HCLOU_HMAC_SECRET` + `HCLOU_STATIC_WORD` đang plain text trong `native-lib.cpp` line 25-28. Trước release production:

1. XOR encode + decrypt runtime.
2. Strip symbols: `llvm-strip --strip-all libhclouff.so`.
3. Server rotate `config.local.php` mỗi 1-2 tuần + bump app versionCode + force update.

## Test với server

```bash
curl -X POST https://teamcrack.linkpc.net/api/connect.php \
  -d "game=com.dts.freefireth" \
  -d "user_key=HCLOU-XXXXXXXXXXXX" \
  -d "serial=...40hex..." \
  -d "nonce=...24chars..." \
  -d "timestamp=$(date +%s)" \
  -d "hmac=..."
```

Verify server response trước khi test app.
