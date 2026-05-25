#include <iostream>
#include <string>
#include <stdio.h>
#include <codecvt>
#include <vector>

extern void *(*il2cpp_class_get_field_from_name)(void *klass, const char *name);
extern void (*il2cpp_field_get_value)(void *object, void *field, void *value);
extern void (*il2cpp_field_set_value)(void *object, void *field, void *value);
extern void (*il2cpp_field_static_get_value)(void *field, void *value);
extern void (*il2cpp_field_static_set_value)(void *field, void *value);
extern void *(*il2cpp_array_new)(void *type, uint32_t length);

class Assembly;
class Class;
class Method;

class Object {
public:
    void *klass;
    void *monitor;
};

class Assembly {
private:
    void *imagePtr;
public:
    static Assembly *LoadAssembly(const std::string &assemblyName);
    Class *LoadClass(const std::string &namespaze, const std::string &name);
    Class *NewClass(const std::string &namespaze, const std::string &name);

    bool assemblyValid() {
        return imagePtr != NULL;
    }
};

class Class : Object {
public:
    Class(void *klassPtr) {
        this->klass = klassPtr;
    }
    
    template<typename T> T getField(const std::string &name) {
        void *fieldPtr = il2cpp_class_get_field_from_name(klass, name.c_str());
        if (fieldPtr == NULL) {
            return T();
        }

        T value;
        if (isFieldStatic(fieldPtr)) {
            il2cpp_field_static_get_value(fieldPtr, &value);
        } else {
            il2cpp_field_get_value((void *) this, fieldPtr, &value);
        }
        return value;
    }
    template<typename T> void setField(const std::string &name, T value) {
        void *fieldPtr = il2cpp_class_get_field_from_name(klass, name.c_str());
        if (fieldPtr == NULL) {
            return;
        }

        if (isFieldStatic(fieldPtr)) {
            il2cpp_field_static_set_value(fieldPtr, &value);
        } else {
            il2cpp_field_set_value((void *) this, fieldPtr, &value);
        }
    }
    Method *getMethod(const std::string &name, int argc=0);
    Method *getMethod(const std::string &name, const std::vector<std::string> &args);
private:
    bool isFieldStatic(void *field);
};

class Method {
public:
    void *Invoke(void *object, void **args = 0);
    void *ReplaceTo(void *newMethod);
};

bool Il2CppInit();
void *Il2CppThreadAttach();
void Il2CppThreadDetach(void *thread);