<?= $this->extend('Layout/Starter') ?>

<?= $this->section('content') ?>

<style>
.dash-kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:18px;margin-bottom:22px}
.kpi{position:relative;overflow:hidden;display:flex;align-items:center;gap:16px;padding:22px;border-radius:18px;
  border:1px solid var(--line);background:linear-gradient(150deg,rgba(24,33,54,.9),rgba(14,20,34,.78));
  box-shadow:var(--glow);transition:.25s cubic-bezier(.4,0,.2,1);animation:lxFade .4s cubic-bezier(.4,0,.2,1) both}
.kpi:hover{transform:translateY(-3px);border-color:var(--line2)}
.kpi::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,var(--c),var(--c2));box-shadow:0 0 18px var(--c)}
.kpi .ico{width:54px;height:54px;flex:0 0 54px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;
  background:linear-gradient(140deg,var(--c),var(--c2));box-shadow:0 8px 22px -8px var(--c)}
.kpi .num{font-family:'Plus Jakarta Sans',sans-serif;font-size:34px;font-weight:800;letter-spacing:-.03em;line-height:1;color:#fff}
.kpi .lbl{font-size:12.5px;color:var(--muted);font-weight:600;margin-top:6px;letter-spacing:.04em}
.dash-cols{display:grid;grid-template-columns:1.4fr 1fr;gap:20px}
.acct-row{display:flex;justify-content:space-between;align-items:center;padding:14px 4px;border-bottom:1px solid var(--line)}
.acct-row:last-child{border-bottom:0}
.acct-row .k{color:var(--muted);font-size:13.5px;font-weight:500;display:flex;align-items:center;gap:9px}
.acct-row .k i{color:var(--cyan);font-size:16px}
.acct-row .v{font-weight:700;font-size:14px;color:#fff}
.role-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:800;
  background:rgba(99,102,241,.16);color:#c7d2fe;border:1px solid rgba(99,102,241,.3)}
.role-pill.admin{background:rgba(52,211,153,.14);color:#6ee7b7;border-color:rgba(52,211,153,.32)}
.saldo-big{font-family:'Plus Jakarta Sans',sans-serif;font-size:40px;font-weight:800;letter-spacing:-.03em;
  background:linear-gradient(135deg,#fff,#bcd4ff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
.saldo-card{text-align:center;padding:6px 0 14px}
.quick-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:4px}
.qa{display:flex;flex-direction:column;align-items:center;gap:7px;padding:18px 10px;border-radius:14px;text-decoration:none;
  border:1px solid var(--line2);background:rgba(255,255,255,.03);color:var(--text);transition:.18s;font-weight:700;font-size:13px;text-align:center}
.qa:hover{background:rgba(99,102,241,.12);border-color:var(--cyan);transform:translateY(-2px);color:#fff}
.qa i{font-size:24px;background:var(--accent);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
@media(max-width:860px){.dash-cols{grid-template-columns:1fr}}
@media(max-width:560px){.dash-kpis{grid-template-columns:1fr}.kpi .num{font-size:28px}}
</style>

<?php $lvl = (int)$user->level; ?>

<?= $this->include('Layout/msgStatus') ?>

<!-- ===== 4 KPI (2x2) ===== -->
<div class="dash-kpis">
    <div class="kpi" style="--c:#6366f1;--c2:#22d3ee">
        <div class="ico"><i class="bi bi-key-fill"></i></div>
        <div><div class="num"><?= number_format($stat['total']) ?></div><div class="lbl">Total keys created</div></div>
    </div>
    <div class="kpi" style="--c:#10b981;--c2:#34d399">
        <div class="ico"><i class="bi bi-check-circle-fill"></i></div>
        <div><div class="num"><?= number_format($stat['active']) ?></div><div class="lbl">Active keys</div></div>
    </div>
    <div class="kpi" style="--c:#f43f5e;--c2:#fb7185">
        <div class="ico"><i class="bi bi-lock-fill"></i></div>
        <div><div class="num"><?= number_format($stat['blocked']) ?></div><div class="lbl">Locked keys</div></div>
    </div>
    <div class="kpi" style="--c:#f59e0b;--c2:#fbbf24">
        <div class="ico"><i class="bi bi-clock-history"></i></div>
        <div><div class="num"><?= number_format($stat['expired']) ?></div><div class="lbl">Expired keys</div></div>
    </div>
</div>

<!-- ===== 2 cột: thông tin tài khoản | số dư + thao tác ===== -->
<div class="dash-cols">
    <div class="card mb-0">
        <div class="card-header"><i class="bi bi-person-badge"></i> Account Info</div>
        <div class="card-body">
            <div class="acct-row">
                <span class="k"><i class="bi bi-person"></i> Username</span>
                <span class="v"><?= esc(getName($user)) ?></span>
            </div>
            <div class="acct-row">
                <span class="k"><i class="bi bi-shield-check"></i> Role</span>
                <span class="v"><span class="role-pill <?= $lvl==1?'admin':'' ?>"><i class="bi bi-<?= $lvl==1?'star-fill':'person-fill' ?>"></i> <?= getLevel($user->level) ?></span></span>
            </div>
            <div class="acct-row">
                <span class="k"><i class="bi bi-diagram-3"></i> Uplink</span>
                <span class="v"><?= esc($user->uplink ?? '—') ?></span>
            </div>
            <div class="acct-row">
                <span class="k"><i class="bi bi-toggle-on"></i> Status</span>
                <span class="v"><?= $user->status ? '<span class="text-success">● Active</span>' : '<span class="text-danger">● Inactive</span>' ?></span>
            </div>
            <div class="acct-row">
                <span class="k"><i class="bi bi-clock"></i> Logged in at</span>
                <span class="v"><?= session()->has('time_since') ? $time::parse(session()->time_since)->humanize() : '—' ?></span>
            </div>
        </div>
    </div>

    <div class="card mb-0">
        <div class="card-header"><i class="bi bi-wallet2"></i> Balance & Actions</div>
        <div class="card-body">
            <div class="saldo-card">
                <div class="saldo-big">$<?= number_format($user->saldo) ?></div>
                <div class="lbl" style="color:var(--muted);font-size:12.5px;margin-top:6px">Available balance</div>
            </div>
            <div class="quick-actions">
                <a class="qa" href="<?= site_url('keys/generate') ?>"><i class="bi bi-plus-circle"></i> Create Key</a>
                <a class="qa" href="<?= site_url('keys') ?>"><i class="bi bi-list-ul"></i> Key list</a>
                <?php if($lvl==1): ?>
                <a class="qa" href="<?= site_url('admin/manage-users') ?>"><i class="bi bi-people"></i> Users</a>
                <a class="qa" href="<?= site_url('admin/create-referral') ?>"><i class="bi bi-person-plus"></i> Referral</a>
                <?php else: ?>
                <a class="qa" href="<?= site_url('settings') ?>" style="grid-column:1/-1"><i class="bi bi-gear"></i> Account settings</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
