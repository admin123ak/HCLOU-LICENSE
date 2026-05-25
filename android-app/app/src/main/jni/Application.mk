# To AIDE Users: build cho cả 32-bit lẫn 64-bit (xóa arm64-v8a nếu máy 32-bit only)
APP_ABI := armeabi-v7a arm64-v8a x86
APP_PLATFORM := android-21 #APP_PLATFORM does not need to be set. It will automatically defaulting
APP_STL := c++_static
APP_OPTIM := release
APP_THIN_ARCHIVE := true
APP_PIE 		:= true
