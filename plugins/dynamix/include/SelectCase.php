<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2012-2018, Bergware International.
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

if ($_POST['mode']=='set') {
  if ($model) file_put_contents("$boot/$file",$model); elseif ($exist) unlink("$boot/$file");
  exit;
}
if ($_POST['mode']=='get') {
  if ($exist) echo file_get_contents("$boot/$file");
  exit;
}
$casemodel = $exist ? file_get_contents("$boot/$file") : '';
?>
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-cases.css")?>">
<style>
div.case-list{float:left;padding:10px;margin-right:10px;margin-bottom:30px;height:88px;width:88px;text-align:center}
div.case-list i{width:auto;max-width:88px;height:88px;font-size:88px;}
div.case-list:hover{color:#f0000c}
div.case-name{margin-top:8px;font-family:clear-sans}
</style>
<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script>
function setCase(model) {
  $.post('/webGui/include/SelectCase.php',{mode:'set',file:'<?=$file?>',model:model,csrf_token:'<?=$_GET['csrf']?>'},function(){top.Shadowbox.close();});
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
?>
</div>
