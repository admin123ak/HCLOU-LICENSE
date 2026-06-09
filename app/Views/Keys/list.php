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
                                        <th>User Keys</th>
                                        <th>Devices</th>
                                        <th>Duration</th>
                                        <th>Expired</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($keylist as $k):
                                        $devCount = !empty($k['devices']) ? count(array_filter(explode(',', $k['devices']))) : 0;
                                        $statusCls = !empty($k['status']) ? 'text-success' : 'text-danger';
                                        $expTxt = !empty($k['expired_date']) ? date('d/m/y - H:i', strtotime($k['expired_date'])) : '(not started yet)';
                                    ?>
                                    <tr>
                                        <td><?= $k['id_keys'] ?></td>
                                        <td><?= esc($k['game']) ?></td>
                                        <td><span class="<?= $statusCls ?> keyBlur key-sensi"><?= esc($k['user_key']) ?></span></td>
                                        <td><span id="devMax-<?= esc($k['user_key']) ?>"><?= $devCount ?>/<?= (int)$k['max_devices'] ?></span></td>
                                        <td><?= (int)$k['duration'] ?> Hours</td>
                                        <td><?php if(!empty($k['expired_date'])): ?><span class="badge text-dark"><?= $expTxt ?></span><?php else: ?><span class="text-muted">(not started yet)</span><?php endif; ?></td>
                                        <td>
                                            <div class="d-grid gap-2 d-md-block">
                                                <button class="btn btn-outline-danger btn-sm" onclick="resetUserKey('<?= esc($k['user_key']) ?>')" title="Reset key?"><i class="bi bi-bootstrap-reboot"></i></button>
                                                <a href="<?= site_url('keys/' . $k['id_keys']) ?>" class="btn btn-outline-dark btn-sm" title="Edit key"><i class="bi bi-person"></i></a>
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

                var base_url = window.location.origin;
                var api_url = `${base_url}/keys/reset`;
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