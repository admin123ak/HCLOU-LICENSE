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
#include "duymmo/Call_Me.h"
#include "Unity/MonoString.h"
//Target lib here
#define targetLibName OBFUSCATE("libFileA.so")

#include "Includes/Macros.h"

bool feature1, feature2, featureHookToggle, Health;
int sliderValue = 1, level = 0;
struct My_Patches {
    // let's assume we have patches for these functions for whatever game
    // like show in miniMap boolean function
    MemoryPatch GodMode, GodMode2, SliderExample;
    // etc...
} hexPatches;
struct {
    float GetFieldOfView = 0;
    float SetFieldOfView = 0;
    bool Active = false;
} WideView;
bool HackMap,Cd,Ult,Lsd,Ten,Avatar,Fps,AimSkill,aimSkill1,aimSkill2,aimSkill3,AutoTrung;
int skillSlot;
int Radius = 25000;
void *instanceBtn;
//hack nap
enum COM_PLAYERCAMP { //void hack map
 ComPlayercampMid = 0,
 ComPlayercamp1 = 1,
 ComPlayercamp2 = 2, };
void (*_SetVisible)(...); //void hack map
void SetVisible(void *instance, COM_PLAYERCAMP camp, bool bVisible, const bool forceSync = false) {
    if (instance != NULL && HackMap) {
    if (camp == ComPlayercamp1 || camp == ComPlayercamp2) {
      bVisible = true;
    }
  }
   return _SetVisible(instance, camp, bVisible, forceSync);
}
//cam xa

float (*old_GetCameraHeightRateValue)(void *instance, int *type);
float GetCameraHeightRateValue(void *instance, int *type) {
 if (instance != NULL) {
  WideView.GetFieldOfView = old_GetCameraHeightRateValue(instance, type);
  if (WideView.SetFieldOfView != 0) {
   WideView.Active = false;
   return (float) WideView.SetFieldOfView + WideView.GetFieldOfView;
  }
  return WideView.GetFieldOfView;
 }
 return old_GetCameraHeightRateValue(instance, type);
}

void (*OnCameraHeightChanged)(void *instance);
void (*old_CameraSystemUpdate)(void *instance);
void CameraSystemUpdate(void *instance) {
 if (instance != NULL && WideView.Active) {
  OnCameraHeightChanged(instance);
 }
 old_CameraSystemUpdate(instance);
}
//hiện hồi chiêu

String *CreateMonoString(const char *str)
{
    
    return Il2CppString::Create(str);
}


uintptr_t (*AsHero)(...);
void (*_SetPlayerName)(...);
void (*old_Update)(uintptr_t instance);
void AUpdate(uintptr_t instance)
{
    if (instance != NULL)
    {
        uintptr_t SkillControl = AsHero(instance);
uintptr_t HudControl = *(int *)(instance + 0x3c);
        if (HudControl > 0 && SkillControl > 0)
        {
            uintptr_t Skill1Cd = *(int *)(SkillControl + 0x28) / 1000;
            uintptr_t Skill2Cd = *(int *)(SkillControl + 0x40) / 1000;
            uintptr_t Skill3Cd = *(int *)(SkillControl + 0x58) / 1000;
            uintptr_t Skill4Cd = *(int *)(SkillControl + 0x88) / 1000;
            string openSk = "[";
            string closeSk = "] ";
            string sk1 = to_string(Skill1Cd);
            string sk2 = to_string(Skill2Cd);
            string sk3 = to_string(Skill3Cd);
            string sk4 = to_string(Skill4Cd);
            string ShowSkill = openSk + sk1 + closeSk + openSk + sk2 + closeSk + openSk + sk3 + closeSk;
            string ShowSkill2 = openSk + sk4 + closeSk;
            const char *str1 = ShowSkill.c_str();
            const char *str2 = ShowSkill2.c_str();
            if (Cd)
            {
                String *playerName = CreateMonoString(str1);
                String *prefixName = CreateMonoString(str2);
                _SetPlayerName(HudControl, playerName, prefixName, true,playerName);
            } else {
                String *playerName = CreateMonoString("MODZ");
                String *prefixName = CreateMonoString("QSG ");
                _SetPlayerName(HudControl, playerName, prefixName, true,playerName);
            }
        }
    }

    return old_Update(instance);
}

