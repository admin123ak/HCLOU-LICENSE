// ============================================================================
// HCLOU FF Loader — native-lib.cpp
// JNI bridge cho MainActivity + ModService
// + License check HCLOU-LICENSE
// + Bypass Login 400 thread (file Bypass_Login.h của user)
// ============================================================================

#include <jni.h>
#include <string>
#include <thread>
#include <atomic>
#include <mutex>
#include <pthread.h>
#include <unistd.h>
#include <ctime>
#include <random>
#include <sstream>
#include <iomanip>
#include <cstring>
#include <android/log.h>

#include <curl/curl.h>
#include <openssl/hmac.h>
#include <openssl/md5.h>
#include <openssl/sha.h>
#include <openssl/evp.h>

#define LOG_TAG "HCLOU-FF"
#define LOGI(...) __android_log_print(ANDROID_LOG_INFO,  LOG_TAG, __VA_ARGS__)
#define LOGE(...) __android_log_print(ANDROID_LOG_ERROR, LOG_TAG, __VA_ARGS__)

// ============================================================================
// HCLOU-LICENSE master secrets (đồng bộ với config.local.php server)
// ============================================================================
static const std::string HCLOU_HMAC_SECRET = "3601b133af42e867e1cffd82993561d37988e9917de27a4f22bc1cc5c803c83c";
static const std::string HCLOU_STATIC_WORD = "b28f2faf89c3a6e21e9f0595f48f60b4";
static const std::string HCLOU_API_URL     = "https://teamcrack.linkpc.net/api/connect.php";

// ============================================================================
// State
// ============================================================================
static std::string g_ModName   = "HCLOU FF";
static std::string g_ModStatus = "UNKNOWN";
static std::string g_Credit;
static std::string g_ExpDate;
static long        g_MaxDevices = 1;

// Bypass400 globals (Bypass_Login.h sẽ reference)
pid_t       pid        = 0;
uintptr_t   il2cppBase = 0;
struct ConfigState { bool bypass400 = false; };
ConfigState g_config;
std::mutex  g_configMutex;

// In-process mem adapter (Bypass_Login.h reference qua namespace mem::)
namespace mem {
    template<typename T> inline bool read(pid_t, uintptr_t addr, T& out) {
        if (addr == 0) { out = T{}; return false; }
        out = *reinterpret_cast<T*>(addr); return true;
    }
    template<typename T> inline T read(pid_t, uintptr_t addr) {
        if (addr == 0) return T{};
        return *reinterpret_cast<T*>(addr);
    }
    template<typename T> inline bool write(pid_t, uintptr_t addr, T value) {
        if (addr == 0) return false;
        *reinterpret_cast<T*>(addr) = value; return true;
    }
}

#include "Bypass_Login.h"

// ============================================================================
// Mod state thread
// ============================================================================
static std::atomic<bool> g_modRunning{false};
static std::thread       g_modThread;

static void modThreadEntry() {
    LOGI("Mod thread start");
    // Wait il2cpp.so loaded — Free Fire dùng libil2cpp.so chứa game logic
    sleep(3);
    pid = getpid();

    // Tìm libil2cpp base bằng /proc/self/maps (đơn giản, không cần KittyMemory)
    while (g_modRunning.load() && il2cppBase == 0) {
        FILE* fp = fopen("/proc/self/maps", "r");
        if (fp) {
            char line[512];
            while (fgets(line, sizeof(line), fp)) {
                if (strstr(line, "libil2cpp.so") && strstr(line, "r-xp")) {
                    uintptr_t base = 0;
                    sscanf(line, "%lx", (unsigned long*)&base);
                    il2cppBase = base;
                    LOGI("Found libil2cpp base: 0x%lx", (unsigned long)il2cppBase);
                    break;
                }
            }
            fclose(fp);
        }
        if (il2cppBase == 0) sleep(2);
    }

    // Start Bypass400 thread (Bypass_Login.h)
    pthread_t bypassThread;
    pthread_create(&bypassThread, NULL, Bypass400, NULL);
    pthread_detach(bypassThread);

    // Keep alive
    while (g_modRunning.load()) sleep(5);
    LOGI("Mod thread stop");
}

// ============================================================================
// Crypto helpers
// ============================================================================
static std::string toHex(const unsigned char* data, size_t len) {
    std::ostringstream oss;
    oss << std::hex << std::setfill('0');
    for (size_t i = 0; i < len; i++) oss << std::setw(2) << (int)data[i];
    return oss.str();
}

