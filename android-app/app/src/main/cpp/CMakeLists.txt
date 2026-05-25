cmake_minimum_required(VERSION 3.18.1)
project(hclouff)

find_library(log-lib log)

add_library(hclouff SHARED native-lib.cpp)

target_include_directories(hclouff PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}
    # ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/openssl/include
    # ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/curl/include
)

# Dev cần add prebuilt curl + openssl cho Android NDK:
# https://github.com/leenjewel/openssl_for_ios_and_android
# Sau khi có prebuilt, uncomment:
#
# target_link_libraries(hclouff
#     ${log-lib}
#     ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/curl/lib/${ANDROID_ABI}/libcurl.a
#     ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/openssl/lib/${ANDROID_ABI}/libssl.a
#     ${CMAKE_CURRENT_SOURCE_DIR}/prebuilt/openssl/lib/${ANDROID_ABI}/libcrypto.a
#     z
# )

# Stub link cho phép gradle sync pass (build sẽ fail ở link nếu chưa add prebuilt)
target_link_libraries(hclouff ${log-lib})

target_compile_options(hclouff PRIVATE
    -fvisibility=hidden
    -O2
    -fno-rtti
    -fno-exceptions
)
