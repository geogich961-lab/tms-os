<?php $title='Resource Monitor · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">REAL-TIME METRICS</p><h1>Resource Monitor</h1><p>Lưu tối đa 288 mẫu gần nhất; dữ liệu được lấy trực tiếp từ hệ thống và Termux:API, không dùng dịch vụ ngoài.</p></div><span class="live-dot">LIVE</span></div>
<div class="metrics-live-grid">
<div class="panel-card metric-live"><span>RAM</span><strong data-monitor-value="memory"><?= (int)$data['current']['memory']?>%</strong><div class="mini-progress"><i data-monitor-bar="memory" style="width:<?= (int)$data['current']['memory']?>%"></i></div></div>
<div class="panel-card metric-live"><span>Storage</span><strong data-monitor-value="storage"><?= (int)$data['current']['storage']?>%</strong><div class="mini-progress"><i data-monitor-bar="storage" style="width:<?= (int)$data['current']['storage']?>%"></i></div></div>
<div class="panel-card metric-live"><span>Load 1m</span><strong data-monitor-value="load"><?=tms_h((string)$data['current']['load'])?></strong></div>
</div>
<section class="panel-card"><div class="section-title-row"><h2>Lịch sử RAM</h2><small>Tự làm mới mỗi 30 giây</small></div><canvas id="monitor-chart" height="220" data-history="<?=tms_h(json_encode($data['history']))?>"></canvas></section>

<section class="panel-card"><h2>Thông tin thiết bị</h2><div class="service-list">
<div class="service-row"><strong>Thiết bị</strong><span><?=tms_h((string)($data['device']['model']??'Không xác định'))?></span></div>
<?php if(!empty($data['device']['android_version'])):?><div class="service-row"><strong>Android</strong><span><?=tms_h('Android '.$data['device']['android_version'])?></span></div><?php endif;?>
<div class="service-row"><strong>Kiến trúc</strong><span><?=tms_h((string)($data['details']['architecture']??'Không xác định'))?> · PHP <?=tms_h((string)($data['details']['php_version']??''))?></span></div>
<div class="service-row"><strong>Uptime</strong><span><?=tms_h((string)($data['details']['uptime']??'Vừa khởi động'))?></span></div>
<div class="service-row"><strong>RAM</strong><span><?=tms_h((string)($data['details']['memory_used_mb']??0))?> / <?=tms_h((string)($data['details']['memory_total_mb']??0))?> MB</span></div>
<div class="service-row"><strong>Lưu trữ</strong><span><?=tms_h((string)($data['details']['storage_used_gb']??0))?> / <?=tms_h((string)($data['details']['storage_total_gb']??0))?> GB</span></div>
<div class="service-row"><strong>Tiến trình</strong><span><?=tms_h((string)($data['details']['processes']??0))?> process</span></div>
</div></section>

<section class="panel-card"><h2>Pin &amp; Nhiệt độ</h2><div class="service-list">
<?php
$bat=$data['details']['battery']??[];$pct=$bat['percentage']??null;
$batLabel=($pct!==null)?((int)$pct.'%'):tms_h((string)($bat['status']??'Không khả dụng'));
?>
<div class="service-row"><strong>Pin</strong><span><?=tms_h($batLabel)?><?php if($pct!==null&&!empty($bat['status'])):?> · <?=tms_h((string)$bat['status'])?><?php endif;?></span></div>
<?php if($pct!==null):?>
<div class="service-row"><strong>Sức khỏe pin</strong><span><?=tms_h((string)($bat['health']??''))?></span></div>
<?php if(!empty($bat['current'])):?><div class="service-row"><strong>Dòng điện</strong><span><?=tms_h((string)$bat['current'])?></span></div><?php endif;?>
<div class="service-row"><strong>Pin</strong><span style="flex:1;min-width:0;display:flex;align-items:center;gap:10px"><span class="mini-progress" style="flex:1"><i style="width:<?=max(0,min(100,(int)$pct))?>%;<?=((int)$pct<20?'background:var(--danger, #e11d48)':'')?>"></i></span><small style="color:var(--muted);font-size:.75rem;white-space:nowrap"><?=(int)$pct?>%</small></span></div>
<?php endif;?>
<?php if($bat['temperature']??null):?><div class="service-row"><strong>Nhiệt độ pin</strong><span><?=tms_h((string)$bat['temperature'])?>°C</span></div><?php endif;?>
<?php $temp=$data['details']['temperature']??null;?>
<div class="service-row"><strong>Nhiệt độ CPU</strong><span><?=$temp!==null?tms_h((string)$temp).'°C':'Không khả dụng'?></span></div>
</div></section>

<section class="panel-card"><h2>Mạng</h2><div class="service-list">
<div class="service-row"><strong>Network RX / TX</strong><span><?=tms_h((string)($data['details']['network']['rx_mb']??0))?> / <?=tms_h((string)($data['details']['network']['tx_mb']??0))?> MB</span></div>
</div></section>

<section class="panel-card"><h2>Dịch vụ</h2><div class="service-list"><?php foreach($data['services'] as $name=>$running):?><div class="service-row"><strong><?=tms_h($name)?></strong><span class="status-pill <?=$running?'running':'stopped'?>"><?=$running?'Running':'Stopped'?></span></div><?php endforeach;?></div></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
