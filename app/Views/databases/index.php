<?php $title='Database · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">Database Manager · <?=tms_h(($driver ?? 'SQLite'))?></p><h1>Database</h1></div><?php if($driver==='SQLite'):?><button class="btn btn-primary" data-modal-open="db-modal">+ Tạo database</button><?php endif;?></div>
<?php if($flash):?><div data-flash-toast="<?=$flash['type']==='success'?'success':'error'?>" hidden><?=nl2br(tms_h((string)$flash['message']))?></div><?php endif;?>
<?php if($error):?><div class="alert alert-error"><?=tms_h($error)?></div><?php endif;?>

<div class="navdb-layout">
  <!-- Sidebar: Object Explorer -->
  <aside class="navdb-sidebar table-card" id="navdb-sidebar">
    <div class="navdb-sidebar-head"><span>Danh sách database</span><button type="button" class="btn btn-ghost btn-small" id="navdb-refresh-sb" title="Làm mới danh sách">↻</button></div>
    <div class="navdb-db-list" id="navdb-db-list"></div>
    <div class="navdb-db-actions">
      <button type="button" class="btn btn-secondary btn-block" id="navdb-create-db-btn" style="display:none">+ Tạo database</button>
    </div>
  </aside>

  <!-- Workspace -->
  <section class="navdb-workspace">
    <div class="navdb-empty" id="navdb-empty">
      <div class="navdb-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></div>
      <p><strong>Chưa có bảng nào được mở</strong></p>
      <p class="muted">Chọn một database bên trái, sau đó bấm vào bảng để duyệt và chỉnh sửa dữ liệu trực tiếp.</p>
    </div>

    <div class="navdb-panel" id="navdb-panel" hidden>
      <div class="navdb-toolbar">
        <div class="navdb-breadcrumb" id="navdb-breadcrumb"></div>
        <div class="navdb-tabs" role="tablist">
          <button class="navdb-tab active" data-tab="browse" type="button">Dữ liệu</button>
          <button class="navdb-tab" data-tab="sql" type="button">SQL</button>
          <button class="navdb-tab" data-tab="structure" type="button">Cấu trúc</button>
        </div>
      </div>

      <!-- Tab Dữ liệu -->
      <div class="navdb-pane" id="navdb-pane-browse">
        <div class="navdb-tools">
          <div class="navdb-tools-row">
            <button class="btn btn-ghost btn-small" id="navdb-browse-refresh" type="button" title="Làm mới">↻ Làm mới</button>
            <label class="navdb-readonly-wrap" title="Bật để chỉ xem, tắt để chỉnh sửa dữ liệu"><input type="checkbox" id="navdb-readonly" checked> Chỉ đọc</label>
            <span class="navdb-count" id="navdb-count"></span>
            <span class="navdb-pageinfo" id="navdb-pageinfo"></span>
          </div>
          <div class="navdb-tools-row navdb-tools-right">
            <input class="navdb-search" id="navdb-search" type="text" placeholder="Tìm trong bảng... (Ctrl+F)">
            <button class="btn btn-secondary btn-small" id="navdb-insert-btn" type="button" disabled>+ Thêm dòng</button>
            <form method="post" action="/databases/export" id="navdb-export-form" style="display:none"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="name" id="navdb-export-name" value=""></form>
          </div>
        </div>
        <div class="navdb-filterbar hidden" id="navdb-filterbar">
          <label><select id="navdb-filter-col" class="navdb-select"></select></label>
          <select id="navdb-filter-op" class="navdb-select" style="max-width:120px"><option value="LIKE">% chứa %</option><option value="=">= chính xác</option><option value="!=">≠ khác</option><option value="&gt;">&gt; lớn hơn</option><option value="&lt;">&lt; nhỏ hơn</option><option value="&gt;=">≥ lớn hơn hoặc bằng</option><option value="&lt;=">≤ nhỏ hơn hoặc bằng</option><option value="IS NULL">IS NULL</option></select>
          <input type="text" id="navdb-filter-val" class="navdb-input" placeholder="Giá trị">
          <button class="btn btn-ghost btn-small" id="navdb-filter-apply" type="button">Lọc</button>
          <button class="btn btn-ghost btn-small" id="navdb-filter-clear" type="button">Bỏ lọc</button>
        </div>
        <div class="navdb-sortbar" id="navdb-sortbar">
          <span class="muted" style="font-size:.85em">Sắp xếp theo</span>
          <select id="navdb-sort-col" class="navdb-select"></select>
          <select id="navdb-sort-dir" class="navdb-select" style="max-width:110px"><option value="ASC">↑ Tăng dần</option><option value="DESC">↓ Giảm dần</option></select>
        </div>
        <div id="navdb-data-empty" class="navdb-msg" hidden>Chọn bảng để xem dữ liệu.</div>
        <div class="navdb-table-wrap" id="navdb-data-wrap"></div>
        <div class="navdb-pager">
          <button class="btn btn-ghost btn-small" id="navdb-prev" type="button" disabled>← Trang trước</button>
          <span class="muted" style="font-size:.85em" id="navdb-pager-text"></span>
          <button class="btn btn-ghost btn-small" id="navdb-next" type="button" disabled>Trang sau →</button>
        </div>
      </div>

      <!-- Tab SQL -->
      <div class="navdb-pane" id="navdb-pane-sql" hidden>
        <textarea id="navdb-sql-input" class="navdb-sql" placeholder="SELECT * FROM ... LIMIT 100" spellcheck="false"></textarea>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <button class="btn btn-primary" id="navdb-run-btn" type="button">▶ Chạy (Ctrl+Enter)</button>
          <label class="navdb-readonly-wrap" style="margin:0"><input type="checkbox" id="navdb-readonly2"> Chế độ chỉ đọc</label>
          <span class="navdb-count" id="navdb-result-meta"></span>
        </div>
        <div class="navdb-table-wrap" id="navdb-result-wrap"></div>
      </div>

      <!-- Tab Cấu trúc -->
      <div class="navdb-pane" id="navdb-pane-structure" hidden>
        <div class="navdb-struct-head"><span id="navdb-struct-title" class="muted"></span><span id="navdb-struct-meta" class="muted"></span></div>
        <div id="navdb-struct-empty" class="navdb-msg" hidden>Chọn bảng để xem cấu trúc.</div>
        <div class="navdb-table-wrap" id="navdb-struct-wrap"></div>
        <div class="navdb-tools" style="margin-top:12px"><button class="btn btn-ghost btn-small" id="navdb-struct-copyddl" type="button">⧉ Sao chép CREATE TABLE</button></div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Thêm dòng -->
<div class="modal" id="navdb-insert-modal"><div class="modal-card"><button class="modal-close" data-modal-close>×</button><h2 id="navdb-insert-h">Thêm dòng vào bảng</h2><form id="navdb-insert-form" class="stack" style="max-height:62vh;overflow:auto"></form></div></div>
<!-- Modal Tạo database -->
<div class="modal" id="db-modal"><div class="modal-card"><button class="modal-close" data-modal-close>×</button><h2>Tạo database</h2><form method="post" action="/databases/create" class="stack"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><label><span>Tên database</span><input name="name" placeholder="project_db" required></label><button class="btn btn-primary">Tạo database</button></form></div></div>

<link rel="stylesheet" href="/assets/navdb.css?v=<?=tms_asset_version()?>">
<script>window.TMS_SQL_DBS=<?=json_encode($databases ?? [], JSON_UNESCAPED_UNICODE)?>;window.TMS_SQL_DRIVER=<?=json_encode($driver ?? 'SQLite')?>;</script>
<script defer src="/assets/navdb.js?v=<?=tms_asset_version() ?>"></script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
