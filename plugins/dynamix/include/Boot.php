<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
$var = parse_ini_file("/var/local/emhttp/var.ini");
?>
<!DOCTYPE HTML>
<html>
<head>
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<style>
div.notice{background-color:#FFF6BF;text-align:center;height:80px;line-height:80px;border-top:2px solid #FFD324;border-bottom:2px solid #FFD324;font-family:arimo;font-size:18px;}
span.title{font-size:28px;text-transform:uppercase;display:block;}
</style>
<script src="/webGui/javascript/dynamix.js"></script>
<script>
var start = new Date();

function timer() {
  var now = new Date();
  return Math.round((now.getTime()-start.getTime())/1000);
}
function reboot_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
   .done(function(){$('div.notice').html('<span class="title">Reboot</span>System is going down... '+timer()); setTimeout(reboot_online,5000);})
   .fail(function(){start=new Date(); setTimeout(reboot_offline,5000);});
}
function reboot_offline() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
   .done(function(){location = '/Main';})
   .fail(function(){$('div.notice').html('<span class="title">Reboot</span>System is rebooting... '+timer()); setTimeout(reboot_offline,1000);});
}

function shutdown_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:5000})
   .done(function(){$('div.notice').html('<span class="title">Shutdown</span>System is going down... '+timer()); setTimeout(shutdown_online,5000);})
   .fail(function(){start=new Date(); setTimeout(shutdown_offline,5000);});
}
function shutdown_offline() {
  var time = timer();
  if (time < 30) {
    $('div.notice').html('<span class="title">Shutdown</span>System is offline... '+time);
    setTimeout(shutdown_offline,5000);
  } else {
    $('div.notice').html('<span class="title">Shutdown</span>System is powered off...');
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
switch ($_POST['cmd']) {
  case 'reboot':
    exec('/sbin/reboot');?>
    <body onload="reboot_online()"><div class='notice'></div></body>
<?  break;
  case 'shutdown':
    exec('/sbin/poweroff');?>
    <body onload="shutdown_online()"><div class='notice'></div></body>
<?  break;
  default:?>
    <body onload="location='/Main'"></body>
<?
}
?>
</html>
