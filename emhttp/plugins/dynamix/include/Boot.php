<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

// add translations
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

$var = parse_ini_file("/var/local/emhttp/var.ini");

/**
 * Just like DefaultPageLayout.php
 */
require_once "$docroot/plugins/dynamix/include/ThemeHelper.php";  
$themeHelper = new ThemeHelper($display['theme']);
$themeName = $themeHelper->getThemeName();

$onload_function = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $safemode = '/boot/unraidsafemode';
  $progress = (_var($var,'fsProgress')!='') ? "<br><span class='blue'>{$var['fsProgress']}</span>" : "<br>&nbsp;";
  
  // Determine the onload function and execute system command
  switch (_var($_POST,'cmd','shutdown')) {
    case 'reboot':
      if (isset($_POST['safemode'])) touch($safemode); else @unlink($safemode);
      $onload_function = 'reboot_now()';
      exec('/sbin/reboot -n');
      break;
    case 'shutdown':
      if (isset($_POST['safemode'])) touch($safemode); else @unlink($safemode);
      $onload_function = 'shutdown_now()';
      exec('/sbin/poweroff -n');
      break;
  }
  
  // Determine array status display
  $array_status = '';
  switch (_var($var,'fsState')) {
    case 'Stopped':
      $array_status = "<span class='red'>" . _('Array Stopped') . "</span>$progress";
      break;
    case 'Starting':
      $array_status = "<span class='orange'>" . _('Array Starting') . "</span>$progress";
      break;
    case 'Stopping':
      $array_status = "<span class='orange'>" . _('Array Stopping') . "</span>$progress";
      break;
    default:
      $array_status = "<span class='green'>" . _('Array Started') . "</span>$progress";
      break;
  }
} else {
  $onload_function = 'redirectToMainPage()';
}
?>
<!DOCTYPE HTML>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>" class="<?= $themeHelper->getThemeHtmlClass() ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="image/png" rel="shortcut icon" href="/webGui/images/<?=_var($var,'mdColor','red-on')?>.png">

<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-color-palette.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-base.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/themes/{$themeName}.css")?>">

<style>
body {
  padding: 2rem 1rem;
}
.boot-title {
  color: var(--orange-300);
  font-size: 4rem;
  text-transform: uppercase;
  text-align: center;
}
.boot-subtext {
  text-align: center;
  font-size: 2.5rem;
  font-weight: 400;
  line-height: 1.5;
}
</style>

<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script src="<?autov('/webGui/javascript/translate.'.($locale?:'en_US').'.js')?>"></script>
<script>
/*
 * If we have a sessionStorage item for hiding the UPC's 'lets unleash your hardware' overlay for ENOKEYFILE state users
 * this will remove the item so that if the user reboots their server the overlay will display again once the server comes back up.
*/
const serverName = '<?=_var($var,'NAME')?>';
const guid = '<?=_var($var,'flashGUID')?>';
sessionStorage.removeItem(`${serverName}_${guid ? guid.slice(-12) : 'NO_GUID'}`);

var start = new Date();

var boot = new NchanSubscriber('/sub/var',{subscriber:'websocket'});
boot.on('message', function(msg) {
  var ini = parseINI(msg);
  switch (ini['fsState']) {
    case 'Stopped'   : var status = "<span class='red'><?=_('Array Stopped')?></span>"; break;
    case 'Started'   : var status = "<span class='green'><?=_('Array Started')?></span>"; break;
    case 'Formatting': var status = "<span class='green'><?=_('Array Started')?></span><br><span class='orange'><?=_('Formatting device(s)')?></span>"; break;
    default          : var status = "<span class='orange'>"+_('Array '+ini['fsState'])+"</span>";
  }
  status += ini['fsProgress'] ? "<br><span class='blue'>"+_(ini['fsProgress'])+"</span>" : "<br>&nbsp;";
  $('.js-arrayStatus').html(status);
});

function redirectToMainPage() {
  window.location = '/Main';
}

function parseINI(msg) {
  var regex = {
    section: /^\s*\[\s*\"*([^\]]*)\s*\"*\]\s*$/,
    param: /^\s*([^=]+?)\s*=\s*\"*(.*?)\s*\"*$/,
    comment: /^\s*;.*$/
  };
  var value = {};
  var lines = msg.split(/[\r\n]+/);
  var section = null;
  lines.forEach(function(line) {
    if (regex.comment.test(line)) {
      return;
    } else if (regex.param.test(line)) {
      var match = line.match(regex.param);
      if (section) {
        value[section][match[1]] = match[2];
      } else {
        value[match[1]] = match[2];
      }
    } else if (regex.section.test(line)) {
      var match = line.match(regex.section);
      value[match[1]] = {};
      section = match[1];
    } else if (line.length==0 && section) {
      section = null;
    };
  });
  return value;
}
function timer() {
  var now = new Date();
  return Math.round((now.getTime()-start.getTime())/1000);
}
function reboot_now() {
  $('.js-bootTitle').html("<?=_('Reboot')?> - <?=gethostname()?>");
  boot.start();
  reboot_online();
}
function shutdown_now() {
  $('.js-bootTitle').html("<?=_('Shutdown')?> - <?=gethostname()?>");
  boot.start();
  shutdown_online();
}
function reboot_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:500})
  .done(function(){
    $('.js-powerStatus').html("<?=_('System is going down')?>... "+timer());
    setTimeout(reboot_online,500);
  })
  .fail(function(){start=new Date(); setTimeout(reboot_offline,500);});
}
function reboot_offline() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:500})
  .done(function(){redirectToMainPage();})
  .fail(function(){
    $('.js-powerStatus').html("<?=_('System is rebooting')?>... "+timer());
    setTimeout(reboot_offline,500);
  });
}
function shutdown_online() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:500})
  .done(function(){
    $('.js-powerStatus').html("<?=_('System is going down')?>... "+timer());
    setTimeout(shutdown_online,500);
  })
  .fail(function(){start=new Date(); setTimeout(shutdown_offline,500);});
}
function shutdown_offline() {
  var time = timer();
  if (time < 30) {
    $('.js-powerStatus').html("<?=_('System is offline')?>... "+time);
    setTimeout(shutdown_offline,500);
  } else {
    $('.js-powerStatus').html("<?=_('System is powered off')?>...");
    setTimeout(power_on,500);
  }
}
function power_on() {
  $.ajax({url:'/webGui/include/ProcessStatus.php',type:'POST',data:{name:'emhttpd',update:true},timeout:500})
  .done(function(){redirectToMainPage();})
  .fail(function(){setTimeout(power_on,500);});
}

$(document).ajaxSend(function(elm, xhr, s){
  if (s.type == 'POST') {
    s.data += s.data?"&":"";
    s.data += "csrf_token=<?=_var($var,'csrf_token')?>";
  }
});
</script>
</head>
<body onload="<?= $onload_function ?>">
  <h1 class="js-bootTitle boot-title"></h1>
  <h2 class="js-arrayStatus boot-subtext"><?= $array_status ?></h2>
  <h3 class="js-powerStatus boot-subtext"></h3>
</body>
</html>