static std::string hmacSha256(const std::string& key, const std::string& msg) {
    unsigned char out[EVP_MAX_MD_SIZE];
    unsigned int outLen = 0;
    HMAC(EVP_sha256(), key.data(), (int)key.size(),
         (const unsigned char*)msg.data(), msg.size(), out, &outLen);
    return toHex(out, outLen);
}

static std::string md5Hex(const std::string& s) {
    unsigned char out[MD5_DIGEST_LENGTH];
    MD5((const unsigned char*)s.data(), s.size(), out);
    return toHex(out, MD5_DIGEST_LENGTH);
}

static std::string randomString(size_t len) {
    static const char chars[] = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    std::random_device rd; std::mt19937 gen(rd());
    std::uniform_int_distribution<> dist(0, (int)sizeof(chars) - 2);
    std::string out; out.reserve(len);
    for (size_t i = 0; i < len; i++) out += chars[dist(gen)];
    return out;
}

// ============================================================================
// Minimal JSON extract
// ============================================================================
static std::string jsonString(const std::string& json, const std::string& key) {
    std::string pat = "\"" + key + "\"";
    size_t p = json.find(pat);
    if (p == std::string::npos) return "";
    p = json.find(':', p + pat.size());
    if (p == std::string::npos) return "";
    p = json.find('"', p);
    if (p == std::string::npos) return "";
    size_t q = json.find('"', p + 1);
    if (q == std::string::npos) return "";
    return json.substr(p + 1, q - p - 1);
}
static long jsonInt(const std::string& json, const std::string& key) {
    std::string pat = "\"" + key + "\"";
    size_t p = json.find(pat);
    if (p == std::string::npos) return 0;
    p = json.find(':', p + pat.size());
    if (p == std::string::npos) return 0;
    p++;
    while (p < json.size() && (json[p] == ' ' || json[p] == '\t')) p++;
    size_t q = p;
    while (q < json.size() && (json[q] == '-' || (json[q] >= '0' && json[q] <= '9'))) q++;
    if (q == p) return 0;
    try { return std::stol(json.substr(p, q - p)); } catch (...) { return 0; }
}
static bool jsonBool(const std::string& json, const std::string& key) {
    std::string pat = "\"" + key + "\"";
    size_t p = json.find(pat);
    if (p == std::string::npos) return false;
    p = json.find(':', p + pat.size());
    if (p == std::string::npos) return false;
    size_t comma = json.find(',', p);
    size_t brace = json.find('}', p);
    size_t end   = (comma == std::string::npos) ? brace : std::min(comma, brace);
    size_t t     = json.find("true", p);
    return (t != std::string::npos && t < end);
}

static std::string urlEncode(const std::string& s) {
    std::ostringstream oss; oss << std::hex << std::uppercase << std::setfill('0');
    for (unsigned char c : s) {
        if ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') ||
            (c >= '0' && c <= '9') || c == '-' || c == '_' || c == '.' || c == '~') oss << c;
        else oss << '%' << std::setw(2) << (int)c;
    }
    return oss.str();
}

static size_t curlWrite(void* contents, size_t size, size_t nmemb, std::string* userp) {
    userp->append((char*)contents, size * nmemb);
    return size * nmemb;
}

