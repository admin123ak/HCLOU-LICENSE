# Keep native methods + JNI entry points
-keepclasseswithmembernames class * {
    native <methods>;
}

# Keep MainActivity + ModService (JNI access from native code)
-keep class com.hclou.mod.MainActivity { *; }
-keep class com.hclou.mod.ModService { *; }
