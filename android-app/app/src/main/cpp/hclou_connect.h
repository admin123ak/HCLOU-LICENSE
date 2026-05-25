// ============================================================================
// HCLOU-LICENSE — Client header
// ============================================================================
#pragma once

#include <string>

namespace hclou {

struct LoginResult {
    bool success;
    std::string reason;   // "OK" hoặc reason từ server (KEY_NOT_FOUND, EXPIRED_KEY, ...)
};

struct ModConfig {
    std::string modname;     // hiện trên UI menu
    std::string modStatus;   // "on" / "off"
    std::string credit;      // text credit
    std::string version;     // mod version
    std::string expireDate;  // YYYY-MM-DD HH:MM:SS
    long maxDevices = 1;
};

// Global state — sau khi login OK, render UI từ field này.
extern ModConfig modConfig;

/**
 * Verify license key + bind device + lấy mod config từ server.
 *
 * @param packageId    Package id game (context.getPackageName())
 * @param userKey      License key user nhập (HCLOU-XXXXXXXXXXXX)
 * @param deviceSerial 40 hex chars sha256(android_id + Build.MODEL + Build.BRAND)
 * @return LoginResult{success, reason}
 */
LoginResult login(const std::string& packageId,
                  const std::string& userKey,
                  const std::string& deviceSerial);

}  // namespace hclou
