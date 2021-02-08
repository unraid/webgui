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
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

$var = parse_ini_file("/var/local/emhttp/var.ini");
?>
<!DOCTYPE HTML>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=1600">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="text/css" rel="stylesheet" href="<?autov('/webGui/styles/default-fonts.css')?>">
<meta name="referrer" content="same-origin">
<style>
html{font-family:clear-sans;font-size:62.5%;height:100%}
body{font-size:1.3rem;color:#1c1c1c;background:#f2f2f2;padding:0;margin:0;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
div.notice{background-color:#FFF6BF;text-align:center;height:80px;line-height:80px;border-top:2px solid #FFD324;border-bottom:2px solid #FFD324;font-size:1.8rem;}
span.title{font-size:2.8rem;text-transform:uppercase;display:block;}
</style>
<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script>
var start = new Date();

function timer() {
  var now = new Date();
  return Math.round((now.getTime()-start.getTime())/1000);
}
function reboot_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
   .done(function(){$('div.notice').html('<span class="title"><?=_("Reboot")?></span><?=_("System is going down")?>... '+timer()); setTimeout(reboot_online,5000);})
   .fail(function(){start=new Date(); setTimeout(reboot_offline,5000);});
}
function reboot_offline() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
   .done(function(){location = '/Main';})
   .fail(function(){$('div.notice').html('<span class="title"><?=_("Reboot")?></span><?=_("System is rebooting")?>... '+timer()); setTimeout(reboot_offline,1000);});
}

function shutdown_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
   .done(function(){$('div.notice').html('<span class="title"><?=_("Shutdown")?></span><?=_("System is going down")?>... '+timer()); setTimeout(shutdown_online,5000);})
   .fail(function(){start=new Date(); setTimeout(shutdown_offline,5000);});
}
function shutdown_offline() {
  var time = timer();
  if (time < 30) {
    $('div.notice').html('<span class="title"><?=_("Shutdown")?></span><?=_("System is offline")?>... '+time);
    setTimeout(shutdown_offline,5000);
  } else {
    $('div.notice').html('<span class="title"><?=_("Shutdown")?></span><?=_("System is powered off")?>...');
  }
}
$(document).ajaxSend(function(elm, xhr, s){
  if (s.type == "POST") {
    s.data += s.data?"&":"";
    s.data += "csrf_token=<?=$var['csrf_token']?>";
  }
});
</script>
</head>
<?
$safemode = '/boot/unraidsafemode';
switch ($_POST['cmd']) {
  case 'reboot':
    if (isset($_POST['safemode'])) touch($safemode); else @unlink($safemode);
    exec('/sbin/reboot -n');?>
    <body onload="reboot_online()"><div class='notice'></div></body>
<?  break;
  case 'shutdown':
    if (isset($_POST['safemode'])) touch($safemode); else @unlink($safemode);
    exec('/sbin/poweroff -n');?>
    <body onload="shutdown_online()"><div class='notice'></div></body>
<?  break;
  default:?>
    <body onload="location='/Main'"></body>
<?
}
?>
</html>
