// ============================================================================
// HCLOU-LICENSE — Sample C++ client integration
// ============================================================================
// Phụ thuộc:
//   - libcurl  (HTTPS POST)
//   - openssl  (SHA256, HMAC, MD5)
//   - nlohmann::json (JSON parse)  hoặc bất kỳ JSON lib khác
//
// Tích hợp:
//   1. #include "hclou_connect.h" trong main mod menu app
//   2. Gọi hclou::login(env, context, userKey) sau khi user nhập key
//   3. Nếu return true → modConfig.modname/mod_status/credit có data
//      → render UI menu + bắt đầu mod logic (Bypass400, etc.)
// ============================================================================

#include "hclou_connect.h"
#include <curl/curl.h>
#include <openssl/hmac.h>
#include <openssl/sha.h>
#include <openssl/md5.h>
#include <chrono>
#include <random>
#include <sstream>
#include <iomanip>
#include <cstring>

namespace hclou {

// =============================================
// CONFIG — embed master secrets (lấy từ config.local.php server)
// LƯU Ý: bảo vệ secrets bằng XOR runtime decode trước khi ship release.
// =============================================
static const std::string API_URL      = "https://teamcrack.linkpc.net/api/connect.php";
static const std::string HMAC_SECRET  = "d26213bb049ed2eaa539715db9b7a55aba89138302f2f39d2dee6b69de6eb00c";
static const std::string STATIC_WORD  = "afcfa84584f1e19e83e18d071bdcc9fa";

// Global state — render UI từ field này sau khi login OK.
ModConfig modConfig;

// =============================================
// HELPERS
// =============================================

static std::string toHex(const unsigned char* data, size_t len) {
    std::ostringstream oss;
    oss << std::hex << std::setfill('0');
    for (size_t i = 0; i < len; i++) oss << std::setw(2) << (int)data[i];
    return oss.str();
}

static std::string hmacSha256Hex(const std::string& key, const std::string& msg) {
    unsigned char out[EVP_MAX_MD_SIZE];
    unsigned int outLen = 0;
    HMAC(EVP_sha256(),
         key.data(), (int)key.size(),
         (const unsigned char*)msg.data(), msg.size(),
         out, &outLen);
    return toHex(out, outLen);
}

static std::string md5Hex(const std::string& s) {
    unsigned char out[MD5_DIGEST_LENGTH];
    MD5((const unsigned char*)s.data(), s.size(), out);
    return toHex(out, MD5_DIGEST_LENGTH);
}

static std::string randomNonce(size_t len = 24) {
    static const char chars[] = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    std::random_device rd;
    std::mt19937 gen(rd());
    std::uniform_int_distribution<> dist(0, (int)sizeof(chars) - 2);
    std::string out;
    out.reserve(len);
    for (size_t i = 0; i < len; i++) out += chars[dist(gen)];
    return out;
}

static std::string urlEncode(const std::string& s) {
    std::ostringstream oss;
    oss << std::hex << std::uppercase << std::setfill('0');
    for (unsigned char c : s) {
        if ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') ||
            (c >= '0' && c <= '9') || c == '-' || c == '_' || c == '.' || c == '~') {
            oss << c;
        } else {
            oss << '%' << std::setw(2) << (int)c;
        }
    }
    return oss.str();
}

static size_t curlWrite(void* contents, size_t size, size_t nmemb, std::string* userp) {
    userp->append((char*)contents, size * nmemb);
    return size * nmemb;
}

// Minimal JSON extract — tránh deps nlohmann::json để lib gọn
static std::string jsonExtractString(const std::string& json, const std::string& key) {
    std::string pattern = "\"" + key + "\"";
    size_t p = json.find(pattern);
    if (p == std::string::npos) return "";
    p = json.find(':', p + pattern.size());
    if (p == std::string::npos) return "";
    p = json.find('"', p);
    if (p == std::string::npos) return "";
    size_t q = json.find('"', p + 1);
    if (q == std::string::npos) return "";
    return json.substr(p + 1, q - p - 1);
}

static long jsonExtractInt(const std::string& json, const std::string& key) {
    std::string pattern = "\"" + key + "\"";
    size_t p = json.find(pattern);
    if (p == std::string::npos) return 0;
    p = json.find(':', p + pattern.size());
    if (p == std::string::npos) return 0;
    p++;
    while (p < json.size() && (json[p] == ' ' || json[p] == '\t')) p++;
    size_t q = p;
    while (q < json.size() && (json[q] == '-' || (json[q] >= '0' && json[q] <= '9'))) q++;
    if (q == p) return 0;
    return std::stol(json.substr(p, q - p));
}

