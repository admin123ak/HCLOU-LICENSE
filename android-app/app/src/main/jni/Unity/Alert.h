#include <jni.h>

Color colorByDistance(int distance, float alpha){
    Color _colorByDistance;
    if (distance < 450)
        _colorByDistance = Color(255,0,0, alpha);
    if (distance < 200)
        _colorByDistance = Color(255,0,0, alpha);
    if (distance < 121)
        _colorByDistance = Color(0,10,51, alpha);
    if (distance < 51)
        _colorByDistance = Color(0,67,0, alpha);
    return _colorByDistance;
}
Vector2 pushToScreenBorder(Vector2 Pos, Vector2 screen, int offset) {
    int X = (int)Pos.X;
    int Y = (int)Pos.Y;
    if (Pos.Y < 50) {
        // top
        Y = 42 - offset;
    }
     if (Pos.X > screen.X) {
        // right
        X =  (int)screen.X + offset;
    }
    if (Pos.Y > screen.Y) {
        // bottom
        Y = (int)screen.Y +  offset;
    }
    if (Pos.X < 60) {
        // left
        X = 20 - offset;
    }
    return Vector2(X, Y);
}
bool isOutsideSafeZone(Vector2 pos, Vector2 screen) {
    if (pos.Y < 60) {
        return true;
    }
    if (pos.X > screen.X) {
        return true;
    }
    if (pos.Y > screen.Y) {
        return true;
    }
    return pos.X < 50;
    
}

