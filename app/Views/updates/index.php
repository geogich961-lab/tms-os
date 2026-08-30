<?php $title='Update Center · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">SAFE UPDATE</p><h1>Update Center</h1><p>Kiểm tra, tải và áp dụng cập nhật an toàn — tự sao lưu và khôi phục nếu lỗi.</p></div></div>
<?php if(!empty($flash)):?><div class="alert <?=($flash['type']??'')==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><?=nl2br(tms_h($flash['message']??''))?></div><?php endif;?>

<section class="panel-card" id="current-version-card"><h2>Phiên bản hiện tại</h2>
<p>Bản đang chạy: <strong><?=tms_h($status['current']??'unknown')?></strong>
<?php if(!empty($status['previous_exists'])):?><span class="status-pill running">Có bản sao lưu gần đây</span><?php endif;?>
</p>
	<div class="update-btn-group">
    <button class="btn btn-secondary" id="check-update-btn" style="flex: 1; min-width: 160px;">Kiểm tra cập nhật</button>
    <?php if(!empty($status['previous_exists'])):?>
    <form method="post" action="/updates/rollback" onsubmit="return confirm('Khôi phục về bản trước? Dữ liệu hiện tại sẽ được giữ trong thư mục sao lưu.');" class="update-secondary-action">
        <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
        <button class="btn btn-danger-soft" style="width: 100%;">Khôi phục bản trước</button>
    </form>
    <?php endif;?>
</div>
	<p class="muted" id="check-result" role="status"></p>
	<p class="muted" id="diagnose-result" role="status" hidden></p>
	<button type="button" class="btn btn-secondary update-secondary-action" id="diagnose-btn" hidden>Chẩn đoán kết nối GitHub</button>
		<div class="update-available-action" id="online-update-action" hidden aria-live="polite">
			<strong>Bản cập nhật sẵn sàng</strong>
			<p id="online-update-summary" class="muted"></p>
			<form id="github-update-form" method="post" action="/updates/apply" class="update-action-form"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><button type="submit" class="btn btn-primary" id="apply-github-btn">Cập nhật ngay</button></form>
			<p class="muted update-action-note">TMS OS sẽ tải gói chính thức, kiểm tra checksum SHA-256, sao lưu source rồi áp dụng an toàn.</p>
		</div>
</section>

<?php $passwordConfigured=!empty($updatePassword['configured']);?>
<section class="panel-card" id="telegram-update-password-card">
<h2>Mật khẩu nâng cấp Telegram</h2>
<p>Trạng thái: <strong><?= $passwordConfigured ? 'Đã thiết lập' : 'Chưa thiết lập' ?></strong></p>
<p class="muted">Đây là mật khẩu riêng chỉ dùng để xác nhận cập nhật qua Telegram, không phải mật khẩu đăng nhập quản trị. Giá trị chỉ được lưu dưới dạng hash một chiều và không bao giờ hiển thị lại.</p>
<form method="post" action="/updates/password" class="update-manual-form" autocomplete="off">
<input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
<?php if($passwordConfigured):?><label>Mật khẩu nâng cấp hiện tại<input type="password" name="current_update_password" autocomplete="current-password" required></label><?php endif;?>
<label>Mật khẩu nâng cấp mới<input type="password" name="new_update_password" autocomplete="new-password" minlength="8" required></label>
<label>Xác nhận mật khẩu mới<input type="password" name="confirm_update_password" autocomplete="new-password" minlength="8" required></label>
<button class="btn btn-primary"><?= $passwordConfigured ? 'Đổi mật khẩu nâng cấp' : 'Thiết lập mật khẩu nâng cấp' ?></button>
</form>
<?php if($passwordConfigured):?><form method="post" action="/updates/password/remove" class="update-secondary-action" onsubmit="return confirm('Tắt mật khẩu nâng cấp Telegram? Bot sẽ không thể yêu cầu xác nhận cập nhật cho đến khi bạn thiết lập lại.');"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><label>Mật khẩu nâng cấp hiện tại<input type="password" name="current_update_password" autocomplete="current-password" required></label><button class="btn btn-danger-soft">Tắt mật khẩu nâng cấp</button></form><?php endif;?>
</section>

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
function parseUpdateJson(response) {
  return response.text().then(function(body) {
    var data = null;
    try { data = JSON.parse(body); } catch (ignore) {}
    if (!response.ok) {
      var requestError = new Error((data && data.error) || ('Panel đang tạm thời chưa sẵn sàng (HTTP ' + response.status + ').'));
      requestError.authRequired = response.status === 401 || response.status === 403 || (data && data.code === 'AUTH_REQUIRED');
      requestError.retryable = !requestError.authRequired && response.status >= 500;
      throw requestError;
    }
    if (!data) {
      var formatError = new Error('Panel đang khởi động lại hoặc trả dữ liệu chưa hoàn chỉnh.');
      formatError.retryable = true;
      throw formatError;
    }
    return data;
  });
}

