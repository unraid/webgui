<?PHP
/* Copyright 2005-2019, Lime Technology
 * Copyright 2012-2019, Bergware International.
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
require_once "$docroot/webGui/include/Helpers.php";

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
  symlink("$boot/$name","$docroot/webGui/images/$name");
  exit;
}
$casemodel = $exist ? file_get_contents("$boot/$file") : '';
?>
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-cases.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/font-awesome.css")?>">
<style>
div.case-list{float:left;padding:10px;margin-right:10px;margin-bottom:44px;height:72px;width:72px;text-align:center}
div.case-list i{width:auto;max-width:72px;height:72px;font-size:72px;}
div.case-list i.fa{padding-top:16px;margin-bottom:-16px;max-width:48px;font-size:48px;}
div.case-list:hover{color:#f0000c;transform:scale(1.4,1.4);-webkit-transform:scale(1.4,1.4)}
div.case-list:hover .case-name{margin-top:-5px;font-size:1rem}
div.case-name{margin-top:8px;font-family:clear-sans}
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
</script>
<div style='margin:20px 0 0 50px'>
<?
$models = [];
$cases = explode("\n",file_get_contents("$docroot/webGui/styles/default-cases.css"));
foreach ($cases as $case) if (substr($case,0,6)=='.case-') $models[] = substr($case,1,strpos($case,':')-1);
sort($models);
foreach ($models as $model) {
  $name = substr($model,5);
  $select = $name==$casemodel ? 'color:#e68a00' : '';
  echo "<a style='text-decoration:none;cursor:pointer;$select' onclick='setCase(\"$name\")'><div class='case-list' id='$name'><i class='$model'></i><div class='case-name'>$name</div></div></a>";
}
$select = substr($casemodel,-4)=='.png' ? 'color:#e68a00' : '';
?>
<a style='text-decoration:none;cursor:pointer;<?=$select?>' onclick='$("input#file").trigger("click")'><div class='case-list' id='Custom'><i class='fa fa-file-image-o'></i><div class='case-name'>custom image</div></div></a>
<a style='text-decoration:none;cursor:pointer' onclick='deleteCase()'><div class='case-list'><i class='fa fa-hdd-o'></i><div class='case-name'>default image</div></div></a>
<input type='file' id='file' accept='.png' onchange='importFile(this.files[0])' style='display:none'>
</div>
