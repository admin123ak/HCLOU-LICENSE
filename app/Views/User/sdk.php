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
?>
<div class="card">
  <div class="card-header"><i class="bi bi-android2"></i> Android code (Kotlin) — paste into the app and run</div>
  <div class="card-body">
    <?php if (!$currentPublic): ?>
      <div class="alert alert-warning" style="font-size:12.5px">⚠️ RSA not configured: <code>PUBLIC_KEY</code> is empty → the app still <b>runs normally</b> with this code (RSA off). Once you generate a key and paste it into <code>.env</code>, come back, copy this code again (public key auto-filled), and rebuild to enable protection.</div>
    <?php endif; ?>
    <p style="color:var(--muted);font-size:12.5px">Create file <code>HclouLicense.kt</code> and paste the block below. Call <code>HclouLicense.verify(game, key, serial)</code> on app launch. <b>No public key → app still runs</b>; fill it in → RSA verify turns on.</p>
    <div class="sdk-code"><pre id="ktCode">object HclouLicense {
    // RSA public key (from panel). No need to hide — verify-only, cannot sign.
    private const val PUBLIC_KEY =
        "<?= esc($pubOneLine) ?>"
    private const val CONNECT_URL = "<?= esc($connectUrl) ?>"

    data class Result(val ok: Boolean, val reason: String = "")

    /** Call server + (if configured) verify RSA signature. Run on a background thread. */
    fun verify(game: String, userKey: String, serial: String): Result {
        try {
            val post = "game=" + enc(game) + "&user_key=" + enc(userKey) + "&serial=" + enc(serial)
            val conn = (java.net.URL(CONNECT_URL).openConnection() as java.net.HttpURLConnection).apply {
                requestMethod = "POST"; doOutput = true; connectTimeout = 10000; readTimeout = 10000
                setRequestProperty("Content-Type", "application/x-www-form-urlencoded")
            }
            conn.outputStream.use { it.write(post.toByteArray()) }
            val body = conn.inputStream.bufferedReader().readText()
            val json = org.json.JSONObject(body)

            // Server reports invalid / expired / wrong-game key...
            if (!json.optBoolean("status", false))
                return Result(false, json.optString("reason", "INVALID"))

            val data = json.optJSONObject("data") ?: return Result(false, "NO_DATA")
            val sig = data.optString("sig", "")
            val payload = data.optString("payload", "")

            // === RSA only active when PUBLIC_KEY is set AND server returns sig ===
            // No key (PUBLIC_KEY empty) or server not signing -> app runs normally.
            if (PUBLIC_KEY.isNotBlank() && sig.isNotBlank()) {
                if (!checkSign(payload, sig)) return Result(false, "BAD_SIGNATURE")
                val p = payload.split("|")
                if (p.size < 4 || p[0] != game || p[1] != userKey || p[2] != serial)
                    return Result(false, "PAYLOAD_MISMATCH")
                val ts = p[3].toLongOrNull() ?: return Result(false, "BAD_TS")
                val now = System.currentTimeMillis() / 1000
                if (Math.abs(now - ts) > 300) return Result(false, "EXPIRED_RESPONSE")
            }
            return Result(true)
        } catch (e: Exception) {
            return Result(false, "NETWORK_ERROR")
        }
    }

    private fun checkSign(payload: String, sigB64: String): Boolean {
        return try {
            val keyBytes = android.util.Base64.decode(PUBLIC_KEY, android.util.Base64.DEFAULT)
            val pub = java.security.KeyFactory.getInstance("RSA")
                .generatePublic(java.security.spec.X509EncodedKeySpec(keyBytes))
            val s = java.security.Signature.getInstance("SHA256withRSA")
            s.initVerify(pub); s.update(payload.toByteArray(Charsets.UTF_8))
            s.verify(android.util.Base64.decode(sigB64, android.util.Base64.DEFAULT))
        } catch (e: Exception) { false }
    }

    private fun enc(s: String) = java.net.URLEncoder.encode(s, "UTF-8")
}</pre></div>
    <button class="btn btn-secondary btn-sm mt-2 copybtn" onclick="cp('ktCode')"><i class="bi bi-clipboard"></i> Copy code Kotlin</button>
    <div class="mt-3 p-2" style="background:rgba(99,102,241,.08);border-radius:10px;font-size:12px;color:var(--muted)">
      💡 Usage: <code>val r = HclouLicense.verify("PUBG", userKeyInput, deviceSerial)</code> → if <code>r.ok == false</code>, block the app.
      <br>⚠️ Put verify in <b>native (C++/NDK)</b> + enable <b>R8/ProGuard obfuscation</b> to make patching harder for skilled crackers.
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
