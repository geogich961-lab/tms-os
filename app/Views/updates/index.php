<?php $title='Update Center · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">SAFE UPDATE</p><h1>Update Center</h1><p>Kiểm tra, tải và áp dụng cập nhật an toàn — tự sao lưu và khôi phục nếu lỗi.</p></div></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><?=nl2br(tms_h($flash['message']??''))?></div><?php endif;?>

<section class="panel-card"><h2>Phiên bản hiện tại</h2>
<p>Bản đang chạy: <strong><?=tms_h($status['current']??'unknown')?></strong>
<?php if(!empty($status['previous_exists'])):?><span class="status-pill running">Có bản sao lưu gần đây</span><?php endif;?>
</p>
	<div class="update-btn-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
    <button class="btn btn-secondary" id="check-update-btn" style="flex: 1; min-width: 160px;">Kiểm tra cập nhật</button>
    <?php if(!empty($status['previous_exists'])):?>
    <form method="post" action="/updates/rollback" onsubmit="return confirm('Khôi phục về bản trước? Dữ liệu hiện tại sẽ được giữ trong thư mục sao lưu.');" style="flex: 1; min-width: 160px; margin: 0;">
        <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
        <button class="btn btn-danger-soft" style="width: 100%;">Khôi phục bản trước</button>
    </form>
    <?php endif;?>
</div>
<p class="muted" id="check-result"></p></section>

<section class="panel-card" id="online-update-card"><h2>Cập nhật nhanh</h2>
<p>TMS OS sẽ tải bản mới nhất từ kho chính thức, kiểm tra checksum SHA-256, sao lưu source hiện tại, rồi áp dụng. Nếu panel không hoạt động sau khi áp dụng, hệ thống tự động khôi phục bản trước.</p>
<form id="github-update-form" method="post" action="/updates/apply"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button type="button" class="btn btn-primary" id="apply-github-btn">Cập nhật ngay</button></form>
<p class="muted">Hoặc qua lệnh (từ thiết bị khác trên mạng LAN): <code>curl -sS -X POST http://127.0.0.1:8888/api/updates/run -d "token=TOKEN_CỦA_BẠN"</code></p></section>

<section class="panel-card"><h2>Tải gói cập nhật thủ công</h2><form method="post" action="/updates/stage" enctype="multipart/form-data" class="update-manual-form"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><div style="margin-bottom: 8px;"><input type="file" name="package" accept=".zip,application/zip" required></div><button class="btn btn-primary">Kiểm tra và lưu</button></form>
<p class="muted">Tải file ZIP gói cập nhật (TMS_OS_V*.zip) rồi dùng nút "Áp dụng" bên dưới. Cách này an toàn vì không ghi đè lõi đang chạy từ trình duyệt.</p></section>

<section class="panel-card">
<div class="section-title-row"><h2>Gói đã lưu trữ</h2><?php if(!empty($items)):?><button type="button" class="btn btn-danger-soft btn-small" id="batch-delete-btn" style="display:none">Xóa mục đã chọn</button><?php endif;?></div>
<?php if(empty($items)):?><p class="muted">Chưa có gói nào.</p><?php else:?>
<form id="batch-delete-form" method="post" action="/updates/delete">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<div class="table-wrap"><table><thead><tr><th style="width:40px"><input type="checkbox" id="select-all-packages"></th><th>File</th><th>Dung lượng</th><th>SHA-256</th><th style="text-align:right">Thao tác</th></tr></thead><tbody>
<?php foreach($items as $i):?><tr>
<td><input type="checkbox" name="names[]" value="<?=tms_h($i['name'])?>" class="package-checkbox"></td>
<td><?=tms_h($i['name'])?></td>
<td><?=number_format($i['size']/1048576,2)?> MB</td>
<td><code><?=tms_h(substr($i['sha256'],0,16))?>…</code></td>
<td style="text-align:right; white-space:nowrap;">
<div style="display:inline-flex; gap:6px; justify-content:flex-end">
<form method="post" action="/updates/staged/apply" style="display:inline" onsubmit="return confirm('Áp dụng gói này?');"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="name" value="<?=tms_h($i['name'])?>"><button class="btn btn-primary-soft btn-small">Áp dụng</button></form>
<form method="post" action="/updates/delete" style="display:inline" onsubmit="return confirm('Xóa gói này?');"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="name" value="<?=tms_h($i['name'])?>"><button class="btn btn-danger-soft btn-small">Xóa</button></form>
</div>
</td></tr><?php endforeach;?></tbody></table></div></form><?php endif;?></section>

