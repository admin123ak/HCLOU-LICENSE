package com.android.support;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.ProgressDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.Message;
import android.provider.Settings;
import android.text.InputType;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.Toast;
import com.android.support.Menu;

@SuppressWarnings("all")
public class TechnicalAkash1 {
    static {
        System.loadLibrary("MyLibName");
    }
    private static SharedPreferences m_Prefs;
    public static void Init(Object object) {
        final Context m_Context = (Context) object;
        Activity m_Activity = (Activity) object;
        if (Build.VERSION.SDK_INT >= 23) {
            if (!Settings.canDrawOverlays(m_Context)) {
                Intent intent = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, Uri.parse("package:" + m_Context.getPackageName()));
                m_Activity.startActivity(intent);
            }
        }

        m_Prefs = m_Context.getSharedPreferences(m_Context.getPackageName(), Context.MODE_PRIVATE);
        if (!m_Prefs.contains("USER_KEY")) {
            LinearLayout mainLayout = new LinearLayout(m_Context);
            mainLayout.setOrientation(LinearLayout.VERTICAL);
            mainLayout.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
            mainLayout.setPadding(45, 40, 45, 25);
            final EditText input = new EditText(m_Context);
            input.setInputType(InputType.TYPE_CLASS_TEXT);
            input.setHint("Nhập key vào đây...");

            mainLayout.addView(input);

            String modName = GetModName();
            if (modName == null || modName.isEmpty()) {
                modName = "KEY FREE ĐỔI SAU 24H";
            }

            AlertDialog.Builder builder = new AlertDialog.Builder(m_Context, 5);
            builder.setTitle(modName);
            builder.setCancelable(false);
            builder.setView(mainLayout);

            builder.setPositiveButton("VÀO GAME", new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        String userKey = input.getText().toString();
                        Login(m_Context, userKey);
                    }
                });
			builder.show();
        } else {
            Login(m_Context, m_Prefs.getString("USER_KEY", null));
        }
    }

    private static void Login(final Context m_Context, final String userKey) {
        final ProgressDialog progressDialog = new ProgressDialog(m_Context, 5);
        progressDialog.setProgressStyle(ProgressDialog.STYLE_SPINNER);
        progressDialog.setMessage("ĐANG KIỂM TRA ...");
        progressDialog.setCancelable(false);
        progressDialog.show();

        final Handler loginHandler = new Handler() {
            @Override
            public void handleMessage(Message msg) {
                if (msg.what == 0) {
                    m_Prefs.edit().putString("USER_KEY", userKey).apply();

                    // Show mod status toast
                    String modStatus = GetModStatus();
                    if (modStatus != null && !modStatus.isEmpty()) {
                        String toastMsg = modStatus.equalsIgnoreCase("on") ? "MOD STATUS: SAFE ✓" : "MOD STATUS: UNSAFE ⚠";
                        Toast.makeText(m_Context, toastMsg, Toast.LENGTH_LONG).show();
                    }

                    Intent i = new Intent(m_Context.getApplicationContext(), Launcher.class);
                    m_Context.startService(i);
                } else if (msg.what == 1) {
                    AlertDialog.Builder builder = new AlertDialog.Builder(m_Context, 5);
                    builder.setTitle("KEY HẾT HẠN VUI LÒNG LẤY KEY MỚI");
                    builder.setMessage(msg.obj.toString());
                    builder.setCancelable(false);

                    builder.setPositiveButton("OK", new DialogInterface.OnClickListener() {
                            @Override
                            public void onClick(DialogInterface dialog, int which) {
                                m_Prefs.edit().clear().apply();
                                ((Activity)m_Context).finish();
                            }
                        });
					builder.show();
                }
                progressDialog.dismiss();
            }
        };

        new Thread(new Runnable() {
                @Override
                public void run() {
                    String result = Check(m_Context, userKey);
                    if (result.equals("OK")) {
                        loginHandler.sendEmptyMessage(0);
                    } else {
                        Message msg = new Message();
                        msg.what = 1;
                        msg.obj = result;
                        loginHandler.sendMessage(msg);
                    }
                }
            }).start();
    }
    private static native String Check(Context mContext, String userKey);
    private static native String GetModName();
    private static native String GetModStatus();
}
