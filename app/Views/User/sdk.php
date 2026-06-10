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
  <div class="card-header"><i class="bi bi-shield-lock"></i> License SDK — chống crack /connect (RSA)</div>
  <div class="card-body">
    <p style="color:var(--muted);font-size:13px">
      Server ký mỗi phản hồi <code><?= esc($connectUrl) ?></code> bằng <b>private key</b> (giữ kín trong <code>.env</code>).
      App Android chỉ giữ <b>public key</b> để verify — mổ app cũng không tự ký token giả được.
    </p>

    <?php if (!$currentPublic): ?>
      <div class="alert alert-warning">
        ⚠️ Chưa cấu hình khoá RSA. Bấm <b>Tạo cặp khoá</b> rồi dán private key vào <code>.env</code>.
      </div>
    <?php else: ?>
      <div class="alert" style="background:rgba(52,211,153,.12);color:#6ee7b7;border:1px solid rgba(52,211,153,.3)">
        ✅ Đã bật ký RSA. Public key bên dưới — dán vào app.
      </div>
    <?php endif; ?>

    <a href="<?= site_url('settings/sdk?gen=1') ?>" class="btn btn-primary btn-sm" onclick="return confirm('Tạo cặp khoá MỚI? Nếu đang dùng key cũ, app sẽ phải cập nhật public key mới.')">
      <i class="bi bi-key"></i> Tạo cặp khoá RSA mới
    </a>
  </div>
</div>

<?php if ($generated): ?>
<div class="card">
  <div class="card-header" style="color:#fbbf24"><i class="bi bi-exclamation-triangle"></i> Cặp khoá MỚI — làm theo đúng thứ tự</div>
  <div class="card-body">
    <p><b>Bước 1.</b> Mở file <code>.env</code> của panel, thêm dòng này (chỉ 1 dòng), rồi lưu:</p>
    <div class="sdk-key" id="privKey">connect.rsaPrivate = "<?= esc($generated['private_b64']) ?>"</div>
    <button class="btn btn-secondary btn-sm mt-2 copybtn" onclick="cp('privKey')"><i class="bi bi-clipboard"></i> Copy dòng .env</button>
    <p class="mt-3" style="color:#fca5a5;font-size:12.5px">🔒 KHÔNG để private key lọt ra ngoài / commit lên git. Chỉ nằm trong .env.</p>
    <hr>
    <p><b>Bước 2.</b> Public key (đã tự nhúng vào code Android bên dưới). Lưu lại nếu cần:</p>
    <div class="sdk-key" id="pubKeyGen"><?= esc($generated['public']) ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($currentPublic):
  // Chuẩn hoá public key 1 dòng để nhúng vào code Android
  $pubOneLine = trim(preg_replace('/-----(BEGIN|END) PUBLIC KEY-----/', '', $currentPublic));
  $pubOneLine = preg_replace('/\s+/', '', $pubOneLine);
?>
<div class="card">
  <div class="card-header"><i class="bi bi-android2"></i> Code Android (Kotlin) — dán vào app là chạy</div>
  <div class="card-body">
    <p style="color:var(--muted);font-size:12.5px">Tạo file <code>HclouLicense.kt</code>, dán nguyên đoạn dưới (public key đã nhúng sẵn). Gọi <code>HclouLicense.verify(game, key, serial)</code> khi mở app.</p>
    <div class="sdk-code"><pre id="ktCode">object HclouLicense {
    // Public key RSA (panel cấp). KHÔNG cần giấu — chỉ verify, không ký được.
    private const val PUBLIC_KEY =
        "<?= esc($pubOneLine) ?>"
    private const val CONNECT_URL = "<?= esc($connectUrl) ?>"

    data class Result(val ok: Boolean, val reason: String = "")

    /** Gọi server + verify chữ ký RSA. Chạy trên background thread. */
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
            if (!json.optBoolean("status", false))
                return Result(false, json.optString("reason", "INVALID"))

            val data = json.getJSONObject("data")
            val payload = data.getString("payload")          // "game|key|serial|ts"
            val sig = data.getString("sig")                  // chữ ký RSA (base64)

            // 1) Verify chữ ký bằng public key
            if (!checkSign(payload, sig)) return Result(false, "BAD_SIGNATURE")

            // 2) Payload phải khớp đúng request (chống tráo)
            val p = payload.split("|")
            if (p.size < 4 || p[0] != game || p[1] != userKey || p[2] != serial)
                return Result(false, "PAYLOAD_MISMATCH")

            // 3) Chống replay: timestamp lệch tối đa 5 phút
            val ts = p[3].toLongOrNull() ?: return Result(false, "BAD_TS")
            val now = System.currentTimeMillis() / 1000
            if (Math.abs(now - ts) > 300) return Result(false, "EXPIRED_RESPONSE")

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
      💡 Cách dùng: <code>val r = HclouLicense.verify("PUBG", userKeyNhap, deviceSerial)</code> → nếu <code>r.ok == false</code> thì chặn app.
      <br>⚠️ Đặt verify ở <b>native (C++/NDK)</b> + bật <b>R8/ProGuard obfuscation</b> để tay to khó patch (xem mục bảo mật).
    </div>
  </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function cp(id){
  var t=document.getElementById(id).innerText;
  if(navigator.clipboard) navigator.clipboard.writeText(t);
  if(typeof Toast!=='undefined') Toast.fire({icon:'success',title:'Đã copy!'});
}
</script>
<?= $this->endSection() ?>
