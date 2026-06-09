<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>

<style>
.tok-box{display:flex;gap:10px;align-items:center;background:rgba(7,11,20,.7);border:1px solid var(--line2);border-radius:12px;padding:12px 14px;margin-bottom:10px}
.tok-box code{flex:1;color:#7db3ff;font-size:13.5px;word-break:break-all;font-family:monospace}
.ep-row{display:flex;gap:10px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--line)}
.ep-row:last-child{border-bottom:0}
.ep-method{font-size:10.5px;font-weight:800;padding:3px 9px;border-radius:7px;letter-spacing:.05em}
.ep-get{background:rgba(52,211,153,.16);color:#6ee7b7}
.ep-post{background:rgba(251,191,36,.16);color:#fcd34d}
.ep-url{font-family:monospace;font-size:13px;color:#c7d2fe;word-break:break-all}
.ep-desc{font-size:12px;color:var(--muted);margin-top:3px}
</style>

<?= $this->include('Layout/msgStatus') ?>

<?php if (!empty($noCol)): ?>
<div class="alert alert-warning">
    ⚠️ Chưa có cột <code>api_token</code> trong DB. Mở <a href="<?= base_url('fix_db.php') ?>" target="_blank" style="color:#fde68a;text-decoration:underline">fix_db.php</a> để tạo, rồi quay lại.
</div>
<?php else: ?>

<div class="row">
    <div class="col-lg-7">
        <!-- Token -->
        <div class="card">
            <div class="card-header"><i class="bi bi-key-fill"></i> API Token của bạn</div>
            <div class="card-body">
                <?php if (!empty($user->api_token)): ?>
                    <div class="tok-box">
                        <code id="apiTok"><?= esc($user->api_token) ?></code>
                        <button class="btn btn-secondary btn-sm" onclick="copyTok()"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="<?= site_url('settings/api/toggle') ?>" class="btn btn-sm <?= !empty($user->api_enabled)?'btn-danger':'btn-success' ?>">
                            <i class="bi bi-<?= !empty($user->api_enabled)?'pause-circle':'play-circle' ?>"></i>
                            <?= !empty($user->api_enabled)?'Tắt API':'Bật API' ?>
                        </a>
                        <a href="<?= site_url('settings/api/generate') ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Tạo token mới? Token cũ sẽ ngừng hoạt động.')">
                            <i class="bi bi-arrow-repeat"></i> Làm mới token
                        </a>
                    </div>
                    <div class="mt-3">
                        Trạng thái:
                        <?php if(!empty($user->api_enabled)): ?>
                            <span class="badge" style="background:rgba(52,211,153,.14);color:#6ee7b7;border:1px solid rgba(52,211,153,.3)">● Đang bật</span>
                        <?php else: ?>
                            <span class="badge" style="background:rgba(248,113,113,.14);color:#fca5a5;border:1px solid rgba(248,113,113,.3)">○ Đang tắt</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Bạn chưa có API token. Tạo token để bot bên ngoài gọi mua key.</p>
                    <a href="<?= site_url('settings/api/generate') ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tạo API Token</a>
                <?php endif; ?>
                <div class="mt-3 p-2" style="background:rgba(99,102,241,.08);border-radius:10px;font-size:12.5px;color:var(--muted)">
                    💰 Số dư: <b style="color:#6ee7b7">$<?= number_format($user->saldo) ?></b> — mỗi lần bot mua key sẽ trừ vào số dư này.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Endpoints -->
        <div class="card">
            <div class="card-header"><i class="bi bi-hdd-network"></i> Endpoints</div>
            <div class="card-body">
                <div class="ep-row">
                    <span class="ep-method ep-get">GET</span>
                    <div><div class="ep-url"><?= esc($baseApiUrl) ?>/products</div><div class="ep-desc">Danh sách gói game + giá + số dư</div></div>
                </div>
                <div class="ep-row">
                    <span class="ep-method ep-get">GET</span>
                    <div><div class="ep-url"><?= esc($baseApiUrl) ?>/balance</div><div class="ep-desc">Kiểm tra số dư</div></div>
                </div>
                <div class="ep-row">
                    <span class="ep-method ep-post">POST</span>
                    <div><div class="ep-url"><?= esc($baseApiUrl) ?>/buy</div><div class="ep-desc">Mua key: <code>game</code>, <code>duration</code>, <code>max_devices</code> → trừ tiền, trả key</div></div>
                </div>
                <div class="mt-2" style="font-size:11.5px;color:var(--muted)">
                    🔑 Gửi token qua header <code>Authorization: Bearer &lt;token&gt;</code> hoặc tham số <code>?token=</code>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ((int)$user->level === 1 && !empty($games)): ?>
<!-- Admin: bật/tắt bán API từng game -->
<div class="card">
    <div class="card-header"><i class="bi bi-toggles"></i> Bật/Tắt bán key qua API (theo game)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Game</th><th>Mã</th><th>Bán API</th><th class="text-end">Đổi</th></tr></thead>
                <tbody>
                <?php foreach ($games as $g): $on = !isset($g['api_sale']) || $g['api_sale']; ?>
                    <tr>
                        <td><b><?= esc($g['name']) ?></b></td>
                        <td><span style="font-family:monospace;color:#7db3ff"><?= esc($g['game_code']) ?></span></td>
                        <td><?= $on ? '<span class="text-success">● Mở bán</span>' : '<span class="text-danger">○ Đã tắt</span>' ?></td>
                        <td class="text-end">
                            <a href="<?= site_url('settings/api/game/' . $g['id_game']) ?>" class="btn btn-sm <?= $on?'btn-danger':'btn-success' ?>">
                                <?= $on?'Tắt bán':'Mở bán' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function copyTok(){
    var t = document.getElementById('apiTok').innerText;
    if(navigator.clipboard) navigator.clipboard.writeText(t);
    Toast.fire({ icon:'success', title:'Đã copy token!' });
}
</script>
<?= $this->endSection() ?>
