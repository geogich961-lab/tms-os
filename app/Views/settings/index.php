<?php $title='Cài đặt · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">System Settings</p><h1>Cài đặt</h1></div></div>
<?php if($flash):?><div class="alert <?=$flash['type']==='success'?'alert-success':'alert-error'?>"><?=tms_h((string)$flash['message'])?></div><?php endif;?>
<section class="settings-grid">
<article class="panel-card appearance-card">
  <div class="section-title-row"><div><p class="eyebrow">Appearance Center</p><h2>Màu giao diện ứng dụng</h2></div><div class="appearance-preview" id="appearance-preview"><span></span><strong>TMS OS</strong></div></div>
  <p class="muted">Màu được áp dụng cho nút, menu, thanh trạng thái PWA và màn hình khởi động Android.</p>
  <form method="post" action="/settings/appearance" class="stack" id="appearance-form">
    <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
    <div class="color-presets" data-color-presets>
      <?php foreach([
        ['#315ee8','#6b4dea','Xanh TMS'],['#0f766e','#14b8a6','Xanh ngọc'],['#7c3aed','#a855f7','Tím'],['#d97706','#f59e0b','Cam'],['#be123c','#e11d48','Đỏ hồng'],['#334155','#64748b','Xám đậm']
      ] as $preset):?>
      <button type="button" class="color-preset" data-primary="<?=$preset[0]?>" data-secondary="<?=$preset[1]?>" title="<?=tms_h($preset[2])?>"><i style="--preset-a:<?=$preset[0]?>;--preset-b:<?=$preset[1]?>"></i><span><?=tms_h($preset[2])?></span></button>
      <?php endforeach;?>
    </div>
    <div class="color-fields">
      <label><span>Màu chính</span><div class="color-input"><input type="color" name="accent" value="<?=tms_h($ui['accent'])?>" data-accent><code><?=tms_h($ui['accent'])?></code></div></label>
      <label><span>Màu phụ</span><div class="color-input"><input type="color" name="accent_secondary" value="<?=tms_h($ui['accent_secondary'])?>" data-accent-secondary><code><?=tms_h($ui['accent_secondary'])?></code></div></label>
      <label><span>Nền splash PWA</span><div class="color-input"><input type="color" name="pwa_background" value="<?=tms_h($ui['pwa_background'])?>" data-pwa-background><code><?=tms_h($ui['pwa_background'])?></code></div></label>
    </div>
    <label><span>Chế độ mặc định</span><select name="default_theme"><option value="light" <?=$ui['default_theme']==='light'?'selected':''?>>Sáng</option><option value="dark" <?=$ui['default_theme']==='dark'?'selected':''?>>Tối</option></select></label>
    <div class="row-actions"><button class="btn btn-primary">Lưu giao diện</button><button type="button" class="btn btn-secondary" data-reset-colors>Khôi phục màu TMS</button></div>
  </form>
  <p class="security-note">Sau khi đổi màu PWA, nên xóa biểu tượng cũ khỏi màn hình chính và cài lại để Android cập nhật splash hoàn toàn.</p>
</article>
<article class="panel-card appearance-card">
  <div class="section-title-row"><div><p class="eyebrow">Brand Center</p><h2>Logo &amp; Thương hiệu</h2></div><div class="appearance-preview" id="brand-preview"><span></span><img class="brand-preview-img" src="<?=tms_h(tms_brand_icon('192'))?>" alt="Logo" id="brand-preview-img"></div></div>
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
