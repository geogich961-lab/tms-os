<?php $title='Resource Monitor · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">REAL-TIME METRICS</p><h1>Resource Monitor</h1><p>Lưu tối đa 288 mẫu gần nhất; dữ liệu được lấy trực tiếp từ hệ thống và Termux:API, không dùng dịch vụ ngoài.</p></div><span class="live-dot">LIVE</span></div>
<div class="metrics-live-grid">
<div class="panel-card metric-live"><span>RAM</span><strong data-monitor-value="memory"><?= (int)$data['current']['memory']?>%</strong><div class="mini-progress"><i data-monitor-bar="memory" style="width:<?= (int)$data['current']['memory']?>%"></i></div></div>
<div class="panel-card metric-live"><span>Storage</span><strong data-monitor-value="storage"><?= (int)$data['current']['storage']?>%</strong><div class="mini-progress"><i data-monitor-bar="storage" style="width:<?= (int)$data['current']['storage']?>%"></i></div></div>
<div class="panel-card metric-live"><span>Load 1m</span><strong data-monitor-value="load"><?=tms_h((string)$data['current']['load'])?></strong></div>
</div>
<section class="panel-card"><div class="section-title-row"><h2>Lịch sử RAM</h2><small>Tự làm mới mỗi 30 giây</small></div><canvas id="monitor-chart" height="220" data-history="<?=tms_h(json_encode($data['history']))?>"></canvas></section>

<section class="panel-card"><h2>Thông tin thiết bị</h2><div class="service-list">
<div class="service-row"><strong>Thiết bị</strong><span data-monitor-value="device_model"><?=tms_h((string)($data['device']['model']??'Không xác định'))?></span></div>
<div class="service-row"><strong>Android</strong><span data-monitor-value="android_version"><?=!empty($data['device']['android_version'])?tms_h('Android '.$data['device']['android_version']):'Không xác định'?></span></div>
<div class="service-row"><strong>Kiến trúc</strong><span><span data-monitor-value="architecture"><?=tms_h((string)($data['details']['architecture']??'Không xác định'))?></span> · PHP <?=tms_h((string)($data['details']['php_version']??''))?></span></div>
<div class="service-row"><strong>Uptime</strong><span data-monitor-value="uptime"><?=tms_h((string)($data['details']['uptime']??'Vừa khởi động'))?></span></div>
<div class="service-row"><strong>RAM</strong><span><span data-monitor-value="memory_used_mb"><?=tms_h((string)($data['details']['memory_used_mb']??0))?></span> / <span data-monitor-value="memory_total_mb"><?=tms_h((string)($data['details']['memory_total_mb']??0))?></span> MB</span></div>
<div class="service-row"><strong>Lưu trữ</strong><span><span data-monitor-value="storage_used_gb"><?=tms_h((string)($data['details']['storage_used_gb']??0))?></span> / <span data-monitor-value="storage_total_gb"><?=tms_h((string)($data['details']['storage_total_gb']??0))?></span> GB</span></div>
<div class="service-row"><strong>Tiến trình</strong><span><span data-monitor-value="processes"><?=tms_h((string)($data['details']['processes']??0))?></span> process</span></div>
</div></section>

<section class="panel-card"><h2>Pin &amp; Nhiệt độ</h2><div class="service-list">
<?php
$bat=$data['details']['battery']??[];$pct=$bat['percentage']??null;
$batLabel=($pct!==null)?((int)$pct.'%'):tms_h((string)($bat['status']??'Không khả dụng'));
?>
<div class="service-row"><strong>Pin</strong><span data-monitor-value="battery_label"><?=tms_h($batLabel)?><?php if($pct!==null&&!empty($bat['status'])):?> · <?=tms_h((string)$bat['status'])?><?php endif;?></span></div>
<div class="service-row" id="battery-health-row" <?=($pct===null?'style="display:none"':'')?>><strong>Sức khỏe pin</strong><span data-monitor-value="battery_health"><?=tms_h((string)($bat['health']??''))?></span></div>
<div class="service-row" id="battery-current-row" <?=(empty($bat['current'])?'style="display:none"':'')?>><strong>Dòng điện</strong><span data-monitor-value="battery_current"><?=tms_h((string)($bat['current']??''))?></span></div>
<div class="service-row" id="battery-bar-row" <?=($pct===null?'style="display:none"':'')?>><strong>Pin</strong><span style="flex:1;min-width:0;display:flex;align-items:center;gap:10px"><span class="mini-progress" style="flex:1"><i data-monitor-bar="battery" style="width:<?=max(0,min(100,(int)$pct))?>%;<?=((int)$pct<20?'background:var(--danger, #e11d48)':'')?>"></i></span><small style="color:var(--muted);font-size:.75rem;white-space:nowrap"><span data-monitor-value="battery_percent"><?=(int)$pct?></span>%</small></span></div>
<div class="service-row" id="battery-temp-row" <?=(!($bat['temperature']??null)?'style="display:none"':'')?>><strong>Nhiệt độ pin</strong><span data-monitor-value="battery_temp"><?=tms_h((string)($bat['temperature']??''))?></span>°C</div>
<?php $temp=$data['details']['temperature']??null;?>
<div class="service-row"><strong>Nhiệt độ CPU</strong><span data-monitor-value="cpu_temp"><?=$temp!==null?tms_h((string)$temp).'°C':'Không khả dụng'?></span></div>
</div></section>

<section class="panel-card"><h2>Mạng</h2><div class="service-list">
<div class="service-row"><strong>Network RX / TX</strong><span><span data-monitor-value="network_rx"><?=tms_h((string)($data['details']['network']['rx_mb']??0))?></span> / <span data-monitor-value="network_tx"><?=tms_h((string)($data['details']['network']['tx_mb']??0))?></span> MB</span></div>
</div></section>

<section class="panel-card"><h2>Dịch vụ</h2><div class="service-list"><?php foreach($data['services'] as $name=>$running):?><div class="service-row"><strong><?=tms_h($name)?></strong><span class="status-pill <?=$running?'running':'stopped'?>"><?=$running?'Running':'Stopped'?></span></div><?php endforeach;?></div></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
