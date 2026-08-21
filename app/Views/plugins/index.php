<?php $title='Package Manager · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">TMS OS · PACKAGE MANAGER</p><h1>Runtime Packages</h1><p>Cài, kiểm tra phiên bản và gỡ các gói Termux đã được kiểm duyệt.</p></div>
<form method="post" action="/packages/update"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button class="btn btn-secondary">Cập nhật kho gói</button></form></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><pre class="flash-pre"><?=tms_h($flash['message']??'')?></pre></div><?php endif;?>
<div class="plugin-grid">
<?php foreach($plugins as $plugin):?>
<article class="panel-card plugin-card">
<div class="plugin-card-top"><div class="plugin-icon"><?=tms_h(strtoupper(substr($plugin['name'],0,2)))?></div><span class="status-pill <?=$plugin['installed']?'running':''?>"><?=$plugin['installed']?'Đã cài':'Chưa cài'?></span></div>
<h2><?=tms_h($plugin['name'])?></h2><p><?=tms_h($plugin['description'])?></p><small><?=tms_h($plugin['group'])?> · pkg <?=tms_h($plugin['package'])?></small>
<?php if($plugin['version']):?><div class="plugin-result"><?=tms_h($plugin['version'])?></div><?php endif;?>
<?php if($plugin['last_result']):?><div class="plugin-result"><?=tms_h($plugin['last_result'])?></div><?php endif;?>
<div class="service-actions">
<?php if(!$plugin['installed']):?><form method="post" action="/packages/install"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($plugin['id'])?>"><button class="btn btn-primary">Cài đặt</button></form>
<?php elseif(empty($plugin['protected'])):?><form method="post" action="/packages/remove" data-confirm="Gỡ gói này?"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="id" value="<?=tms_h($plugin['id'])?>"><button class="btn btn-danger-soft">Gỡ bỏ</button></form>
<?php else:?><button class="btn btn-secondary" disabled>Gói lõi</button><?php endif;?>
</div>
</article>
<?php endforeach;?></div>
<section class="panel-card"><h2>Package Guard</h2><p>Chỉ các tên gói cố định trong danh mục được truyền tới <code>pkg</code>. PHP, Nginx, MariaDB và cURL được bảo vệ để tránh làm hỏng TMS OS.</p></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
