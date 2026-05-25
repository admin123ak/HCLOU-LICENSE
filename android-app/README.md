# HCLOU Mod — Android NDK App

Source code Android Studio NDK project. Mở folder này trong Android Studio → build → ra APK.

## Yêu cầu

- **Android Studio Hedgehog 2023.1+** hoặc mới hơn
- **Android NDK 25+** (auto cài qua SDK Manager)
- **CMake 3.22.1+** (auto cài qua SDK Manager)
- **JDK 17** (Android Studio ship sẵn)

## Cấu trúc

```
android-app/
├── app/
│   ├── build.gradle              Gradle config + NDK abi + minify release
│   ├── proguard-rules.pro        Giữ JNI native methods
│   └── src/main/
│       ├── AndroidManifest.xml   permissions: INTERNET, SYSTEM_ALERT_WINDOW
│       ├── java/com/hclou/mod/
│       │   ├── MainActivity.java login screen + JNI call
│       │   └── ModService.java   floating menu service
│       ├── cpp/
│       │   ├── CMakeLists.txt    build native lib hcloumod.so
│       │   ├── native-lib.cpp    JNI bridge + mod thread loop
│       │   ├── hclou_connect.h   license API interface
│       │   └── hclou_connect.cpp HMAC + MD5 + curl POST + token verify
│       └── res/
│           ├── layout/           activity_login + floating_menu
│           ├── drawable/         bg_input + bg_btn + bg_float
│           └── values/           strings + themes (lavender dark)
├── build.gradle                  Top-level
├── settings.gradle               Include :app
├── gradle.properties             AndroidX + JVM args
└── gradle/wrapper/               Gradle 8.7
```

## Setup curl + openssl trên Android NDK

Sample `CMakeLists.txt` hiện stub (chỉ link liblog). Để build thực tế, cần prebuilt curl + openssl cho Android:

**Option A — Dùng prebuilt repository:**
```bash
# Tải prebuilt từ https://github.com/leenjewel/openssl_for_ios_and_android
# Hoặc https://github.com/robertying/openssl-build-scripts
# Copy thư mục prebuilt/ vào app/src/main/cpp/
```

**Option B — Build từ nguồn:**
```bash
# Build openssl 3.x cho Android NDK theo hướng dẫn:
# https://wiki.openssl.org/index.php/Android
# Tương tự curl với --with-openssl
```

Sau khi có prebuilt, uncomment trong `CMakeLists.txt`:
```cmake
target_include_directories(hcloumod PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/openssl/include
    ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/curl/include
)
target_link_libraries(hcloumod
    ${log-lib}
    ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/curl/lib/${ANDROID_ABI}/libcurl.a
    ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/openssl/lib/${ANDROID_ABI}/libssl.a
    ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/openssl/lib/${ANDROID_ABI}/libcrypto.a
    z
)
```

## Build APK

```bash
# Trong Android Studio:
Build → Build Bundle(s)/APK(s) → Build APK(s)

# Hoặc CLI:
./gradlew assembleRelease
# → app/build/outputs/apk/release/app-release.apk
```

Sau khi build, sign APK bằng keystore của dev rồi giao user cài.

## Bảo mật trước khi release

1. **XOR encode secrets** trong `hclou_connect.cpp` line 25-26 (`HMAC_SECRET` + `STATIC_WORD`).
2. **Strip symbols** sau build release:
   ```bash
   $ANDROID_NDK/toolchains/llvm/prebuilt/linux-x86_64/bin/llvm-strip --strip-all \
     app/build/intermediates/cmake/release/obj/arm64-v8a/libhcloumod.so
   ```
3. **ProGuard** đã bật `minifyEnabled true` cho release.
4. **Anti-debug** thêm trong `native-lib.cpp::modThreadEntry()` check `/proc/self/status` TracerPid.

## Flow user

1. User cài APK.
2. Mở app → màn hình login → nhập key `HCLOU-XXXXXXXXXXXX`.
3. Bấm "Đăng nhập" → app gọi `jniLogin()` → `hclou::login()` → POST `/api/connect.php`.
4. Server verify → trả `modname/credit/token` → token verify pass → modConfig populated.
5. Màn hình hiện modname + credit + ngày hết hạn → button "Start Mod" enable.
6. User bấm "Start Mod" → app xin quyền SYSTEM_ALERT_WINDOW → ModService start.
7. Floating menu hiện trên màn hình game → user toggle Bypass → mod thread run.

## TODO cho dev

- [ ] Add prebuilt openssl + libcurl vào `app/src/main/cpp/prebuilt/`.
- [ ] Implement mod logic thật trong `native-lib.cpp::modThreadEntry()` — Bypass400, AimBot, etc.
- [ ] Process memory access: mở `/proc/<pid>/mem` (cần root) hoặc dùng `ptrace` (non-root limited).
- [ ] XOR encode secrets trước release.
- [ ] Sign APK với keystore production.
