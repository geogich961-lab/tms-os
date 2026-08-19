<?php $title='Log website · TMS OS';$showShell=true;require dirname(__DIR__).'/layouts/header.php';?>
<div class="page-head"><div><p class="eyebrow">SITE LOGS</p><h1><?=tms_h($name)?></h1></div><a class="btn btn-secondary" href="/websites">Quay lại</a></div>
<section class="panel-card"><h2>Error log</h2><pre class="terminal-output"><?=tms_h($logs['error']??'')?></pre></section>
<section class="panel-card"><h2>Access log</h2><pre class="terminal-output"><?=tms_h($logs['access']??'')?></pre></section>
<?php require dirname(__DIR__).'/layouts/footer.php';?>
