<?= $this->extend('Layout/Starter') ?>
<?= $this->section('content') ?>

<style>
.sdk-code{background:#0a0f1c;border:1px solid var(--line2);border-radius:12px;padding:14px;overflow:auto;max-height:520px}
.sdk-code pre{margin:0;color:#cdd9f0;font-family:monospace;font-size:12.5px;line-height:1.55;white-space:pre}
.sdk-key{background:rgba(7,11,20,.7);border:1px solid var(--line2);border-radius:10px;padding:10px;font-family:monospace;font-size:11.5px;color:#7db3ff;word-break:break-all;white-space:pre-wrap}
.copybtn{cursor:pointer}
</style>

<?= $this->include('Layout/msgStatus') ?>

<div class="card">
  <div class="card-header"><i class="bi bi-shield-lock"></i> License SDK — anti-crack /connect (RSA)</div>
  <div class="card-body">
    <p style="color:var(--muted);font-size:13px">
      The server signs every <code><?= esc($connectUrl) ?></code> response with the <b>private key</b> (kept secret in <code>.env</code>).
      The Android app only holds the <b>public key</b> to verify — even reversing the app can't forge a valid token.
    </p>

    <?php if (!$currentPublic): ?>
      <div class="alert alert-warning">
        ⚠️ RSA key not configured. Click <b>Generate key pair</b>, then paste the private key into <code>.env</code>.
      </div>
    <?php else: ?>
      <div class="alert" style="background:rgba(52,211,153,.12);color:#6ee7b7;border:1px solid rgba(52,211,153,.3)">
        ✅ RSA signing is active. Public key is below — paste it into the app.
      </div>
    <?php endif; ?>

    <a href="<?= site_url('settings/sdk?gen=1') ?>" class="btn btn-primary btn-sm" onclick="return confirm('Generate a NEW key pair? If a key is already in use, the app must be updated with the new public key.')">
      <i class="bi bi-key"></i> Generate new RSA key pair
    </a>
  </div>
</div>

<?php if ($generated): ?>
<div class="card">
  <div class="card-header" style="color:#fbbf24"><i class="bi bi-exclamation-triangle"></i> NEW key pair — follow these steps in order</div>
  <div class="card-body">
    <p><b>Step 1.</b> Open the panel's <code>.env</code> file, add this single line, then save:</p>
    <div class="sdk-key" id="privKey">connect.rsaPrivate = "<?= esc($generated['private_b64']) ?>"</div>
    <button class="btn btn-secondary btn-sm mt-2 copybtn" onclick="cp('privKey')"><i class="bi bi-clipboard"></i> Copy .env line</button>
    <p class="mt-3" style="color:#fca5a5;font-size:12.5px">🔒 NEVER leak the private key or commit it to git. Keep it only in .env.</p>
    <hr>
    <p><b>Step 2.</b> Public key (already embedded in the Android code below). Save it if needed:</p>
    <div class="sdk-key" id="pubKeyGen"><?= esc($generated['public']) ?></div>
  </div>
</div>
<?php endif; ?>

<?php
  // Public key as single line for embedding (empty if not configured -> app still runs)
  $pubOneLine = '';
  if ($currentPublic) {
      $pubOneLine = trim(preg_replace('/-----(BEGIN|END) PUBLIC KEY-----/', '', $currentPublic));
      $pubOneLine = preg_replace('/\s+/', '', $pubOneLine);
  }
  // Symmetric secret for request encryption (must match server's connect.apiSecret / staticWords)
  $apiSecret = env('connect.apiSecret') ?: 'Vm8Lk7Uj2JmsjCPVPVjrLa7zgfx3uz9E';
  // Public key formatted as C++ string literal (each line "...\n") for main.cpp
  $pubCpp = '';
  if ($currentPublic) {
      foreach (explode("\n", trim($currentPublic)) as $ln) {
          $ln = trim($ln);
          if ($ln !== '') $pubCpp .= '    "' . $ln . '\\n"' . "\n";
      }
  }
?>

<?php if ($currentPublic): ?>
<div class="card">
  <div class="card-header"><i class="bi bi-filetype-cpp"></i> Public key cho <code>main.cpp</code> (C++ / native app)</div>
  <div class="card-body">
    <p style="color:var(--muted);font-size:12.5px">App native (.so) → dán đoạn này vào <code>RSA_PUBLIC_KEY</code> trong <code>main_secure.cpp</code> (đã format sẵn <code>"...\n"</code> từng dòng):</p>
    <div class="sdk-code"><pre id="cppKey">static const char* RSA_PUBLIC_KEY =
<?= esc(rtrim($pubCpp)) ?>;</pre></div>
    <button class="btn btn-secondary btn-sm mt-2 copybtn" onclick="cp('cppKey')"><i class="bi bi-clipboard"></i> Copy public key (C++)</button>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="bi bi-android2"></i> Android code (Java) — encrypted request + RSA verify</div>
  <div class="card-body">
    <div class="alert" style="background:rgba(99,102,241,.1);font-size:12.5px;color:#c7d2fe">
      🔐 Protection layers (stronger than Kuro): <b>AES-256 encrypted request</b> + <b>HMAC-SHA256</b> + <b>timestamp/nonce anti-replay</b> + <b>RSA-signed response</b>.
    </div>
    <div class="alert" style="background:rgba(251,191,36,.12);font-size:12.5px;color:#fcd34d">
      📌 <b>App native (.so / C++ / mod menu)?</b> → KHÔNG dùng code Java này. Dùng file <code>client_sdk/main_secure.cpp</code> trong repo (dán đè <code>main.cpp</code> cũ). Code Java dưới chỉ cho app Java thuần.
    </div>
    <?php if (!$currentPublic): ?>
      <div class="alert alert-warning" style="font-size:12.5px">⚠️ RSA not configured yet: <code>PUBLIC_KEY</code> is empty → the app still <b>runs</b> (encryption + HMAC already active). Generate a key + paste into <code>.env</code>, then copy this code again to enable RSA response verification.</div>
    <?php endif; ?>
    <p style="color:var(--muted);font-size:12.5px">Create <code>HclouLicense.java</code> and paste the block below (keys already embedded).</p>
    <div class="sdk-code"><pre id="ktCode">import android.util.Base64;
import org.json.JSONObject;
import javax.crypto.Cipher;
import javax.crypto.Mac;
import javax.crypto.spec.IvParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.security.*;
import java.security.spec.X509EncodedKeySpec;
import java.io.*;

public class HclouLicense {
    // RSA public key — verify server response (cannot sign). Safe to embed.
    private static final String PUBLIC_KEY = "<?= esc($pubOneLine) ?>";
    // Symmetric secret — AES + HMAC request encryption (must match server .env).
    private static final String API_SECRET = "<?= esc($apiSecret) ?>";
    private static final String CONNECT_URL = "<?= esc($connectUrl) ?>";

    public static class Result {
        public final boolean ok; public final String reason;
        Result(boolean ok, String reason) { this.ok = ok; this.reason = reason; }
    }

    /** Verify a license key. Call on a BACKGROUND thread. hwid = unique device id. */
    public static Result verify(String game, String userKey, String hwid) {
        try {
            String ts = String.valueOf(System.currentTimeMillis() / 1000L);
            String nonce = randomHex(8);
            JSONObject p = new JSONObject();
            p.put("game", game); p.put("key", userKey); p.put("hwid", hwid);

            byte[] sessionKey = sha256(API_SECRET + "|" + ts);
            byte[] macKey     = sha256(API_SECRET + "|" + nonce + "|mac");
            String d = aesEncrypt(p.toString(), sessionKey);          // base64(iv + ciphertext)
            String m = hmacB64(macKey, d + "|" + ts + "|" + nonce);   // HMAC-SHA256

            String form = "d=" + enc(d) + "&t=" + ts + "&n=" + enc(nonce) + "&m=" + enc(m);
            HttpURLConnection conn = (HttpURLConnection) new URL(CONNECT_URL).openConnection();
            conn.setRequestMethod("POST"); conn.setDoOutput(true);
            conn.setConnectTimeout(10000); conn.setReadTimeout(10000);
            conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
            conn.setRequestProperty("X-API-Client", "android"); // required (browser block)
            conn.getOutputStream().write(form.getBytes("UTF-8"));

            BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
            StringBuilder sb = new StringBuilder(); String ln;
            while ((ln = br.readLine()) != null) sb.append(ln);

            // Response is encrypted (AES+HMAC wrapper). Decrypt; fall back to plain JSON on error.
            JSONObject json = decryptResponse(sb.toString().trim());
            if (json == null) return new Result(false, "BAD_RESPONSE");

            if (!json.optBoolean("status", false))
                return new Result(false, json.optString("reason", "INVALID"));

            JSONObject data = json.optJSONObject("data");
            if (data == null) return new Result(false, "NO_DATA");
            String sig = data.optString("sig", "");
            String payload = data.optString("payload", "");

            // RSA response check (active once PUBLIC_KEY + server sig are present)
            if (!PUBLIC_KEY.isEmpty() && !sig.isEmpty()) {
                if (!checkSign(payload, sig)) return new Result(false, "BAD_SIGNATURE");
                String[] pp = payload.split("\\|");
                if (pp.length < 4 || !pp[0].equals(game) || !pp[1].equals(userKey) || !pp[2].equals(hwid))
                    return new Result(false, "PAYLOAD_MISMATCH");
                long t = Long.parseLong(pp[3]);
                long now = System.currentTimeMillis() / 1000L;
                if (Math.abs(now - t) > 300) return new Result(false, "EXPIRED_RESPONSE");
            }
            return new Result(true, "");
        } catch (Exception e) {
            return new Result(false, "NETWORK_ERROR");
        }
    }

    /** Decrypt encrypted response wrapper; if not a wrapper, parse as plain JSON. */
    private static JSONObject decryptResponse(String body) {
        try {
            String dec = new String(Base64.decode(body, Base64.DEFAULT), "UTF-8");
            JSONObject w = new JSONObject(dec);
            if (w.has("x") && w.has("t") && w.has("n") && w.has("m")) {
                String x = w.getString("x"), t = w.getString("t"), n = w.getString("n"), m = w.getString("m");
                byte[] mk = sha256(API_SECRET + "|" + n + "|mac");
                if (!hmacB64(mk, x + "|" + t + "|" + n).equals(m)) return null; // bad MAC
                byte[] raw = Base64.decode(x, Base64.NO_WRAP);
                byte[] iv = new byte[16]; System.arraycopy(raw, 0, iv, 0, 16);
                byte[] ct = new byte[raw.length - 16]; System.arraycopy(raw, 16, ct, 0, ct.length);
                Cipher c = Cipher.getInstance("AES/CBC/PKCS5Padding");
                c.init(Cipher.DECRYPT_MODE, new SecretKeySpec(sha256(API_SECRET + "|" + t), "AES"), new IvParameterSpec(iv));
                return new JSONObject(new String(c.doFinal(ct), "UTF-8"));
            }
            return w; // plain JSON (legacy / pre-decrypt error)
        } catch (Exception e) {
            try { return new JSONObject(body); } catch (Exception e2) { return null; }
        }
    }

    private static byte[] sha256(String s) throws Exception {
        return MessageDigest.getInstance("SHA-256").digest(s.getBytes("UTF-8"));
    }
    private static String aesEncrypt(String plain, byte[] key) throws Exception {
        byte[] iv = new byte[16]; new SecureRandom().nextBytes(iv);
        Cipher c = Cipher.getInstance("AES/CBC/PKCS5Padding");
        c.init(Cipher.ENCRYPT_MODE, new SecretKeySpec(key, "AES"), new IvParameterSpec(iv));
        byte[] ct = c.doFinal(plain.getBytes("UTF-8"));
        byte[] out = new byte[16 + ct.length];
        System.arraycopy(iv, 0, out, 0, 16);
        System.arraycopy(ct, 0, out, 16, ct.length);
        return Base64.encodeToString(out, Base64.NO_WRAP);
    }
    private static String hmacB64(byte[] key, String data) throws Exception {
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(key, "HmacSHA256"));
        return Base64.encodeToString(mac.doFinal(data.getBytes("UTF-8")), Base64.NO_WRAP);
    }
    private static boolean checkSign(String payload, String sigB64) {
        try {
            byte[] kb = Base64.decode(PUBLIC_KEY, Base64.DEFAULT);
            PublicKey pub = KeyFactory.getInstance("RSA").generatePublic(new X509EncodedKeySpec(kb));
            Signature s = Signature.getInstance("SHA256withRSA");
            s.initVerify(pub); s.update(payload.getBytes("UTF-8"));
            return s.verify(Base64.decode(sigB64, Base64.DEFAULT));
        } catch (Exception e) { return false; }
    }
    private static String randomHex(int n) {
        byte[] b = new byte[n]; new SecureRandom().nextBytes(b);
        StringBuilder sb = new StringBuilder();
        for (byte x : b) sb.append(String.format("%02x", x));
        return sb.toString();
    }
    private static String enc(String s) throws Exception { return URLEncoder.encode(s, "UTF-8"); }
}</pre></div>
    <button class="btn btn-secondary btn-sm mt-2 copybtn" onclick="cp('ktCode')"><i class="bi bi-clipboard"></i> Copy Java code</button>
    <div class="mt-3 p-2" style="background:rgba(99,102,241,.08);border-radius:10px;font-size:12px;color:var(--muted)">
      💡 Usage (background thread):<br>
      <code>new Thread(() -> { HclouLicense.Result r = HclouLicense.verify("PUBG", keyInput, hwid);<br>
      runOnUiThread(() -> { if (!r.ok) finish(); }); }).start();</code>
      <br>🔑 <code>hwid</code> = unique device id (e.g. <code>Settings.Secure.ANDROID_ID</code>).
      <br>⚠️ For max protection: move this verify into <b>native C/C++ (NDK .so)</b> + enable <b>R8/ProGuard</b>.
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function cp(id){
  var t=document.getElementById(id).innerText;
  if(navigator.clipboard) navigator.clipboard.writeText(t);
  if(typeof Toast!=='undefined') Toast.fire({icon:'success',title:'Copied!'});
}
</script>
<?= $this->endSection() ?>
