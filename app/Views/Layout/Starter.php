<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= BASE_NAME ?> - <?= isset($title) ? $title : 'Panel' ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Fonts + icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.1.0/sweetalert2.min.css" rel="stylesheet">

    <?= $this->renderSection('css') ?>

    <!-- ============ LUXURY THEME (inline để LUÔN load, không phụ thuộc baseURL) ============ -->
    <style>
    :root{
      --bg0:#070a12;--bg1:#0a0f1c;--panel-solid:#141c2e;
      --line:rgba(120,150,200,.14);--line2:rgba(130,165,220,.26);
      --text:#eef3fb;--muted:#8ba0c4;--gold:#e8c879;
      --blue:#5b9dff;--cyan:#3ed6e0;--violet:#a78bfa;--green:#34d399;--red:#f87171;--orange:#fbbf24;
      --accent:linear-gradient(135deg,#6366f1,#22d3ee);
      --glow:0 0 0 1px rgba(255,255,255,.04),0 22px 60px -18px rgba(0,0,0,.7);
      --radius:18px;--sidebar-w:248px;
    }
    *{-webkit-tap-highlight-color:transparent}
    body{
      font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif!important;
      background:var(--bg0)!important;color:var(--text)!important;
      -webkit-font-smoothing:antialiased;letter-spacing:.01em;min-height:100vh;position:relative;margin:0;
    }
    body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
      background:
        radial-gradient(1100px 620px at 80% -8%,rgba(99,102,241,.16),transparent 60%),
        radial-gradient(900px 540px at 6% 8%,rgba(34,211,238,.1),transparent 55%),
        radial-gradient(700px 700px at 95% 100%,rgba(167,139,250,.1),transparent 60%),
        linear-gradient(180deg,var(--bg0),var(--bg1) 45%,var(--bg0));}

    /* ===== LAYOUT: sidebar + main ===== */
    .lx-shell{display:flex;min-height:100vh}
    .lx-sidebar{
      width:var(--sidebar-w);flex-shrink:0;position:fixed;top:0;left:0;bottom:0;z-index:1040;
      background:linear-gradient(180deg,rgba(12,18,32,.98),rgba(8,12,22,.99));
      border-right:1px solid var(--line);backdrop-filter:blur(20px);
      display:flex;flex-direction:column;overflow-y:auto;transition:transform .3s cubic-bezier(.4,0,.2,1);
    }
    .lx-main{flex:1;margin-left:var(--sidebar-w);min-width:0;display:flex;flex-direction:column;min-height:100vh}
    .lx-topbar{
      height:62px;display:flex;align-items:center;gap:14px;padding:0 22px;position:sticky;top:0;z-index:1030;
      background:linear-gradient(180deg,rgba(13,19,33,.92),rgba(10,15,28,.78));
      backdrop-filter:blur(22px) saturate(1.4);border-bottom:1px solid var(--line);
    }
    .lx-content{flex:1;padding:26px 26px 60px}
    .lx-content .container,.lx-content .container-fluid{max-width:100%!important;padding:0!important;background:transparent!important}

    /* Sidebar brand */
    .lx-brand{padding:20px 20px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:11px;text-decoration:none}
    .lx-brand .lx-logo{width:42px;height:42px;border-radius:13px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;box-shadow:0 0 26px rgba(99,102,241,.5)}
    .lx-brand .lx-name{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;background:var(--accent);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
    .lx-brand .lx-sub{font-size:9.5px;color:var(--muted);letter-spacing:.14em;text-transform:uppercase;margin-top:3px}

    /* Sidebar nav */
    .lx-navgroup{padding:14px 0 4px}
    .lx-navlabel{font-size:9.5px;font-weight:800;color:#566685;padding:6px 20px;letter-spacing:.16em;text-transform:uppercase}
    .lx-navitem{display:flex;align-items:center;gap:12px;margin:1px 12px 1px 0;padding:10px 18px;border-radius:0 13px 13px 0;
      color:#a7b6d2;text-decoration:none;font-size:13.5px;font-weight:600;transition:.18s cubic-bezier(.4,0,.2,1);position:relative}
    .lx-navitem i{font-size:16px;width:20px;text-align:center}
    .lx-navitem:hover{background:rgba(255,255,255,.045);color:#fff;transform:translateX(2px)}
    .lx-navitem.active{background:linear-gradient(90deg,rgba(99,102,241,.22),rgba(34,211,238,.05));color:#fff;font-weight:800}
    .lx-navitem.active::before{content:"";position:absolute;left:0;top:8px;bottom:8px;width:3px;background:var(--accent);border-radius:0 4px 4px 0;box-shadow:0 0 14px rgba(99,102,241,.7)}
    .lx-navitem.danger:hover{background:rgba(248,113,113,.14);color:#fca5a5}

    /* Topbar user */
    .lx-burger{background:none;border:1px solid var(--line2);color:#cdd9ee;width:40px;height:40px;border-radius:11px;display:none;align-items:center;justify-content:center;cursor:pointer;font-size:18px}
    .lx-topbar-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#fff;flex:1}
    .lx-userchip{display:flex;align-items:center;gap:9px;padding:6px 8px 6px 12px;border:1px solid var(--line2);border-radius:999px;background:rgba(255,255,255,.04);color:var(--text);text-decoration:none;font-size:13px;font-weight:600}
    .lx-userchip .av{width:30px;height:30px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px}

    /* ===== Bootstrap overrides ===== */
    .container{max-width:1180px!important}
    .card{background:linear-gradient(160deg,rgba(26,36,58,.85),rgba(16,23,39,.72))!important;border:1px solid var(--line)!important;border-radius:var(--radius)!important;box-shadow:var(--glow)!important;backdrop-filter:blur(12px);overflow:hidden;margin-bottom:20px;animation:lxFade .4s cubic-bezier(.4,0,.2,1) both}
    .card-header{background:linear-gradient(180deg,rgba(99,102,241,.12),transparent)!important;border-bottom:1px solid var(--line)!important;color:#eaf1fc!important;font-family:'Plus Jakarta Sans',sans-serif!important;font-weight:800!important;font-size:15px!important;padding:16px 20px!important;position:relative}
    .card-header::after{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent)}
    .card-body{padding:20px!important;color:var(--text)}

    .btn{font-weight:700!important;border-radius:11px!important;transition:.18s cubic-bezier(.4,0,.2,1)!important;padding:9px 16px!important;border:1px solid transparent}
    .btn:active{transform:scale(.97)}
    .btn-primary,.btn-outline-dark,.btn-outline-secondary,.btn-dark{background:var(--accent)!important;color:#fff!important;border:0!important;box-shadow:0 10px 26px -10px rgba(79,70,229,.6)}
    .btn-primary:hover,.btn-outline-dark:hover,.btn-outline-secondary:hover,.btn-dark:hover{filter:brightness(1.1);transform:translateY(-1.5px);color:#fff!important}
    .btn-success{background:linear-gradient(135deg,#059669,#10b981)!important;color:#fff!important;border:0!important}
    .btn-danger,.btn-outline-danger{background:linear-gradient(135deg,#dc2626,#f43f5e)!important;color:#fff!important;border:0!important}
    .btn-secondary,.btn-outline-light{background:rgba(255,255,255,.06)!important;color:#e6edf3!important;border:1px solid var(--line2)!important}
    .btn-secondary:hover,.btn-outline-light:hover{background:rgba(255,255,255,.12)!important;color:#fff!important}
    .btn-sm{padding:6px 11px!important;font-size:12.5px!important;border-radius:9px!important}

    .form-label,label{color:#9fb4d6!important;font-size:12.5px!important;font-weight:700!important;margin-bottom:6px}
    .form-control,.form-select{background:rgba(7,11,20,.8)!important;border:1px solid var(--line2)!important;border-radius:11px!important;color:var(--text)!important;font-size:14px!important;padding:11px 14px!important}
    .form-control:focus,.form-select:focus{border-color:var(--cyan)!important;box-shadow:0 0 0 3px rgba(62,214,224,.15)!important;background:rgba(7,11,20,.95)!important;color:var(--text)!important}
    .form-control::placeholder{color:#5d6f8e!important}
    .form-select option{background:#0c1322!important;color:var(--text)}
    .form-check-input{background-color:rgba(7,11,20,.8)!important;border-color:var(--line2)!important}
    .form-check-input:checked{background:var(--accent)!important;border-color:transparent!important}
    .form-check-label{color:var(--muted)!important;font-weight:500}

    .table{color:var(--text)!important;margin-bottom:0}
    .table>:not(caption)>*>*{background:transparent!important;border-bottom-color:rgba(120,150,200,.1)!important;padding:12px 14px!important;color:var(--text)!important}
    .table thead th{color:#9fb4d6!important;font-size:11px!important;font-weight:800!important;text-transform:uppercase;letter-spacing:.06em;background:rgba(16,23,40,.6)!important;border-bottom:1px solid var(--line2)!important}
    .table-hover>tbody>tr:hover>*{background:rgba(99,102,241,.07)!important}
    .table-bordered,.table-bordered td,.table-bordered th{border-color:var(--line)!important}

    .dataTables_wrapper{color:var(--muted)!important;padding-top:6px}
    .dataTables_wrapper .dataTables_filter input,.dataTables_wrapper .dataTables_length select{background:rgba(7,11,20,.8)!important;border:1px solid var(--line2)!important;border-radius:9px!important;color:var(--text)!important;padding:5px 10px!important}
    .dataTables_wrapper .dataTables_info,.dataTables_wrapper label{color:var(--muted)!important}
    .dataTables_wrapper .paginate_button{color:var(--muted)!important;border-radius:8px!important}
    .dataTables_wrapper .paginate_button.current,.dataTables_wrapper .paginate_button:hover{background:var(--accent)!important;color:#fff!important;border:0!important}
    .page-link{background:transparent!important;border-color:var(--line)!important;color:var(--muted)!important}
    .page-item.active .page-link{background:var(--accent)!important;border:0!important}

    .badge{font-weight:700!important;border-radius:999px!important;padding:4px 10px!important;font-size:11px!important}
    .text-success,.badge.text-success{color:#6ee7b7!important}
    .text-danger,.badge.text-danger{color:#fca5a5!important}
    .text-warning,.badge.text-warning{color:#fcd34d!important}
    .text-info,.badge.text-info{color:#67e8f9!important}
    .text-primary,.badge.text-primary{color:#93c5fd!important}
    .text-muted{color:#5f7390!important}
    .text-white{color:var(--text)!important}

    .list-group-item{background:rgba(7,11,20,.4)!important;border-color:var(--line)!important;color:var(--text)!important;font-size:13.5px;padding:12px 15px!important}
    .list-group-item-action:hover{background:rgba(99,102,241,.1)!important}

    .alert{border-radius:13px!important;border:1px solid var(--line2)!important;font-weight:600;font-size:13.5px}
    .alert-success{background:linear-gradient(135deg,rgba(16,185,129,.14),rgba(16,185,129,.05))!important;border-color:rgba(52,211,153,.3)!important;color:#a7f3d0!important}
    .alert-danger{background:linear-gradient(135deg,rgba(248,113,113,.14),rgba(248,113,113,.05))!important;border-color:rgba(248,113,113,.3)!important;color:#fca5a5!important}
    .alert-warning{background:linear-gradient(135deg,rgba(251,191,36,.13),rgba(251,191,36,.04))!important;border-color:rgba(251,191,36,.3)!important;color:#fde68a!important}
    .alert-secondary{background:rgba(99,102,241,.1)!important;border-color:var(--line2)!important;color:#c7d4ea!important}

    /* Không làm mờ key nữa - hiện rõ luôn */
    .key-sensi{filter:none}

    .after-card small{background:rgba(255,255,255,.04)!important;border:1px solid var(--line2);color:var(--muted)!important;border-radius:999px;display:inline-block;padding:7px 16px!important}
    .after-card a{color:var(--cyan)!important;text-decoration:none;font-weight:700}

    ::-webkit-scrollbar{width:9px;height:9px}
    ::-webkit-scrollbar-track{background:transparent}
    ::-webkit-scrollbar-thumb{background:rgba(120,150,200,.22);border-radius:99px;border:2px solid transparent;background-clip:padding-box}
    ::-webkit-scrollbar-thumb:hover{background:rgba(120,150,200,.4);background-clip:padding-box}
    @keyframes lxFade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
    .swal2-popup{background:linear-gradient(180deg,#161f33,#0e1422)!important;color:var(--text)!important;border:1px solid var(--line2)!important;border-radius:var(--radius)!important}
    .swal2-title,.swal2-html-container{color:var(--text)!important}
    .lx-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1035;opacity:0;pointer-events:none;transition:.3s}
    .lx-overlay.show{opacity:1;pointer-events:auto}

    @media(max-width:992px){
      .lx-sidebar{transform:translateX(-100%)}
      .lx-sidebar.open{transform:translateX(0);box-shadow:6px 0 40px rgba(0,0,0,.6)}
      .lx-main{margin-left:0}
      .lx-burger{display:flex}
      .lx-content{padding:18px 14px 60px}
    }

    /* ===== Trang Auth (chưa đăng nhập) ===== */
    .lx-main.guest{margin-left:0}
    .lx-auth-wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
    .lx-auth-logo{width:74px;height:74px;border-radius:22px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:34px;color:#fff;margin-bottom:16px;box-shadow:0 0 40px rgba(99,102,241,.55);animation:lxFloat 4s ease-in-out infinite}
    .lx-auth-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:26px;background:linear-gradient(135deg,#fff,#c7d2fe);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:4px}
    .lx-auth-sub{color:var(--muted);font-size:13px;margin-bottom:22px;letter-spacing:.03em}
    .lx-auth-wrap .card{width:420px;max-width:100%}
    @keyframes lxFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
</head>

<body>
<?php
$loggedIn = session()->has('userid');
// Fallback an toàn: nếu view nào quên truyền $user, lấy từ session/model để sidebar không vỡ
if ($loggedIn && (!isset($user) || !is_object($user))) {
    try { $user = (new \App\Models\UserModel())->getUser(); } catch (\Throwable $e) { $user = null; }
}
?>
<div class="lx-shell">
    <?php if ($loggedIn): ?>
        <!-- ===== SIDEBAR ===== -->
        <aside class="lx-sidebar" id="lxSidebar">
            <a class="lx-brand" href="<?= site_url() ?>">
                <span class="lx-logo"><i class="bi bi-shield-lock-fill"></i></span>
                <span><span class="lx-name"><?= BASE_NAME ?></span><div class="lx-sub">License Panel</div></span>
            </a>
            <?php
                $curPath = trim(parse_url(current_url(), PHP_URL_PATH) ?? '', '/');
                $isActive = function($needle) use ($curPath) {
                    return (strpos($curPath, $needle) !== false) ? 'active' : '';
                };
                $lvl = isset($user->level) ? (int)$user->level : 2;
                $onGen = strpos($curPath, 'keys/generate') !== false;
            ?>
            <div class="lx-navgroup">
                <div class="lx-navlabel">Quản lý</div>
                <a class="lx-navitem <?= $isActive('dashboard') ?>" href="<?= site_url('dashboard') ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
                <a class="lx-navitem <?= (strpos($curPath,'keys')!==false && !$onGen) ? 'active':'' ?>" href="<?= site_url('keys') ?>"><i class="bi bi-key"></i> Keys</a>
                <a class="lx-navitem <?= $onGen ? 'active':'' ?>" href="<?= site_url('keys/generate') ?>"><i class="bi bi-plus-circle"></i> Generate Key</a>
            </div>
            <?php if ($lvl == 1): ?>
            <div class="lx-navgroup">
                <div class="lx-navlabel">Admin</div>
                <a class="lx-navitem <?= $isActive('admin/games') ?>" href="<?= site_url('admin/games') ?>"><i class="bi bi-controller"></i> Games</a>
                <a class="lx-navitem <?= $isActive('manage-users') ?>" href="<?= site_url('admin/manage-users') ?>"><i class="bi bi-people"></i> Manage Users</a>
                <a class="lx-navitem <?= $isActive('create-referral') ?>" href="<?= site_url('admin/create-referral') ?>"><i class="bi bi-person-plus"></i> Create Referral</a>
            </div>
            <?php endif; ?>
            <div class="lx-navgroup" style="margin-top:auto">
                <div class="lx-navlabel">Tài khoản</div>
                <a class="lx-navitem <?= $isActive('settings') ?>" href="<?= site_url('settings') ?>"><i class="bi bi-gear"></i> Settings</a>
                <a class="lx-navitem danger" href="<?= site_url('logout') ?>"><i class="bi bi-box-arrow-left"></i> Logout</a>
            </div>
        </aside>
        <div class="lx-overlay" id="lxOverlay" onclick="lxToggle(false)"></div>
    <?php endif; ?>

    <div class="lx-main <?= $loggedIn ? '' : 'guest' ?>">
        <?php if ($loggedIn): ?>
        <div class="lx-topbar">
            <button class="lx-burger" onclick="lxToggle()"><i class="bi bi-list"></i></button>
            <div class="lx-topbar-title"><?= isset($title) ? $title : 'Panel' ?></div>
            <?php $uname = (isset($user) && is_object($user)) ? getName($user) : 'User'; ?>
            <a class="lx-userchip" href="<?= site_url('settings') ?>">
                <span class="av"><?= strtoupper(substr($uname, 0, 1)) ?></span>
                <?= esc($uname) ?>
            </a>
        </div>
        <main class="lx-content">
            <?= $this->renderSection('content') ?>
        </main>
        <?php else: ?>
        <div class="lx-auth-wrap">
            <div class="lx-auth-logo"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="lx-auth-title"><?= BASE_NAME ?></div>
            <div class="lx-auth-sub">License Management Panel</div>
            <?= $this->renderSection('content') ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.1.0/sweetalert2.all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
function lxToggle(force){
  var s=document.getElementById('lxSidebar'),o=document.getElementById('lxOverlay');
  if(!s)return;
  var open=(typeof force==='boolean')?force:!s.classList.contains('open');
  s.classList.toggle('open',open); if(o)o.classList.toggle('show',open);
}
// Toast dùng chung (trước nằm trong natacode.js — nhúng thẳng để luôn có)
var Toast = Swal.mixin({
  toast: true, position: 'top-end', showConfirmButton: false,
  timer: 3000, timerProgressBar: true,
  didOpen: function(t){ t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); }
});
// SweetAlert nền tối đồng bộ theme
var _swalDark = { background:'#161f33', color:'#eef3fb' };
</script>
<?= $this->renderSection('js') ?>
</body>

</html>
