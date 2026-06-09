<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>

<div style="width:420px;max-width:100%">
        <?= $this->include('Layout/msgStatus') ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header">
                <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
            </div>
            <div class="card-body">
                <?= form_open() ?>
                <div class="form-group mb-3">
                    <label for="username">Username</label>
                    <input type="text" class="form-control mt-2" name="username" id="username" aria-describedby="help-username" placeholder="Your username" required minlength="4">
                    <?php if ($validation->hasError('username')) : ?>
                        <small id="help-username" class="form-text text-danger"><?= $validation->getError('username') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group mb-3">
                    <label for="password">Password</label>
                    <input type="password" class="form-control mt-2" name="password" id="password" aria-describedby="help-password" placeholder="Your password" required minlength="6">
                    <?php if ($validation->hasError('password')) : ?>
                        <small id="help-password" class="form-text text-danger"><?= $validation->getError('password') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-check mb-3">
                    <label class="form-check-label" data-bs-toggle="tooltip" data-bs-placement="top" title="Keep session more than 30 minutes">
                        <input type="checkbox" class="form-check-input" name="stay_log" id="stay_log" value="yes">
                        Stay login?
                    </label>
                </div>
                <div class="form-group mb-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</button>
                </div>
                <?= form_close() ?>
            </div>
        </div>
        <p class="text-center after-card">
            <small>
                Chưa có tài khoản?
                <a href="<?= site_url('register') ?>">Đăng ký tại đây</a>
            </small>
        </p>
</div>

<?= $this->endSection() ?>