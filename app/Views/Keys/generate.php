<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <?= $this->include('Layout/msgStatus') ?>
        <?php if (session()->getFlashdata('user_key')) : ?>
            <div class="alert alert-success" role="alert">
                Game : <?= session()->getFlashdata('game') ?> / <?= session()->getFlashdata('duration') ?> Hours<br>
                License :
                <strong id="genKey" style="font-size:16px;letter-spacing:.5px;background:#fff;color:#111;padding:2px 8px;border-radius:6px;font-family:monospace"><?= session()->getFlashdata('user_key') ?></strong>
                <button type="button" class="btn btn-sm btn-dark" style="padding:1px 8px" onclick="(function(b){var k=document.getElementById('genKey').innerText;navigator.clipboard&&navigator.clipboard.writeText(k);b.innerText='✓ Copied';setTimeout(function(){b.innerText='Copy'},1200);})(this)">Copy</button><br>
                Available for <?= session()->getFlashdata('max_devices') ?> Devices<br>
                <small>
                    <i>Duration will start when license login.</i><br>
                    <i class="bi bi-wallet"></i> Saldo Reduce :
                    <span class="text-danger">-<?= session()->getFlashdata('fees') ?></span>
                    (Total left <?= $user->saldo ?>$)
                </small>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header p3" text-white">
                <div class="row">
                    <div class="col pt-1">
                        Create License
                    </div>
                    <div class="col text-end">
                        <a class="btn btn-sm btn-outline-light" href="<?= site_url('keys') ?>"><i class="bi bi-people"></i></a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?= form_open() ?>

                <div class="row">
                    <div class="form-group col-lg-6 mb-3">
                        <label for="game" class="form-label">Games</label>
                        <?= form_dropdown(['class' => 'form-select', 'name' => 'game', 'id' => 'game'], $game, old('game') ?: '') ?>
                        <?php if ($validation->hasError('game')) : ?>
                            <small id="help-game" class="text-danger"><?= $validation->getError('game') ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group col-lg-6 mb-3">
                        <label for="max_devices" class="form-label">Max Devices</label>
                        <input type="number" name="max_devices" id="max_devices" class="form-control" placeholder="1" value="<?= old('max_devices') ?: 1 ?>">
                        <?php if ($validation->hasError('game')) : ?>
                            <small id="help-max_devices" class="text-danger"><?= $validation->getError('max_devices') ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label for="duration" class="form-label">Duration</label>
                    <select class="form-select" name="duration" id="duration"></select>
                    <?php if ($validation->hasError('duration')) : ?>
                        <small id="help-duration" class="text-danger"><?= $validation->getError('duration') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group mb-3">
                    <label for="estimation" class="form-label">Estimation</label>
                    <input type="text" id="estimation" class="form-control" placeholder="Your order will total" readonly>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-outline-dark">Generate</button>
                </div>
                <?= form_close() ?>

            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    // Map gói + giá theo từng game (key = game_code)
    var DURATIONS = <?= json_encode($durationsByGame) ?>;
    var PRICES = JSON.parse('<?= $pricesByGame ?>');

    $(document).ready(function() {
        fillDurations();
        estimate();

        $("#game").change(function() { fillDurations(); estimate(); });
        $("#max_devices, #duration").on('change input', estimate);

        // Đổ danh sách gói (duration) theo game đang chọn
        function fillDurations() {
            var g = $("#game").val();
            var list = DURATIONS[g] || {};
            var $d = $("#duration").empty();
            var keys = Object.keys(list);
            if (!keys.length) {
                $d.append('<option value="">-- No package --</option>');
                return;
            }
            keys.forEach(function(h) {
                $d.append('<option value="' + h + '">' + list[h] + '</option>');
            });
        }

        // Tính tạm tính = giá gói × số thiết bị
        function estimate() {
            var g = $("#game").val();
            var device = parseInt($("#max_devices").val(), 10) || 0;
            var durate = $("#duration").val();
            var priceTbl = PRICES[g] || {};
            var gprice = parseFloat(priceTbl[durate]);
            if (!isNaN(gprice)) {
                $("#estimation").val('$' + (device * gprice));
            } else {
                $("#estimation").val('—');
            }
        }
    });
</script>
<?= $this->endSection() ?>