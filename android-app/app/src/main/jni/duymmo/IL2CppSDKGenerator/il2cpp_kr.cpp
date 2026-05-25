#include "il2cpp_kr.h"
#include "fake_dlfcn.h"

#include <android/log.h>

#define LOG_TAG "Chitoge-Il2Cpp"
#define LOGI(...) __android_log_print(ANDROID_LOG_INFO,LOG_TAG,__VA_ARGS__)
#define LOGD(...) __android_log_print(ANDROID_LOG_DEBUG,LOG_TAG,__VA_ARGS__)
#define LOGW(...) __android_log_print(ANDROID_LOG_WARN,LOG_TAG,__VA_ARGS__)
#define LOGE(...) __android_log_print(ANDROID_LOG_ERROR,LOG_TAG,__VA_ARGS__)

void *(*il2cpp_class_from_name)(void *image, const char *namespaze, const char *name);

void *(*il2cpp_domain_get)();
void *(*il2cpp_domain_assembly_open)(void *domain, const char *name);
void *(*il2cpp_assembly_get_image)(void *assembly);

void *(*il2cpp_class_get_field_from_name)(void *klass, const char *name);
void (*il2cpp_field_get_value)(void *object, void *field, void *value);
void (*il2cpp_field_set_value)(void *object, void *field, void *value);
void (*il2cpp_field_static_get_value)(void *field, void *value);
void (*il2cpp_field_static_set_value)(void *field, void *value);
void *(*il2cpp_field_get_type)(void *field);
int (*il2cpp_field_get_flags)(void *field);

char *(*il2cpp_type_get_name)(void *type);
uint32_t (*il2cpp_type_get_attrs)(void *type);

void *(*il2cpp_class_get_methods)(void *klass, void **iter);
void *(*il2cpp_class_get_method_from_name)(void *klass, const char *name, int argsCount);

const char *(*il2cpp_method_get_name)(void *method);
uint32_t (*il2cpp_method_get_param_count)(void *method);
void *(*il2cpp_method_get_param)(void *method, uint32_t index);

void *(*il2cpp_string_new)(const char *text);
void *(*il2cpp_string_new_utf16)(const wchar_t *text, int32_t len);

void *(*il2cpp_object_new)(void *klass);
void *(*il2cpp_runtime_object_init)(void *object);
void *(il2cpp_runtime_class_init)(void *klass);

void *(*il2cpp_runtime_invoke)(void *method, void *obj, void **params, void **exc);
void *(*il2cpp_thread_attach)(void *domain);
void (*il2cpp_thread_detach)(void *thread);

void *(*il2cpp_array_new)(void *type, uint32_t length);


bool Il2CppInit() {
    void *il2cpp = dlopen_ex("libil2cpp.so", 4);
    if (!il2cpp) {
        return false;
    }

    il2cpp_class_from_name = (void *(*)(void *, const char *, const char *))dlsym_ex(il2cpp, "il2cpp_class_from_name");
    il2cpp_domain_get = (void *(*)(void))dlsym_ex(il2cpp, "il2cpp_domain_get");
    il2cpp_domain_assembly_open = (void *(*)(void *, const char *))dlsym_ex(il2cpp, "il2cpp_domain_assembly_open");
    il2cpp_assembly_get_image = (void *(*)(void *))dlsym_ex(il2cpp, "il2cpp_assembly_get_image");
    il2cpp_class_get_field_from_name = (void *(*)(void *, const char *))dlsym_ex(il2cpp, "il2cpp_class_get_field_from_name");
    il2cpp_field_get_value = (void (*)(void *, void *, void *))dlsym_ex(il2cpp, "il2cpp_field_get_value");
    il2cpp_field_set_value = (void (*)(void *, void *, void *))dlsym_ex(il2cpp, "il2cpp_field_set_value");
    il2cpp_field_static_get_value = (void (*)(void *, void *))dlsym_ex(il2cpp, "il2cpp_field_static_get_value");
    il2cpp_field_static_set_value = (void (*)(void *, void *))dlsym_ex(il2cpp, "il2cpp_field_static_set_value");
    il2cpp_field_get_type = (void *(*)(void *))dlsym_ex(il2cpp, "il2cpp_field_get_type");
    il2cpp_field_get_flags = (int (*)(void *))dlsym_ex(il2cpp, "il2cpp_field_get_flags");
    il2cpp_type_get_name = (char *(*)(void *))dlsym_ex(il2cpp, "il2cpp_type_get_name");
    //il2cpp_type_get_attrs = (uint32_t (*)(void *))dlsym_ex(il2cpp, "il2cpp_type_get_attrs");
    il2cpp_class_get_methods = (void *(*)(void *, void **))dlsym_ex(il2cpp, "il2cpp_class_get_methods");
    il2cpp_class_get_method_from_name = (void *(*)(void *, const char *, int))dlsym_ex(il2cpp, "il2cpp_class_get_method_from_name");
    il2cpp_method_get_name = (const char *(*)(void *))dlsym_ex(il2cpp, "il2cpp_method_get_name");
    il2cpp_method_get_param_count = (uint32_t (*)(void *))dlsym_ex(il2cpp, "il2cpp_method_get_param_count");
    il2cpp_method_get_param = (void *(*)(void *, uint32_t))dlsym_ex(il2cpp, "il2cpp_method_get_param");
    il2cpp_string_new = (void *(*)(const char *))dlsym_ex(il2cpp, "il2cpp_string_new");
    il2cpp_string_new_utf16 = (void *(*)(const wchar_t *, int32_t))dlsym_ex(il2cpp, "il2cpp_string_new_utf16");
    il2cpp_object_new = (void *(*)(void *))dlsym_ex(il2cpp, "il2cpp_object_new");
    il2cpp_runtime_object_init = (void *(*)(void *))dlsym_ex(il2cpp, "il2cpp_runtime_object_init");
    il2cpp_runtime_invoke = (void *(*)(void *, void *, void **, void **))dlsym_ex(il2cpp, "il2cpp_runtime_invoke");
    il2cpp_thread_attach = (void *(*)(void *))dlsym_ex(il2cpp, "il2cpp_thread_attach");
    il2cpp_thread_detach = (void (*)(void *))dlsym_ex(il2cpp, "il2cpp_thread_detach");
    il2cpp_array_new = (void *(*)(void *, uint32_t))dlsym_ex(il2cpp, "il2cpp_array_new");

    return true;
}

