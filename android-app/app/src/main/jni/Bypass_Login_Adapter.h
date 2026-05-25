// ============================================================================
// Bypass_Login_Adapter.h
// Adapter để code Bypass_Login.h chạy IN-PROCESS (lib inject vào game).
// Cung cấp: namespace mem::read/write, struct ConfigState, pid, il2cppBase.
// ============================================================================
#pragma once

#include <cstdint>
#include <unistd.h>
#include <mutex>
#include "KittyMemory/KittyMemory.h"

extern pid_t pid;
extern uintptr_t il2cppBase;

struct ConfigState { bool bypass400 = false; };
extern ConfigState g_config;
extern std::mutex  g_configMutex;

namespace mem {
    template<typename T>
    inline bool read(pid_t, uintptr_t addr, T& out) {
        if (addr == 0) { out = T{}; return false; }
        out = *reinterpret_cast<T*>(addr);
        return true;
    }
    template<typename T>
    inline T read(pid_t, uintptr_t addr) {
        if (addr == 0) return T{};
        return *reinterpret_cast<T*>(addr);
    }
    template<typename T>
    inline bool write(pid_t, uintptr_t addr, T value) {
        if (addr == 0) return false;
        *reinterpret_cast<T*>(addr) = value;
        return true;
    }
}
