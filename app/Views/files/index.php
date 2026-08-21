<?php
$title = 'TMS Explorer · TMS OS';
$showShell = true;
require dirname(__DIR__) . '/layouts/header.php';

$root = (string)$listing['root_key'];
$path = (string)$listing['relative'];
?>

<div class="page-head explorer-page-head">
    <div>
        <p class="eyebrow">TMS Explorer</p>
        <h1>Quản lý tệp</h1>
    </div>
    <button type="button" class="btn btn-primary explorer-create-desktop" data-modal-open="create-modal">+ Tạo mới</button>
</div>


<form class="panel-card file-search-bar" method="get" action="/files">
    <input type="hidden" name="root" value="<?=tms_h($root)?>">
    <input type="hidden" name="path" value="<?=tms_h($path)?>">
    <input type="search" name="q" value="<?=tms_h($query??'')?>" placeholder="Tìm tệp hoặc thư mục trong khu vực hiện tại">
    <button class="btn btn-secondary">Tìm kiếm</button>
    <?php if(!empty($query)):?><a class="btn btn-ghost" href="<?=tms_h(tms_url('/files',['root'=>$root,'path'=>$path]))?>">Xóa lọc</a><?php endif;?>
</form>

<?php if (!empty($flash)): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden>
        <?= tms_h((string)($flash['message'] ?? '')) ?>
    </div>
<?php endif; ?>

