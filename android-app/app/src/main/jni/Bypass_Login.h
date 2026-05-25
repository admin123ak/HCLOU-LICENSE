static bool Bypass400ReadPtr(uintptr_t address, uintptr_t& out) {
    out = 0;
    return address != 0 && mem::read<uintptr_t>(pid, address, out);
}

static uintptr_t Bypass400ResolveTaggedMetadata(uintptr_t slotAddress) {
    uintptr_t tagged = 0;
    if (!Bypass400ReadPtr(slotAddress, tagged) || tagged == 0) {
        return 0;
    }

    if ((tagged & 1ULL) != 0) {
        uintptr_t reread = 0;
        if (!Bypass400ReadPtr(slotAddress, reread) || reread == 0) {
            return 0;
        }
        tagged = reread;
    }

    uintptr_t holderSlot = 0;
    if (!Bypass400ReadPtr(tagged + 0xB8, holderSlot) || holderSlot == 0) {
        return 0;
    }

    uintptr_t holder = 0;
    if (!Bypass400ReadPtr(holderSlot, holder)) {
        return 0;
    }
    return holder;
}

static uintptr_t Bypass400ResolveStaticFieldsHolder(uintptr_t root) {
    uintptr_t ptrA = 0;
    uintptr_t ptrB = 0;
    if (!Bypass400ReadPtr(root + 0x20, ptrA) || ptrA == 0) {
        return 0;
    }
    if (!Bypass400ReadPtr(ptrA + 0xC0, ptrB) || ptrB == 0) {
        return 0;
    }

    uintptr_t holder = Bypass400ResolveTaggedMetadata(ptrB + 0x10);
    if (holder != 0) {
        return holder;
    }

    uintptr_t unused = 0;
    (void)Bypass400ReadPtr(ptrB + 0x18, unused);
    return Bypass400ResolveTaggedMetadata(ptrB + 0x10);
}

void *Bypass400(void *) {
    constexpr uintptr_t ROOT_OFFSET     = 0xAA0D678;
    constexpr uint32_t  STATE_CODE_ON   = 0x0001007B;
    constexpr uint32_t  STATE_CODE_OFF  = 0x0002007C;
    constexpr uint32_t  STATE_FLAG_ON   = 0x00000001;
    constexpr uint32_t  STATE_FLAG_OFF  = 0x0000000E;
    bool lastEnabled = false;
    uintptr_t lastTarget = 0;

    while (true) {
        bool enabled;
        {
            std::lock_guard<std::mutex> lock(g_configMutex);
            enabled = g_config.bypass400;
        }

        if (pid > 0 && il2cppBase != 0) {
            uintptr_t root = mem::read<uintptr_t>(pid, il2cppBase + ROOT_OFFSET);
            if (root != 0) {
                uintptr_t holder = Bypass400ResolveStaticFieldsHolder(root);
                uintptr_t target = 0;
                if (holder != 0 && Bypass400ReadPtr(holder + 0x18, target) && target != 0) {
                    uint32_t code = enabled ? STATE_CODE_ON : STATE_CODE_OFF;
                    uint32_t flag = enabled ? STATE_FLAG_ON : STATE_FLAG_OFF;
                    bool wroteCode = mem::write<uint32_t>(pid, target + 0x10, code);
                    bool wroteFlag = mem::write<uint32_t>(pid, target + 0x14, flag);
                    if (target != lastTarget || enabled != lastEnabled) {
                   /*     LOGI("Bypass400 enabled=%d holder=0x%llx target=0x%llx write=%d/%d",
                             enabled ? 1 : 0,
                             (unsigned long long)holder,
                             (unsigned long long)target,
                             wroteCode ? 1 : 0,
                             wroteFlag ? 1 : 0);*/
                        lastTarget = target;
                        lastEnabled = enabled;
                    }
                }
            }
        }

        sleep(2);
    }
    return nullptr;
}



//credit: crg vietnam, kmods cheat (khánh mods)