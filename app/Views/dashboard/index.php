<?php
$title = 'Dashboard · TMS OS 6.0';
$showShell = true;
$time = (string)($metrics['time'] ?? 'Không rõ');
$memoryPercent = (int)($metrics['memory_percent'] ?? 0);
$memoryUsed = (int)($metrics['memory_used_mb'] ?? 0);
$memoryTotal = (int)($metrics['memory_total_mb'] ?? 0);
$storagePercent = (int)($metrics['storage_percent'] ?? 0);
$storageUsed = (float)($metrics['storage_used_gb'] ?? 0);
$storageTotal = (float)($metrics['storage_total_gb'] ?? 0);
$loadAverage = (string)($metrics['load_1m'] ?? '0');
$uptime = (string)($metrics['uptime'] ?? 'Không rõ');
$architecture = (string)($metrics['architecture'] ?? 'Không rõ');
$phpVersion = (string)($metrics['php_version'] ?? PHP_VERSION);
$lanIp = (string)($network['lan_ip'] ?? 'Không phát hiện');
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="page-head"><div><p class="eyebrow">Tổng quan hệ thống</p><h1>Dashboard</h1></div><span class="page-time"><?=tms_h($time)?></span></div>
<?php if (!empty($flash)): ?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>"><?=nl2br(tms_h((string)($flash['message']??'')))?></div><?php endif; ?>
<section class="metric-grid">
 <article class="metric-card"><span>RAM</span><strong><?=$memoryPercent?>%</strong><div class="progress"><i style="width:<?=min(100,max(0,$memoryPercent))?>%"></i></div><small><?=$memoryUsed?> / <?=$memoryTotal?> MB</small></article>
 <article class="metric-card"><span>Dung lượng</span><strong><?=$storagePercent?>%</strong><div class="progress"><i style="width:<?=min(100,max(0,$storagePercent))?>%"></i></div><small><?=$storageUsed?> / <?=$storageTotal?> GB</small></article>
 <article class="metric-card"><span>Tải hệ thống</span><strong><?=tms_h($loadAverage)?></strong><small>Load average 1 phút</small></article>
 <article class="metric-card"><span>Uptime</span><strong class="metric-text"><?=tms_h($uptime)?></strong><small><?=tms_h($architecture)?> · PHP <?=tms_h($phpVersion)?></small></article>
</section>
<section class="dashboard-summary-grid">
 <article class="summary-card"><div class="summary-icon">🌐</div><div><span>IPv4 LAN</span><strong><?=tms_h($lanIp)?></strong><small><?=tms_h((string)($network['gateway']??'Không phát hiện'))?> gateway</small></div><?php if(filter_var($lanIp,FILTER_VALIDATE_IP)):?><button class="btn btn-secondary btn-small" data-copy="<?=tms_h($lanIp)?>">Copy</button><?php endif;?></article>
 <article class="summary-card"><div class="summary-icon">◎</div><div><span>Website</span><strong><?=count($sites??[])?> website</strong><small>Đang được Nginx quản lý</small></div><a class="btn btn-secondary btn-small" href="/websites">Quản lý</a></article>
 <article class="summary-card"><div class="summary-icon">✓</div><div><span>System Check</span><strong><?=count(array_filter($services??[]))?> / <?=count($services??[])?> dịch vụ</strong><small>Kiểm tra môi trường máy chủ</small></div><a class="btn btn-secondary btn-small" href="/diagnostics">Mở</a></article>
</section>
<section class="two-grid">
 <article class="panel-card"><div class="card-title"><p class="eyebrow">Service Manager</p><h2>Trạng thái dịch vụ</h2></div><div class="service-list"><?php foreach(($services??[]) as $name=>$running):?><div class="service-row"><div><strong><?=tms_h((string)$name)?></strong><span><?=$running?'Tiến trình đang hoạt động':'Không phát hiện tiến trình'?></span></div><b class="status-pill <?=$running?'running':'stopped'?>"><?=$running?'Đang chạy':'Đã dừng'?></b></div><?php endforeach;?></div></article>
 <article class="panel-card"><div class="card-title"><p class="eyebrow">Quick Actions</p><h2>Điều khiển nhanh</h2></div><form method="post" action="/service/action" class="action-grid" data-action-form><input type="hidden" name="csrf" value="<?=tms_h((string)($csrf??''))?>"><button class="btn btn-primary" name="action" value="start_all">Khởi động tất cả</button><button class="btn btn-secondary" name="action" value="reload_nginx">Nạp lại Nginx</button><button class="btn btn-secondary" name="action" value="restart_php">Khởi động lại PHP</button><button class="btn btn-secondary" name="action" value="start_mariadb">Bật MariaDB</button><button class="btn btn-secondary" name="action" value="start_ssh">Bật SSH</button><button class="btn btn-secondary" name="action" value="backup">Sao lưu ngay</button></form></article>
</section>
<section class="panel-card"><div class="card-title"><p class="eyebrow">Website đang chạy</p><h2>Truy cập nhanh</h2></div><?php if(empty($network['urls'])):?><div class="empty-state">Chưa có website hoặc chưa phát hiện IPv4 LAN.</div><?php else:?><div class="service-list"><?php foreach($network['urls'] as $site):?><div class="service-row network-url-row"><div><strong><?=tms_h($site['name'])?></strong><span><?=tms_h($site['url'])?></span></div><div class="row-actions"><a class="btn btn-primary btn-small" href="<?=tms_h($site['url'])?>" target="_blank">Mở</a><button class="btn btn-secondary btn-small" data-copy="<?=tms_h($site['url'])?>">Copy</button></div></div><?php endforeach;?></div><?php endif;?></section>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
