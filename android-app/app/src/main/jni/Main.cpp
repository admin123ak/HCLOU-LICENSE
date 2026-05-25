// ============================================================================
// HCLOU Mod Loader — Main.cpp (clean rewrite từ template gốc)
// Login HCLOU-LICENSE + Bypass Login 400 (1 chức năng duy nhất)
// Dùng cho máy ROOT (KittyMemory inject lib vào game process).
// ============================================================================

#include <list>
#include <vector>
#include <string.h>
#include <pthread.h>
#include <thread>
#include <cstring>
#include <jni.h>
#include <unistd.h>
#include <fstream>
#include <iostream>
#include <dlfcn.h>

#include "Includes/Logger.h"
#include "Includes/obfuscate.h"
#include "Includes/Utils.h"
#include "KittyMemory/MemoryPatch.h"
#include "Menu/Setup.h"
#include "Includes/Macros.h"

#include "StrEnc.h"
#include <curl/curl.h>
#include "json.hpp"
#include "LicenseTools.h"
#include <openssl/evp.h>
#include <openssl/md5.h>
#include <openssl/sha.h>
#include <openssl/hmac.h>

#include "Bypass_Login_Adapter.h"
#include "Bypass_Login.h"

using json = nlohmann::ordered_json;
using namespace std;

// ============================================================================
// HCLOU-LICENSE MASTER SECRETS — đồng bộ với config.local.php trên server.
// XOR / OBFUSCATE trước khi release production.
// ============================================================================
static const std::string HCLOU_HMAC_SECRET = "3601b133af42e867e1cffd82993561d37988e9917de27a4f22bc1cc5c803c83c";
static const std::string HCLOU_STATIC_WORD = "b28f2faf89c3a6e21e9f0595f48f60b4";
static const std::string HCLOU_API_URL     = "https://teamcrack.linkpc.net/api/connect.php";

// ============================================================================
// GLOBAL STATE
// ============================================================================
bool bValid = false;
std::string g_Auth, g_Token;
std::string g_ModName   = "HCLOU MOD";
std::string g_ModStatus = "UNKNOWN";
std::string g_Credit;

// Globals cho Bypass_Login.h (defined trong Bypass_Login_Adapter.h)
pid_t       pid        = 0;
uintptr_t   il2cppBase = 0;
ConfigState g_config;
std::mutex  g_configMutex;

// ============================================================================
// CRYPTO HELPERS
// ============================================================================
std::string RandomString(const int len) {
    static const char chars[] = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    srand((unsigned) time(0) * getpid());
    std::string tmp; tmp.reserve(len);
    for (int i = 0; i < len; ++i) tmp += chars[rand() % (sizeof(chars) - 1)];
    return tmp;
}

std::string CalcMD5(const std::string& s) {
    unsigned char hash[MD5_DIGEST_LENGTH];
    MD5_CTX md5;
    MD5_Init(&md5);
    MD5_Update(&md5, s.c_str(), s.length());
    MD5_Final(hash, &md5);
    std::string result; char tmp[4];
    for (unsigned char b : hash) { sprintf(tmp, "%02x", b); result += tmp; }
    return result;
}

std::string CalcHMAC(const std::string& key, const std::string& msg) {
    unsigned char out[EVP_MAX_MD_SIZE];
    unsigned int outLen = 0;
    HMAC(EVP_sha256(), key.data(), (int)key.size(),
         (const unsigned char*)msg.data(), msg.size(), out, &outLen);
    std::string result; char tmp[4];
    for (unsigned int i = 0; i < outLen; i++) { sprintf(tmp, "%02x", out[i]); result += tmp; }
    return result;
}

// ============================================================================
// HACK THREAD — wait libil2cpp + start Bypass400 thread
// ============================================================================
void *hack_thread(void *) {
    sleep(5);
    ProcMap il2cppMap;
    do {
        il2cppMap = KittyMemory::getLibraryMap("libil2cpp.so");
        sleep(1);
    } while (!il2cppMap.isValid());

    pid        = getpid();
    il2cppBase = (uintptr_t)il2cppMap.startAddr;

    pthread_t bypassThread;
    pthread_create(&bypassThread, NULL, Bypass400, NULL);
    pthread_detach(bypassThread);
    return NULL;
}

__attribute__((constructor))
void lib_main() {
    pthread_t ptid;
    pthread_create(&ptid, NULL, hack_thread, NULL);
}