//show ulti
bool ShowInfHero;
void (*_ShowSkillStateInfo)(void *instance, bool bShow);
void ShowSkillStateInfo(void *instance, bool bShow) {
    if (instance != NULL && Ult) {
      bShow = true;
    }
    _ShowSkillStateInfo(instance, bShow);
}
bool ShowIcon;
void (*_ShowHeroInfo)(void *instance,int actor, bool bShow);
void ShowHeroInfo(void *instance, int actor,bool bShow) {
    if (instance != NULL && Ult) {
      bShow = true;
    } 
    _ShowHeroInfo(instance,actor, bShow);
}
bool ShowHP;
void (*_ShowHeroHpInfo)(void *instance, bool bShow);
void ShowHeroHpInfo(void *instance, bool bShow) {
    if (instance != NULL && Ult) {
      bShow = true;
    } 
    _ShowHeroHpInfo(instance, bShow);
}
// lsd
bool (*_IsHostProfile)(void *instance);
bool IsHostProfile(void *instance) {
    if (instance != NULL && Lsd) 
    {
        return true;
    }
    return _IsHostProfile(instance);

}
//avatar

int (*_checkTeamLaderGradeMax)(void *instance);
int checkTeamLaderGradeMax(void *instance){
    if (instance != NULL && Avatar) { 
        return 0;
    }
   return _checkTeamLaderGradeMax(instance); 
}
//tên cấm chọn
void *(*_InitTeamHeroList) (...);
void *InitTeamHeroList(void* instance, void *listScript, int camp, bool isLeftList, const bool isMidPos = false) {
    if (instance != NULL && Ten ) { 
        isLeftList = true;
    }
    return _InitTeamHeroList(instance, listScript, camp, isLeftList, isMidPos);
    }
 //fake name sảnh
 void (*_SettlementHelper_SetPlayerName)(...);
void SettlementHelper_SetPlayerName(void *instance, String *playerName, bool isHostPlayer){
    
    if(instance){
        
        String *fake = Il2CppString::Create("<b><color=#66FF66>QSG</a><color=#ffef2c> MODZ</a></b>");
    
        _SettlementHelper_SetPlayerName(instance, fake, isHostPlayer);
        
    }
    
}
//fps
const bool (*_get_Supported60FPSMode)(void *instance);
const bool get_Supported60FPSMode(void *instance) {
   if (instance != NULL && Fps) { 
     return true;
    } 
}

const bool (*_get_SupportedBoth60FPS_CameraHeight)(void *instance);
const bool get_SupportedBoth60FPS_CameraHeight(void *instance) {
    if (instance != NULL && Fps) {
        return true;
    } 
}

const bool (*_IsIPadDevice)(void *instance);
const bool IsIPadDevice(void *instance) {
    if (instance != NULL && Fps) {
       return true;
    }
}

//aim
bool (*_IsSmartUse)(void *instance);
bool (*_get_IsUseCameraMoveWithIndicator)(void *instance);

bool IsSmartUse(void *instance){
    
    bool aim = false;
    
    if(skillSlot == 1 && aimSkill1){
        aim = true;
    }
    
    if(skillSlot == 2 && aimSkill2){
        aim = true;
    }
    
    if(skillSlot == 3 && aimSkill3){
        aim = true;
    }
    
    if(AutoTrung && aim){
        return true;
    }
    
    return _IsSmartUse(instance);
}


