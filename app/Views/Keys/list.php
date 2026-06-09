<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="row">
        <div class="col-lg-12">
            <?= $this->include('Layout/msgStatus') ?>
        </div>
        <div class="col-lg-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-key"></i> Danh sách Key</span>
                    <a class="btn btn-primary btn-sm" href="<?= site_url('keys/generate') ?>"><i class="bi bi-plus-lg"></i> Tạo Key</a>
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
                                        <td>
                                            <span class="<?= $isActive?'text-success':'text-danger' ?>" style="font-family:monospace;font-weight:700;cursor:pointer" onclick="copyKey('<?= esc($k['user_key']) ?>')" title="Bấm để copy"><?= esc($k['user_key']) ?> <i class="bi bi-clipboard" style="font-size:11px;opacity:.6"></i></span>
                                        </td>
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
                                                <button type="button" class="btn btn-sm <?= $isActive?'btn-danger':'btn-success' ?>" title="<?= $isActive?'Khoá key':'Mở key' ?>"
                                                   onclick="toggleKey(<?= (int)$k['id_keys'] ?>, <?= $isActive?'true':'false' ?>, '<?= esc($k['user_key']) ?>')"><i class="bi bi-<?= $isActive?'lock-fill':'unlock-fill' ?>"></i></button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="resetUserKey('<?= esc($k['user_key']) ?>')" title="Reset thiết bị"><i class="bi bi-arrow-repeat"></i></button>
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
    // Cấu hình SweetAlert đồng bộ theme tối
    var SW = { background:'#161f33', color:'#eef3fb', confirmButtonColor:'#6366f1', cancelButtonColor:'#475569' };

    // URL base lấy từ chính URL hiện tại (cùng origin + đúng subfolder) -> tránh sai domain/https
    // Trang này luôn là .../keys -> cắt phần sau '/keys' để có base, rồi nối lại.
    var KEYS_BASE = (function(){
        var u = window.location.pathname;
        var i = u.indexOf('/keys');
        return (i >= 0 ? u.substring(0, i) : '') + '/keys';
    })();
    var RESET_URL  = KEYS_BASE + '/reset';
    var TOGGLE_URL = KEYS_BASE + '/toggle/';

    $(document).ready(function() {
        $('#datatable').DataTable({ order: [[0, "desc"]] });
    });

    // Copy license key
    function copyKey(k){
        if(navigator.clipboard){ navigator.clipboard.writeText(k); }
        else { var t=document.createElement('textarea'); t.value=k; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
        Toast.fire({ icon:'success', title:'Đã copy key!' });
    }

    // Reset thiết bị của key
    function resetUserKey(keys) {
        Swal.fire(Object.assign({}, SW, {
            title: 'Reset thiết bị?',
            text: "Key này sẽ được gỡ khỏi tất cả thiết bị đã đăng nhập.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-arrow-repeat"></i> Reset',
            cancelButtonText: 'Huỷ'
        })).then(function(result) {
            if (!result.isConfirmed) return;
            Swal.fire(Object.assign({}, SW, { title:'Đang xử lý...', didOpen:function(){Swal.showLoading();}, allowOutsideClick:false }));
            $.ajax({
                url: RESET_URL,
                method: 'GET',
                data: { userkey: keys, reset: 1 },
                dataType: 'json'
            }).done(function(data){
                if (data && data.registered) {
                    if (data.reset) {
                        $('#devMax-' + keys).html('0/' + data.devices_max);
                        Swal.fire(Object.assign({}, SW, { title:'Thành công!', text:'Đã reset thiết bị về 0.', icon:'success' }));
                    } else {
                        Swal.fire(Object.assign({}, SW, {
                            title: 'Không thể reset',
                            text: data.devices_total ? 'Bạn không có quyền với key này.' : 'Key chưa có thiết bị nào để reset.',
                            icon: data.devices_total ? 'error' : 'info'
                        }));
                    }
                } else {
                    Swal.fire(Object.assign({}, SW, { title:'Lỗi', text:'Key không tồn tại.', icon:'error' }));
                }
            }).fail(function(xhr){
                var msg = 'Không gọi được server (mã '+xhr.status+').';
                if (xhr.status === 0) msg = 'Không kết nối được tới: ' + RESET_URL + ' — kiểm tra baseURL / https.';
                else if (xhr.status === 404) msg = 'Không tìm thấy route reset (404). Kiểm tra URL: ' + RESET_URL;
                else if (xhr.status === 403) msg = 'Bị từ chối (403). Có thể chưa đăng nhập.';
                else if (xhr.responseText) msg += ' ' + String(xhr.responseText).substring(0,120);
                Swal.fire(Object.assign({}, SW, { title:'Lỗi kết nối', text:msg, icon:'error' }));
            });
        });
    }

    // Khoá / mở key
    function toggleKey(id, isActive, keyName) {
        Swal.fire(Object.assign({}, SW, {
            title: isActive ? 'Khoá key này?' : 'Mở key này?',
            text: isActive ? 'Người dùng sẽ không thể đăng nhập bằng key này.' : 'Key sẽ hoạt động trở lại.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: isActive ? '<i class="bi bi-lock-fill"></i> Khoá' : '<i class="bi bi-unlock-fill"></i> Mở',
            cancelButtonText: 'Huỷ',
            confirmButtonColor: isActive ? '#dc2626' : '#10b981'
        })).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = TOGGLE_URL + id;
            }
        });
    }
</script>

<?= $this->endSection() ?>