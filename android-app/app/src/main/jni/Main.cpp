// ============================================================================
// HCLOU Mod Loader — Main.cpp
// Login HCLOU-LICENSE + Bypass Login 400 only (anti-cheat IL2CPP)
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

using json = nlohmann::ordered_json;
using namespace std;

// ============================================================================
// GLOBAL STATE
// ============================================================================
bool bValid = false;
std::string g_Auth, g_Token;
std::string g_ModName = "HCLOU MOD";
std::string g_ModStatus = "UNKNOWN";
std::string g_Credit = "";

// Bypass Login 400 toggle (menu item ID 100)
bool g_bypass400 = false;

// ============================================================================
// HCLOU-LICENSE MASTER SECRETS — đồng bộ config.local.php server.
// Bọc OBFUSCATE/StrEnc trước release để không lộ plain trong .so.
// ============================================================================
static const std::string HCLOU_HMAC_SECRET = "d26213bb049ed2eaa539715db9b7a55aba89138302f2f39d2dee6b69de6eb00c";
static const std::string HCLOU_STATIC_WORD = "afcfa84584f1e19e83e18d071bdcc9fa";
static const std::string HCLOU_API_URL     = "https://teamcrack.linkpc.net/api/connect.php";

// ============================================================================
// CRYPTO HELPERS
// ============================================================================
std::string RandomString(const int len) {
    static const char alphanumerics[] = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    srand((unsigned) time(0) * getpid());
    std::string tmp; tmp.reserve(len);
    for (int i = 0; i < len; ++i) tmp += alphanumerics[rand() % (sizeof(alphanumerics) - 1)];
    return tmp;
}

