<?php $title='SQL Editor · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">SQL Editor · <?=tms_h(($driver ?? 'SQLite'))?></p><h1>SQL Editor</h1></div></div>
<?php if($error):?><div class="alert alert-error" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><?=tms_h($error)?></div><?php endif;?>
<div class="sql-layout">
<aside class="sql-sidebar table-card">
 <p class="eyebrow" style="margin:0 0 8px">Database</p>
 <?php if(!$databases):?><div class="muted" style="font-size:.85rem;padding:8px 0">Chưa có database nào.<br>Vào trang Database để tạo.</div><?php endif;?>
 <?php foreach($databases as $d):?>
 <button class="sql-db-item" data-db="<?=tms_h($d['db_key'])?>"><?=tms_h($d['name'])?></button>
 <?php endforeach;?>
</aside>
<div class="sql-main">
<div class="sql-tabs" role="tablist">
 <button class="sql-tab active" data-tab="data" type="button">Dữ liệu</button>
 <button class="sql-tab" data-tab="sql" type="button">Chạy SQL</button>
 <button class="sql-tab" data-tab="structure" type="button">Cấu trúc</button>
</div>
<div id="sql-no-db" class="sql-empty">Chọn một database bên trái để bắt đầu.</div>
<div id="sql-panel" hidden>
 <!-- Tab Dữ liệu -->
 <div class="sql-pane" id="tab-data">
  <div class="sql-toolbar">
   <select id="sql-tables" class="sql-select"></select>
   <div class="sql-toolbar-right">
    <label class="sql-readonly"><input type="checkbox" id="sql-readonly"> Chỉ đọc</label>
    <button class="btn btn-secondary btn-small" id="sql-insert-btn" type="button">+ Thêm dòng</button>
    <button class="btn btn-secondary btn-small" id="sql-refresh-btn" type="button">↻ Làm mới</button>
   </div>
  </div>
  <div id="sql-table-empty" class="sql-empty" hidden>Chọn bảng ở trên để xem dữ liệu.</div>
  <div class="table-wrap" id="sql-data-wrap"></div>
  <p class="muted sql-count" id="sql-count"></p>
 </div>
 <!-- Tab Chạy SQL -->
 <div class="sql-pane" id="tab-sql" hidden>
  <div class="sql-editor-row">
   <textarea id="sql-input" class="sql-input" placeholder="SELECT * FROM bang WHERE id = 1;&#10;UPDATE bang SET ten = 'moi' WHERE id = 2;&#10;(Mẹo: Ctrl+Enter để chạy)" spellcheck="false"></textarea>
  </div>
  <div class="sql-toolbar">
   <label class="sql-readonly"><input type="checkbox" id="sql-readonly2"> Chế độ chỉ đọc (an toàn, không ghi)</label>
   <div class="sql-toolbar-right">
    <button class="btn btn-primary btn-small" id="sql-run-btn" type="button">▶ Chạy (Ctrl+Enter)</button>
   </div>
  </div>
  <div id="sql-result-meta" class="sql-count"></div>
  <div class="table-wrap" id="sql-result-wrap"></div>
 </div>
 <!-- Tab Cấu trúc -->
 <div class="sql-pane" id="tab-structure" hidden>
  <div class="sql-toolbar"><select id="sql-struct-tables" class="sql-select"></select></div>
  <div id="sql-struct-empty" class="sql-empty" hidden>Chọn bảng để xem cấu trúc.</div>
  <div class="table-wrap" id="sql-struct-wrap"></div>
 </div>
</div>
</div>
</div>
<!-- Modal thêm dòng -->
<div class="modal" id="sql-insert-modal" hidden><div class="modal-card"><button class="modal-close" data-modal-close>×</button><h2>Thêm dòng mới — <span id="sql-insert-table"></span></h2><form id="sql-insert-form" class="stack"></form></div></div>
<style>
.sql-layout{display:grid;grid-template-columns:230px 1fr;gap:16px;align-items:start}
@media(max-width:800px){.sql-layout{grid-template-columns:1fr}}
.sql-sidebar{display:flex;flex-direction:column;gap:4px;max-height:62vh;overflow:auto}
.sql-db-item{display:block;width:100%;text-align:left;padding:10px 12px;border:0;border-radius:11px;background:transparent;color:var(--text);font-weight:700;font-size:.9rem;cursor:pointer}
.sql-db-item:hover{background:var(--surface2)}
.sql-db-item.active{background:rgba(var(--primary-rgb),.13);color:var(--primary)}
.sql-tabs{display:flex;gap:8px;margin-bottom:12px;border-bottom:1px solid var(--border)}
.sql-tab{padding:10px 16px;border:0;border-bottom:3px solid transparent;background:transparent;color:var(--muted);font-weight:800;font-size:.92rem;cursor:pointer}
.sql-tab.active{color:var(--primary);border-bottom-color:var(--primary)}
.sql-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:10px 0}
.sql-toolbar-right{margin-left:auto;display:flex;gap:8px;align-items:center}
.sql-select{flex:1;min-width:160px;padding:10px 12px;border:1px solid var(--border);border-radius:11px;background:var(--surface);color:var(--text);font-weight:700;font-size:.9rem}
.sql-readonly{display:inline-flex;gap:6px;align-items:center;color:var(--muted);font-weight:700;font-size:.85rem}
.sql-empty{padding:48px 20px;color:var(--muted);text-align:center}
.sql-input{width:100%;min-height:150px;padding:14px;border:1px solid var(--border);border-radius:13px;background:var(--surface);color:var(--text);font-family:'JetBrains Mono','SF Mono',monospace;font-size:.88rem;resize:vertical}
.sql-input:focus{outline:2px solid rgba(var(--primary-rgb),.4);border-color:var(--primary)}
.sql-grid{border-collapse:collapse;width:100%}
.sql-grid th{position:sticky;top:0;background:var(--surface2);padding:9px 11px;text-align:left;font-size:.82rem;color:var(--muted);border-bottom:1px solid var(--border)}
.sql-grid td{padding:7px 11px;border-bottom:1px solid var(--border);font-size:.88rem;vertical-align:top;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sql-grid tr:hover td{background:var(--surface2)}
.sql-grid .cell-edit{cursor:pointer;min-width:60px}
.sql-grid .cell-edit:focus-within{outline:none;background:transparent}
.sql-cell-input{width:100%;padding:6px 8px;border:1px solid var(--primary);border-radius:8px;background:var(--surface);color:var(--text);font-size:.88rem;font-family:inherit}
.sql-null{color:var(--muted);font-style:italic}
.sql-count{font-size:.82rem;margin:8px 0 16px}
.sql-action{display:inline-flex;gap:4px}
.sql-action button{border:0;border-radius:8px;background:transparent;color:var(--muted);cursor:pointer;font-size:1.05rem;padding:4px 7px}
.sql-action button:hover{background:var(--surface2);color:var(--text)}
.sql-action button.del:hover{color:#e05252}
.sql-result-error{padding:12px 14px;border-radius:11px;background:rgba(224,82,82,.1);color:#e05252;font-weight:700;margin:10px 0}
.sql-result-ok{padding:12px 14px;border-radius:11px;background:rgba(48,180,120,.1);color:var(--success);font-weight:700;margin:10px 0}
</style>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
