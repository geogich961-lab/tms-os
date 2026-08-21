<?php $title='Network Center · TMS OS 4.0';$showShell=true;require dirname(__DIR__).'/layouts/header.php'; ?>
<div class="page-head"><div><p class="eyebrow">Kết nối máy chủ</p><h1>Network Center</h1></div><span class="page-time"><?=tms_h($network['hostname'])?></span></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><?=tms_h($flash['message']??'')?></div><?php endif;?>
<section class="metric-grid network-metrics">
 <article class="metric-card"><span>IPv4 trong Wi-Fi</span><strong class="metric-text" data-copy-value="<?=tms_h($network['lan_ip'])?>"><?=tms_h($network['lan_ip'])?></strong><button class="btn btn-secondary btn-small" data-copy="<?=tms_h($network['lan_ip'])?>">Sao chép</button></article>
 <article class="metric-card"><span>Bộ định tuyến</span><strong class="metric-text"><?=tms_h($network['gateway'])?></strong><small>Gateway mạng nội bộ</small></article>
 <article class="metric-card"><span>Trạng thái LAN</span><strong class="metric-text"><?=filter_var($network['lan_ip'],FILTER_VALIDATE_IP)?'Sẵn sàng':'Chưa xác định'?></strong><small>Các máy phải cùng Wi-Fi</small></article>
 <article class="metric-card"><span>Truy cập Internet</span><strong class="metric-text">Cloudflare</strong><small><?=!empty($network['packages']['Cloudflared'])?'Đã cài CLI':'Chưa cài CLI'?></small></article>
</section>
<section class="two-grid">
 <article class="panel-card"><div class="card-title"><p class="eyebrow">Địa chỉ truy cập</p><h2>Website trong mạng LAN</h2></div>
 <?php if(empty($network['urls'])):?><div class="empty-state">Chưa tạo được URL LAN. Hãy kiểm tra kết nối Wi-Fi và website.</div><?php else:?><div class="service-list"><?php foreach($network['urls'] as $site):?><div class="service-row network-url-row"><div><strong><?=tms_h($site['name'])?></strong><span><?=tms_h($site['url'])?></span></div><div class="row-actions"><a class="btn btn-primary btn-small" href="<?=tms_h($site['url'])?>" target="_blank">Mở</a><button class="btn btn-secondary btn-small" data-copy="<?=tms_h($site['url'])?>">Copy</button></div></div><?php endforeach;?></div><?php endif;?>
 </article>
 <article class="panel-card"><div class="card-title"><p class="eyebrow">Môi trường</p><h2>Thành phần hệ thống</h2></div><div class="service-list"><?php foreach($network['packages'] as $name=>$ready):?><div class="service-row"><div><strong><?=tms_h($name)?></strong><span><?=$ready?'Đã sẵn sàng':'Chưa được cài đặt'?></span></div><b class="status-pill <?=$ready?'running':'stopped'?>"><?=$ready?'OK':'Thiếu'?></b></div><?php endforeach;?></div></article>
</section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