// ============================================================================
// MENU — chỉ 1 toggle "Bypass Login 400"
// ============================================================================
jobjectArray GetFeatureList(JNIEnv *env, jobject context) {
    const char *features[] = {
        OBFUSCATE("Category_HCLOU MOD"),
        OBFUSCATE("100_Toggle_Bypass Login 400"),
    };
    int total = sizeof(features) / sizeof(features[0]);
    jobjectArray ret = (jobjectArray) env->NewObjectArray(total, env->FindClass(OBFUSCATE("java/lang/String")), env->NewStringUTF(""));
    for (int i = 0; i < total; i++) env->SetObjectArrayElement(ret, i, env->NewStringUTF(features[i]));
    return ret;
}

void Changes(JNIEnv *env, jclass clazz, jobject obj,
             jint featNum, jstring featName, jint value,
             jboolean boolean, jstring str) {
    if (featNum == 100) {
        std::lock_guard<std::mutex> lock(g_configMutex);
        g_config.bypass400 = boolean;
    }
}

// ============================================================================
// LICENSE CHECK — HCLOU-LICENSE backend
// Server formula (PHP):
//   perHmac   = hmac_sha256(HMAC_SECRET,   "hmac:"   + userKey)
//   perStatic = hmac_sha256(STATIC_WORD,   "static:" + userKey)
//   payload   = "{game}|{userKey}|{serial}|{nonce}|{timestamp}"
//   expected  = hmac_sha256(perHmac, payload)
//   token     = md5("{game}-{userKey}-{serial}-{perStatic}")
// ============================================================================
struct LicenseChunk { char *memory; size_t size; };

static size_t LicenseWriteCallback(void *contents, size_t size, size_t nmemb, void *userp) {
    size_t realsize = size * nmemb;
    auto *m = (LicenseChunk*) userp;
    m->memory = (char*) realloc(m->memory, m->size + realsize + 1);
    if (!m->memory) return 0;
    memcpy(&(m->memory[m->size]), contents, realsize);
    m->size += realsize;
    m->memory[m->size] = 0;
    return realsize;
}

extern "C" {

JNIEXPORT jstring JNICALL
Java_com_android_support_TechnicalAkash1_Check(JNIEnv *env, jclass clazz, jobject mContext, jstring mUserKey) {
    const char *userKey = env->GetStringUTFChars(mUserKey, 0);

    std::string hwid = std::string(userKey)
                     + GetAndroidID(env, mContext)
                     + GetDeviceModel(env)
                     + GetDeviceBrand(env);
    std::string UUID = GetDeviceUniqueIdentifier(env, hwid.c_str());

    std::string errMsg;
    LicenseChunk chunk{};
    chunk.memory = (char*) malloc(1);
    chunk.size   = 0;

    CURL *curl = curl_easy_init();
    if (curl) {
        curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_easy_setopt(curl, CURLOPT_URL,           HCLOU_API_URL.c_str());
        curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
        struct curl_slist *headers = NULL;
        headers = curl_slist_append(headers, "Content-Type: application/x-www-form-urlencoded");
        curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

        const char *packageName = GetPackageName(env, mContext);

        std::string nonce = RandomString(24);
        std::string ts    = std::to_string((long)time(NULL));
        std::string payload = std::string(packageName) + "|" + userKey + "|" + UUID + "|" + nonce + "|" + ts;
        std::string perHmac = CalcHMAC(HCLOU_HMAC_SECRET, "hmac:" + std::string(userKey));
        std::string hmac    = CalcHMAC(perHmac, payload);

        char data[8192];
        snprintf(data, sizeof(data),
                 "game=%s&user_key=%s&serial=%s&nonce=%s&timestamp=%s&hmac=%s",
                 packageName, userKey, UUID.c_str(), nonce.c_str(), ts.c_str(), hmac.c_str());
        curl_easy_setopt(curl, CURLOPT_POSTFIELDS, data);

        curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION,  LicenseWriteCallback);
        curl_easy_setopt(curl, CURLOPT_WRITEDATA,      (void*) &chunk);
        curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 0L);
        curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 0L);
        curl_easy_setopt(curl, CURLOPT_TIMEOUT,        15L);
        curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);

        CURLcode res = curl_easy_perform(curl);
        if (res == CURLE_OK) {
            try {
                json result = json::parse(chunk.memory);
                if (result["status"] == true) {
                    std::string token = result["data"]["token"].get<std::string>();
                    time_t rng = result["data"]["rng"].get<time_t>();

                    if (rng + 30 > time(0)) {
                        std::string perStatic = CalcHMAC(HCLOU_STATIC_WORD, "static:" + std::string(userKey));
                        std::string auth = std::string(packageName) + "-" + userKey + "-" + UUID + "-" + perStatic;
                        std::string outputAuth = CalcMD5(auth);
                        g_Token = token; g_Auth = outputAuth;
                        bValid = (g_Token == g_Auth);

                        if (bValid) {
                            auto d = result["data"];
                            if (d.contains("modname"))    g_ModName   = d["modname"].get<std::string>();
                            if (d.contains("mod_status")) g_ModStatus = d["mod_status"].get<std::string>();
                            if (d.contains("credit"))     g_Credit    = d["credit"].get<std::string>();
                        } else {
                            errMsg = "TOKEN_MISMATCH";
                        }
                    } else {
                        errMsg = "TOKEN_EXPIRED";
                    }
                } else {
                    errMsg = result.contains("reason") ? result["reason"].get<std::string>() : "UNKNOWN";
                }
            } catch (json::exception &e) {
                errMsg = std::string("JSON: ") + e.what();
            }
        } else {
            errMsg = curl_easy_strerror(res);
        }
        curl_slist_free_all(headers);
    }
    curl_easy_cleanup(curl);
    free(chunk.memory);
    return bValid ? env->NewStringUTF("OK") : env->NewStringUTF(errMsg.c_str());
}