bool get_IsUseCameraMoveWithIndicator(void *instance){
    
    bool aim = false;
    
    if(skillSlot == 1 && aimSkill1){
        aim = true;
    }
    
    if(skillSlot == 2 && aimSkill2){
        aim = true;
    }
    
    if(skillSlot == 3 && aimSkill3){
        aim = true;
    }
    
    
    if(AutoTrung && aim){
        return true;
    }
    
    return _get_IsUseCameraMoveWithIndicator(instance);
}


bool (*_IsUseSkillJoystick)(...);
bool IsUseSkillJoystick(void *instance, int slot){
    skillSlot = slot;
    return _IsUseSkillJoystick(instance, slot);
}
void (*old_IsDistanceLowerEqualAsAttacker)(void *instance, int targetActor, int radius);
void IsDistanceLowerEqualAsAttacker(void *instance, int targetActor, int radius) {
    if (instance != NULL && AimSkill) {
        radius = Radius;
    }
    old_IsDistanceLowerEqualAsAttacker(instance, targetActor, radius);
}
// we will run our hacks in a new thread so our while loop doesn't block process main thread
void *hack_thread(void *) {
    sleep(5);
    
    ProcMap il2cppMap;
    do {
        il2cppMap = KittyMemory::getLibraryMap("libil2cpp.so");
        sleep(1);
    } while (!il2cppMap.isValid());
    
    sleep(5);
    
    IL2Cpp::Il2CppAttach(); 
    
    
 IL2Cpp::Il2CppAttach();    
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project.Plugins_d.dll"), OBFUSCATE("NucleusDrive.Logic"), OBFUSCATE("LVActorLinker") , OBFUSCATE("SetVisible"), 3), (void *) SetVisible , (void **) &_SetVisible);
//get offset hack map
OnCameraHeightChanged = (void(*)(void *))IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE(""), OBFUSCATE("CameraSystem") , OBFUSCATE("OnCameraHeightChanged"), 0);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE(""), OBFUSCATE("CameraSystem") , OBFUSCATE("Update"), 0), (void *) CameraSystemUpdate, (void **) &old_CameraSystemUpdate);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE(""), OBFUSCATE("CameraSystem") , OBFUSCATE("GetCameraHeightRateValue"), 1), (void *) GetCameraHeightRateValue, (void **) &old_GetCameraHeightRateValue);
//get offset cam xa
Tools::Hook((void *) (uint64_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Kyrios.Actor"), OBFUSCATE("ActorLinker") , OBFUSCATE("Update"), 0), (void *) AUpdate, (void **) &old_Update);
AsHero = (uintptr_t(*)(...)) IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Kyrios.Actor"), OBFUSCATE("ActorLinker") , OBFUSCATE("AsHero"), 0);
_SetPlayerName = (void (*)(...)) ((uint64_t)IL2Cpp::Il2CppGetMethodOffset("Project_d.dll","Assets.Scripts.GameLogic","HudComponent3D","SetPlayerName",4));

Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.GameSystem"), OBFUSCATE("SettlementHelper") , OBFUSCATE("SetPlayerName"), 2), (void *) SettlementHelper_SetPlayerName, (void **) &_SettlementHelper_SetPlayerName);
    
// hiện hồi chiêu
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.GameSystem"), OBFUSCATE("HeroInfoPanel") , OBFUSCATE("ShowHeroInfo"), 2), (void *) ShowHeroInfo, (void **) &_ShowHeroInfo);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE(""), OBFUSCATE("MiniMapHeroInfo") , OBFUSCATE("ShowSkillStateInfo"), 1), (void *) ShowSkillStateInfo, (void **) &_ShowSkillStateInfo);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE(""), OBFUSCATE("MiniMapHeroInfo") , OBFUSCATE("ShowHeroHpInfo"), 1), (void *) ShowHeroHpInfo, (void **) &_ShowHeroHpInfo);      
// unti + hp + icon
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.GameSystem"), OBFUSCATE("CPlayerProfile") , OBFUSCATE("get_IsHostProfile"), 0), (void *) IsHostProfile, (void **) &_IsHostProfile);
//lsd
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.Framework"), OBFUSCATE("GameSettings") , OBFUSCATE("get_Supported60FPSMode"), 0), (void *) get_Supported60FPSMode , (void **) &_get_Supported60FPSMode);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.Framework"), OBFUSCATE("GameSettings") , OBFUSCATE("get_Supported90FPSMode"), 0), (void *) get_Supported60FPSMode , (void **) &_get_Supported60FPSMode);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.Framework"), OBFUSCATE("GameSettings") , OBFUSCATE("get_Supported120FPSMode"), 0), (void *) get_Supported60FPSMode , (void **) &_get_Supported60FPSMode);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.Framework"), OBFUSCATE("GameSettings") , OBFUSCATE("get_SupportedBoth60FPS_CameraHeight"), 0), (void *) get_SupportedBoth60FPS_CameraHeight , (void **) &_get_SupportedBoth60FPS_CameraHeight);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.Framework"), OBFUSCATE("GameSettings") , OBFUSCATE("IsIPadDevice"), 0), (void *) IsIPadDevice , (void **) &_IsIPadDevice);
//fps + cam 3
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.GameSystem"), OBFUSCATE("HeroSelectBanPickWindow") , OBFUSCATE("InitTeamHeroList"), 4), (void *) InitTeamHeroList , (void **) &_InitTeamHeroList);
//ten cam chon
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Assets.Scripts.GameSystem"), OBFUSCATE("CMatchingSystem") , OBFUSCATE("checkTeamLaderGradeMax"), 1), (void *) checkTeamLaderGradeMax, (void **) &_checkTeamLaderGradeMax);
//avatar
//fake name sảnh 
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset("Project_d.dll","Assets.Scripts.GameLogic","SkillComponent","IsUseSkillJoystick",1), (void *) IsUseSkillJoystick, (void **) &_IsUseSkillJoystick);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset("Project_d.dll","Assets.Scripts.GameLogic","GameInput","IsSmartUse",0), (void *) IsSmartUse, (void **) &_IsSmartUse);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset("Project_d.dll","Assets.Scripts.GameLogic","Skill","get_IsUseCameraMoveWithIndicator",0), (void *) get_IsUseCameraMoveWithIndicator, (void **) &_get_IsUseCameraMoveWithIndicator);
Tools::Hook((void *) (uintptr_t)IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Project_d.dll"), OBFUSCATE("Kyrios.Actor"), OBFUSCATE("ObjLinkerWrapper") , OBFUSCATE("IsDistanceLowerEqualAsAttacker"), 2), (void *) IsDistanceLowerEqualAsAttacker, (void **) &old_IsDistanceLowerEqualAsAttacker);
//aim skill    
 
 
 
   
    //  Tools::Hook(IL2Cpp::Il2CppGetMethodOffset(OBFUSCATE("Assembly-CSharp.dll"), OBFUSCATE(""), OBFUSCATE("Warrior") , OBFUSCATE("Update"),0),(void *) &_Updateok, (void **) &_OldUpdateok);
return NULL;
}