function setOnlineUpdateVisibility(visible) {
	  var action = document.getElementById('online-update-action');
	  if (action) action.hidden = !visible;
}

function showAvailableUpdate(available) {
	  var summary = document.getElementById('online-update-summary');
	  if (summary) {
	    var notes = (available.notes || '').split('\n').filter(function(line) {
	      return line.trim().startsWith('-');
	    }).slice(0, 6).join('<br>');
	    summary.innerHTML = 'Có bản mới <strong>' + available.version + '</strong> (từ ' + available.tag + ').' + (notes ? '<br>' + notes : '');
	  }
	  setOnlineUpdateVisibility(true);
}

document.getElementById('check-update-btn')?.addEventListener('click',function(){
	  var btn=this;btn.disabled=true;btn.textContent='Đang kiểm tra…';
	  var out=document.getElementById('check-result');out.textContent='Đang kết nối GitHub…';
	  var diagOut=document.getElementById('diagnose-result'),diagBtn=document.getElementById('diagnose-btn');
	  if(diagOut){diagOut.hidden=true;diagOut.textContent='';}
	  if(diagBtn){diagBtn.hidden=true;}
	  setOnlineUpdateVisibility(false);
	  fetch('/api/updates/check',{credentials:'same-origin'}).then(parseUpdateJson).then(function(d){
	    btn.disabled=false;btn.textContent='Kiểm tra cập nhật';
	    if(d.error){out.textContent='Lỗi: '+d.error;if(diagBtn){diagBtn.hidden=false;}return;}
	    if(diagBtn){diagBtn.hidden=true;}
	    if(d.available){
	      out.textContent='Đã tìm thấy bản cập nhật mới.';
	      showAvailableUpdate(d.available);
	    }else{
	      out.textContent='Bạn đang dùng phiên bản mới nhất ('+d.current+').';
	      setOnlineUpdateVisibility(false);
	    }
	  }).catch(function(error){btn.disabled=false;btn.textContent='Kiểm tra cập nhật';out.textContent=(error && error.message) ? error.message : 'Không thể kiểm tra — hãy thử lại.';});
});