JNIEXPORT jstring JNICALL
Java_com_android_support_TechnicalAkash1_GetModName(JNIEnv *env, jclass clazz) {
    return env->NewStringUTF(g_ModName.c_str());
}

JNIEXPORT jstring JNICALL
Java_com_android_support_TechnicalAkash1_GetModStatus(JNIEnv *env, jclass clazz) {
    return env->NewStringUTF(g_ModStatus.c_str());
}

}  // extern "C"

// ============================================================================
// JNI REGISTRATION
// ============================================================================
int RegisterMenu(JNIEnv *env) {
    JNINativeMethod methods[] = {
        {OBFUSCATE("Icon"),             OBFUSCATE("()Ljava/lang/String;"), reinterpret_cast<void*>(Icon)},
        {OBFUSCATE("IconWebViewData"),  OBFUSCATE("()Ljava/lang/String;"), reinterpret_cast<void*>(IconWebViewData)},
        {OBFUSCATE("IsGameLibLoaded"),  OBFUSCATE("()Z"),                  reinterpret_cast<void*>(isGameLibLoaded)},
        {OBFUSCATE("Init"),             OBFUSCATE("(Landroid/content/Context;Landroid/widget/TextView;Landroid/widget/TextView;)V"), reinterpret_cast<void*>(Init)},
        {OBFUSCATE("SettingsList"),     OBFUSCATE("()[Ljava/lang/String;"), reinterpret_cast<void*>(SettingsList)},
        {OBFUSCATE("GetFeatureList"),   OBFUSCATE("()[Ljava/lang/String;"), reinterpret_cast<void*>(GetFeatureList)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Menu"));
    if (!clazz) return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0) return JNI_ERR;
    return JNI_OK;
}

int RegisterPreferences(JNIEnv *env) {
    JNINativeMethod methods[] = {
        {OBFUSCATE("Changes"), OBFUSCATE("(Landroid/content/Context;ILjava/lang/String;IZLjava/lang/String;)V"), reinterpret_cast<void*>(Changes)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Preferences"));
    if (!clazz) return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0) return JNI_ERR;
    return JNI_OK;
}

int RegisterMain(JNIEnv *env) {
    JNINativeMethod methods[] = {
        {OBFUSCATE("CheckOverlayPermission"), OBFUSCATE("(Landroid/content/Context;)V"), reinterpret_cast<void*>(CheckOverlayPermission)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Main"));
    if (!clazz) return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0) return JNI_ERR;
    return JNI_OK;
}

extern "C"
JNIEXPORT jint JNICALL
JNI_OnLoad(JavaVM *vm, void *reserved) {
    JNIEnv *env;
    vm->GetEnv((void**) &env, JNI_VERSION_1_6);
    if (RegisterMenu(env)        != 0) return JNI_ERR;
    if (RegisterPreferences(env) != 0) return JNI_ERR;
    if (RegisterMain(env)        != 0) return JNI_ERR;
    return JNI_VERSION_1_6;
}
