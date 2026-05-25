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
#include <atomic>
#include "Includes/Logger.h"
#include "Includes/obfuscate.h"
#include "Includes/Utils.h"
#include "KittyMemory/MemoryPatch.h"
#include "KittyMemory/KittyMemory.h"
#include "Menu/Setup.h"
#include "duymmo/Call_Me.h"
#include "Unity/MonoString.h"
//Target lib here
#define targetLibName OBFUSCATE("libFileA.so")

#include "Includes/Macros.h"

// ============================================================================
// Bypass Login (port từ /root/Bypass_Login.h, in-process style)
// ============================================================================
std::atomic<bool> g_bypassLogin(false);
uintptr_t g_il2cppBase = 0;

static bool bp_readPtr(uintptr_t address, uintptr_t& out) {
    out = 0;
    if (address == 0) return false;
    out = *(volatile uintptr_t *)address;
    return true;
}

static uintptr_t bp_resolveTagged(uintptr_t slotAddress) {
    uintptr_t tagged = 0;
    if (!bp_readPtr(slotAddress, tagged) || tagged == 0) return 0;
    if ((tagged & 1ULL) != 0) {
        uintptr_t reread = 0;
        if (!bp_readPtr(slotAddress, reread) || reread == 0) return 0;
        tagged = reread;
    }
    uintptr_t holderSlot = 0;
    if (!bp_readPtr(tagged + 0xB8, holderSlot) || holderSlot == 0) return 0;
    uintptr_t holder = 0;
    if (!bp_readPtr(holderSlot, holder)) return 0;
    return holder;
}

static uintptr_t bp_resolveStaticFieldsHolder(uintptr_t root) {
    uintptr_t ptrA = 0, ptrB = 0;
    if (!bp_readPtr(root + 0x20, ptrA) || ptrA == 0) return 0;
    if (!bp_readPtr(ptrA + 0xC0, ptrB) || ptrB == 0) return 0;
    return bp_resolveTagged(ptrB + 0x10);
}

void *BypassLoginThread(void *) {
    constexpr uintptr_t ROOT_OFFSET    = 0xAA0D678;
    constexpr uint32_t  STATE_CODE_ON  = 0x0001007B;
    constexpr uint32_t  STATE_CODE_OFF = 0x0002007C;
    constexpr uint32_t  STATE_FLAG_ON  = 0x00000001;
    constexpr uint32_t  STATE_FLAG_OFF = 0x0000000E;

    while (true) {
        bool enabled = g_bypassLogin.load();
        if (g_il2cppBase != 0) {
            uintptr_t root = 0;
            bp_readPtr(g_il2cppBase + ROOT_OFFSET, root);
            if (root != 0) {
                uintptr_t holder = bp_resolveStaticFieldsHolder(root);
                if (holder != 0) {
                    uintptr_t target = 0;
                    if (bp_readPtr(holder + 0x18, target) && target != 0) {
                        *(volatile uint32_t *)(target + 0x10) = enabled ? STATE_CODE_ON : STATE_CODE_OFF;
                        *(volatile uint32_t *)(target + 0x14) = enabled ? STATE_FLAG_ON : STATE_FLAG_OFF;
                    }
                }
            }
        }
        sleep(2);
    }
    return nullptr;
}

// ============================================================================
// Worker thread — chờ libil2cpp.so load xong → spawn Bypass worker
// ============================================================================
void *hack_thread(void *) {
    sleep(5);
    ProcMap il2cppMap;
    do {
        il2cppMap = KittyMemory::getLibraryMap("libil2cpp.so");
        sleep(1);
    } while (!il2cppMap.isValid());

    g_il2cppBase = il2cppMap.startAddress;

    pthread_t btid;
    pthread_create(&btid, NULL, BypassLoginThread, NULL);
    return NULL;
}

// ============================================================================
// Feature list — chỉ 1 toggle
// ============================================================================
jobjectArray GetFeatureList(JNIEnv *env, jobject context) {
    jobjectArray ret;
    const char *features[] = {
        OBFUSCATE("Category_HCLOU Loader"),
        OBFUSCATE("1_Toggle_Bypass Login"),
    };
    int Total_Feature = (sizeof features / sizeof features[0]);
    ret = (jobjectArray)
            env->NewObjectArray(Total_Feature, env->FindClass(OBFUSCATE("java/lang/String")),
                                env->NewStringUTF(""));
    for (int i = 0; i < Total_Feature; i++)
        env->SetObjectArrayElement(ret, i, env->NewStringUTF(features[i]));
    return ret;
}

void Changes(JNIEnv *env, jclass clazz, jobject obj,
             jint featNum, jstring featName, jint value,
             jboolean boolean, jstring str) {
    switch (featNum) {
        case 1:
            g_bypassLogin.store(boolean);
            break;
    }
}

__attribute__((constructor))
void lib_main() {
    pthread_t ptid;
    pthread_create(&ptid, NULL, hack_thread, NULL);
}

