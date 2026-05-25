// ============================================================================
// Bypass_Login_Adapter.h
// Adapter để code Bypass_Login.h (external process style) chạy IN-PROCESS
// trong lib inject vào game (LGL template).
// ============================================================================
#pragma once

#include <cstdint>
#include <unistd.h>
#include <mutex>
#include "KittyMemory/KittyMemory.h"

// ---------------------------------------------------------------------------
// Globals mà Bypass_Login.h reference
// ---------------------------------------------------------------------------
extern pid_t pid;
extern uintptr_t il2cppBase;

struct ConfigState {
    bool bypass400 = false;
};
extern ConfigState g_config;
extern std::mutex  g_configMutex;

// ---------------------------------------------------------------------------
// mem:: namespace adapter (in-process direct read/write)
// Lib chạy trong game process → addr là pointer hợp lệ trong VM space.
// ---------------------------------------------------------------------------
namespace mem {
    template<typename T>
    inline bool read(pid_t /*pid*/, uintptr_t addr, T& out) {
        if (addr == 0) return false;
        out = *reinterpret_cast<T*>(addr);
        return true;
    }

    template<typename T>
    inline T read(pid_t /*pid*/, uintptr_t addr) {
        if (addr == 0) return T{};
        return *reinterpret_cast<T*>(addr);
    }

    template<typename T>
    inline bool write(pid_t /*pid*/, uintptr_t addr, T value) {
        if (addr == 0) return false;
        *reinterpret_cast<T*>(addr) = value;
        return true;
    }
}