<section class="tms-explorer">
    <nav class="explorer-tabs" aria-label="Khu vực lưu trữ">
        <?php foreach ($roots as $key => $dir): ?>
            <a class="explorer-tab <?= $key === $root ? 'active' : '' ?>" href="<?= tms_url('/files', ['root' => $key]) ?>">
                <span class="explorer-tab-icon"><?= $key === 'websites' ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>' : ($key === 'backups' ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>' : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>') ?></span>
                <span><?= tms_h(ucfirst($key)) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="explorer-content">
        <div class="explorer-topbar">
            <div class="explorer-breadcrumbs" aria-label="Đường dẫn hiện tại">
                <?php foreach ($listing['breadcrumbs'] as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span class="breadcrumb-separator">›</span><?php endif; ?>
                    <a href="<?= tms_url('/files', ['root' => $root, 'path' => $crumb['path']]) ?>">
                        <?= $i === 0 ? '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg> ' : '' ?><?= tms_h($crumb['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="post" action="/files/upload" enctype="multipart/form-data" class="explorer-upload" id="explorer-upload-form">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <label class="explorer-file-picker">
                    <input type="file" name="upload" required data-file-picker>
                    <span class="picker-icon">＋</span>
                    <span class="picker-text" data-file-picker-text>Chọn tệp để tải lên</span>
                </label>
                <button class="btn btn-primary" type="submit">Tải lên</button>
            </form>
        </div>

        <div class="explorer-list">
            <?php if ($path !== ''): ?>
                <a class="explorer-item explorer-back" href="<?= tms_url('/files', ['root' => $root, 'path' => dirname($path) === '.' ? '' : dirname($path)]) ?>">
                    <span class="explorer-item-icon">↰</span>
                    <span class="explorer-item-main">
                        <strong>Quay lại</strong>
                        <small>Thư mục trước</small>
                    </span>
                    <span class="explorer-item-arrow">›</span>
                </a>
            <?php endif; ?>

            <?php foreach ($listing['items'] as $item): ?>
                <?php
                $isZip = !$item['is_dir'] && strtolower(pathinfo($item['name'], PATHINFO_EXTENSION)) === 'zip';
                $primaryUrl = $item['is_dir']
                    ? tms_url('/files', ['root' => $root, 'path' => $item['relative']])
                    : ($item['editable']
                        ? tms_url('/files/editor', ['root' => $root, 'file' => $item['relative']])
                        : tms_url('/files/download', ['root' => $root, 'file' => $item['relative']]));
                ?>
                <div class="explorer-item">
                    <a class="explorer-item-open" href="<?= $primaryUrl ?>">
                        <span class="explorer-item-icon <?= $item['is_dir'] ? 'folder' : 'file' ?>"><?= $item['is_dir'] ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>' : ($isZip ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M21 16v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3"/><line x1="4" y1="12" x2="20" y2="12"/></svg>' : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>') ?></span>
                        <span class="explorer-item-main">
                            <strong><?= tms_h($item['name']) ?></strong>
                            <small>
                                <?= $item['is_dir'] ? 'Thư mục' : tms_format_bytes((int)$item['size']) ?>
                                <span>•</span>
                                Sửa lúc <?= date('H:i · d/m/Y', (int)$item['modified']) ?>
                            </small>
                        </span>
                    </a>

                    <button
                        type="button"
                        class="explorer-more"
                        aria-label="Thao tác với <?= tms_h($item['name']) ?>"
                        data-file-actions
                        data-name="<?= tms_h($item['name']) ?>"
                        data-relative="<?= tms_h($item['relative']) ?>"
                        data-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"
                        data-is-zip="<?= $isZip ? '1' : '0' ?>"
                        data-download="<?= !$item['is_dir'] ? tms_url('/files/download', ['root' => $root, 'file' => $item['relative']]) : '' ?>"
                    >⋮</button>
                </div>
            <?php endforeach; ?>

            <?php if (empty($listing['items'])): ?>
                <div class="explorer-empty">
                    <div>📭</div>
                    <strong>Thư mục trống</strong>
                    <span>Hãy tải tệp lên hoặc tạo mục mới.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<button type="button" class="explorer-fab" data-modal-open="create-modal" aria-label="Tạo mới">＋</button>
<button type="button" class="explorer-use-here" id="explorer-use-here" data-use-here>📥 Dùng thư mục này</button>

<div class="action-sheet" id="file-action-sheet" aria-hidden="true">
    <button type="button" class="action-sheet-backdrop" data-action-sheet-close aria-label="Đóng"></button>
    <section class="action-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="action-sheet-title">
        <div class="action-sheet-handle"></div>
        <div class="action-sheet-header">
            <div class="action-sheet-file-icon" id="action-sheet-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div>
                <small>Thao tác với</small>
                <h2 id="action-sheet-title">Tệp</h2>
            </div>
            <button type="button" class="action-sheet-close" data-action-sheet-close>×</button>
        </div>

        <div class="action-sheet-actions">
            <button type="button" class="sheet-action" id="sheet-rename">
                <span>✏️</span><b>Đổi tên</b>
            </button>
            <a class="sheet-action" id="sheet-download" href="#">
                <span>⬇️</span><b>Tải xuống</b>
            </a>
            <form method="post" action="/files/archive" id="sheet-archive-form">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <input type="hidden" name="relative" value="">
                <button class="sheet-action" type="submit"><span><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M21 16v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3"/><line x1="4" y1="12" x2="20" y2="12"/></svg></span><b>Tạo ZIP</b></button>
            </form>
            <form method="post" action="/files/extract" id="sheet-extract-form">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <input type="hidden" name="relative" value="">
                <button class="sheet-action" type="submit"><span>📦</span><b>Giải nén tại đây</b></button>
            </form>

            <form method="post" action="/files/copy" id="sheet-copy-form">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <input type="hidden" name="relative" value="">
                <input type="hidden" name="target_path" value="" data-copy-target>
                <button class="sheet-action" type="button" data-copy-open><span>📋</span><b>Sao chép</b></button>
            </form>
            <form method="post" action="/files/move" id="sheet-move-form">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <input type="hidden" name="relative" value="">
                <input type="hidden" name="target_path" value="" data-move-target>
                <button class="sheet-action" type="button" data-move-open><span>➡️</span><b>Di chuyển</b></button>
            </form>
            <form method="post" action="/files/chmod" id="sheet-chmod-form">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <input type="hidden" name="relative" value="">
                <input type="hidden" name="recursive" value="0" data-chmod-recursive>
                <input type="hidden" name="mode" value="600" data-chmod-mode>
                <button class="sheet-action" type="button" data-chmod-open><span>🔐</span><b>Phân quyền</b></button>
            </form>

            <form method="post" action="/files/delete" id="sheet-delete-form" data-confirm="Xóa mục này?">
                <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
                <input type="hidden" name="root" value="<?= tms_h($root) ?>">
                <input type="hidden" name="path" value="<?= tms_h($path) ?>">
                <input type="hidden" name="relative" value="">
                <button class="sheet-action danger" type="submit"><span>🗑️</span><b>Xóa</b></button>
            </form>
        </div>
        <button type="button" class="sheet-cancel" data-action-sheet-close>Hủy</button>
    </section>
</div>

<div class="modal" id="chmod-modal">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close>×</button>
        <h2>Phân quyền</h2>
        <form method="post" action="/files/perms/apply" id="chmod-apply-form" class="stack">
            <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
            <input type="hidden" name="root" value="<?= tms_h($root) ?>">
            <input type="hidden" name="relative" value="" data-chmod-relative>
            <label><span>Mã quyền (bát phân)</span><input name="mode" value="600" data-chmod-input required pattern="[0-7]{3,4}" maxlength="4"></label>
            <div class="chmod-presets">
                <button type="button" class="chmod-preset" data-chmod-preset="600"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> 600</button>
                <button type="button" class="chmod-preset" data-chmod-preset="644"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> 644</button>
                <button type="button" class="chmod-preset" data-chmod-preset="700"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> 700</button>
                <button type="button" class="chmod-preset" data-chmod-preset="755"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg> 755</button>
            </div>
            <label class="checkbox-label"><input type="checkbox" name="recursive" value="1" data-chmod-recursive-cb> <span>Áp dụng cho tất cả nội dung bên trong (thư mục)</span></label>
            <p class="explorer-hint" data-chmod-target-name></p>
            <button class="btn btn-primary">Cập nhật quyền</button>
        </form>
    </div>
</div>

<div class="modal" id="copy-modal">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close>×</button>
        <h2>Sao chép</h2>
        <form method="post" action="/files/copy" id="copy-apply-form" class="stack">
            <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
            <input type="hidden" name="root" value="<?= tms_h($root) ?>">
            <input type="hidden" name="relative" value="" data-copy-relative>
            <p class="explorer-hint">Đang xem thư mục đích: <b data-copy-target-display>—</b></p>
            <label><span>Sao chép vào thư mục</span><input name="target_path" value="" data-copy-target-input placeholder="ví dụ: thu-muc-dest"></label>
            <label class="checkbox-label"><input type="checkbox" name="overwrite" value="1"> <span>Cho phép ghi đè nếu trùng tên (đổi tên tự động nếu bỏ chọn)</span></label>
            <button class="btn btn-primary">Sao chép tại đây</button>
        </form>
    </div>
</div>

<div class="modal" id="move-modal">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close>×</button>
        <h2>Di chuyển</h2>
        <form method="post" action="/files/move" id="move-apply-form" class="stack">
            <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
            <input type="hidden" name="root" value="<?= tms_h($root) ?>">
            <input type="hidden" name="relative" value="" data-move-relative>
            <p class="explorer-hint">Đang xem thư mục đích: <b data-move-target-display>—</b></p>
            <label><span>Di chuyển vào thư mục</span><input name="target_path" value="" data-move-target-input placeholder="ví dụ: thu-muc-dest"></label>
            <button class="btn btn-primary">Di chuyển tại đây</button>
        </form>
    </div>
</div>
    </section>
</div>

<div class="modal" id="create-modal">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close>×</button>
        <h2>Tạo mới</h2>
        <form method="post" action="/files/create" class="stack">
            <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
            <input type="hidden" name="root" value="<?= tms_h($root) ?>">
            <input type="hidden" name="path" value="<?= tms_h($path) ?>">
            <label><span>Loại</span><select name="kind"><option value="folder">Thư mục</option><option value="file">Tệp</option></select></label>
            <label><span>Tên</span><input name="name" required></label>
            <button class="btn btn-primary">Tạo</button>
        </form>
    </div>
</div>

<div class="modal" id="rename-modal">
    <div class="modal-card">
        <button class="modal-close" type="button" data-modal-close>×</button>
        <h2>Đổi tên</h2>
        <form method="post" action="/files/rename" class="stack">
            <input type="hidden" name="csrf" value="<?= tms_h($csrf) ?>">
            <input type="hidden" name="root" value="<?= tms_h($root) ?>">
            <input type="hidden" name="path" value="<?= tms_h($path) ?>">
            <input type="hidden" name="relative" id="rename-relative">
            <label><span>Tên mới</span><input name="new_name" id="rename-name" required></label>
            <button class="btn btn-primary">Lưu</button>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