document.getElementById('diagnose-btn')?.addEventListener('click',function(){
	  var btn=this;btn.disabled=true;btn.textContent='Đang chẩn đoán…';
	  var out=document.getElementById('diagnose-result');out.hidden=false;out.textContent='Đang dò từng endpoint GitHub…';
	  fetch('/api/updates/diagnose',{credentials:'same-origin',cache:'no-store'}).then(parseUpdateJson).then(function(d){
	    btn.disabled=false;btn.textContent='Chẩn đoán kết nối GitHub';
	    var diag=d&&d.diagnostics;var lines=[];
	    lines.push('cURL: '+(diag&&diag.curl?'có':'không có')+' · JSON: '+(diag&&diag.json?'có':'không có'));
	    (diag&&diag.endpoints||[]).forEach(function(ep){
	      lines.push((ep.ok?'✓ ':'✗ ')+ep.endpoint+(ep.ok?'':' — '+(ep.error||'không phản hồi')));
	    });
	    var failed=((diag&&diag.endpoints)||[]).some(function(ep){return !ep.ok;});
	    out.textContent=lines.join('\n')+ (failed ? '\n→ Thiết bị này không truy cập được một số endpoint GitHub. Dùng bản ZIP thủ công hoặc thử lại khi mạng ổn định (Wi-Fi/4G khác).' : '\n→ Tất cả endpoint GitHub đều phản hồi. Hãy bấm Kiểm tra cập nhật lại.');
	    out.style.whiteSpace='pre-line';
	  }).catch(function(error){btn.disabled=false;btn.textContent='Chẩn đoán kết nối GitHub';out.textContent=(error && error.message) ? error.message : 'Không thể chẩn đoán — hãy thử lại.';});
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

		// V16.0.14: Xử lý cập nhật bất đồng bộ để tránh lỗi 502 Bad Gateway.
		// Button vẫn là submit chuẩn, nên form còn có đường dự phòng khi JavaScript không chạy.
		document.getElementById('github-update-form')?.addEventListener('submit', function(event) {
		  event.preventDefault();
		  if (!confirm('Áp dụng bản cập nhật mới nhất từ GitHub? Panel sẽ khởi động lại dịch vụ sau khi hoàn tất.')) return;

		  var form = this;
		  var btn = document.getElementById('apply-github-btn');
		  if (!btn) return;
		  btn.disabled = true;
		  btn.textContent = 'Đang tải & áp dụng...';

		  var formData = new FormData(form);
	  
	  fetch(form.action, {
	    method: 'POST',
	    body: formData,
	    headers: { 'X-Requested-With': 'XMLHttpRequest' }
	  }).then(parseUpdateJson).then(function(d) {
    if (!d.ok) {
      throw new Error(d.error || 'Không thể áp dụng cập nhật.');
    }
    btn.textContent = 'Đang xác minh phiên bản...';
    btn.className = 'btn btn-success';
    if (window.tms_toast) {
      tms_toast(d.message || 'Gói đã được áp dụng; đang xác minh phiên bản thực tế...', 'success');
    }
    if (d.queued && d.job) {
      pollUpdateJob(String(d.job), String(d.version || ''), 0);
      return;
    }
    verifyAppliedVersion(0);
  }).catch(function(e) {
    // PHP-CGI có thể bị khởi động lại trước khi fetch nhận response. Không được
    // coi trường hợp này là thành công; chỉ xác nhận sau khi đọc phiên bản mới.
    verifyAppliedVersion(0, e && e.message ? e.message : 'Không nhận được phản hồi từ panel.');
  });

  function versionMatches(current, expected) {
    return expected !== '' && String(current || '').replace(/^v/i, '') === expected.replace(/^v/i, '');
  }

  function requestUpdateReauthentication() {
    btn.disabled = true;
    btn.className = 'btn btn-secondary';
    btn.textContent = 'Cần đăng nhập lại';
    if (window.tms_toast) {
      tms_toast('Phiên đăng nhập đã hết hạn sau khi TMS OS khởi động lại. Đang chuyển tới trang đăng nhập…', 'info');
    }
    window.setTimeout(function() {
      window.location.assign('/login?next=%2Fupdates&reason=update-restart');
    }, 350);
  }

  function finishVerified(current) {
    btn.textContent = 'Đã cập nhật ' + current;
    btn.disabled = true;
    btn.className = 'btn btn-success';
    if (window.tms_toast) tms_toast('Đã xác minh phiên bản đang chạy: ' + current, 'success');
    setTimeout(function() { window.location.href = '/dashboard?updated=1'; }, 1200);
  }

  function pollUpdateJob(job, expected, attempt, fallbackError) {
    fetch('/api/updates/job-status?job=' + encodeURIComponent(job) + '&_=' + Date.now(), {credentials:'same-origin', cache:'no-store'})
      .then(parseUpdateJson)
      .then(function(status) {
        if (status && status.code === 'AUTH_REQUIRED') {
          var authError = new Error(status.error || 'Phiên đăng nhập đã hết.');
          authError.authRequired = true;
          throw authError;
        }
        if (status.job && status.job !== job) {
          verifyAppliedVersion(0, fallbackError || 'Trạng thái worker đã thay đổi; đang kiểm tra source thực tế.', expected);
          return;
        }
        if (status.phase === 'failed' || status.phase === 'restart_failed') {
          throw new Error(status.message || 'Cập nhật không thành công; hệ thống đã giữ bản đang chạy.');
        }
        if (status.phase === 'restarting') {
          if (attempt < 36) {
            btn.textContent = status.message || ('Đang chờ panel khởi động lại (' + (attempt + 1) + '/36)...');
            setTimeout(function() { pollUpdateJob(job, expected, attempt + 1, fallbackError); }, 1500);
            return;
          }
          // 502 qua Cloudflare có thể kéo dài hơn lúc PHP engine khởi động lại.
          // Luôn đối chiếu source một lượt nữa trước khi kết luận trạng thái.
          btn.textContent = 'Đang kiểm tra phiên bản thực tế sau khi khởi động lại...';
          verifyAppliedVersion(0, fallbackError || 'Panel cần thêm thời gian để hoàn tất khởi động lại.', expected);
          return;
        }
        if (status.update_ok === false) {
          throw new Error(status.message || 'Cập nhật không thành công; hệ thống đã giữ bản đang chạy.');
        }
        if (versionMatches(status.current, expected)) {
          finishVerified(String(status.current));
          return;
        }
        if (status.update_ok === true || status.phase === 'skipped') {
          verifyAppliedVersion(0, null, expected);
          return;
        }
        if (attempt < 36) {
          btn.textContent = status.message || ('Đang áp dụng cập nhật (' + (attempt + 1) + '/36)...');
          setTimeout(function() { pollUpdateJob(job, expected, attempt + 1, fallbackError); }, 1500);
          return;
        }
        verifyAppliedVersion(0, fallbackError || 'Worker vẫn đang xử lý nền; đang kiểm tra source thực tế.', expected);
      })
      .catch(function(error) {
        if (error && error.authRequired) {
          requestUpdateReauthentication();
          return;
        }
        if (attempt < 36 && error && error.retryable) {
          btn.textContent = 'Đang chờ panel khởi động lại (' + (attempt + 1) + '/36)...';
          setTimeout(function() { pollUpdateJob(job, expected, attempt + 1, fallbackError || error.message); }, 1500);
          return;
        }
        btn.disabled = false;
        btn.className = 'btn btn-primary';
        btn.textContent = 'Cập nhật ngay';
        alert('Không thể xác minh trạng thái cập nhật: ' + (error.message || fallbackError || 'panel chưa phản hồi.'));
      });
  }

  function verifyAppliedVersion(attempt, fallbackError, expected) {
    fetch('/api/updates/check?verify=' + Date.now(), {credentials:'same-origin', cache:'no-store'})
      .then(parseUpdateJson)
      .then(function(status) {
        if (status && status.code === 'AUTH_REQUIRED') {
          var authError = new Error(status.error || 'Phiên đăng nhập đã hết.');
          authError.authRequired = true;
          throw authError;
        }
        var current = String(status.current || '');
        var available = status.available && String(status.available.version || '');
        if (!status.error && (versionMatches(current, expected || '') || (!expected && available === ''))) {
          finishVerified(current);
          return;
        }
        if (attempt < 12) {
          btn.textContent = 'Đang xác minh phiên bản (' + (attempt + 1) + '/12)...';
          setTimeout(function() { verifyAppliedVersion(attempt + 1, fallbackError, expected); }, 2500);
          return;
        }
        throw new Error(fallbackError || ('Phiên bản thực tế vẫn chưa được xác nhận (đang là ' + current + ').'));
      })
      .catch(function(error) {
        if (error && error.authRequired) {
          requestUpdateReauthentication();
          return;
        }
        if (attempt < 12 && error && error.retryable) {
          btn.textContent = 'Đang chờ panel khởi động lại (' + (attempt + 1) + '/12)...';
          setTimeout(function() { verifyAppliedVersion(attempt + 1, fallbackError || error.message, expected); }, 2500);
          return;
        }
        btn.disabled = false;
        btn.className = 'btn btn-primary';
        btn.textContent = 'Cập nhật ngay';
        alert('Không thể xác minh trạng thái cập nhật: ' + (error.message || fallbackError || 'panel chưa phản hồi.'));
      });
  }
	});
	</script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
