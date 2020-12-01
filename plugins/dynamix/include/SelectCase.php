<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// add translations
$_SERVER['REQUEST_URI'] = 'dashboard';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

$boot  = "/boot/config/plugins/dynamix";
$file  = $_GET['file'] ?? $_POST['file'];
$model = $_POST['model'] ?? false;
$exist = file_exists("$boot/$file");

switch ($_POST['mode']) {
case 'set':
  file_put_contents("$boot/$file",$model);
  exit;
case 'get':
  if ($exist) echo file_get_contents("$boot/$file");
  exit;
case 'del':
  if ($exist) unlink("$boot/$file");
  exit;
case 'file':
  $name = 'case-model.png';
  file_put_contents("$boot/$file",$name);
  file_put_contents("$boot/$name",base64_decode(str_replace('data:image/png;base64,','',$_POST['data'])));
  exit;
}
$casemodel = $exist ? file_get_contents("$boot/$file") : '';
?>
<!DOCTYPE html>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<title><?=_('Select Case Model')?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=1600">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-cases.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/font-awesome.css")?>">
<style>
div.tab{float:left;margin-top:0}
div.tab input[id^="tab"]{display:none}
div.tab [type=radio]+label:hover{background-color:transparent;border:1px solid #ff8c2f;border-bottom:none;cursor:pointer;opacity:1}
div.tab [type=radio]:checked+label{cursor:default;background-color:transparent;border:1px solid #ff8c2f;border-bottom:none;opacity:1}
div.tab [type=radio]+label~.content{display:none}
div.tab [type=radio]:checked+label~.content{display:inline}
div.tab [type=radio]+label{position:relative;font-size:1.4rem;letter-spacing:1.8px;padding:4px 10px;margin-right:2px;border-top-left-radius:6px;border-top-right-radius:6px;border:1px solid #b2b2b2;border-bottom:none;background-color:#e2e2e2;opacity:0.5}
div.tab [type=radio]+label img{padding-right:4px}
div.content{position:absolute;top:0;left:0;width:100%}
label+.content{margin-top:64px}
div.case-list{float:left;padding:10px;margin-right:10px;margin-bottom:64px;height:128px;width:128px;text-align:center}
div.case-list.left{margin-left:20px}
div.case-list.right{margin-right:0;padding-right:0}
div.case-list i{width:auto;max-width:128px;height:128px;font-size:128px}
div.case-list i.fa{padding-top:16px;margin-bottom:-16px;max-width:80px;font-size:80px}
div.case-list:hover{color:#f0000c}
div.case-name{margin-top:8px;font-family:clear-sans!important}
</style>
<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script>
function importFile(file) {
  var reader = new FileReader();
  reader.readAsDataURL(file);
  reader.onload = function(e){$.post('/webGui/include/SelectCase.php',{mode:'file',file:'<?=$file?>',data:e.target.result,csrf_token:'<?=$_GET['csrf']?>'},function(){top.Shadowbox.close();})};
}
function setCase(model) {
  $.post('/webGui/include/SelectCase.php',{mode:'set',file:'<?=$file?>',model:model,csrf_token:'<?=$_GET['csrf']?>'},function(){top.Shadowbox.close();});
}
function deleteCase() {
  $.post('/webGui/include/SelectCase.php',{mode:'del',file:'<?=$file?>',csrf_token:'<?=$_GET['csrf']?>'},function(){top.Shadowbox.close();});
}
$(function() {
  $('#tab1').prop('checked',true);
});
</script>
</head>
<body>
<div style='margin:20px 0 0 30px'>
<?
$models = [];
$cases = explode("\n",file_get_contents("$docroot/webGui/styles/default-cases.css"));
foreach ($cases as $case) if (substr($case,0,6)=='.case-') $models[] = substr($case,1,strpos($case,':')-1);
natsort($models);
$tabs = floor((count($models)+2)/18)+1;
for ($tab=1; $tab<=$tabs; $tab++) {
  echo "<div class='tab'><input type='radio' id='tab{$tab}' name='tabs'><label for='tab{$tab}'>$tab</label><div class='content'>";
  for ($i=($tab-1)*18; $i < $tab*18; $i++) {
    if ($i>=count($models)) break;
    $model = $models[$i];
    $name = substr($model,5);
    $title = str_replace('3u-avs-10-4','3u-avs-10/4',$name);
    $select = $name==$casemodel ? 'color:#e68a00' : '';
    echo "<a style='text-decoration:none;cursor:pointer;$select' onclick='setCase(\"$name\")'><div class='case-list".($i%6==0?' left':($i%6==5?' right':''))."' id='$name'><i class='$model'></i><div class='case-name'>$title</div></div></a>";
  }
  if ($tab==$tabs) {
    echo "<a style='text-decoration:none;cursor:pointer;$select' onclick='$(\"input#file\").trigger(\"click\")'><div class='case-list".($i%6==0?' left':($i%6==5?' right':''))."' id='Custom'><i class='fa fa-file-image-o'></i><div class='case-name'>"._('custom image')."</div></div></a>"; $i++;
  }
  echo "</div></div>";
}
$select = substr($casemodel,-4)=='.png' ? 'color:#e68a00' : '';
?>
<input type='file' id='file' accept='.png' onchange='importFile(this.files[0])' style='display:none'>
</div>
</body>
</html>
