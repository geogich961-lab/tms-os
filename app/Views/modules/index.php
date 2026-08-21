<?php $title='Module Center · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">TMS OS · MODULE CENTER</p><h1>Module Center</h1><p>Registry trung tâm cho kiến trúc module độc lập, có kiểm tra manifest, phụ thuộc và trạng thái hoạt động.</p></div><form method="post" action="/modules/repair"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-secondary">Đồng bộ registry</button></form></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><?=tms_h($flash['message']??'')?></div><?php endif;?>
<div class="module-summary-grid">
<div class="stat-card"><span>Tổng module</span><strong><?=tms_h((string)$summary['total'])?></strong></div><div class="stat-card"><span>Đang bật</span><strong><?=tms_h((string)$summary['enabled'])?></strong></div><div class="stat-card"><span>Module lõi</span><strong><?=tms_h((string)$summary['core'])?></strong></div><div class="stat-card"><span>Khỏe mạnh</span><strong><?=tms_h((string)$summary['healthy'])?></strong></div>
</div>
<div class="module-grid">
<?php foreach($modules as $module):?>
<article class="panel-card module-card">
<div class="module-card-head"><div class="module-icon"><?=tms_h((string)($module['icon']??'◇'))?></div><div class="module-title"><h2><?=tms_h((string)$module['name'])?></h2><small><?=tms_h((string)$module['id'])?> · v<?=tms_h((string)$module['version'])?></small></div><span class="module-health health-<?=tms_h((string)$module['health'])?>"><?=tms_h(match($module['health']){'healthy'=>'Sẵn sàng','disabled'=>'Đã tắt','warning'=>'Cảnh báo',default=>'Lỗi'})?></span></div>
<p><?=tms_h((string)$module['description'])?></p>
<div class="module-meta"><span><?=tms_h((string)$module['category'])?></span><?php if(!empty($module['core'])):?><span>Core protected</span><?php endif;?><?php if(!empty($module['route'])):?><a href="<?=tms_h((string)$module['route'])?>">Mở module</a><?php endif;?></div>
<?php if(!empty($module['dependencies'])):?><div class="module-deps">Phụ thuộc: <?=tms_h(implode(', ',(array)$module['dependencies']))?></div><?php endif;?>
<?php if(!empty($module['errors'])):?><div class="module-error"><?=tms_h(implode(' · ',(array)$module['errors']))?></div><?php endif;?>
<?php if(!empty($module['missing_dependencies'])):?><div class="module-error">Thiếu: <?=tms_h(implode(', ',(array)$module['missing_dependencies']))?></div><?php endif;?>
<form method="post" action="/modules/toggle"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h((string)$module['id'])?>"><input type="hidden" name="enabled" value="<?=$module['enabled']?'0':'1'?>"><button class="btn <?=$module['enabled']?'btn-secondary':'btn-primary'?> btn-block" <?=!empty($module['core'])?'disabled':''?>><?=!empty($module['core'])?'Module lõi được bảo vệ':($module['enabled']?'Tắt module':'Bật module')?></button></form>
</article>
<?php endforeach;?></div>
<section class="panel-card"><h2>Kiến trúc V11</h2><p>Mỗi module có manifest riêng trong <code>app/Modules/&lt;module-id&gt;/module.json</code>. Registry không thực thi mã tùy ý từ manifest; các route và service vẫn được nạp qua lõi đã kiểm soát để tránh làm hỏng hệ thống.</p></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
