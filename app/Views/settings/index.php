<?php $title='Cài đặt · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">System Settings</p><h1>Cài đặt</h1></div></div>
<?php if($flash):?><div class="alert <?=$flash['type']==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=$flash['type']==='success'?'success':'error'?>" hidden><?=tms_h((string)$flash['message'])?></div><?php endif;?>
<section class="settings-grid">
<article class="panel-card appearance-card">
	  <div class="section-title-row" style="flex-wrap: wrap; gap: 12px;"><div><p class="eyebrow">Appearance Center</p><h2>Giao diện & Thông báo</h2></div><div class="appearance-preview" id="appearance-preview" style="background: linear-gradient(45deg, #a70e13, #ed1d24, #a70e13); flex-shrink: 0;"><span></span><strong>TMS OS</strong></div></div>
	  <p class="muted">Cấu hình chế độ hiển thị và thông báo hệ thống.</p>
	  <form method="post" action="/settings/appearance" class="stack" id="appearance-form">
	    <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">

	    <label><span>Chế độ mặc định</span><select name="default_theme"><option value="light" <?=$ui['default_theme']==='light'?'selected':''?>>Sáng</option><option value="dark" <?=$ui['default_theme']==='dark'?'selected':''?>>Tối</option></select></label>
	    <label><span>Thời gian hiển thị thông báo (giây)</span><input type="number" name="toast_duration" value="<?=tms_h($ui['toast_duration'])?>" min="1" max="60"></label>
	    <div class="row-actions"><button class="btn btn-primary">Lưu cài đặt</button></div>
	  </form>
	</article>
<article class="panel-card appearance-card">
  <div class="section-title-row" style="flex-wrap: wrap; gap: 12px;"><div><p class="eyebrow">Brand Center</p><h2>Logo &amp; Thương hiệu</h2></div><div class="appearance-preview" id="brand-preview" style="flex-shrink: 0;"><span></span><img class="brand-preview-img" src="<?=tms_h(tms_brand_icon('192'))?>" alt="Logo" id="brand-preview-img"></div></div>
  <p class="muted">Đổi logo của TMS OS. Logo mới được áp dụng cho trang đăng nhập, menu, biểu tượng trên màn hình chính Android và iPhone/iPad.</p>
  <p class="security-note"><strong>Yêu cầu hình ảnh:</strong> định dạng PNG, JPG hoặc WebP · kích thước tối thiểu <strong>128x128px</strong> · tối đa <strong>2048x2048px</strong> · dung lượng tối đa <strong>2 MB</strong> · khuyến nghị <strong>512x512px</strong> vuông để icon hiển thị đẹp nhất trên mọi thiết bị.</p>
  <form method="post" action="/settings/logo" enctype="multipart/form-data" class="stack" id="brand-form">
    <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
    <label><span>Chọn tệp logo mới</span><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" required></label>
    <div class="row-actions"><button class="btn btn-primary" id="brand-apply" disabled>Áp dụng logo</button><button type="button" class="btn btn-secondary" data-reset-brand>Khôi phục logo TMS mặc định</button></div>
  </form>
</article>
<article class="panel-card"><h2>Xóa cache</h2><p class="muted">Sau khi cập nhật phiên bản mới, hãy xóa cache để giao diện tải lại dữ liệu mới nhất. Thao tác này xóa các session cũ, tệp tạm trên máy chủ và buộc trình duyệt/PWA tải lại toàn bộ giao diện (CSS, JS, biểu tượng).</p><form method="post" action="/settings/cache" class="stack"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><p class="security-note">Phiên đăng nhập hiện tại của bạn vẫn được giữ nguyên.</p><div class="row-actions"><button type="button" class="btn btn-secondary" data-clear-cache>Xóa cache</button></div></form></article>
<article class="panel-card"><h2>Đổi mật khẩu quản trị</h2><form method="post" action="/settings/password" class="stack"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><label><span>Mật khẩu mới</span><input type="password" name="new_password" minlength="8" required></label><label><span>Xác nhận mật khẩu</span><input type="password" name="confirm_password" minlength="8" required></label><button class="btn btn-primary">Đổi mật khẩu</button></form></article>
<article class="panel-card"><h2>Thông tin hệ thống</h2><dl class="detail-list single"><div><dt>Sản phẩm</dt><dd>TMS OS by THCGaming</dd></div><div><dt>Kênh phát hành</dt><dd>Platform Stable</dd></div><div><dt>Panel</dt><dd>127.0.0.1:8888</dd></div></dl><p class="security-note">Panel chỉ lắng nghe localhost. Không công khai cổng 8888 trực tiếp ra Internet.</p></article>
</section><?php require dirname(__DIR__).'/layouts/footer.php';?>