// Do not change or translate the first text unless you know what you are doing
// Assigning feature numbers is optional. Without it, it will automatically count for you, starting from 0
// Assigned feature numbers can be like any numbers 1,3,200,10... instead in order 0,1,2,3,4,5...
// ButtonLink, Category, RichTextView and RichWebView is not counted. They can't have feature number assigned
// Toggle, ButtonOnOff and Checkbox can be switched on by default, if you add True_. Example: CheckBox_True_The Check Box
// To learn HTML, go to this page: https://www.w3schools.com/

jobjectArray GetFeatureList(JNIEnv *env, jobject context) {
    jobjectArray ret;

    const char *features[] = {
            OBFUSCATE("Category_Kênh Up Apk Mod - Key Chính Thức !"), //Not counted
            OBFUSCATE("ButtonLink_KÊNH QSG MODZ_https://t.me/QMODZzZ "),
            OBFUSCATE("ButtonLink_KEY FREE_https://t.me/QMODZzZ "),
            OBFUSCATE("ButtonLink_MUA KEY THÁNG_t.me/QMODZz "),
            OBFUSCATE("Category_Menu Bypass"),
            OBFUSCATE("Toggle_Bypass Anti-Cheat ( Sảnh )"),
            OBFUSCATE("Toggle_Anti Report ( Beta )"),
            OBFUSCATE("Category_Main Menu"),
            OBFUSCATE("1_Toggle_Hack map"),
            OBFUSCATE("2_SeekBar_Cam xa_0_20"),
            OBFUSCATE("3_Toggle_Hiện hồi chiêu"),
            OBFUSCATE("4_Toggle_Hiện Ulti + hp"),
            OBFUSCATE("5_Toggle_Hiện Lịch sử đấu"),
            OBFUSCATE("6_Toggle_Hiện Avatar"),
            OBFUSCATE("7_Toggle_Hiện tên cấm chọn"),
            OBFUSCATE("8_Toggle_Unlock fps"),
            OBFUSCATE("Category_MENU AIM SKILL"), //
            OBFUSCATE("9_Toggle_Kích hoạt aim"),
            OBFUSCATE("10_Toggle_Aim skill 1"),
            OBFUSCATE("11_Toggle_Aim skill 2"),
            OBFUSCATE("12_Toggle_Aim skil 3"),
            OBFUSCATE("13_Toggle_Ẩn tia"),
            };

    //Now you dont have to manually update the number everytime;
    int Total_Feature = (sizeof features / sizeof features[0]);
    ret = (jobjectArray)
            env->NewObjectArray(Total_Feature, env->FindClass(OBFUSCATE("java/lang/String")),
                                env->NewStringUTF(""));

    for (int i = 0; i < Total_Feature; i++)
        env->SetObjectArrayElement(ret, i, env->NewStringUTF(features[i]));

    return (ret);
}

