<?php
$title = 'Thùng rác · TMS OS';
$showShell = true;
require dirname(__DIR__) . '/layouts/header.php';
?>
<div class="page-head explorer-page-head">
    <div>
        <p class="eyebrow">TMS Explorer</p>
        <h1>Thùng rác</h1>
    </div>
    <div class="explorer-head-actions">
        <a class="btn btn-secondary" href="/files">Quay lại Explorer</a>
        <?php if(!empty($items)):?>
        <form method="post" action="/files/trash/empty" data-confirm="Dọn sạch thùng rác? Các mục sẽ bị xóa vĩnh viễn, không thể khôi phục.">
            <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
            <button class="btn btn-danger-soft" type="submit">Dọn sạch</button>
        </form>
        <?php endif;?>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>" data-flash-toast="<?=($flash['type']??'')==='success'?'success':'error'?>" hidden><?=tms_h((string)($flash['message']??''))?></div>
<?php endif; ?>

<div class="panel-card">
    <?php if(empty($items)):?>
        <div class="explorer-empty">
            <p>Thùng rác trống.</p>
            <p class="muted">Các tệp/thư mục bạn xóa sẽ nằm ở đây và có thể khôi phục.</p>
        </div>
    <?php else:?>
        <div class="trash-list">
            <?php foreach($items as $it):?>
            <div class="trash-item" data-trash-item>
                <div class="trash-icon"><?=$it['is_dir']?'📁':'📄'?></div>
                <div class="trash-info">
                    <strong><?=tms_h($it['name'])?></strong>
                    <span class="muted">Gốc: <?=tms_h($it['root'])?>/<?=tms_h($it['relative'])?></span>
                    <span class="muted">Đã xóa: <?=date('d/m/Y H:i',(int)$it['deleted_at'])?><?=isset($it['size'])?' · '.tms_h(tms_format_bytes((int)$it['size'])):''?></span>
                </div>
                <div class="trash-actions">
                    <form method="post" action="/files/trash/restore">
                        <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
                        <input type="hidden" name="id" value="<?=tms_h($it['id'])?>">
                        <button class="btn btn-secondary btn-small" type="submit">Khôi phục</button>
                    </form>
                    <form method="post" action="/files/trash/delete" data-confirm="Xóa vĩnh viễn &quot;<?=tms_h($it['name'])?>&quot;? Không thể khôi phục.">
                        <input type="hidden" name="csrf" value="<?=tms_h($csrf)?>">
                        <input type="hidden" name="id" value="<?=tms_h($it['id'])?>">
                        <button class="btn btn-danger-soft btn-small" type="submit">Xóa vĩnh viễn</button>
                    </form>
                </div>
            </div>
            <?php endforeach;?>
        </div>
    <?php endif;?>
</div>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
