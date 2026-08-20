<?php
$ui=tms_ui_settings();
$theme=$_COOKIE['tms_theme']??$ui['default_theme'];
$current=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(!function_exists('nav_active')){function nav_active(string $prefix,string $current):string{return $prefix==='/'?($current==='/'?'active':''):(str_starts_with($current,$prefix)?'active':'');}}
?>
<!doctype html><html lang="vi" data-theme="<?=tms_h($theme)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="<?=tms_h($ui['accent'])?>"><meta name="application-name" content="TMS OS"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><link rel="manifest" href="/manifest.php?v=<?=tms_asset_version()?>"><link rel="apple-touch-icon" href="<?=tms_h(tms_brand_icon('192'))?>"><link rel="icon" href="/assets/favicon.png"><title><?=tms_h($title??tms_config('name'))?></title><style>:root{--primary:<?=tms_h($ui['accent'])?>;--primary2:<?=tms_h($ui['accent_secondary'])?>;--primary-rgb:<?=tms_h(tms_hex_rgb($ui['accent']))?>}</style><link rel="stylesheet" href="/assets/app.css?v=<?=tms_asset_version()?>"><script defer src="/assets/app.js?v=<?=tms_asset_version()?>"></script></head><body>
<?php if(!empty($showShell)):?><div class="os-shell"><aside class="os-sidebar" id="sidebar"><div class="os-brand"><img class="brand-logo small" src="<?=tms_h(tms_brand_icon('logo'))?>" alt="TMS OS"><div><strong>TMS OS</strong></div></div><nav class="os-nav os-nav-grid">
<a class="nav-item <?=nav_active('/',$current)?>" href="/"><span>⌂</span>Dashboard</a>
<a class="nav-item <?=nav_active('/files',$current)?>" href="/files"><span>▤</span>TMS Explorer</a>
<a class="nav-item <?=nav_active('/websites',$current)?>" href="/websites"><span>◎</span>Website</a>
<a class="nav-item <?=nav_active('/databases',$current)?>" href="/databases"><span>▦</span>Database</a>
<a class="nav-item <?=nav_active('/network',$current)?>" href="/network"><span>⌁</span>Network Center</a>
<a class="nav-item <?=nav_active('/terminal',$current)?>" href="/terminal"><span>›_</span>Terminal</a>
<a class="nav-item <?=nav_active('/services',$current)?>" href="/services"><span>◉</span>Service Manager</a>
<a class="nav-item <?=nav_active('/guardian',$current)?>" href="/guardian"><span>🛡</span>TMS Guardian</a>
<a class="nav-item <?=nav_active('/cf-hosting',$current)?>" href="/cf-hosting"><span>☁</span>Cloudflare Hosting</a>
<a class="nav-item <?=nav_active('/modules',$current)?>" href="/modules"><span>◇</span>Module Center</a>
<a class="nav-item <?=nav_active('/packages',$current)?>" href="/packages"><span>◫</span>Runtime Packages</a>
<a class="nav-item <?=nav_active('/apps',$current)?>" href="/apps"><span>▣</span>App Marketplace</a>
<a class="nav-item <?=nav_active('/monitoring',$current)?>" href="/monitoring"><span>⌁</span>Resource Monitor</a>
<a class="nav-item <?=nav_active('/notifications',$current)?>" href="/notifications"><span>♢</span>Thông báo</a>
<a class="nav-item <?=nav_active('/updates',$current)?>" href="/updates"><span>↻</span>Update Center</a>
<a class="nav-item <?=nav_active('/backups',$current)?>" href="/backups"><span>◈</span>Backup</a>
<a class="nav-item <?=nav_active('/logs',$current)?>" href="/logs"><span>≡</span>Logs</a>
<div class="nav-divider" aria-hidden="true"></div>
<a class="nav-item <?=nav_active('/diagnostics',$current)?>" href="/diagnostics"><span>✓</span>System Check</a>
<a class="nav-item nav-settings <?=nav_active('/settings',$current)?>" href="/settings"><span>⚙</span>Cài đặt</a>
</nav><div class="sidebar-footer"><button type="button" class="btn btn-primary btn-block pwa-install-hidden" data-pwa-install>⤓ Cài TMS OS lên màn hình chính</button><button type="button" class="btn btn-ghost btn-block" data-theme-toggle>Đổi giao diện</button><form method="post" action="/logout"><input type="hidden" name="csrf" value="<?=tms_h(tms_csrf_token())?>"><button class="btn btn-danger-soft btn-block">Đăng xuất</button></form></div></aside><div class="sidebar-overlay" data-sidebar-overlay></div><main class="os-main"><header class="mobile-header"><button class="menu-button" data-menu-toggle>☰</button><strong>TMS OS</strong><span class="online-dot"></span></header><?php endif;?>
