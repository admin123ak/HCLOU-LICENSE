<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>

<style>
.gm-rows .gm-line{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:8px}
.gm-rows input{margin:0}
.gm-del{background:rgba(248,113,113,.14);border:1px solid rgba(248,113,113,.3);color:#fca5a5;border-radius:9px;width:42px;cursor:pointer}
.gm-modal{position:fixed;inset:0;z-index:1100;display:none;align-items:flex-start;justify-content:center;padding:40px 16px;background:rgba(6,10,20,.72);backdrop-filter:blur(6px);overflow-y:auto}
.gm-modal.show{display:flex}
.gm-modal .card{width:560px;max-width:100%;margin:auto}
.dur-badge{display:inline-block;background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.3);color:#c7d2fe;border-radius:7px;padding:2px 8px;font-size:11px;font-weight:700;margin:2px}
</style>

<?= $this->include('Layout/msgStatus') ?>

<?php if (!empty($noTable)): ?>
<div class="alert alert-warning">
    ⚠️ <b>Bảng <code>games</code> chưa tồn tại trong database.</b><br>
    Hãy mở <code><?= site_url('fix_db.php') ?></code> (hoặc <a href="<?= base_url('fix_db.php') ?>" target="_blank" style="color:#fde68a;text-decoration:underline">bấm vào đây</a>) để tạo bảng, rồi quay lại trang này.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-controller"></i> Quản lý Game</span>
        <button class="btn btn-primary btn-sm" onclick="gmOpen()"><i class="bi bi-plus-lg"></i> Thêm game</button>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <p class="text-center text-muted py-4">Chưa có game nào. Bấm "Thêm game" để tạo.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>#</th><th>Tên game</th><th>Mã (API)</th><th>Gói cước</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr>
                </thead>
                <tbody>
                <?php foreach ($games as $g):
                    $durs = \App\Models\GameModel::parseDurations($g['durations']);
                ?>
                    <tr>
                        <td class="text-muted"><?= $g['id_game'] ?></td>
                        <td><b><?= esc($g['name']) ?></b></td>
                        <td><span class="dur-badge" style="font-family:monospace"><?= esc($g['game_code']) ?></span></td>
                        <td>
                            <?php foreach ($durs as $d): ?>
                                <span class="dur-badge"><?= $d['hours'] ?>h = $<?= rtrim(rtrim(number_format($d['price'],2,'.',''),'0'),'.') ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?= $g['status'] ? '<span class="text-success">● Bật</span>' : '<span class="text-danger">○ Tắt</span>' ?></td>
                        <td class="text-end">
                            <button class="btn btn-primary btn-sm" title="Sửa"
                                onclick='gmOpen(<?= json_encode([
                                    "id" => $g["id_game"], "name" => $g["name"], "code" => $g["game_code"],
                                    "status" => (int)$g["status"], "sort" => (int)$g["sort_order"],
                                    "durs" => $durs,
                                ], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= site_url('admin/games/delete') ?>" style="display:inline" onsubmit="return confirm('Xoá game <?= esc($g['name']) ?>?')">
                                <input type="hidden" name="id_game" value="<?= $g['id_game'] ?>">
                                <button class="btn btn-danger btn-sm" title="Xoá"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal thêm/sửa -->
<div class="gm-modal" id="gmModal">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span id="gmTitle"><i class="bi bi-plus-circle"></i> Thêm game</span>
            <button class="btn btn-secondary btn-sm" onclick="gmClose()">✕</button>
        </div>
        <form method="post" action="<?= site_url('admin/games/save') ?>">
            <div class="card-body">
                <input type="hidden" name="id_game" id="gm_id" value="">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label">Tên game (hiển thị)</label>
                        <input class="form-control" name="name" id="gm_name" placeholder="Free Fire" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Mã game (API)</label>
                        <input class="form-control" name="game_code" id="gm_code" placeholder="FREEFIRE" style="font-family:monospace;text-transform:uppercase" required>
                        <small class="text-muted">CHỮ HOA + số + _ . Client gửi mã này.</small>
                    </div>
                </div>
                <label class="form-label">Gói cước (Số giờ → Giá $)</label>
                <div class="gm-rows" id="gmRows"></div>
                <button type="button" class="btn btn-secondary btn-sm mt-1" onclick="gmAddRow()"><i class="bi bi-plus"></i> Thêm gói</button>
                <div class="row mt-3">
                    <div class="col-6 mb-2">
                        <label class="form-label">Thứ tự</label>
                        <input type="number" class="form-control" name="sort_order" id="gm_sort" value="0">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="status" id="gm_status">
                            <option value="1">Bật</option>
                            <option value="0">Tắt</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0 d-flex gap-2">
                <button type="button" class="btn btn-secondary w-50" onclick="gmClose()">Huỷ</button>
                <button type="submit" class="btn btn-primary w-50"><i class="bi bi-save"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function gmAddRow(h, p) {
    var row = document.createElement('div');
    row.className = 'gm-line';
    row.innerHTML =
        '<input type="number" class="form-control" name="d_hours[]" placeholder="Số giờ (vd 24)" value="' + (h||'') + '" min="1">' +
        '<input type="number" step="0.01" class="form-control" name="d_price[]" placeholder="Giá $ (vd 40)" value="' + (p===0||p?p:'') + '" min="0">' +
        '<button type="button" class="gm-del" onclick="this.parentNode.remove()"><i class="bi bi-x-lg"></i></button>';
    document.getElementById('gmRows').appendChild(row);
}
function gmOpen(data) {
    var rows = document.getElementById('gmRows');
    rows.innerHTML = '';
    if (data) {
        document.getElementById('gmTitle').innerHTML = '<i class="bi bi-pencil"></i> Sửa game';
        document.getElementById('gm_id').value = data.id;
        document.getElementById('gm_name').value = data.name;
        document.getElementById('gm_code').value = data.code;
        document.getElementById('gm_sort').value = data.sort;
        document.getElementById('gm_status').value = data.status;
        (data.durs || []).forEach(function(d) { gmAddRow(d.hours, d.price); });
        if (!data.durs || !data.durs.length) gmAddRow();
    } else {
        document.getElementById('gmTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Thêm game';
        ['gm_id','gm_name','gm_code'].forEach(function(id){document.getElementById(id).value='';});
        document.getElementById('gm_sort').value = 0;
        document.getElementById('gm_status').value = 1;
        gmAddRow(1, 10); gmAddRow(24, 40); gmAddRow(720, 500);
    }
    document.getElementById('gmModal').classList.add('show');
}
function gmClose() { document.getElementById('gmModal').classList.remove('show'); }
document.getElementById('gmModal').addEventListener('click', function(e){ if(e.target===this) gmClose(); });
</script>
<?= $this->endSection() ?>
