<?php $title='Resource Monitor · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">REAL-TIME METRICS</p><h1>Resource Monitor</h1><p>Lưu tối đa 288 mẫu gần nhất; dữ liệu được lấy từ hệ thống, không dùng dịch vụ ngoài.</p></div><span class="live-dot">LIVE</span></div>
<div class="metrics-live-grid">
<div class="panel-card metric-live"><span>RAM</span><strong data-monitor-value="memory"><?= (int)$data['current']['memory']?>%</strong><div class="mini-progress"><i data-monitor-bar="memory" style="width:<?= (int)$data['current']['memory']?>%"></i></div></div>
<div class="panel-card metric-live"><span>Storage</span><strong data-monitor-value="storage"><?= (int)$data['current']['storage']?>%</strong><div class="mini-progress"><i data-monitor-bar="storage" style="width:<?= (int)$data['current']['storage']?>%"></i></div></div>
<div class="panel-card metric-live"><span>Load 1m</span><strong data-monitor-value="load"><?=tms_h((string)$data['current']['load'])?></strong></div>
</div>
<section class="panel-card"><div class="section-title-row"><h2>Lịch sử RAM</h2><small>Tự làm mới mỗi 30 giây</small></div><canvas id="monitor-chart" height="220" data-history="<?=tms_h(json_encode($data['history']))?>"></canvas></section>

<section class="panel-card"><h2>Thông tin thiết bị</h2><div class="service-list">
<div class="service-row"><strong>RAM</strong><span><?=tms_h((string)($data['details']['memory_used_mb']??0))?> / <?=tms_h((string)($data['details']['memory_total_mb']??0))?> MB</span></div>
<div class="service-row"><strong>Lưu trữ</strong><span><?=tms_h((string)($data['details']['storage_used_gb']??0))?> / <?=tms_h((string)($data['details']['storage_total_gb']??0))?> GB</span></div>
<div class="service-row"><strong>Pin</strong><span><?=($data['details']['battery']['percentage']??null)!==null?tms_h((string)$data['details']['battery']['percentage']).'%':tms_h((string)($data['details']['battery']['status']??'Không khả dụng'))?></span></div>
<div class="service-row"><strong>Nhiệt độ</strong><span><?=($data['details']['temperature']??null)!==null?tms_h((string)$data['details']['temperature']).'°C':'Không khả dụng'?></span></div>
<div class="service-row"><strong>Network RX / TX</strong><span><?=tms_h((string)($data['details']['network']['rx_mb']??0))?> / <?=tms_h((string)($data['details']['network']['tx_mb']??0))?> MB</span></div>
<div class="service-row"><strong>Tiến trình</strong><span><?=tms_h((string)($data['details']['processes']??0))?></span></div>
<div class="service-row"><strong>Uptime</strong><span><?=tms_h((string)($data['details']['uptime']??''))?></span></div>
</div></section>
<section class="panel-card"><h2>Dịch vụ</h2><div class="service-list"><?php foreach($data['services'] as $name=>$running):?><div class="service-row"><strong><?=tms_h($name)?></strong><span class="status-pill <?=$running?'running':'stopped'?>"><?=$running?'Running':'Stopped'?></span></div><?php endforeach;?></div></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
