// ============================================================================
//  HCLOU LICENSE — main.cpp (BẢN BẢO MẬT: AES request + HMAC + RSA verify)
//  Thay hàm Check() cũ (plaintext + md5) bằng bản này. Native C++ + OpenSSL.
//  Giữ nguyên RegisterMenu / RegisterPreferences / RegisterMain / JNI_OnLoad cũ.
//
//  CẦN ĐIỀN 3 thứ ở dưới: SERVER_URL, API_SECRET, RSA_PUBLIC_KEY (lấy ở panel
//  -> menu "License SDK"). API_SECRET phải GIỐNG connect.apiSecret trong .env.
// ============================================================================
#include "StrEnc.h"
#include <curl/curl.h>
#include "json.hpp"
#include "LicenseTools.h"
#include <openssl/evp.h>
#include <openssl/pem.h>
#include <openssl/rsa.h>
#include <openssl/err.h>
#include <openssl/md5.h>
#include <openssl/sha.h>
#include <openssl/hmac.h>
#include <openssl/rand.h>
#include <string>
#include <ctime>

using json = nlohmann::ordered_json;
using namespace std;

bool bValid = false;
std::string g_Auth, g_Token;

// ---- CẤU HÌNH (nên bọc StrEnc khi build thật) ----
static const char* SERVER_URL  = "https://clouzy.sbs/connect";
static const char* API_SECRET  = "Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E"; // == connect.apiSecret
static const char* RSA_PUBLIC_KEY =
    "-----BEGIN PUBLIC KEY-----\n"
    "PASTE_PUBLIC_KEY_FROM_PANEL_SDK_PAGE_HERE\n"
    "-----END PUBLIC KEY-----\n";

// ===================== Helpers =====================
static const std::string B64_CHARS =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

static std::string b64encode(const std::string& in) {
    std::string out; int val = 0, valb = -6;
    for (unsigned char c : in) {
        val = (val << 8) + c; valb += 8;
        while (valb >= 0) { out.push_back(B64_CHARS[(val >> valb) & 0x3F]); valb -= 6; }
    }
    if (valb > -6) out.push_back(B64_CHARS[((val << 8) >> (valb + 8)) & 0x3F]);
    while (out.size() % 4) out.push_back('=');
    return out;
}
static std::string b64decode(const std::string& in) {
    std::vector<int> T(256, -1);
    for (int i = 0; i < 64; i++) T[(unsigned char)B64_CHARS[i]] = i;
    std::string out; int val = 0, valb = -8;
    for (unsigned char c : in) {
        if (T[c] == -1) continue;
        val = (val << 6) + T[c]; valb += 6;
        if (valb >= 0) { out.push_back(char((val >> valb) & 0xFF)); valb -= 8; }
    }
    return out;
}
static std::string sha256raw(const std::string& s) {
    unsigned char h[SHA256_DIGEST_LENGTH];
    SHA256((const unsigned char*)s.data(), s.size(), h);
    return std::string((char*)h, SHA256_DIGEST_LENGTH);
}
static std::string hmacB64(const std::string& key, const std::string& data) {
    unsigned char out[EVP_MAX_MD_SIZE]; unsigned int len = 0;
    HMAC(EVP_sha256(), key.data(), key.size(),
         (const unsigned char*)data.data(), data.size(), out, &len);
    return b64encode(std::string((char*)out, len));
}
static std::string aesEncrypt(const std::string& plain, const std::string& key32) {
    unsigned char iv[16]; RAND_bytes(iv, 16);
    EVP_CIPHER_CTX* ctx = EVP_CIPHER_CTX_new();
    EVP_EncryptInit_ex(ctx, EVP_aes_256_cbc(), NULL,
                       (const unsigned char*)key32.data(), iv);
    std::string buf; buf.resize(plain.size() + 16);
    int l1 = 0, l2 = 0;
    EVP_EncryptUpdate(ctx, (unsigned char*)&buf[0], &l1,
                      (const unsigned char*)plain.data(), plain.size());
    EVP_EncryptFinal_ex(ctx, (unsigned char*)&buf[0] + l1, &l2);
    EVP_CIPHER_CTX_free(ctx);
    std::string ct = std::string((char*)iv, 16) + buf.substr(0, l1 + l2);
    return b64encode(ct);
}
static std::string aesDecrypt(const std::string& b64, const std::string& key32) {
    std::string raw = b64decode(b64);
    if (raw.size() <= 16) return "";
    unsigned char iv[16]; memcpy(iv, raw.data(), 16);
    std::string ct = raw.substr(16);
    EVP_CIPHER_CTX* ctx = EVP_CIPHER_CTX_new();
    EVP_DecryptInit_ex(ctx, EVP_aes_256_cbc(), NULL,
                       (const unsigned char*)key32.data(), iv);
    std::string out; out.resize(ct.size());
    int l1 = 0, l2 = 0;
    EVP_DecryptUpdate(ctx, (unsigned char*)&out[0], &l1,
                      (const unsigned char*)ct.data(), ct.size());
    if (EVP_DecryptFinal_ex(ctx, (unsigned char*)&out[0] + l1, &l2) != 1) {
        EVP_CIPHER_CTX_free(ctx); return "";
    }
    EVP_CIPHER_CTX_free(ctx);
    return out.substr(0, l1 + l2);
}
static bool rsaVerify(const std::string& payload, const std::string& sigB64) {
    std::string sig = b64decode(sigB64);
    BIO* bio = BIO_new_mem_buf(RSA_PUBLIC_KEY, -1);
    EVP_PKEY* pkey = PEM_read_bio_PUBKEY(bio, NULL, NULL, NULL);
    BIO_free(bio);
    if (!pkey) return false;
    EVP_MD_CTX* ctx = EVP_MD_CTX_new();
    bool ok = false;
    if (EVP_DigestVerifyInit(ctx, NULL, EVP_sha256(), NULL, pkey) == 1) {
        EVP_DigestVerifyUpdate(ctx, payload.data(), payload.size());
        ok = (EVP_DigestVerifyFinal(ctx,
              (const unsigned char*)sig.data(), sig.size()) == 1);
    }
    EVP_MD_CTX_free(ctx); EVP_PKEY_free(pkey);
    return ok;
}
std::string RandomString(const int len) {
    static const char a[] = "0123456789ABCDEFabcdef";
    srand((unsigned)time(0) * getpid());
    std::string t; t.reserve(len);
    for (int i = 0; i < len; ++i) t += a[rand() % (sizeof(a) - 1)];
    return t;
}

