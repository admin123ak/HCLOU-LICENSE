<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="row">
        <div class="col-lg-12">
            <?= $this->include('Layout/msgStatus') ?>
        </div>
        <div class="col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header" text-white">
                    <div class="row">
                        <div class="col pt-1">
                            Keys Registered
                        </div>
                        <div class="col text-end">
                            <a class="btn btn-outline-light btn-sm" href="<?= site_url('keys/generate') ?>"><i class="bi bi-person-plus"></i> KEY</a>
                            <button class="btn btn-secondary btn-sm ms-1" id="blur-out" data-bs-toggle="tooltip" data-bs-placement="top" title="Eye Protect"><i class="bi bi-eye-slash"></i></button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($keylist) : ?>
                        <div class="table-responsive">
                            <table id="datatable" class="table table-bordered table-hover text-center" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Game</th>
                                        <th>License Key</th>
                                        <th>Thiết bị</th>
                                        <th>Gói</th>
                                        <th>Hết hạn</th>
                                        <th>Trạng thái</th>
                                        <th class="text-end">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keylist as $k):
                                        $devCount = !empty($k['devices']) ? count(array_filter(explode(',', $k['devices']))) : 0;
                                        $isActive = !empty($k['status']);
                                        $expired  = !empty($k['expired_date']);
                                        $expTxt   = $expired ? date('d/m/Y H:i', strtotime($k['expired_date'])) : '';
                                        $isOver   = $expired && strtotime($k['expired_date']) < time();
                                    ?>
                                    <tr>
                                        <td class="text-muted"><?= $k['id_keys'] ?></td>
                                        <td><span class="badge" style="background:rgba(99,102,241,.16);color:#c7d2fe;border:1px solid rgba(99,102,241,.3)"><?= esc($k['game']) ?></span></td>
                                        <td><span class="<?= $isActive?'text-success':'text-danger' ?> keyBlur key-sensi" style="font-family:monospace;font-weight:700"><?= esc($k['user_key']) ?></span></td>
                                        <td><span id="devMax-<?= esc($k['user_key']) ?>" style="font-weight:700;color:#fff"><?= $devCount ?>/<?= (int)$k['max_devices'] ?></span></td>
                                        <td><span style="font-weight:700;color:#fcd34d"><?= (int)$k['duration'] ?>h</span></td>
                                        <td>
                                            <?php if($expired): ?>
                                                <span style="font-weight:700;color:<?= $isOver?'#fca5a5':'#6ee7b7' ?>"><?= $expTxt ?></span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-style:italic">Chưa kích hoạt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($isActive): ?>
                                                <span class="badge" style="background:rgba(52,211,153,.14);color:#6ee7b7;border:1px solid rgba(52,211,153,.3)">● Active</span>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(248,113,113,.14);color:#fca5a5;border:1px solid rgba(248,113,113,.3)">🔒 Khoá</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-end flex-nowrap">
                                                <a href="<?= site_url('keys/toggle/' . $k['id_keys']) ?>" class="btn btn-sm <?= $isActive?'btn-danger':'btn-success' ?>" title="<?= $isActive?'Khoá key':'Mở key' ?>"
                                                   onclick="return confirm('<?= $isActive?'Khoá':'Mở' ?> key này?')"><i class="bi bi-<?= $isActive?'lock-fill':'unlock-fill' ?>"></i></a>
                                                <button class="btn btn-secondary btn-sm" onclick="resetUserKey('<?= esc($k['user_key']) ?>')" title="Reset thiết bị"><i class="bi bi-arrow-repeat"></i></button>
                                                <a href="<?= site_url('keys/' . $k['id_keys']) ?>" class="btn btn-primary btn-sm" title="Sửa key"><i class="bi bi-pencil"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-center">Nothing keys to show</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('css') ?>
<?= link_tag("https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap5.min.css") ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<?= script_tag("https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js") ?>

<?= script_tag("https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap5.min.js") ?>
<script>
    $(document).ready(function() {
        var table = $('#datatable').DataTable({
            order: [
                [0, "desc"]
            ]
        });

        $("#blur-out").click(function() {
            if ($(".keyBlur").hasClass("key-sensi")) {
                $(".keyBlur").removeClass("key-sensi");
                $("#blur-out").html(`<i class="bi bi-eye"></i>`);
            } else {
                $(".keyBlur").addClass("key-sensi");
                $("#blur-out").html(`<i class="bi bi-eye-slash"></i>`);
            }
        });
    });

    function resetUserKey(keys) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, reset'
        }).then((result) => {
            if (result.isConfirmed) {
                Toast.fire({
                    icon: 'info',
                    title: 'Please wait...'
                })

                var api_url = "<?= site_url('keys/reset') ?>";
                $.getJSON(api_url, {
                        userkey: keys,
                        reset: 1
                    },
                    function(data, textStatus, jqXHR) {
                        if (textStatus == 'success') {
                            if (data.registered) {
                                if (data.reset) {
                                    $(`#devMax-${keys}`).html(`0/${data.devices_max}`);
                                    Swal.fire(
                                        'Reset!',
                                        'Your device key has been reset.',
                                        'success'
                                    )
                                } else {
                                    Swal.fire(
                                        'Failed!',
                                        data.devices_total ? "You don't have any access to this user." : "User key devices already reset.",
                                        data.devices_total ? 'error' : 'warning'
                                    )
                                }
                            } else {
                                Swal.fire(
                                    'Failed!',
                                    "User key no longer exists.",
                                    'error'
                                )
                            }
                        }
                    }
                );
            }
        });
    }
</script>

<?= $this->endSection() ?>