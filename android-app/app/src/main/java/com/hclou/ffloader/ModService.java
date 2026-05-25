package com.hclou.ffloader;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.os.Build;
import android.os.IBinder;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.Nullable;

public class ModService extends Service {

    static { System.loadLibrary("hclouff"); }

    public native void jniStartMod();
    public native void jniStopMod();
    public native void jniToggleBypass(boolean on);
    public native String jniGetModname();

    private WindowManager wm;
    private View floatingView;
    private boolean bypassOn = false;

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        startForegroundCompat();
        showFloating();
        jniStartMod();
        return START_STICKY;
    }

    private void startForegroundCompat() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationManager nm = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
            String chId = "hclou_ff";
            NotificationChannel ch = new NotificationChannel(chId, "HCLOU FF Loader",
                NotificationManager.IMPORTANCE_LOW);
            nm.createNotificationChannel(ch);
            Notification n = new Notification.Builder(this, chId)
                .setContentTitle("HCLOU FF active")
                .setContentText("Floating menu đang chạy")
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .build();
            startForeground(1, n);
        }
    }

    @SuppressWarnings("ClickableViewAccessibility")
    private void showFloating() {
        wm = (WindowManager) getSystemService(WINDOW_SERVICE);
        floatingView = LayoutInflater.from(this).inflate(R.layout.floating_menu, null);

        TextView tvTitle = floatingView.findViewById(R.id.tvFloatTitle);
        tvTitle.setText(jniGetModname());

        floatingView.findViewById(R.id.btnToggleBypass).setOnClickListener(v -> {
            bypassOn = !bypassOn;
            jniToggleBypass(bypassOn);
            Toast.makeText(this, "Bypass Login 400: " + (bypassOn ? "ON" : "OFF"), Toast.LENGTH_SHORT).show();
        });

        floatingView.findViewById(R.id.btnCloseFloat).setOnClickListener(v -> {
            jniStopMod();
            try { wm.removeView(floatingView); } catch (Exception e) {}
            stopForeground(true);
            stopSelf();
        });

        int type = Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
                 ? WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
                 : WindowManager.LayoutParams.TYPE_PHONE;

        WindowManager.LayoutParams params = new WindowManager.LayoutParams(
            WindowManager.LayoutParams.WRAP_CONTENT,
            WindowManager.LayoutParams.WRAP_CONTENT,
            type,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE,
            PixelFormat.TRANSLUCENT);
        params.gravity = Gravity.TOP | Gravity.START;
        params.x = 0; params.y = 100;

        // Drag-to-move
        floatingView.setOnTouchListener(new View.OnTouchListener() {
            private int initX, initY; private float touchX, touchY;
            @Override
            public boolean onTouch(View v, MotionEvent e) {
                switch (e.getAction()) {
                    case MotionEvent.ACTION_DOWN:
                        initX = params.x; initY = params.y;
                        touchX = e.getRawX(); touchY = e.getRawY();
                        return true;
                    case MotionEvent.ACTION_MOVE:
                        params.x = initX + (int)(e.getRawX() - touchX);
                        params.y = initY + (int)(e.getRawY() - touchY);
                        wm.updateViewLayout(floatingView, params);
                        return true;
                }
                return false;
            }
        });

        wm.addView(floatingView, params);
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        if (floatingView != null && wm != null) try { wm.removeView(floatingView); } catch (Exception e) {}
    }

    @Nullable @Override public IBinder onBind(Intent intent) { return null; }
}