<script>
document.getElementById('check-update-btn')?.addEventListener('click',function(){
  var btn=this;btn.disabled=true;btn.textContent='Đang kiểm tra…';
  var out=document.getElementById('check-result');out.textContent='Đang kết nối GitHub…';
  fetch('/api/updates/check',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
    btn.disabled=false;btn.textContent='Kiểm tra cập nhật';
    if(d.error){out.textContent='Lỗi: '+d.error;return;}
    if(d.available){
      var notes=(d.available.notes||'').split('\n').filter(function(l){return l.trim().startsWith('-');}).slice(0,6).join('<br>');
      out.innerHTML='Có bản mới <strong>'+d.available.version+'</strong> (từ '+d.available.tag+').<br>'+notes+'<br><p class="muted small" style="margin-top:8px">Vui lòng sử dụng mục "Cập nhật nhanh" bên dưới để áp dụng.</p>';
    }else{out.textContent='Bạn đang dùng phiên bản mới nhất ('+d.current+').';}
  }).catch(function(){btn.disabled=false;btn.textContent='Kiểm tra cập nhật';out.textContent='Không thể kiểm tra — hãy thử lại.';});
});

document.getElementById('select-all-packages')?.addEventListener('change', function() {
  document.querySelectorAll('.package-checkbox').forEach(cb => cb.checked = this.checked);
  updateBatchBtn();
});
document.querySelectorAll('.package-checkbox').forEach(cb => cb.addEventListener('change', updateBatchBtn));
function updateBatchBtn() {
  var count = document.querySelectorAll('.package-checkbox:checked').length;
  var btn = document.getElementById('batch-delete-btn');
  if (btn) {
    btn.style.display = count > 0 ? 'block' : 'none';
    btn.textContent = 'Xóa ' + count + ' gói đã chọn';
  }
}
document.getElementById('batch-delete-btn')?.addEventListener('click', function() {
	  if (confirm('Xóa các gói đã chọn?')) {
	    document.getElementById('batch-delete-form').submit();
	  }
	});

	// V16.0.14: Xử lý cập nhật bất đồng bộ để tránh lỗi 502 Bad Gateway
	document.getElementById('apply-github-btn')?.addEventListener('click', function() {
	  if (!confirm('Áp dụng bản cập nhật mới nhất từ GitHub? Panel sẽ khởi động lại dịch vụ sau khi hoàn tất.')) return;
	  
	  var btn = this;
	  btn.disabled = true;
	  btn.textContent = 'Đang tải & áp dụng...';
	  
	  var form = document.getElementById('github-update-form');
	  var formData = new FormData(form);
	  
	  fetch(form.action, {
	    method: 'POST',
	    body: formData,
	    headers: { 'X-Requested-With': 'XMLHttpRequest' }
	  }).then(function(r) {
	    return r.json();
	  }).then(function(d) {
	    if (d.ok) {
	      btn.textContent = 'Đang khởi động lại...';
	      btn.className = 'btn btn-success';
	      
	      // Hiển thị thông báo toast nếu có
	      if (window.tms_toast) {
	        tms_toast(d.message || 'Cập nhật thành công, đang khởi động lại...', 'success');
	      }
	      
	      // Đợi 8 giây để dịch vụ PHP-CGI và Nginx khởi động lại hoàn toàn trên Termux
	      setTimeout(function() {
	        window.location.href = '/dashboard?updated=1';
	      }, 8000);
	    } else {
	      btn.disabled = false;
	      btn.textContent = 'Cập nhật ngay';
	      alert('Lỗi: ' + (d.error || 'Không thể áp dụng cập nhật.'));
	    }
	  }).catch(function(e) {
	    // Nếu bị lỗi kết nối (thường do PHP bị kill ngay lập tức), vẫn đợi rồi reload
	    btn.textContent = 'Đang khởi động lại...';
	    setTimeout(function() {
	      window.location.href = '/dashboard?updated=1';
	    }, 8000);
	  });
	});
	</script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
