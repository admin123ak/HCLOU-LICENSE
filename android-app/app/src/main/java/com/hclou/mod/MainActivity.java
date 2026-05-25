package com.hclou.mod;

import android.app.AlertDialog;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.AsyncTask;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.text.TextUtils;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import java.security.MessageDigest;

public class MainActivity extends AppCompatActivity {

    static { System.loadLibrary("hcloumod"); }

    // JNI native methods
    public native String jniLogin(String packageId, String userKey, String serial);
    public native String jniGetModname();
    public native String jniGetCredit();
    public native String jniGetExp();

    private EditText etKey;
    private TextView tvStatus;
    private Button btnLogin, btnStart;
    private SharedPreferences prefs;

    @Override
    protected void onCreate(Bundle s) {
        super.onCreate(s);
        setContentView(R.layout.activity_login);

        etKey     = findViewById(R.id.etKey);
        tvStatus  = findViewById(R.id.tvStatus);
        btnLogin  = findViewById(R.id.btnLogin);
        btnStart  = findViewById(R.id.btnStart);
        prefs     = getSharedPreferences("hclou", MODE_PRIVATE);

        // Tự fill key đã lưu
        String saved = prefs.getString("user_key", "");
        if (!TextUtils.isEmpty(saved)) etKey.setText(saved);

        btnLogin.setOnClickListener(v -> doLogin());
        btnStart.setOnClickListener(v -> startMod());
        btnStart.setEnabled(false);
    }

    private void doLogin() {
        String key = etKey.getText().toString().trim().toUpperCase();
        if (!key.startsWith("HCLOU-")) {
            toast("Key phải bắt đầu HCLOU-");
            return;
        }
        btnLogin.setEnabled(false);
        tvStatus.setText("Đang xác thực...");

        // Game target - default PUBG, dev đổi theo nhu cầu
        String targetPkg = "com.tencent.ig";
        String serial = computeSerial();

        new AsyncTask<String, Void, String>() {
            @Override
            protected String doInBackground(String... a) {
                try { return jniLogin(a[0], a[1], a[2]); }
                catch (Throwable t) { return "NATIVE_FAIL: " + t.getMessage(); }
            }
            @Override
            protected void onPostExecute(String reason) {
                btnLogin.setEnabled(true);
                if ("OK".equals(reason)) {
                    String mn = jniGetModname();
                    String cr = jniGetCredit();
                    String ex = jniGetExp();
                    tvStatus.setText("✓ " + mn + "\nCredit: " + cr + "\nHết hạn: " + ex);
                    btnStart.setEnabled(true);
                    prefs.edit().putString("user_key", key).apply();
                } else {
                    tvStatus.setText("✗ " + reason);
                    btnStart.setEnabled(false);
                }
            }
        }.execute(targetPkg, key, serial);
    }

    private void startMod() {
        // SYSTEM_ALERT_WINDOW permission check
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M
            && !Settings.canDrawOverlays(this)) {
            new AlertDialog.Builder(this)
                .setTitle("Cần quyền")
                .setMessage("App cần quyền hiển thị trên màn hình để mở floating menu.")
                .setPositiveButton("Mở Settings", (d, w) -> {
                    Intent i = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                                          Uri.parse("package:" + getPackageName()));
                    startActivity(i);
                })
                .setNegativeButton("Huỷ", null)
                .show();
            return;
        }
        startService(new Intent(this, ModService.class));
        toast("Đã khởi động mod. Mở game để chạy.");
        moveTaskToBack(true);
    }

    /**
     * Compute device serial = sha256(android_id + Build.MODEL + Build.BRAND), 40 hex chars.
     * Match với getDeviceSerial trong C++ (xem API_DOCUMENTATION.md).
     */
    private String computeSerial() {
        try {
            String androidId = Settings.Secure.getString(getContentResolver(), Settings.Secure.ANDROID_ID);
            String seed = (androidId == null ? "" : androidId)
                        + "|" + Build.MODEL
                        + "|" + Build.BRAND;
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] hash = md.digest(seed.getBytes("UTF-8"));
            StringBuilder sb = new StringBuilder();
            for (byte b : hash) sb.append(String.format("%02x", b));
            return sb.substring(0, 40);
        } catch (Exception e) {
            return "fallback_" + System.currentTimeMillis();
        }
    }

    private void toast(String s) { Toast.makeText(this, s, Toast.LENGTH_SHORT).show(); }
}
