// ============================================================================
// HCLOU Mod — JNI bridge giữa Java (MainActivity/ModService) và native lib.
// Native lib bao gồm hclou_connect.cpp (license) + mod logic (Bypass400, etc.)
// ============================================================================

#include <jni.h>
#include <string>
#include <thread>
#include <atomic>
#include <android/log.h>
#include "hclou_connect.h"

#define LOG_TAG "HCLOU-Native"
#define LOGI(...) __android_log_print(ANDROID_LOG_INFO,  LOG_TAG, __VA_ARGS__)
#define LOGE(...) __android_log_print(ANDROID_LOG_ERROR, LOG_TAG, __VA_ARGS__)

// ============================================================================
// Mod state
// ============================================================================
static std::atomic<bool> g_modRunning{false};
static std::atomic<bool> g_bypassOn{false};
static std::thread       g_modThread;

// User cấy Bypass400 (hoặc mod logic khác) vào hàm này.
// pid + il2cppBase + g_config… cần dev tự setup tuỳ game.
static void modThreadEntry() {
    LOGI("Mod thread start");
    while (g_modRunning.load()) {
        if (g_bypassOn.load()) {
            // TODO: gọi Bypass400() / AimBot() / etc.
            // memory read/write thông qua /proc/<pid>/mem
        }
        std::this_thread::sleep_for(std::chrono::milliseconds(200));
    }
    LOGI("Mod thread stop");
}

// ============================================================================
// JNI bindings cho MainActivity
// ============================================================================
extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_mod_MainActivity_jniLogin(
    JNIEnv* env, jobject /*thiz*/,
    jstring jPackageId, jstring jUserKey, jstring jSerial) {

    auto toCpp = [&env](jstring js) -> std::string {
        if (!js) return "";
        const char* c = env->GetStringUTFChars(js, nullptr);
        std::string s = c ? c : "";
        env->ReleaseStringUTFChars(js, c);
        return s;
    };

    std::string pkg    = toCpp(jPackageId);
    std::string key    = toCpp(jUserKey);
    std::string serial = toCpp(jSerial);

    auto r = hclou::login(pkg, key, serial);
    LOGI("login pkg=%s reason=%s success=%d", pkg.c_str(), r.reason.c_str(), r.success ? 1 : 0);
    return env->NewStringUTF(r.success ? "OK" : r.reason.c_str());
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_mod_MainActivity_jniGetModname(JNIEnv* env, jobject /*thiz*/) {
    return env->NewStringUTF(hclou::modConfig.modname.c_str());
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_mod_MainActivity_jniGetCredit(JNIEnv* env, jobject /*thiz*/) {
    return env->NewStringUTF(hclou::modConfig.credit.c_str());
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_mod_MainActivity_jniGetExp(JNIEnv* env, jobject /*thiz*/) {
    return env->NewStringUTF(hclou::modConfig.expireDate.c_str());
}

// ============================================================================
// JNI bindings cho ModService (floating menu)
// ============================================================================
extern "C" JNIEXPORT void JNICALL
Java_com_hclou_mod_ModService_jniStartMod(JNIEnv* /*env*/, jobject /*thiz*/) {
    if (g_modRunning.load()) return;
    g_modRunning = true;
    g_modThread  = std::thread(modThreadEntry);
}

extern "C" JNIEXPORT void JNICALL
Java_com_hclou_mod_ModService_jniStopMod(JNIEnv* /*env*/, jobject /*thiz*/) {
    g_modRunning = false;
    if (g_modThread.joinable()) g_modThread.join();
}

extern "C" JNIEXPORT void JNICALL
Java_com_hclou_mod_ModService_jniToggleBypass(JNIEnv* /*env*/, jobject /*thiz*/, jboolean on) {
    g_bypassOn = (bool)on;
    LOGI("toggle bypass: %d", g_bypassOn.load() ? 1 : 0);
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_mod_ModService_jniGetModname(JNIEnv* env, jobject /*thiz*/) {
    return env->NewStringUTF(hclou::modConfig.modname.c_str());
}
