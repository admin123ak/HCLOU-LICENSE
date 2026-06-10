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
    ⚠️ <b>Table <code>games</code> does not exist in the database.</b><br>
    Open <code><?= site_url('fix_db.php') ?></code> (or <a href="<?= base_url('fix_db.php') ?>" target="_blank" style="color:#fde68a;text-decoration:underline">click here</a>) to create the table, then come back.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-controller"></i> Manage Games</span>
        <button class="btn btn-primary btn-sm" onclick="gmOpen()"><i class="bi bi-plus-lg"></i> Add game</button>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <p class="text-center text-muted py-4">No games yet. Click "Add game" to create one.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>#</th><th>Game name</th><th>Code (API)</th><th>Packages</th><th>Status</th><th class="text-end">Actions</th></tr>
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
                        <td><?= $g['status'] ? '<span class="text-success">● On</span>' : '<span class="text-danger">○ Off</span>' ?></td>
                        <td class="text-end">
                            <button class="btn btn-primary btn-sm" title="Edit"
                                onclick='gmOpen(<?= json_encode([
                                    "id" => $g["id_game"], "name" => $g["name"], "code" => $g["game_code"],
                                    "status" => (int)$g["status"], "sort" => (int)$g["sort_order"],
                                    "durs" => $durs,
                                ], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= site_url('admin/games/delete') ?>" style="display:inline" onsubmit="return confirm('Delete game <?= esc($g['name']) ?>?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id_game" value="<?= $g['id_game'] ?>">
                                <button class="btn btn-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
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

<!-- Add/Edit modal -->
<div class="gm-modal" id="gmModal">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span id="gmTitle"><i class="bi bi-plus-circle"></i> Add game</span>
            <button class="btn btn-secondary btn-sm" onclick="gmClose()">✕</button>
        </div>
        <form method="post" action="<?= site_url('admin/games/save') ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <input type="hidden" name="id_game" id="gm_id" value="">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label">Game name (display)</label>
                        <input class="form-control" name="name" id="gm_name" placeholder="Free Fire" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">Game code (API)</label>
                        <input class="form-control" name="game_code" id="gm_code" placeholder="FREEFIRE" style="font-family:monospace;text-transform:uppercase" required>
                        <small class="text-muted">UPPERCASE + digits + _ . Client sends this code.</small>
                    </div>
                </div>
                <label class="form-label">Packages (Hours → Price $)</label>
                <div class="gm-rows" id="gmRows"></div>
                <button type="button" class="btn btn-secondary btn-sm mt-1" onclick="gmAddRow()"><i class="bi bi-plus"></i> Add package</button>
                <div class="row mt-3">
                    <div class="col-6 mb-2">
                        <label class="form-label">Sort order</label>
                        <input type="number" class="form-control" name="sort_order" id="gm_sort" value="0">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="gm_status">
                            <option value="1">On</option>
                            <option value="0">Off</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0 d-flex gap-2">
                <button type="button" class="btn btn-secondary w-50" onclick="gmClose()">Cancel</button>
                <button type="submit" class="btn btn-primary w-50"><i class="bi bi-save"></i> Save</button>
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
        '<input type="number" class="form-control" name="d_hours[]" placeholder="Hours (e.g. 24)" value="' + (h||'') + '" min="1">' +
        '<input type="number" step="0.01" class="form-control" name="d_price[]" placeholder="Price $ (e.g. 40)" value="' + (p===0||p?p:'') + '" min="0">' +
        '<button type="button" class="gm-del" onclick="this.parentNode.remove()"><i class="bi bi-x-lg"></i></button>';
    document.getElementById('gmRows').appendChild(row);
}
function gmOpen(data) {
    var rows = document.getElementById('gmRows');
    rows.innerHTML = '';
    if (data) {
        document.getElementById('gmTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit game';
        document.getElementById('gm_id').value = data.id;
        document.getElementById('gm_name').value = data.name;
        document.getElementById('gm_code').value = data.code;
        document.getElementById('gm_sort').value = data.sort;
        document.getElementById('gm_status').value = data.status;
        (data.durs || []).forEach(function(d) { gmAddRow(d.hours, d.price); });
        if (!data.durs || !data.durs.length) gmAddRow();
    } else {
        document.getElementById('gmTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Add game';
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