std::string CalcMD5(std::string s) {
    std::string result;
    unsigned char hash[MD5_DIGEST_LENGTH];
    char tmp[4];
    MD5_CTX md5;
    MD5_Init(&md5);
    MD5_Update(&md5, s.c_str(), s.length());
    MD5_Final(hash, &md5);
    for (unsigned char i : hash) { sprintf(tmp, "%02x", i); result += tmp; }
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
// BYPASS LOGIN 400 — IL2CPP memory write (in-process, runs in game's lib)
// Logic resolve static fields holder → write state codes vào target struct.
// ============================================================================
static inline bool BPReadPtr(uintptr_t address, uintptr_t& out) {
    out = 0;
    if (address == 0) return false;
    out = *reinterpret_cast<uintptr_t*>(address);
    return true;
}

static uintptr_t BPResolveTaggedMetadata(uintptr_t slotAddress) {
    uintptr_t tagged = 0;
    if (!BPReadPtr(slotAddress, tagged) || tagged == 0) return 0;
    if ((tagged & 1ULL) != 0) {
        uintptr_t reread = 0;
        if (!BPReadPtr(slotAddress, reread) || reread == 0) return 0;
        tagged = reread;
    }
    uintptr_t holderSlot = 0;
    if (!BPReadPtr(tagged + 0xB8, holderSlot) || holderSlot == 0) return 0;
    uintptr_t holder = 0;
    if (!BPReadPtr(holderSlot, holder)) return 0;
    return holder;
}

static uintptr_t BPResolveStaticFieldsHolder(uintptr_t root) {
    uintptr_t ptrA = 0, ptrB = 0;
    if (!BPReadPtr(root + 0x20, ptrA) || ptrA == 0) return 0;
    if (!BPReadPtr(ptrA + 0xC0, ptrB) || ptrB == 0) return 0;
    uintptr_t holder = BPResolveTaggedMetadata(ptrB + 0x10);
    if (holder != 0) return holder;
    uintptr_t unused = 0;
    (void)BPReadPtr(ptrB + 0x18, unused);
    return BPResolveTaggedMetadata(ptrB + 0x10);
}

static void Bypass400Loop() {
    constexpr uintptr_t ROOT_OFFSET     = 0xAA0D678;
    constexpr uint32_t  STATE_CODE_ON   = 0x0001007B;
    constexpr uint32_t  STATE_CODE_OFF  = 0x0002007C;
    constexpr uint32_t  STATE_FLAG_ON   = 0x00000001;
    constexpr uint32_t  STATE_FLAG_OFF  = 0x0000000E;

    ProcMap il2cppMap;
    do {
        il2cppMap = KittyMemory::getLibraryMap("libil2cpp.so");
        sleep(1);
    } while (!il2cppMap.isValid());

    uintptr_t il2cppBase = il2cppMap.startAddress;

    while (true) {
        if (il2cppBase != 0) {
            uintptr_t root = *reinterpret_cast<uintptr_t*>(il2cppBase + ROOT_OFFSET);
            if (root != 0) {
                uintptr_t holder = BPResolveStaticFieldsHolder(root);
                uintptr_t target = 0;
                if (holder != 0 && BPReadPtr(holder + 0x18, target) && target != 0) {
                    *reinterpret_cast<uint32_t*>(target + 0x10) =
                        g_bypass400 ? STATE_CODE_ON : STATE_CODE_OFF;
                    *reinterpret_cast<uint32_t*>(target + 0x14) =
                        g_bypass400 ? STATE_FLAG_ON : STATE_FLAG_OFF;
                }
            }
        }
        sleep(2);
    }
}

void *hack_thread(void *) {
    sleep(5);
    Bypass400Loop();
    return NULL;
}

__attribute__((constructor))
void lib_main() {
    pthread_t ptid;
    pthread_create(&ptid, NULL, hack_thread, NULL);
}

// ============================================================================
// MENU FEATURES — chỉ 1 toggle "Bypass Login 400"
// ============================================================================
jobjectArray GetFeatureList(JNIEnv *env, jobject context) {
    jobjectArray ret;
    const char *features[] = {
        OBFUSCATE("Category_HCLOU MOD"),
        OBFUSCATE("100_Toggle_Bypass Login 400"),
    };
    int Total_Feature = (sizeof features / sizeof features[0]);
    ret = (jobjectArray) env->NewObjectArray(Total_Feature, env->FindClass(OBFUSCATE("java/lang/String")), env->NewStringUTF(""));
    for (int i = 0; i < Total_Feature; i++)
        env->SetObjectArrayElement(ret, i, env->NewStringUTF(features[i]));
    return ret;
}

void Changes(JNIEnv *env, jclass clazz, jobject obj,
             jint featNum, jstring featName, jint value,
             jboolean boolean, jstring str) {
    LOGD(OBFUSCATE("Feature %d | bool=%d"), featNum, boolean);
    switch (featNum) {
        case 100:
            g_bypass400 = boolean;
            break;
    }
}

// ============================================================================
// LICENSE CHECK — HCLOU-LICENSE backend với HMAC + per_static derive
// ============================================================================
struct LicenseMemoryStruct { char *memory; size_t size; };

static size_t LicenseWriteCallback(void *contents, size_t size, size_t nmemb, void *userp) {
    size_t realsize = size * nmemb;
    auto *mem = (LicenseMemoryStruct*) userp;
    mem->memory = (char*)realloc(mem->memory, mem->size + realsize + 1);
    if (!mem->memory) return 0;
    memcpy(&(mem->memory[mem->size]), contents, realsize);
    mem->size += realsize;
    mem->memory[mem->size] = 0;
    return realsize;
}

extern "C" {

JNIEXPORT jstring JNICALL
Java_com_android_support_TechnicalAkash1_Check(JNIEnv *env, jclass clazz, jobject mContext, jstring mUserKey) {
    auto userKey = env->GetStringUTFChars(mUserKey, 0);

    std::string hwid = userKey;
    hwid += GetAndroidID(env, mContext);
    hwid += GetDeviceModel(env);
    hwid += GetDeviceBrand(env);
    std::string UUID = GetDeviceUniqueIdentifier(env, hwid.c_str());

    std::string errMsg;
    LicenseMemoryStruct chunk{};
    chunk.memory = (char*) malloc(1); chunk.size = 0;

    CURL *curl = curl_easy_init();
    if (curl) {
        curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_easy_setopt(curl, CURLOPT_URL, HCLOU_API_URL.c_str());
        curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
        struct curl_slist *headers = NULL;
        headers = curl_slist_append(headers, "Content-Type: application/x-www-form-urlencoded");
        curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

        const char *packageName = GetPackageName(env, mContext);

        // Build HMAC sign + nonce + timestamp
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

        curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, LicenseWriteCallback);
        curl_easy_setopt(curl, CURLOPT_WRITEDATA, (void*) &chunk);
        curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 0L);
        curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 0L);
        curl_easy_setopt(curl, CURLOPT_TIMEOUT, 15L);
        curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);

        CURLcode res = curl_easy_perform(curl);
        if (res == CURLE_OK) {
            try {
                json result = json::parse(chunk.memory);
                if (result["status"] == true) {
                    std::string token = result["data"]["token"].get<std::string>();
                    time_t rng = result["data"]["rng"].get<time_t>();

                    if (rng + 30 > time(0)) {
                        // Per-key static derive (match server deriveStaticWord)
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
// JNI REGISTRATION (Menu framework + lifecycle)
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