void Changes(JNIEnv *env, jclass clazz, jobject obj,
                                        jint featNum, jstring featName, jint value,
                                        jboolean boolean, jstring str) {

    LOGD(OBFUSCATE("Feature name: %d - %s | Value: = %d | Bool: = %d | Text: = %s"), featNum,
         env->GetStringUTFChars(featName, 0), value,
         boolean, str != NULL ? env->GetStringUTFChars(str, 0) : "");

    //BE CAREFUL NOT TO ACCIDENTLY REMOVE break;

    switch (featNum) {
           case 1:
            HackMap = boolean;
            break;
        case 2:
            WideView.SetFieldOfView = (float) value * 0.1362f;
            WideView.Active = true;
            break;  
        case 3:
           Cd = boolean;
            break;  
        case 4:
          Ult = boolean;
          break;
        case 5:
          Lsd = boolean;
          break;
        case 6:
          Avatar = boolean;
          break;
        case 7:
           Ten = boolean;
           break;
        case 8:
            Fps = boolean;
            break;
            
        case 9:
            AimSkill = boolean;
            break;
        case 10:
             aimSkill1 = boolean;
             break;
        case 11:
             aimSkill2 = boolean;
            break;
        case 12:
             aimSkill3 = boolean;
              break;
         case 13:
              AutoTrung = boolean;
              break;
    }
}

__attribute__((constructor))
void lib_main() {
    // Create a new thread so it does not block the main thread, means the game would not freeze
    pthread_t ptid;
    pthread_create(&ptid, NULL, hack_thread, NULL);
}

#include "StrEnc.h"
#include <curl/curl.h>
#include "json.hpp"
#include "LicenseTools.h"
#include <openssl/evp.h>
#include <openssl/pem.h>
#include <openssl/rsa.h>
#include <openssl/err.h>
#include <openssl/md5.h>
#include <openssl/hmac.h>
#include <openssl/sha.h>
#include <mutex>
#include "Bypass_Login_Adapter.h"
#include "Bypass_Login.h"