// ============================================================================
// License check — sync HCLOU-LICENSE server (HMAC + per-key derive)
// ============================================================================
#include "StrEnc.h"
#include <curl/curl.h>
#include "json.hpp"
#include "LicenseTools.h"
#include <openssl/evp.h>
#include <openssl/md5.h>
#include <openssl/hmac.h>
#include <openssl/sha.h>

static const std::string HCLOU_HMAC_SECRET = "3601b133af42e867e1cffd82993561d37988e9917de27a4f22bc1cc5c803c83c";
static const std::string HCLOU_STATIC_WORD = "b28f2faf89c3a6e21e9f0595f48f60b4";

using json = nlohmann::ordered_json;
using namespace std;

bool bValid = false;
std::string g_Token, g_Auth;
std::string g_ModName = "HCLOU LOADER";
std::string g_ModStatus = "UNKNOWN";
std::string g_Credit = "";

std::string RandomString(const int len) {
    static const char alphanumerics[] = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    srand((unsigned) time(0) * getpid());
    std::string tmp;
    tmp.reserve(len);
    for (int i = 0; i < len; ++i) {
        tmp += alphanumerics[rand() % (sizeof(alphanumerics) - 1)];
    }
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
    std::string r; char tmp[4];
    for (unsigned int i = 0; i < outLen; i++) { sprintf(tmp, "%02x", out[i]); r += tmp; }
    return r;
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
    struct MemoryStruct chunk{};
    chunk.memory = (char *) malloc(1);
    chunk.size = 0;

    CURL *curl = curl_easy_init();
    if (curl) {
        curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_easy_setopt(curl, CURLOPT_URL, "https://teamcrack.linkpc.net/api/connect.php");
        curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);

        struct curl_slist *headers = NULL;
        headers = curl_slist_append(headers, "Content-Type: application/x-www-form-urlencoded");
        curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

        const char* packageName = GetPackageName(env, mContext);

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

        curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteMemoryCallback);
        curl_easy_setopt(curl, CURLOPT_WRITEDATA, (void *) &chunk);
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
                        std::string perStatic = CalcHMAC(HCLOU_STATIC_WORD, "static:" + std::string(userKey));
                        std::string auth = std::string(packageName) + "-" + userKey + "-" + UUID + "-" + perStatic;
                        std::string expected = CalcMD5(auth);

                        g_Token = token;
                        g_Auth  = expected;
                        bValid  = (g_Token == g_Auth);

                        if (bValid) {
                            try {
                                auto d = result["data"];
                                if (d.contains("modname"))    g_ModName   = d["modname"].get<std::string>();
                                if (d.contains("mod_status")) g_ModStatus = d["mod_status"].get<std::string>();
                                if (d.contains("credit"))     g_Credit    = d["credit"].get<std::string>();
                            } catch (...) {}
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
                errMsg = std::string("PARSE: ") + e.what();
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
}

// ============================================================================
// JNI register
// ============================================================================
int RegisterMenu(JNIEnv *env) {
    JNINativeMethod methods[] = {
        {OBFUSCATE("Icon"), OBFUSCATE("()Ljava/lang/String;"), reinterpret_cast<void *>(Icon)},
        {OBFUSCATE("IconWebViewData"),  OBFUSCATE("()Ljava/lang/String;"), reinterpret_cast<void *>(IconWebViewData)},
        {OBFUSCATE("IsGameLibLoaded"),  OBFUSCATE("()Z"), reinterpret_cast<void *>(isGameLibLoaded)},
        {OBFUSCATE("Init"),  OBFUSCATE("(Landroid/content/Context;Landroid/widget/TextView;Landroid/widget/TextView;)V"), reinterpret_cast<void *>(Init)},
        {OBFUSCATE("SettingsList"),  OBFUSCATE("()[Ljava/lang/String;"), reinterpret_cast<void *>(SettingsList)},
        {OBFUSCATE("GetFeatureList"),  OBFUSCATE("()[Ljava/lang/String;"), reinterpret_cast<void *>(GetFeatureList)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Menu"));
    if (!clazz) return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0) return JNI_ERR;
    return JNI_OK;
}

int RegisterPreferences(JNIEnv *env) {
    JNINativeMethod methods[] = {
        {OBFUSCATE("Changes"), OBFUSCATE("(Landroid/content/Context;ILjava/lang/String;IZLjava/lang/String;)V"), reinterpret_cast<void *>(Changes)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Preferences"));
    if (!clazz) return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0) return JNI_ERR;
    return JNI_OK;
}

int RegisterMain(JNIEnv *env) {
    JNINativeMethod methods[] = {
        {OBFUSCATE("CheckOverlayPermission"), OBFUSCATE("(Landroid/content/Context;)V"), reinterpret_cast<void *>(CheckOverlayPermission)},
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
    vm->GetEnv((void **) &env, JNI_VERSION_1_6);
    if (RegisterMenu(env) != 0) return JNI_ERR;
    if (RegisterPreferences(env) != 0) return JNI_ERR;
    if (RegisterMain(env) != 0) return JNI_ERR;
    return JNI_VERSION_1_6;
}