Assembly *Assembly::LoadAssembly(const std::string &assemblyName) {
    auto result = new Assembly();

    auto domain = il2cpp_domain_get();
    auto assembly = il2cpp_domain_assembly_open(domain, assemblyName.c_str());
    if (assembly) {
        result->imagePtr = il2cpp_assembly_get_image(assembly);
    } else result->imagePtr = nullptr;

    return result;
}

Class *Assembly::LoadClass(const std::string &namespaze, const std::string &name) {
    return new Class((void *)il2cpp_class_from_name(imagePtr, namespaze.c_str(), name.c_str()));
}

Class *Assembly::NewClass(const std::string &namespaze, const std::string &name) {
    void *klass = il2cpp_class_from_name(imagePtr, namespaze.c_str(), name.c_str());
    void *obj = il2cpp_object_new(klass);
    return (Class *) obj;
}

bool Class::isFieldStatic(void *field) {
    return (il2cpp_field_get_flags(field) & 0x10) != 0;
}

Method *Class::getMethod(const std::string &name, int argc) {
    auto result = il2cpp_class_get_method_from_name(klass, name.c_str(), argc);
    if (result) {
        LOGI("Method %s found", name.c_str());
        return *(Method **)result;
    }
    LOGI("Method %s not found", name.c_str());
    return 0;
}

Method *Class::getMethod(const std::string &name, const std::vector<std::string> &args) {
    void *iter = 0;
    void *method = (void*) il2cpp_class_get_methods(klass, &iter);
    while(method) {
        bool ok = false;
        const char *method_name = il2cpp_method_get_name(method);
        if (!strcmp(method_name, name.c_str())) {
            uint32_t method_argc = il2cpp_method_get_param_count(method);
            if (method_argc == args.size()) {
                ok = true;
                for (int i = 0; i < args.size(); i++) {
                    void *method_arg = il2cpp_method_get_param(method, i);
                    const char *method_arg_name = il2cpp_type_get_name(method_arg);
                    if (strcmp(method_arg_name, args[i].c_str()) != 0) {
                        ok = false;
                        break;
                    }
                }
            }
        }

        if(ok) {
            LOGI("Method %s found", name.c_str());
            return *(Method **)(method);
        }

        method = (void *) il2cpp_class_get_methods(klass, &iter);
    }
    LOGI("Method %s not found", name.c_str());
    return 0;
}

void *Method::Invoke(void *object, void **args) {
    void *exc;
    void *result = il2cpp_runtime_invoke(this, object, args, &exc);
    return result;
}

void *Method::ReplaceTo(void *newMethod) {
    void *oldMethod = *(void **) ((uintptr_t)this + 0x0);
    *(void **) ((uintptr_t)this + 0x0) = newMethod;
    return oldMethod;
}
// =============================================== //
void *Il2CppThreadAttach() {
    return il2cpp_thread_attach(il2cpp_domain_get());
}

void Il2CppThreadDetach(void *thread) {
    il2cpp_thread_detach(thread);
}
