cmake_minimum_required(VERSION 3.18.1)
project(hcloumod)

# Cần openssl + curl. Trên Android NDK, prebuilt từ external/ hoặc cmake fetch.
# Đề xuất: ndk-curl + ndk-openssl prebuilt.
# Sample sample link với system libs (cần adapt theo NDK version).

find_library(log-lib log)

add_library(hcloumod SHARED
    native-lib.cpp
    hclou_connect.cpp
)

target_include_directories(hcloumod PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}
    # ${CMAKE_CURRENT_SOURCE_DIR}/../../../../prebuilt/openssl/include
    # ${CMAKE_CURRENT_SOURCE_DIR}/../../../../prebuilt/curl/include
)

# Tuỳ cách prebuilt curl + openssl của dev — thay path tương ứng
# Vd dùng ndk-curl: https://github.com/leenjewel/openssl_for_ios_and_android
# target_link_libraries(hcloumod
#     ${log-lib}
#     curl
#     ssl
#     crypto
# )

# Stub đơn giản (nếu chưa có curl/openssl prebuilt, build vẫn pass):
target_link_libraries(hcloumod ${log-lib})

target_compile_options(hcloumod PRIVATE
    -fvisibility=hidden
    -fno-rtti
    -fno-exceptions
)
