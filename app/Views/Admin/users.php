<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header"><i class="bi bi-people"></i> Manage <?= esc($title) ?></div>
    <div class="card-body">
        <div class="mb-3">
            <input type="text" id="userSearch" class="form-control" placeholder="Search username / fullname / uplink..." onkeyup="filterUsers()">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="userTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Fullname</th>
                        <th>Level</th>
                        <th>Saldo</th>
                        <th>Status</th>
                        <th>Uplink</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($user_list)): ?>
                    <?php foreach ($user_list as $u): $isAdmin = ((int)$u->level === 1); ?>
                        <tr>
                            <td class="text-muted"><?= $u->id_users ?></td>
                            <td><b><?= esc($u->username) ?></b></td>
                            <td><?= $u->fullname ? esc($u->fullname) : '<span class="text-muted">~</span>' ?></td>
                            <td><?= function_exists('getLevel') ? getLevel($u->level) : esc($u->level) ?></td>
                            <td><?= $isAdmin ? '<span class="text-muted">∞</span>' : '<span style="color:#6ee7b7">$' . number_format((float)$u->saldo) . '</span>' ?></td>
                            <td><?= (int)$u->status === 1 ? '<span class="text-success">● Active</span>' : '<span class="text-danger">○ Banned</span>' ?></td>
                            <td><?= $u->uplink ? esc($u->uplink) : '<span class="text-muted">~</span>' ?></td>
                            <td class="text-end">
                                <a href="<?= site_url('admin/user/' . $u->id_users) ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted" style="font-size:12.5px">Total: <b><?= !empty($user_list) ? count($user_list) : 0 ?></b> users</p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
function filterUsers(){
    var q = document.getElementById('userSearch').value.toLowerCase();
    document.querySelectorAll('#userTable tbody tr').forEach(function(tr){
        tr.style.display = tr.innerText.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
    });
}
</script>
<?= $this->endSection() ?>