// HCLOU-LICENSE master secrets (đồng bộ với config.local.php server)
static const std::string HCLOU_HMAC_SECRET = "3601b133af42e867e1cffd82993561d37988e9917de27a4f22bc1cc5c803c83c";
static const std::string HCLOU_STATIC_WORD = "b28f2faf89c3a6e21e9f0595f48f60b4";

// Globals cho Bypass_Login.h
pid_t       pid         = 0;
uintptr_t   il2cppBase  = 0;
ConfigState g_config;
std::mutex  g_configMutex;
using json = nlohmann::ordered_json;
using namespace std;

bool bValid = false;
std::string g_Auth, g_Token;
std::string g_ModName = "MOD MENU";
std::string g_ModStatus = "UNKNOWN";
std::string g_Credit = "";
std::string RandomString(const int len);
std::string CalcMD5(std::string s);
std::string CalcSHA256(std::string s);
std::string CalcHMAC(const std::string& key, const std::string& msg) {
    unsigned char out[EVP_MAX_MD_SIZE];
    unsigned int outLen = 0;
    HMAC(EVP_sha256(), key.data(), (int)key.size(),
         (const unsigned char*)msg.data(), msg.size(), out, &outLen);
    std::string result; char tmp[4];
    for (unsigned int i = 0; i < outLen; i++) { sprintf(tmp, "%02x", out[i]); result += tmp; }
    return result;
}
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
    for (unsigned char i : hash) {
        sprintf(tmp, "%02x", i);
        result += tmp;
    }
    return result;
}