static bool jsonExtractBool(const std::string& json, const std::string& key) {
    std::string pattern = "\"" + key + "\"";
    size_t p = json.find(pattern);
    if (p == std::string::npos) return false;
    p = json.find(':', p + pattern.size());
    if (p == std::string::npos) return false;
    return json.find("true", p) < json.find(',', p);
}

// =============================================
// MAIN LOGIN
// =============================================

LoginResult login(const std::string& packageId,
                  const std::string& userKey,
                  const std::string& deviceSerial) {

    LoginResult r{};
    r.success = false;

    if (packageId.empty() || userKey.empty() || deviceSerial.empty()) {
        r.reason = "EMPTY_PARAM";
        return r;
    }

    // 1. Build payload
    std::string nonce = randomNonce(24);
    long ts = std::chrono::duration_cast<std::chrono::seconds>(
                  std::chrono::system_clock::now().time_since_epoch()).count();
    std::string tsStr = std::to_string(ts);

    std::string payload = packageId + "|" + userKey + "|" + deviceSerial + "|" + nonce + "|" + tsStr;

    // 2. Derive per-key HMAC (match server deriveHmac)
    std::string perHmac = hmacSha256Hex(HMAC_SECRET, "hmac:" + userKey);
    std::string signature = hmacSha256Hex(perHmac, payload);

    // 3. Build form body
    std::string body =
        "game="      + urlEncode(packageId) +
        "&user_key=" + urlEncode(userKey) +
        "&serial="   + urlEncode(deviceSerial) +
        "&nonce="    + urlEncode(nonce) +
        "&timestamp=" + tsStr +
        "&hmac="     + signature;

    // 4. POST curl
    CURL* curl = curl_easy_init();
    if (!curl) { r.reason = "CURL_INIT"; return r; }

    std::string resp;
    long httpCode = 0;

    curl_easy_setopt(curl, CURLOPT_URL,            API_URL.c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS,     body.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION,  curlWrite);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA,      &resp);
    curl_easy_setopt(curl, CURLOPT_TIMEOUT,        10L);
    curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
    curl_easy_setopt(curl, CURLOPT_USERAGENT,      "HCLOU-Mod/1.0");
    // SSL cert pinning có thể add ở đây nếu cần

    struct curl_slist* headers = nullptr;
    headers = curl_slist_append(headers, "Content-Type: application/x-www-form-urlencoded");
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

    CURLcode res = curl_easy_perform(curl);
    curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &httpCode);

    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);

    if (res != CURLE_OK) {
        r.reason = std::string("CURL_FAIL: ") + curl_easy_strerror(res);
        return r;
    }

    // 5. Parse response
    if (!jsonExtractBool(resp, "status")) {
        r.reason = jsonExtractString(resp, "reason");
        if (r.reason.empty()) r.reason = "UNKNOWN_REJECT";
        return r;
    }

    std::string modname    = jsonExtractString(resp, "modname");
    std::string modStatus  = jsonExtractString(resp, "mod_status");
    std::string credit     = jsonExtractString(resp, "credit");
    std::string version    = jsonExtractString(resp, "version");
    std::string token      = jsonExtractString(resp, "token");
    std::string exp        = jsonExtractString(resp, "EXP");
    long rng               = jsonExtractInt   (resp, "rng");
    long maxDev            = jsonExtractInt   (resp, "device");

    if (token.empty() || modname.empty()) {
        r.reason = "MALFORMED_RESPONSE";
        return r;
    }

    // 6. Verify token md5(game-user_key-serial-per_static)
    std::string perStatic = hmacSha256Hex(STATIC_WORD, "static:" + userKey);
    std::string expectedToken = md5Hex(packageId + "-" + userKey + "-" + deviceSerial + "-" + perStatic);

    if (token != expectedToken) {
        r.reason = "TOKEN_MISMATCH_SERVER_FAKE";
        return r;
    }

    // 7. Verify rng window 30s
    long nowSec = std::chrono::duration_cast<std::chrono::seconds>(
                      std::chrono::system_clock::now().time_since_epoch()).count();
    if (rng + 30 < nowSec) {
        r.reason = "TOKEN_EXPIRED";
        return r;
    }

    // 8. SUCCESS — populate global state
    modConfig.modname    = modname;
    modConfig.modStatus  = modStatus;
    modConfig.credit     = credit;
    modConfig.version    = version;
    modConfig.expireDate = exp;
    modConfig.maxDevices = maxDev;

    r.success = true;
    r.reason  = "OK";
    return r;
}

}  // namespace hclou
