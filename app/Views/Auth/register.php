<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>

<div style="width:420px;max-width:100%">
        <?= $this->include('Layout/msgStatus') ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header">
                <i class="bi bi-person-plus"></i> Đăng ký
            </div>
            <div class="card-body">
                <?= form_open() ?>

                <div class="form-group mb-3">
                    <label for="username">Tài khoản</label>
                    <input type="text" class="form-control mt-2" name="username" id="username" aria-describedby="help-username" placeholder="Your username" minlength="4" maxlength="24" value="<?= old('username') ?>" required>
                    <?php if ($validation->hasError('username')) : ?>
                        <small id="help-username" class="form-text text-danger"><?= $validation->getError('username') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group mb-3">
                    <label for="password">Mật khẩu</label>
                    <input type="password" class="form-control mt-2" name="password" id="password" aria-describedby="help-password" placeholder="Your password" minlength="6" maxlength="24" required>
                    <?php if ($validation->hasError('password')) : ?>
                        <small id="help-password" class="form-text text-danger"><?= $validation->getError('password') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group mb-3">
                    <label for="password2">Nhập lại mật khẩu</label>
                    <input type="password" name="password2" id="password2" class="form-control mt-2" placeholder="Confirm password" aria-describedby="help-password2" minlength="6" maxlength="24" required>
                    <?php if ($validation->hasError('password2')) : ?>
                        <small id="help-password2" class="form-text text-danger"><?= $validation->getError('password2') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group mb-3">
                    <label for="referral">Mã giới thiệu (Referral)</label>
                    <input type="text" name="referral" id="referral" class="form-control mt-2" placeholder="Referral code" aria-describedby="help-referral" value="<?= old('referral') ?>" maxlength="25" required>
                    <?php if ($validation->hasError('referral')) : ?>
                        <small id="help-referral" class="form-text text-danger"><?= $validation->getError('referral') ?></small>
                    <?php endif; ?>
                </div>
                <div class="form-group mb-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Đăng ký</button>
                </div>
                <?= form_close() ?>

            </div>
        </div>
        <p class="text-center after-card">
            <small>
                Đã có tài khoản?
                <a href="<?= site_url('login') ?>">Đăng nhập tại đây</a>
            </small>
        </p>
</div>

<?= $this->endSection() ?>