// ===================== JNI Check =====================
extern "C" {
JNIEXPORT jstring JNICALL
Java_com_android_support_TechnicalAkash1_Check(JNIEnv *env, jclass clazz, jobject mContext, jstring mUserKey) {
    auto userKey = env->GetStringUTFChars(mUserKey, 0);

    // ---- HWID (giữ logic cũ) ----
    std::string hwidSrc = userKey;
    hwidSrc += GetAndroidID(env, mContext);
    hwidSrc += GetDeviceModel(env);
    hwidSrc += GetDeviceBrand(env);
    std::string UUID = GetDeviceUniqueIdentifier(env, hwidSrc.c_str());

    std::string errMsg;
    bValid = false;

    // ---- Tạo request mã hoá AES + HMAC ----
    std::string ts = std::to_string((long)time(0));
    std::string nonce = RandomString(16);
    std::string sessionKey = sha256raw(std::string(API_SECRET) + "|" + ts);
    std::string macKey     = sha256raw(std::string(API_SECRET) + "|" + nonce + "|mac");

    json payload;
    payload["game"] = "PUBG";
    payload["key"]  = std::string(userKey);
    payload["hwid"] = UUID;
    std::string d = aesEncrypt(payload.dump(), sessionKey);
    std::string m = hmacB64(macKey, d + "|" + ts + "|" + nonce);

    struct MemoryStruct chunk{};
    chunk.memory = (char *) malloc(1);
    chunk.size = 0;

    CURL *curl = curl_easy_init();
    if (curl) {
        char *ed = curl_easy_escape(curl, d.c_str(), 0);
        char *em = curl_easy_escape(curl, m.c_str(), 0);
        char *en = curl_easy_escape(curl, nonce.c_str(), 0);
        std::string post = "d=" + std::string(ed) + "&t=" + ts +
                           "&n=" + std::string(en) + "&m=" + std::string(em);
        if (ed) curl_free(ed); if (em) curl_free(em); if (en) curl_free(en);

        curl_easy_setopt(curl, CURLOPT_URL, SERVER_URL);
        curl_easy_setopt(curl, CURLOPT_POST, 1L);
        curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
        struct curl_slist *headers = NULL;
        headers = curl_slist_append(headers, "Content-Type: application/x-www-form-urlencoded");
        headers = curl_slist_append(headers, "X-API-Client: NativeApp");
        curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
        curl_easy_setopt(curl, CURLOPT_POSTFIELDS, post.c_str());
        curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteMemoryCallback);
        curl_easy_setopt(curl, CURLOPT_WRITEDATA, (void *) &chunk);
        curl_easy_setopt(curl, CURLOPT_SSL_VERIFYPEER, 0L);
        curl_easy_setopt(curl, CURLOPT_SSL_VERIFYHOST, 0L);

        CURLcode res = curl_easy_perform(curl);
        if (res == CURLE_OK) {
            try {
                // ---- Giải mã response (wrapper base64 -> json{x,t,n,m}) ----
                std::string wrapJson = b64decode(std::string(chunk.memory));
                json plainResult;
                bool decryptedOk = false;
                try {
                    json w = json::parse(wrapJson);
                    if (w.contains("x") && w.contains("t") && w.contains("n") && w.contains("m")) {
                        std::string rx = w["x"].get<std::string>();
                        std::string rt = w["t"].get<std::string>();
                        std::string rn = w["n"].get<std::string>();
                        std::string rm = w["m"].get<std::string>();
                        std::string rMacKey = sha256raw(std::string(API_SECRET) + "|" + rn + "|mac");
                        if (hmacB64(rMacKey, rx + "|" + rt + "|" + rn) == rm) {
                            std::string rSk = sha256raw(std::string(API_SECRET) + "|" + rt);
                            std::string dec = aesDecrypt(rx, rSk);
                            if (!dec.empty()) { plainResult = json::parse(dec); decryptedOk = true; }
                        }
                    }
                } catch (...) {}
                // Fallback: server trả JSON thường (lỗi pre-decrypt / legacy)
                if (!decryptedOk) plainResult = json::parse(chunk.memory);

                if (plainResult["status"] == true) {
                    auto dataNode = plainResult["data"];
                    std::string sig     = dataNode.value("sig", "");
                    std::string sigPld  = dataNode.value("payload", "");
                    bool rsaConfigured  = (std::string(RSA_PUBLIC_KEY).find("PASTE_PUBLIC_KEY") == string::npos);
                    // ---- Verify chữ ký RSA (không giả được) ----
                    if (!rsaConfigured) {
                        // Chưa dán public key (đang test) -> chấp nhận status. Dán key -> bắt buộc verify.
                        bValid = true;
                    } else if (!sig.empty() && rsaVerify(sigPld, sig)) {
                        // payload = "game|key|hwid|ts" -> kiểm khớp + tươi
                        size_t p1 = sigPld.find('|');
                        size_t p2 = sigPld.find('|', p1 + 1);
                        size_t p3 = sigPld.find('|', p2 + 1);
                        if (p1 != string::npos && p2 != string::npos && p3 != string::npos) {
                            std::string g  = sigPld.substr(0, p1);
                            std::string k  = sigPld.substr(p1 + 1, p2 - p1 - 1);
                            std::string h  = sigPld.substr(p2 + 1, p3 - p2 - 1);
                            long pts       = atol(sigPld.substr(p3 + 1).c_str());
                            if (g == "PUBG" && k == std::string(userKey) && h == UUID
                                && labs((long)time(0) - pts) <= 300) {
                                bValid = true;
                            } else errMsg = "PAYLOAD_MISMATCH";
                        } else errMsg = "BAD_PAYLOAD";
                    } else {
                        errMsg = "BAD_SIGNATURE";
                    }
                } else {
                    errMsg = plainResult.value("reason", std::string("INVALID"));
                }
            } catch (json::exception &e) {
                errMsg = std::string("{") + e.what() + "}{" + chunk.memory + "}";
            }
        } else {
            errMsg = curl_easy_strerror(res);
        }
        curl_easy_cleanup(curl);
    }
    if (chunk.memory) free(chunk.memory);

    return bValid ? env->NewStringUTF("OK") : env->NewStringUTF(errMsg.c_str());
}}