// ============================================================================
// License login
// ============================================================================
static std::string licenseLogin(const std::string& packageId, const std::string& userKey, const std::string& serial) {
    std::string nonce = randomString(24);
    long ts = (long)time(NULL);
    std::string tsStr = std::to_string(ts);

    std::string payload = packageId + "|" + userKey + "|" + serial + "|" + nonce + "|" + tsStr;
    std::string perHmac = hmacSha256(HCLOU_HMAC_SECRET, "hmac:" + userKey);
    std::string hmac    = hmacSha256(perHmac, payload);

    std::string body =
        "game="      + urlEncode(packageId) +
        "&user_key=" + urlEncode(userKey) +
        "&serial="   + urlEncode(serial) +
        "&nonce="    + urlEncode(nonce) +
        "&timestamp=" + tsStr +
        "&hmac="     + hmac;

    CURL* curl = curl_easy_init();
    if (!curl) return "CURL_INIT";

    std::string resp;
    struct curl_slist* headers = NULL;
    headers = curl_slist_append(headers, "Content-Type: application/x-www-form-urlencoded");

    curl_easy_setopt(curl, CURLOPT_URL,            HCLOU_API_URL.c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS,     body.c_str());
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER,     headers);
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION,  curlWrite);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA,      &resp);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT,        15L);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);
    curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 0L);
    curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 0L);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(curl, CURLOPT_USERAGENT,      "HCLOU-FF/1.0");

    CURLcode res = curl_easy_perform(curl);
    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);

    if (res != CURLE_OK) return std::string("CURL_FAIL: ") + curl_easy_strerror(res);
    if (!jsonBool(resp, "status")) {
        std::string r = jsonString(resp, "reason");
        return r.empty() ? "UNKNOWN" : r;
    }

    std::string token = jsonString(resp, "token");
    long rng = jsonInt(resp, "rng");
    if (token.empty()) return "MALFORMED_RESPONSE";

    std::string perStatic = hmacSha256(HCLOU_STATIC_WORD, "static:" + userKey);
    std::string expectedToken = md5Hex(packageId + "-" + userKey + "-" + serial + "-" + perStatic);
    if (token != expectedToken) return "TOKEN_MISMATCH";

    long now = (long)time(NULL);
    if (rng + 30 < now) return "TOKEN_EXPIRED";

    g_ModName    = jsonString(resp, "modname"); if (g_ModName.empty()) g_ModName = "HCLOU FF";
    g_ModStatus  = jsonString(resp, "mod_status");
    g_Credit     = jsonString(resp, "credit");
    g_ExpDate    = jsonString(resp, "EXP");
    g_MaxDevices = jsonInt   (resp, "device");

    return "OK";
}

// ============================================================================
// JNI bindings — MainActivity
// ============================================================================
extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_ffloader_MainActivity_jniLogin(
    JNIEnv* env, jobject, jstring jPkg, jstring jKey, jstring jSerial) {

    auto cpp = [&env](jstring js) {
        if (!js) return std::string("");
        const char* c = env->GetStringUTFChars(js, nullptr);
        std::string s = c ? c : "";
        env->ReleaseStringUTFChars(js, c);
        return s;
    };
    std::string pkg    = cpp(jPkg);
    std::string key    = cpp(jKey);
    std::string serial = cpp(jSerial);

    std::string r = licenseLogin(pkg, key, serial);
    LOGI("login pkg=%s reason=%s", pkg.c_str(), r.c_str());
    return env->NewStringUTF(r.c_str());
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_ffloader_MainActivity_jniGetModname(JNIEnv* env, jobject) {
    return env->NewStringUTF(g_ModName.c_str());
}
extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_ffloader_MainActivity_jniGetCredit(JNIEnv* env, jobject) {
    return env->NewStringUTF(g_Credit.c_str());
}
extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_ffloader_MainActivity_jniGetExp(JNIEnv* env, jobject) {
    return env->NewStringUTF(g_ExpDate.c_str());
}

// ============================================================================
// JNI bindings — ModService (floating menu)
// ============================================================================
extern "C" JNIEXPORT void JNICALL
Java_com_hclou_ffloader_ModService_jniStartMod(JNIEnv*, jobject) {
    if (g_modRunning.load()) return;
    g_modRunning = true;
    g_modThread  = std::thread(modThreadEntry);
}

extern "C" JNIEXPORT void JNICALL
Java_com_hclou_ffloader_ModService_jniStopMod(JNIEnv*, jobject) {
    g_modRunning = false;
    if (g_modThread.joinable()) g_modThread.join();
    {
        std::lock_guard<std::mutex> lock(g_configMutex);
        g_config.bypass400 = false;
    }
}

extern "C" JNIEXPORT void JNICALL
Java_com_hclou_ffloader_ModService_jniToggleBypass(JNIEnv*, jobject, jboolean on) {
    std::lock_guard<std::mutex> lock(g_configMutex);
    g_config.bypass400 = (bool)on;
    LOGI("toggle bypass400: %d", g_config.bypass400 ? 1 : 0);
}

extern "C" JNIEXPORT jstring JNICALL
Java_com_hclou_ffloader_ModService_jniGetModname(JNIEnv* env, jobject) {
    return env->NewStringUTF(g_ModName.c_str());
}
