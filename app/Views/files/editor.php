<?php $title='Editor · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';
$v=tms_asset_version();$cm='/assets/vendor/codemirror';?>
<link rel="stylesheet" href="<?=$cm?>/codemirror.min.css?v=<?=$v?>">
<div class="page-head"><div><p class="eyebrow">Code Editor</p><h1><?=tms_h($document['name'])?></h1></div><a class="btn btn-secondary" href="<?=tms_url('/files',['root'=>$root,'path'=>dirname($document['relative'])==='.'?'':dirname($document['relative'])])?>">Quay lại</a></div><?php if($flash):?><div class="alert <?=$flash['type']==='success'?'alert-success':'alert-error'?>" data-flash-toast="<?=$flash['type']==='success'?'success':'error'?>" hidden><?=tms_h((string)$flash['message'])?></div><?php endif;?><form method="post" action="/files/save" class="editor-card" id="tms-editor-form"><input type="hidden" name="csrf" value="<?=tms_h($csrf)?>"><input type="hidden" name="root" value="<?=tms_h($root)?>"><input type="hidden" name="file" value="<?=tms_h($document['relative'])?>"><textarea name="content" id="tms-code" class="code-editor" spellcheck="false"><?=tms_h($document['content'])?></textarea><div class="editor-actions"><span><?=tms_h($document['relative'])?></span><button class="btn btn-primary">Lưu thay đổi</button></div></form>
<script src="<?=$cm?>/cm-loader.js?v=<?=$v?>"></script>
<script src="<?=$cm?>/cm-b64-1.js?v=<?=$v?>"></script>
<script src="<?=$cm?>/cm-b64-2.js?v=<?=$v?>"></script>
<script src="<?=$cm?>/cm-b64-3.js?v=<?=$v?>"></script>
<script>
(function(){
  var ta=document.getElementById('tms-code');
  var form=document.getElementById('tms-editor-form');
  function wirePlain(){ /* textarea thường: submit mặc định đã gửi nội dung */ }
  function wireEditor(){
    var mode=<?=json_encode($cmMode ?? '')?>;
    var editor=CodeMirror.fromTextArea(ta,{
      lineNumbers:true,
      mode:mode||null,
      indentUnit:4,
      tabSize:4,
      indentWithTabs:false,
      lineWrapping:false,
      viewportMargin:20
    });
    editor.setSize('100%','60vh');
    form.addEventListener('submit',function(){editor.save();});
  }
  if(window.tmsLoadCodeMirror){
    window.tmsLoadCodeMirror(<?=json_encode($cm)?>,<?=json_encode($v)?>,<?=json_encode($cmMode ?? '')?>).then(function(ok){
      if(ok&&window.CodeMirror){wireEditor();}else{wirePlain();}
    });
  }else{wirePlain();}
})();
</script>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
