#ifndef CONFIG_H
#define CONFIG_H

uintptr_t AfindLibrary(const char *library) {
    char filename[0xFF] = {0},
            buffer[1024] = {0};
    FILE *fp = NULL;
    uintptr_t address = 0;

    sprintf( filename, "/proc/self/maps");

    fp = fopen( filename, "rt" );
    if( fp == NULL ){
        perror("fopen");
        goto done;
    }

    while( fgets( buffer, sizeof(buffer), fp ) ) {
        if(strstr( buffer, library ) ){
            address = (long)strtoul( buffer, NULL, 16 );
            goto done;
        }
    }

    done:

    if(fp){
        fclose(fp);
    }

    return address;
}

uintptr_t ClibBase;

uintptr_t AgetAbsoluteAddress(const char* libraryName, uintptr_t relativeAddr) {
    if (ClibBase == 0) {
        ClibBase = AfindLibrary(libraryName);
        if (ClibBase == 0) {
            ClibBase = 0;
        }
    }
    return ClibBase + relativeAddr;
}

# define getRealOffset(offset) AgetAbsoluteAddress("libil2cpp.so",offset)
#define getLibbase(libname) AfindLibrary(libname)

#endif