std::string CalcSHA256(std::string s) {
    std::string result;

    unsigned char hash[SHA256_DIGEST_LENGTH];
    char tmp[4];

    SHA256_CTX sha256;
    SHA256_Init(&sha256);
    SHA256_Update(&sha256, s.c_str(), s.length());
    SHA256_Final(hash, &sha256);
    for (unsigned char i : hash) {
        sprintf(tmp, "%02x", i);
        result += tmp;
    }
    return result;
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

    CURL *curl;
    CURLcode res;
    curl = curl_easy_init();
    if (curl) {
        curl_easy_setopt(curl, CURLOPT_CUSTOMREQUEST, /*POST*/ StrEnc(",IL=", "\x7C\x06\x1F\x69", 4).c_str());
        curl_easy_setopt(curl, CURLOPT_URL, "https://teamcrack.linkpc.net/api/connect.php");
        curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);
        curl_easy_setopt(curl, CURLOPT_DEFAULT_PROTOCOL, /*https*/ StrEnc("!mLBO", "\x49\x19\x38\x32\x3C", 5).c_str());
        struct curl_slist *headers = NULL;
        headers = curl_slist_append(headers, /*Content-Type: application/x-www-form-urlencoded*/ StrEnc("@;Ls\\(KP4Qrop`b#d3094/r1cf<c<=H)AiiBG6i|Ta66s2[", "\x03\x54\x22\x07\x39\x46\x3F\x7D\x60\x28\x02\x0A\x4A\x40\x03\x53\x14\x5F\x59\x5A\x55\x5B\x1B\x5E\x0D\x49\x44\x4E\x4B\x4A\x3F\x04\x27\x06\x1B\x2F\x6A\x43\x1B\x10\x31\x0F\x55\x59\x17\x57\x3F", 47).c_str());
        curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

        // Get package name dynamically for multi-game support
        const char* packageName = GetPackageName(env, mContext);

        // HCLOU-LICENSE: HMAC sign + nonce + timestamp
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

        res = curl_easy_perform(curl);
        if (res == CURLE_OK) {
            try {
                json result = json::parse(chunk.memory);
                if (result[/*status*/ StrEnc("(>_LBm", "\x5B\x4A\x3E\x38\x37\x1E", 6).c_str()] == true) {
                    std::string token = result[/*data*/ StrEnc("fAVA", "\x02\x20\x22\x20", 4).c_str()][/*token*/ StrEnc("{>3Lr", "\x0F\x51\x58\x29\x1C", 5).c_str()].get<std::string>();
                    time_t rng = result[/*data*/ StrEnc("fAVA", "\x02\x20\x22\x20", 4).c_str()][/*rng*/ StrEnc("+n,", "\x59\x00\x4B", 3).c_str()].get<time_t>();

                    if (rng + 30 > time(0)) {
                        // HCLOU-LICENSE: per_static derive
                        std::string perStatic = CalcHMAC(HCLOU_STATIC_WORD, "static:" + std::string(userKey));
                        std::string auth = packageName;
                        auth += "-";
                        auth += userKey;
                        auth += "-";
                        auth += UUID;
                        auth += "-";
                        auth += perStatic;
                        std::string outputAuth = CalcMD5(auth);

                        g_Token = token;
                        g_Auth = outputAuth;

                        bValid = g_Token == g_Auth;

                        if (bValid) {
                            // Parse mod info from server
                            try {
                                auto dataObj = result[/*data*/ StrEnc("fAVA", "\x02\x20\x22\x20", 4).c_str()];
                                if (dataObj.contains("modname")) {
                                    g_ModName = dataObj["modname"].get<std::string>();
                                }
                                if (dataObj.contains("mod_status")) {
                                    g_ModStatus = dataObj["mod_status"].get<std::string>();
                                }
                                if (dataObj.contains("credit")) {
                                    g_Credit = dataObj["credit"].get<std::string>();
                                }
                            } catch (...) {
                                // Ignore parse errors for optional fields
                            }
                        }
                    }
                } else {
                    errMsg = result[/*reason*/ StrEnc("LW(3(c", "\x3E\x32\x49\x40\x47\x0D", 6).c_str()].get<std::string>();
                }
            } catch (json::exception &e) {
                errMsg = "{";
                errMsg += e.what();
                errMsg += "}\n{";
                errMsg += chunk.memory;
                errMsg += "}";
            }
        } else {
            errMsg = curl_easy_strerror(res);
        }
    }
    curl_easy_cleanup(curl);
    return bValid ? env->NewStringUTF(/*OK*/ StrEnc("8q", "\x77\x3A", 2).c_str()) : env->NewStringUTF(errMsg.c_str());
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
    if (!clazz)
        return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0)
        return JNI_ERR;
    return JNI_OK;
}

int RegisterPreferences(JNIEnv *env) {
    JNINativeMethod methods[] = {
            {OBFUSCATE("Changes"), OBFUSCATE("(Landroid/content/Context;ILjava/lang/String;IZLjava/lang/String;)V"), reinterpret_cast<void *>(Changes)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Preferences"));
    if (!clazz)
        return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0)
        return JNI_ERR;
    return JNI_OK;
}

int RegisterMain(JNIEnv *env) {
    JNINativeMethod methods[] = {
            {OBFUSCATE("CheckOverlayPermission"), OBFUSCATE("(Landroid/content/Context;)V"), reinterpret_cast<void *>(CheckOverlayPermission)},
    };
    jclass clazz = env->FindClass(OBFUSCATE("com/android/support/Main"));
    if (!clazz)
        return JNI_ERR;
    if (env->RegisterNatives(clazz, methods, sizeof(methods) / sizeof(methods[0])) != 0)
        return JNI_ERR;

    return JNI_OK;
}

extern "C"
JNIEXPORT jint JNICALL
JNI_OnLoad(JavaVM *vm, void *reserved) {
    JNIEnv *env;
    vm->GetEnv((void **) &env, JNI_VERSION_1_6);
    if (RegisterMenu(env) != 0)
        return JNI_ERR;
    if (RegisterPreferences(env) != 0)
        return JNI_ERR;
    if (RegisterMain(env) != 0)
        return JNI_ERR;
    return JNI_VERSION_1_6;
}
