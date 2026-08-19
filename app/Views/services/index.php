<?php $title='Unified Core · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head service-pro-head"><div><p class="eyebrow">V13.0.1 · UNIFIED SYSTEM CORE</p><h1>Unified System Core</h1><p>Một nguồn trạng thái thống nhất cho Service Manager, Guardian, Diagnostics, Terminal và Dashboard.</p></div><div class="head-actions"><button type="button" class="btn btn-secondary" data-service-refresh>Làm mới</button><form method="post" action="/services/restart-all" data-confirm="Khởi động lại toàn bộ dịch vụ đã cài?" data-action-form><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-primary">Restart All</button></form></div></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>"><pre class="flash-pre"><?=tms_h($flash['message']??'')?></pre></div><?php endif;?>
<div class="service-summary-grid" data-service-summary>
<article class="panel-card service-summary-card"><span>Tổng dịch vụ</span><strong data-service-total><?=tms_h((string)$summary['total'])?></strong></article>
<article class="panel-card service-summary-card"><span>Đã cài</span><strong data-service-installed><?=tms_h((string)$summary['installed'])?></strong></article>
<article class="panel-card service-summary-card service-summary-ok"><span>Đang chạy</span><strong data-service-running><?=tms_h((string)$summary['running'])?></strong></article>
<article class="panel-card service-summary-card service-summary-warn"><span>Đã dừng</span><strong data-service-stopped><?=tms_h((string)$summary['stopped'])?></strong></article>
</div>
<div class="service-pro-grid" data-service-grid>
<?php foreach($services as $service):?>
<article class="panel-card service-pro-card" data-service-card="<?=tms_h($service['id'])?>">
<div class="service-pro-card-head"><div class="plugin-icon"><?=tms_h(strtoupper(substr($service['name'],0,2)))?></div><div class="service-pro-title"><h2><?=tms_h($service['name'])?></h2><small><?=tms_h($service['version'])?></small></div><span class="status-pill <?=$service['running']?'running':''?>" data-service-status><?=tms_h($service['health']['label'])?></span></div>
<div class="service-pro-metrics">
<div><span>PID</span><strong data-service-pid><?=tms_h($service['pid']?:'—')?></strong></div>
<div><span>RAM</span><strong data-service-memory><?=tms_h((string)$service['metrics']['memory_mb'])?> MB</strong></div>
<div><span>Threads</span><strong data-service-threads><?=tms_h((string)$service['metrics']['threads'])?></strong></div>
<div><span>Uptime</span><strong data-service-uptime><?=tms_h($service['metrics']['uptime'])?></strong></div>
</div>
<p class="service-health-message" data-service-health><?=tms_h($service['health']['message'])?></p>
<div class="service-actions">
<?php foreach(['start'=>'Start','restart'=>'Restart','stop'=>'Stop'] as $act=>$label):?>
<form method="post" action="/services/action" data-action-form <?=$act==='stop'?'data-confirm="Dừng dịch vụ '.$service['name'].'?"':''?>><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($service['id'])?>"><input type="hidden" name="action" value="<?=$act?>"><button class="btn <?=$act==='stop'?'btn-danger-soft':'btn-secondary'?>" <?=$service['installed']?'':'disabled'?>><?=$label?></button></form>
<?php endforeach;?>
</div>
<div class="service-pro-secondary">
<form method="post" action="/services/autostart"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($service['id'])?>"><input type="hidden" name="enabled" value="<?=$service['autostart']?'0':'1'?>"><button class="btn btn-ghost btn-block"><?=$service['autostart']?'Tắt Auto Start':'Bật Auto Start'?></button></form>
<button type="button" class="btn btn-ghost btn-block" data-service-log="<?=tms_h($service['id'])?>" data-service-name="<?=tms_h($service['name'])?>">Live Log</button>
</div>
<?php if($service['last_action']):?><div class="plugin-result"><?=tms_h($service['last_action'])?></div><?php endif;?>
</article>
<?php endforeach;?>
</div>
<section class="panel-card service-safety"><div><h2>Unified Core V13.0.1 dành riêng cho Termux</h2><p>Mọi module dùng cùng adapter Termux để đọc trạng thái, PID, RAM, uptime và phiên bản. Thao tác vẫn chạy qua queue/worker độc lập; PHP luôn được xử lý cuối trong Restart All.</p></div><span class="release-badge">One Source of Truth</span></section>
<dialog class="tms-dialog service-log-dialog" id="service-log-dialog"><div class="service-log-shell"><div class="dialog-head"><div><h2 id="service-log-title">Live Log</h2><small>Auto refresh mỗi 3 giây</small></div><button type="button" data-dialog-close aria-label="Đóng">×</button></div><div class="service-log-tools"><button type="button" class="btn btn-secondary btn-small" data-log-refresh>Làm mới</button><button type="button" class="btn btn-ghost btn-small" data-log-pause>Tạm dừng</button><select data-log-lines><option value="80">80 dòng</option><option value="150" selected>150 dòng</option><option value="300">300 dòng</option><option value="500">500 dòng</option></select></div><pre class="terminal-output service-live-log" data-service-log-output>Đang tải log...</pre></div></dialog>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
