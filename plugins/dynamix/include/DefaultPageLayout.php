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
$display['font'] = filter_var($_COOKIE['fontSize']??$display['font'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION);
$theme   = strtok($display['theme'],'-');
$header  = $display['header'];
$backgnd = $display['background'];
$themes1 = in_array($theme,['black','white']);
$themes2 = in_array($theme,['gray','azure']);
$config  = "/boot/config";
$entity  = $notify['entity'] & 1 == 1;
$alerts  = '/tmp/plugins/my_alerts.txt';

// adjust the text color in docker log window
$fgcolor = in_array($theme,['white','azure']) ? '#1c1c1c' : '#f2f2f2';
exec("sed -ri 's/^\.logLine\{color:#......;/.logLine{color:$fgcolor;/' $docroot/plugins/dynamix.docker.manager/log.htm >/dev/null &");

function annotate($text) {echo "\n<!--\n",str_repeat("#",strlen($text)),"\n$text\n",str_repeat("#",strlen($text)),"\n-->\n";}
?>
<!DOCTYPE html>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<title><?=$var['NAME']?>/<?=$myPage['name']?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="image/png" rel="shortcut icon" href="/webGui/images/<?=$var['mdColor']?>.png">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-cases.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/font-awesome.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/context.standalone.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/jquery.sweetalert.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-{$display['theme']}.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/dynamix-{$display['theme']}.css")?>">
<style>
<?if ($display['font']):?>
html{font-size:<?=$display['font']?>%}
<?endif;?>
<?if ($header):?>
#header,#header .logo,#header .text-right a{color:#<?=$header?>}
#header .block{background-color:transparent}
<?endif;?>
<?if ($backgnd):?>
#header{background-color:#<?=$backgnd?>}
<?if ($themes1):?>
.nav-tile{background-color:#<?=$backgnd?>}
.nav-item a{color:#<?=$header?>}
.nav-item.active:after{background-color:#<?=$header?>}
<?endif;?>
<?endif;?>
.inline_help{display:none}
.upgrade_notice{position:fixed;top:24px;left:50%;margin-left:-480px;width:900px;height:38px;line-height:38px;color:#e68a00;background-color:#feefb3;border:#e68a00 1px solid;border-radius:38px;text-align:center;font-size:1.4rem;z-index:999}
.upgrade_notice.done{color:#4f8a10;background-color:#dff2bf;border-color:#4f8a10}
.upgrade_notice.alert{color:#f0000c;background-color:#ff9e9e;border-color:#f0000c}
.upgrade_notice i{float:right;cursor:pointer}
.back_to_top{display:none;position:fixed;bottom:30px;right:12px;color:#e22828;font-size:2.5rem;z-index:999}
span.big.blue-text{cursor:pointer}
i.abortOps{font-size:2rem;float:right;margin-right:20px;margin-top:8px;cursor:pointer}
pre#swalbody p{margin-block-end:1em}
<?
$nchan = ['webGui/nchan/notify_poller','webGui/nchan/session_check'];
$safemode = $var['safeMode']=='yes';
$tasks = find_pages('Tasks');
$buttons = find_pages('Buttons');
$banner = "$config/plugins/dynamix/banner.png";
echo "#header.image{background-image:url(";
echo file_exists($banner) ? autov($banner) : '/webGui/images/banner.png';
echo ")}\n";
if ($themes2) {
  foreach ($tasks as $button) if (isset($button['Code'])) echo ".nav-item a[href='/{$button['name']}']:before{content:'\\{$button['Code']}'}\n";
  echo ".nav-item.LockButton a:before{content:'\\e955'}\n";
  foreach ($buttons as $button) if (isset($button['Code'])) echo ".nav-item.{$button['name']} a:before{content:'\\{$button['Code']}'}\n";
}
$notes = '/var/tmp/unRAIDServer.txt';
if (!file_exists($notes)) file_put_contents($notes,shell_exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin changes $docroot/plugins/unRAIDServer/unRAIDServer.plg"));
$notes = "&nbsp;<span class='fa fa-info-circle fa-fw big blue-text' title='"._('View Release Notes')."' onclick=\"openChanges('showchanges $notes','"._('Release Notes')."')\"></span>";
?>
</style>

<noscript>
<div class="upgrade_notice"><?=_("Your browser has JavaScript disabled")?></div>
</noscript>

<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script src="<?autov('/webGui/javascript/translate.'.($locale?:'en_US').'.js')?>"></script>
<script>
String.prototype.actionName = function(){return this.split(/[\\/]/g).pop();}
String.prototype.channel = function(){return this.split(':')[1].split(',').findIndex((e)=>/\[\d\]/.test(e));}

Shadowbox.init({skipSetup:true});

// server uptime
var uptime = <?=strtok(exec("cat /proc/uptime"),' ')?>;
var expiretime = <?=$var['regTy']=='Trial'||strstr($var['regTy'],'expired')?$var['regTm2']:0?>;
var before = new Date();

// page timer events
const timers = {};
timers.bannerWarning = null;

// tty window
var tty_window = null;

const addAlert = {};
addAlert.text = $.cookie('addAlert-text');
addAlert.cmd = $.cookie('addAlert-cmd');
addAlert.plg = $.cookie('addAlert-plg');
addAlert.func = $.cookie('addAlert-func');

// current csrf_token
var csrf_token = "<?=$var['csrf_token']?>";

// form has unsaved changes indicator
var formHasUnsavedChanges = false;

// docker progess indicators
var progress_dots = [], progress_span = [];
function pauseEvents(id) {
  $.each(timers, function(i,timer){
    if (!id || i==id) clearTimeout(timer);
  });
}
function resumeEvents(id,delay) {
  var startDelay = delay||50;
  $.each(timers, function(i,timer) {
    if (!id || i==id) timers[i] = setTimeout(i+'()', startDelay);
    startDelay += 50;
  });
}
function plus(value,single,plural,last) {
  return value>0 ? (value+' '+(value==1?single:plural)+(last?'':', ')) : '';
}
function updateTime() {
  var now = new Date();
  var days = parseInt(uptime/86400);
  var hour = parseInt(uptime/3600%24);
  var mins = parseInt(uptime/60%60);
  $('span.uptime').html(((days|hour|mins)?plus(days,"<?=_('day')?>","<?=_('days')?>",(hour|mins)==0)+plus(hour,"<?=_('hour')?>","<?=_('hours')?>",mins==0)+plus(mins,"<?=_('minute')?>","<?=_('minutes')?>",true):"<?=_('less than a minute')?>"));
  uptime += Math.round((now.getTime() - before.getTime())/1000);
  before = now;
  if (expiretime > 0) {
    var remainingtime = expiretime - now.getTime()/1000;
    if (remainingtime > 0) {
      days = parseInt(remainingtime/86400);
      hour = parseInt(remainingtime/3600%24);
      mins = parseInt(remainingtime/60%60);
      if (days) {
        $('#licenseexpire').html(plus(days,"<?=_('day')?>","<?=_('days')?>",true)+" <?=_('remaining')?>");
      } else if (hour) {
        $('#licenseexpire').html(plus(hour,"<?=_('hour')?>","<?=_('hours')?>",true)+" <?=_('remaining')?>").addClass('orange-text');
      } else if (mins) {
        $('#licenseexpire').html(plus(mins,"<?=_('minute')?>","<?=_('minutes')?>",true)+" <?=_('remaining')?>").addClass('red-text');
      } else {
        $('#licenseexpire').html("<?=_('less than a minute remaining')?>").addClass('red-text');
      }
    } else {
      $('#licenseexpire').addClass('red-text');
    }
  }
  setTimeout(updateTime,1000);
}
function refresh(top) {
  if (typeof top === 'undefined') {
    for (var i=0,element; element=document.querySelectorAll('input,button,select')[i]; i++) {element.disabled = true;}
    for (var i=0,link; link=document.getElementsByTagName('a')[i]; i++) { link.style.color = "gray"; } //fake disable
    location.reload();
  } else {
    $.cookie('top',top);
    location.reload();
  }
}
function initab(page) {
  $.removeCookie('one');
  $.removeCookie('tab');
  if (page != null) location.replace(page);
}
function settab(tab) {
<?switch ($myPage['name']):?>
<?case'Main':?>
  $.cookie('tab',tab);
<?if ($var['fsState']=='Started'):?>
  $.cookie('one','tab1');
<?endif;?>
<?break;?>
<?case'Cache':case'Data':case'Flash':case'Parity':?>
  $.cookie('one',tab);
<?break;?>
<?default:?>
  $.cookie(($.cookie('one')==null?'tab':'one'),tab);
<?endswitch;?>
}
function done(key) {
  var url = location.pathname.split('/');
  var path = '/'+url[1];
  if (key) for (var i=2; i<url.length; i++) if (url[i]==key) break; else path += '/'+url[i];
  $.removeCookie('one');
  location.replace(path);
}
function chkDelete(form, button) {
  button.value = form.confirmDelete.checked ? "<?=_('Delete')?>" : "<?=_('Apply')?>";
  button.disabled = false;
}
function makeWindow(name,height,width) {
  var top = (screen.height-height)/2;
  if (top < 0) {top = 0; height = screen.availHeight;}
  var left = (screen.width-width)/2;
  if (left < 0) {left = 0; width = screen.availWidth;}
  return window.open('',name,'resizeable=yes,scrollbars=yes,height='+height+',width='+width+',top='+top+',left='+left);
}
function openBox(cmd,title,height,width,load,func,id) {
  // open shadowbox window (run in foreground)
  // included for legacy purposes, replaced by openPlugin
  var uri = cmd.split('?');
  var run = uri[0].substr(-4)=='.php' ? cmd+(uri[1]?'&':'?')+'done=<?=urlencode(_("Done"))?>' : '/logging.htm?cmd='+cmd+'&csrf_token='+csrf_token+'&done=<?=urlencode(_("Done"))?>';
  var options = load ? (func ? {modal:true,onClose:function(){setTimeout(func+'('+'"'+(id||'')+'")');}} : {modal:true,onClose:function(){location.reload();}}) : {modal:false};
  Shadowbox.open({content:run, player:'iframe', title:title, height:Math.min(screen.availHeight,800), width:Math.min(screen.availWidth,1200), options:options});
}
function openWindow(cmd,title,height,width) {
  // open regular window (run in background)
  // included for legacy purposes, replaced by openTerminal
  var window_name = title.replace(/ /g,"_");
  var form_html = '<form action="/logging.htm" method="post" target="'+window_name+'">'+'<input type="hidden" name="csrf_token" value="'+csrf_token+'">'+'<input type="hidden" name="title" value="'+title+'">';
  var vars = cmd.split('&');
  form_html += '<input type="hidden" name="cmd" value="'+vars[0]+'">';
  for (var i = 1; i < vars.length; i++) {
    var pair = vars[i].split('=');
    form_html += '<input type="hidden" name="'+pair[0]+'" value="'+pair[1]+'">';
  }
  form_html += '</form>';
  var form = $(form_html);
  $('body').append(form);
  makeWindow(window_name,height,width);
  form.submit();
}
function openTerminal(tag,name,more) {
  if (/MSIE|Edge/.test(navigator.userAgent)) {
    swal({title:"_(Unsupported Feature)_",text:"_(Sorry, this feature is not supported by MSIE/Edge)_.<br>_(Please try a different browser)_",type:'error',html:true,confirmButtonText:"_(Ok)_"});
    return;
  }
  // open terminal window (run in background)
  name = name.replace(/[ #]/g,"_");
  tty_window = makeWindow(name+(more=='.log'?more:''),Math.min(screen.availHeight,800),Math.min(screen.availWidth,1200));
  var socket = ['ttyd','syslog'].includes(tag) ? '/webterminal/'+tag+'/' : '/logterminal/'+name+(more=='.log'?more:'')+'/';
  $.get('/webGui/include/OpenTerminal.php',{tag:tag,name:name,more:more},function(){tty_window.location=socket; tty_window.focus();});
}
function bannerAlert(text,cmd,plg,func,start) {
  $.post('/webGui/include/StartCommand.php',{cmd:cmd,pid:1},function(pid) {
    if (pid == 0) {
      if ($(".upgrade_notice").hasClass('done') || timers.bannerAlert == null) {
        forcedBanner = false;
        if ($.cookie('addAlert') != null) {
          removeBannerWarning($.cookie('addAlert'));
          $.removeCookie('addAlert');
        }
        $(".upgrade_notice").removeClass('alert done');
        timers.callback = null;
        if (plg != null) {
          if ($.cookie('addAlert-page') == null || $.cookie('addAlert-page') == '<?=$task?>') {
            setTimeout((func||'loadlist')+'("'+plg+'")',250);
          } else if ('Plugins' == '<?=$task?>') {
            setTimeout(refresh);
          }
        }
        $.removeCookie('addAlert-page');
      } else {
        $(".upgrade_notice").removeClass('alert').addClass('done');
        timers.bannerAlert = null;
        setTimeout(function(){bannerAlert(text,cmd,plg,func,start);},1000);
      }
    } else {
      $.cookie('addAlert',addBannerWarning(text,true,true,true));
      $.cookie('addAlert-text',text);
      $.cookie('addAlert-cmd',cmd);
      $.cookie('addAlert-plg',plg);
      $.cookie('addAlert-func',func);
      if ($.cookie('addAlert-page') == null) $.cookie('addAlert-page','<?=$task?>');
      timers.bannerAlert = setTimeout(function(){bannerAlert(text,cmd,plg,func,start);},1000);
      if (start==1 && timers.callback==null && plg!=null) timers.callback = setTimeout((func||'loadlist')+'("'+plg+'")',250);
    }
  });
}
function openPlugin(cmd,title,plg,func,start=0,button=0) {
  // start  = 0 : run command only when not already running (default)
  // start  = 1 : run command unconditionally
  // button = 0 : show CLOSE button (default)
  // button = 1 : hide CLOSE button
  nchan_plugins.start();
  $.post('/webGui/include/StartCommand.php',{cmd:cmd+' nchan',start:start},function(pid) {
    if (pid==0) {
      nchan_plugins.stop();
      $('div.spinner.fixed').hide();
      $(".upgrade_notice").addClass('alert');
      return;
    }
    swal({title:title,text:"<pre id='swaltext'></pre><hr>",html:true,animation:'none',showConfirmButton:button==0,confirmButtonText:"<?=_('Close')?>"},function(close){
      nchan_plugins.stop();
      $('div.spinner.fixed').hide();
      $('.sweet-alert').hide('fast').removeClass('nchan');
      setTimeout(function(){bannerAlert("<?=_('Attention - operation continues in background')?> ["+pid.toString().padStart(8,'0')+"]<i class='fa fa-bomb fa-fw abortOps' title=\"<?=_('Abort background process')?>\" onclick='abortOperation("+pid+")'></i>",cmd,plg,func,start);});
    });
    $('.sweet-alert').addClass('nchan');
    $('button.confirm').prop('disabled',button!=0);
  });
}
function openDocker(cmd,title,plg,func,start=0,button=0) {
  // start  = 0 : run command only when not already running (default)
  // start  = 1 : run command unconditionally
  // button = 0 : hide CLOSE button (default)
  // button = 1 : show CLOSE button
  nchan_docker.start();
  $.post('/webGui/include/StartCommand.php',{cmd:cmd,start:start},function(pid) {
    if (pid==0) {
      nchan_docker.stop();
      $('div.spinner.fixed').hide();
      $(".upgrade_notice").addClass('alert');
      return;
    }
    swal({title:title,text:"<pre id='swaltext'></pre><hr>",html:true,animation:'none',showConfirmButton:button!=0,confirmButtonText:"<?=_('Close')?>"},function(close){
      nchan_docker.stop();
      $('div.spinner.fixed').hide();
      $('.sweet-alert').hide('fast').removeClass('nchan');
      setTimeout(function(){bannerAlert("<?=_('Attention - operation continues in background')?> ["+pid.toString().padStart(8,'0')+"]<i class='fa fa-bomb fa-fw abortOps' title=\"<?=_('Abort background process')?>\" onclick='abortOperation("+pid+")'></i>",cmd,plg,func,start);});
    });
    $('.sweet-alert').addClass('nchan');
    $('button.confirm').prop('disabled',button==0);
  });
}
function abortOperation(pid) {
  swal({title:"<?=_('Abort background operation')?>",text:"<?=_('This may leave an unknown state')?>",html:true,animation:'none',type:'warning',showCancelButton:true,confirmButtonText:"<?=_('Proceed')?>",cancelButtonText:"<?=_('Cancel')?>"},function(){
    $.post('/webGui/include/StartCommand.php',{kill:pid},function() {
      clearTimeout(timers.bannerAlert);
      timers.bannerAlert = null;
      timers.callback = null;
      forcedBanner = false;
      removeBannerWarning($.cookie('addAlert'));
      $.removeCookie('addAlert');
      $(".upgrade_notice").removeClass('alert done').hide();
    });
  });
}
function openChanges(cmd,title,nchan,button=0) {
  // button = 0 : hide CLOSE button (default)
  // button = 1 : show CLOSE button
  // nchan argument is not used, exists for backward compatibility
  $.post('/webGui/include/StartCommand.php',{cmd:cmd,start:2},function(data) {
    $('div.spinner.fixed').hide();
    swal({title:title,text:"<pre id='swalbody'></pre><hr>",html:true,animation:'none',showConfirmButton:button!=0,confirmButtonText:"<?=_('Close')?>"},function(close){
      $('.sweet-alert').hide('fast').removeClass('nchan');
    });
    $('.sweet-alert').addClass('nchan');
    $('pre#swalbody').html(data);
    $('button.confirm').text("<?=_('Done')?>").prop('disabled',false).show();
  });
}
function openAlert(cmd,title,func) {
  nchan_changes.start();
  $.post('/webGui/include/StartCommand.php',{cmd:cmd+' nchan'},function(pid) {
    if (pid==0) {
      nchan_changes.stop();
      $('div.spinner.fixed').hide();
      return;
    }
    swal({title:title,text:"<pre id='swalbody'></pre><hr>",html:true,animation:'none',showCancelButton:true,closeOnConfirm:false,confirmButtonText:"<?=_('Proceed')?>",cancelButtonText:"<?=_('Cancel')?>"},function(proceed){
      nchan_changes.stop();
      if (proceed) setTimeout(func+'()');
    });
    $('.sweet-alert').addClass('nchan');
  });
}
function openDone(data) {
  if (data == '_DONE_') {
    $('div.spinner.fixed').hide();
    $('button.confirm').text("<?=_('Done')?>").prop('disabled',false).show();
    return true;
  }
  return false;
}
function showStatus(name,plugin,job) {
  $.post('/webGui/include/ProcessStatus.php',{name:name,plugin:plugin,job:job},function(status){$(".tabs").append(status);});
}
function showFooter(data, id) {
  if (id !== undefined) $('#'+id).remove();
  $('#copyright').prepend(data);
}
function showNotice(data) {
  $('#user-notice').html(data.replace(/<a>(.*)<\/a>/,"<a href='/Plugins'>$1</a>"));
}
function escapeQuotes(form) {
  $(form).find('input[type=text]').each(function(){$(this).val($(this).val().replace(/"/g,'\\"'));});
}

// Banner warning system
var bannerWarnings = [];
var currentBannerWarning = 0;
var osUpgradeWarning = false;
var forcedBanner = false;

function addBannerWarning(text, warning=true, noDismiss=false, forced=false) {
  var cookieText = text.replace(/[^a-z0-9]/gi,'');
  if ($.cookie(cookieText) == "true") return false;
  if (warning) text = "<i class='fa fa-warning fa-fw' style='float:initial'></i> "+text;
  if (bannerWarnings.indexOf(text) < 0) {
    if (forced) {
      var arrayEntry = 0; bannerWarnings = []; clearTimeout(timers.bannerWarning); timers.bannerWarning = null; forcedBanner = true;
    } else {
      var arrayEntry = bannerWarnings.push("placeholder") - 1;
    }
    if (!noDismiss) text += "<a class='bannerDismiss' onclick='dismissBannerWarning("+arrayEntry+",&quot;"+cookieText+"&quot;)'></a>";
    bannerWarnings[arrayEntry] = text;
  } else {
    return bannerWarnings.indexOf(text);
  }
  if (timers.bannerWarning==null) showBannerWarnings();
  return arrayEntry;
}

function dismissBannerWarning(entry,cookieText) {
  $.cookie(cookieText,"true",{expires:30}); // reset after 1 month
  removeBannerWarning(entry);
}

function removeBannerWarning(entry) {
  if (forcedBanner) return;
  bannerWarnings[entry] = false;
  clearTimeout(timers.bannerWarning);
  showBannerWarnings();
}

function bannerFilterArray(array) {
  var newArray = [];
  array.filter(function(value,index,arr) {
    if (value) newArray.push(value);
  });
  return newArray;
}

function showBannerWarnings() {
  var allWarnings = bannerFilterArray(Object.values(bannerWarnings));
  if (allWarnings.length == 0) {
    $(".upgrade_notice").hide();
    timers.bannerWarning = null;
    return;
  }
  if (currentBannerWarning >= allWarnings.length) currentBannerWarning = 0;
  $(".upgrade_notice").show().html(allWarnings[currentBannerWarning]);
  currentBannerWarning++;
  timers.bannerWarning = setTimeout(showBannerWarnings,3000);
}

function addRebootNotice(message="<?=_('You must reboot for changes to take effect')?>") {
  addBannerWarning("<i class='fa fa-warning' style='float:initial;'></i> "+message,false,true);
  $.post("/plugins/dynamix.plugin.manager/scripts/PluginAPI.php",{action:'addRebootNotice',message:message});
}

function removeRebootNotice(message="<?=_('You must reboot for changes to take effect')?>") {
  var bannerIndex = bannerWarnings.indexOf("<i class='fa fa-warning' style='float:initial;'></i> "+message);
  if (bannerIndex < 0) return;
  removeBannerWarning(bannerIndex);
  $.post("/plugins/dynamix.plugin.manager/scripts/PluginAPI.php",{action:'removeRebootNotice',message:message});
}

function showUpgradeChanges() {
  openChanges("showchanges /tmp/plugins/unRAIDServer.txt","<?=_('Release Notes')?>");
}
function showUpgrade(text,noDismiss=false) {
  if ($.cookie('os_upgrade')==null) {
    if (osUpgradeWarning) removeBannerWarning(osUpgradeWarning);
    osUpgradeWarning = addBannerWarning(text.replace(/<a>(.+?)<\/a>/,"<a href='#' onclick='openUpgrade()'>$1</a>").replace(/<b>(.*)<\/b>/,"<a href='#' onclick='document.rebootNow.submit()'>$1</a>"),false,noDismiss);
  }
}
function hideUpgrade(set) {
  removeBannerWarning(osUpgradeWarning);
  if (set)
    $.cookie('os_upgrade','true');
  else
    $.removeCookie('os_upgrade');
}
function confirmUpgrade(confirm) {
  if (confirm) {
    swal({title:"<?=_('Update')?> Unraid OS",text:"<?=_('Do you want to update to the new version')?>?",type:'warning',html:true,showCancelButton:true,closeOnConfirm:false,confirmButtonText:"<?=_('Proceed')?>",cancelButtonText:"<?=_('Cancel')?>"},function(){
      openPlugin("plugin update unRAIDServer.plg","<?=_('Update')?> Unraid OS");
    });
  } else {
    openPlugin("plugin update unRAIDServer.plg","<?=_('Update')?> Unraid OS");
  }
}
function openUpgrade() {
  hideUpgrade();
  $.get('/plugins/dynamix.plugin.manager/include/ShowPlugins.php',{cmd:'alert'},function(data) {
    if (data==0) {
      // no alert message - proceed with upgrade
      confirmUpgrade(true);
    } else {
      // show alert message and ask for confirmation
      openAlert("showchanges <?=$alerts?>","<?=_('Alert Message')?>",'confirmUpgrade');
    }
  });
}
function digits(number) {
  if (number < 10) return 'one';
  if (number < 100) return 'two';
  return 'three';
}
function openNotifier(filter) {
  $.post('/webGui/include/Notify.php',{cmd:'get',csrf_token:csrf_token},function(json) {
    var data = $.parseJSON(json);
    $.each(data, function(i, notify) {
      if (notify.importance == filter) {
        $.jGrowl(notify.subject+'<br>'+notify.description, {
          group: notify.importance,
          header: notify.event+': '+notify.timestamp,
          theme: notify.file,
          sticky: true,
          beforeOpen: function(e,m,o){if ($('div.jGrowl-notification').hasClass(notify.file)) return(false);},
          close: function(e,m,o){$.post('/webGui/include/Notify.php',{cmd:'archive',file:notify.file,csrf_token:csrf_token});}
        });
      }
    });
  });
}
function closeNotifier(filter) {
  $.post('/webGui/include/Notify.php',{cmd:'get',csrf_token:csrf_token},function(json) {
    var data = $.parseJSON(json);
    $.each(data, function(i, notify) {
      if (notify.importance == filter) $.post('/webGui/include/Notify.php',{cmd:'archive',file:notify.file,csrf_token:csrf_token});
    });
    $('div.jGrowl').find('.'+filter).find('div.jGrowl-close').trigger('click');
  });
}
function viewHistory(filter) {
  location.replace('/Tools/NotificationsArchive?filter='+filter);
}
function flashReport() {
  $.post('/webGui/include/Report.php',{cmd:'config'},function(check){
    if (check>0) addBannerWarning("<?=_('Your flash drive is corrupted or offline').'. '._('Post your diagnostics in the forum for help').'.'?> <a target='_blank' href='https://wiki.unraid.net/Manual/Changing_The_Flash_Device'><?=_('See also here')?></a>");
  });
}
$(function() {
  var tab = $.cookie('one')||$.cookie('tab')||'tab1';
  if (tab=='tab0') tab = 'tab'+$('input[name$="tabs"]').length; else if ($('#'+tab).length==0) {initab(); tab = 'tab1';}
  if ($.cookie('help')=='help') {$('.inline_help').show(); $('.nav-item.HelpButton').addClass('active');}
  $('#'+tab).attr('checked', true);
  updateTime();
  $.jGrowl.defaults.closeTemplate = '<i class="fa fa-close"></i>';
  $.jGrowl.defaults.closerTemplate = '<?=$notify['position'][0]=='b' ? '<div class="bottom">':'<div class="top">'?>[ <?=_("close all notifications")?> ]</div>';
  $.jGrowl.defaults.check = 100;
  $.jGrowl.defaults.position = '<?=$notify['position']?>';
  $.jGrowl.defaults.themeState = '';
  Shadowbox.setup('a.sb-enable', {modal:true});
// add any pre-existing reboot notices
  $.post('/webGui/include/Report.php',{cmd:'notice'},function(notices){
    notices = notices.split('\n');
    for (var i=0,notice; notice=notices[i]; i++) addBannerWarning("<i class='fa fa-warning' style='float:initial;'></i> "+notice,false,true);
  });
// check for flash offline / corrupted (delayed).
  timers.flashReport = setTimeout(flashReport,6000);
});

var mobiles=['ipad','iphone','ipod','android'];
var device=navigator.platform.toLowerCase();
for (var i=0,mobile; mobile=mobiles[i]; i++) {
  if (device.indexOf(mobile)>=0) {$('#footer').css('position','static'); break;}
}
$.ajaxPrefilter(function(s, orig, xhr){
  if (s.type.toLowerCase() == "post" && !s.crossDomain) {
    s.data = s.data || "";
    s.data += s.data?"&":"";
    s.data += "csrf_token="+csrf_token;
  }
});
</script>
<?include "$docroot/plugins/dynamix.my.servers/include/myservers1.php"?>
</head>
<body>
 <div id="template">
  <div class="upgrade_notice" style="display:none"></div>
  <div id="header" class="<?=$display['banner']?>">
   <div class="logo">
   <a href="https://unraid.net" target="_blank"><?readfile("$docroot/webGui/images/UN-logotype-gradient.svg")?></a>
   <?=_('Version')?>: <?=$var['version']?><?=$notes?>
   </div>
   <?include "$docroot/plugins/dynamix.my.servers/include/myservers2.php"?>
  </div>
  <a href="#" class="back_to_top" title="<?=_('Back To Top')?>"><i class="fa fa-arrow-circle-up"></i></a>
<?
// Build page menus
echo "<div id='menu'>";
if ($themes2) echo "<div id='nav-block'>";
echo "<div class='nav-tile'>";
foreach ($tasks as $button) {
  $page = $button['name'];
  $play = $task==$page ? " active" : "";
  echo "<div class='nav-item{$play}'>";
  echo "<a href=\"/$page\" onclick=\"initab('/$page')\">"._($button['Name'] ?? $page)."</a></div>";
  // create list of nchan scripts to be started
  if (isset($button['Nchan'])) nchan_merge($button['root'], $button['Nchan']);
}
unset($tasks);
echo "</div>";
echo "<div class='nav-tile right'>";
if (in_array($task,['Dashboard','Docker','VMs'])) {
  $title = $themes2 ?  "" : _('Unlock sortable items');
  echo "<div class='nav-item LockButton util'><a 'href='#' class='hand' onclick='LockButton();return false;' title=\"$title\"><b class='icon-u-lock system red-text'></b><span>"._('Unlock sortable items')."</span></a></div>";
}
if ($display['usage']) my_usage();
foreach ($buttons as $button) {
  if (empty($button['Link'])) {
    $icon = $button['Icon'];
    if (substr($icon,-4)=='.png') {
      $icon = "<img src='/{$button['root']}/icons/$icon' class='system'>";
    } elseif (substr($icon,0,5)=='icon-') {
      $icon = "<b class='$icon system'></b>";
    } else {
      if (substr($icon,0,3)!='fa-') $icon = "fa-$icon";
      $icon = "<b class='fa $icon system'></b>";
    }
    $title = $themes2 ? "" : " title=\""._($button['Title'])."\"";
    echo "<div class='nav-item {$button['name']} util'><a href='".($button['Href'] ?? '#')."' onclick='{$button['name']}();return false;'{$title}>$icon<span>"._($button['Title'])."</span></a></div>";
  } else {
    echo "<div class='{$button['Link']}'></div>";
  }
  // create list of nchan scripts to be started
  if (isset($button['Nchan'])) nchan_merge($button['root'], $button['Nchan']);
}

echo "<div id='nav-tub1' class='nav-user'><b id='box-tub1' class='system graybar'>0</b></div>";
echo "<div id='nav-tub2' class='nav-user'><b id='box-tub2' class='system graybar'>0</b></div>";
echo "<div id='nav-tub3' class='nav-user'><b id='box-tub3' class='system graybar'>0</b></div>";

if ($themes2) echo "</div>";
echo "</div></div>";
foreach ($buttons as $button) {
  annotate($button['file']);
  eval('?>'.parse_text($button['text']));
}
unset($buttons,$button);

// Build page content
// Reload page every X minutes during extended viewing?
if (isset($myPage['Load']) && $myPage['Load']>0) echo "\n<script>timers.reload = setTimeout(function(){location.reload();},".($myPage['Load']*60000).");</script>\n";
echo "<div class='tabs'>";
$tab = 1;
$pages = [];
if (!empty($myPage['text'])) $pages[$myPage['name']] = $myPage;
if (($myPage['Type'] ?? '')=='xmenu') $pages = array_merge($pages, find_pages($myPage['name']));
if (isset($myPage['Tabs'])) $display['tabs'] = strtolower($myPage['Tabs'])=='true' ? 0 : 1;
$tabbed = $display['tabs']==0 && count($pages)>1;

foreach ($pages as $page) {
  $close = false;
  if (isset($page['Title'])) {
    eval("\$title=\"".htmlspecialchars($page['Title'])."\";");
    if ($tabbed) {
      echo "<div class='tab'><input type='radio' id='tab{$tab}' name='tabs' onclick='settab(this.id)'><label for='tab{$tab}'>";
      echo tab_title($title,$page['root'],$page['Tag']??false);
      echo "</label><div class='content'>";
      $close = true;
    } else {
      if ($tab==1) echo "<div class='tab'><input type='radio' id='tab{$tab}' name='tabs'><div class='content shift'>";
      echo "<div class='title'><span class='left'>";
      echo tab_title($title,$page['root'],$page['Tag']??false);
      echo "</span></div>";
    }
    $tab++;
  }
  if (isset($page['Type']) && $page['Type']=='menu') {
    $pgs = find_pages($page['name']);
    foreach ($pgs as $pg) {
      @eval("\$title=\"".htmlspecialchars($pg['Title'])."\";");
      $icon = $pg['Icon'] ?? "<i class='icon-app PanelIcon'></i>";
      if (substr($icon,-4)=='.png') {
        $root = $pg['root'];
        if (file_exists("$docroot/$root/images/$icon")) {
          $icon = "<img src='/$root/images/$icon' class='PanelImg'>";
        } elseif (file_exists("$docroot/$root/$icon")) {
          $icon = "<img src='/$root/$icon' class='PanelImg'>";
        } else {
          $icon = "<i class='icon-app PanelIcon'></i>";
        }
      } elseif (substr($icon,0,5)=='icon-') {
        $icon = "<i class='$icon PanelIcon'></i>";
      } elseif ($icon[0]!='<') {
        if (substr($icon,0,3)!='fa-') $icon = "fa-$icon";
        $icon = "<i class='fa $icon PanelIcon'></i>";
      }
      echo "<div class=\"Panel\"><a href=\"/$path/{$pg['name']}\" onclick=\"$.cookie('one','tab1')\"><span>$icon</span><div class=\"PanelText\">"._($title)."</div></a></div>";
    }
  }
  // create list of nchan scripts to be started
  if (isset($page['Nchan'])) nchan_merge($page['root'], $page['Nchan']);
  annotate($page['file']);
  empty($page['Markdown']) || $page['Markdown']=='true' ? eval('?>'.Markdown(parse_text($page['text']))) : eval('?>'.parse_text($page['text']));
  if ($close) echo "</div></div>";
}
if (count($pages)) {
  $running = file_exists($nchan_pid) ? file($nchan_pid,FILE_IGNORE_NEW_LINES) : [];
  $start   = array_diff($nchan, $running);  // returns any new scripts to be started
  $stop    = array_diff($running, $nchan);  // returns any old scripts to be stopped
  $running = array_merge($start, $running); // update list of current running nchan scripts
  // start nchan scripts which are new
  foreach ($start as $row) {
    $script = explode(':',$row)[0];
    exec("$docroot/$script &>/dev/null &");
  }
  // stop nchan scripts with the :stop option
  foreach ($stop as $row) {
    [$script,$opt] = my_explode(':',$row);
    if ($opt == 'stop') {
      exec("pkill -f $docroot/$script >/dev/null &");
      array_splice($running,array_search($row,$running),1);
    }
  }
  if (count($running)) file_put_contents($nchan_pid,implode("\n",$running)."\n"); else @unlink($nchan_pid);
}
unset($pages,$page,$pgs,$pg,$icon,$nchan,$running,$start,$stop,$row,$script,$opt,$nchan_run);
?>
</div></div>
<div class="spinner fixed"></div>
<form name="rebootNow" method="POST" action="/webGui/include/Boot.php"><input type="hidden" name="cmd" value="reboot"></form>
<iframe id="progressFrame" name="progressFrame" frameborder="0"></iframe>
<?
// Build footer
annotate('Footer');
echo '<div id="footer"><span id="statusraid"><span id="statusbar">';
$progress = ($var['fsProgress']!='')? "&bullet;<span class='blue strong'>{$var['fsProgress']}</span>" : '';
switch ($var['fsState']) {
case 'Stopped':
  echo "<span class='red strong'><i class='fa fa-stop-circle'></i> "._('Array Stopped')."</span>$progress"; break;
case 'Starting':
  echo "<span class='orange strong'><i class='fa fa-pause-circle'></i> "._('Array Starting')."</span>$progress"; break;
case 'Stopping':
  echo "<span class='orange strong'><i class='fa fa-pause-circle'></i> "._('Array Stopping')."</span>$progress"; break;
default:
  echo "<span class='green strong'><i class='fa fa-play-circle'></i> "._('Array Started')."</span>$progress"; break;
}
echo "</span></span><span id='countdown'></span><span id='user-notice' class='red-text'></span>";
echo "<span id='copyright'>Unraid&reg; webGui &copy;2023, Lime Technology, Inc.";
echo " <a href='https://wiki.unraid.net/Manual' target='_blank' title=\""._('Online manual')."\"><i class='fa fa-book'></i> "._('manual')."</a>";
echo "</span></div>";
?>
<script>
// Firefox specific workaround, not needed anymore in firefox version 100 and higher
//if (typeof InstallTrigger!=='undefined') $('#nav-block').addClass('mozilla');

function parseINI(data){
  var regex = {
    section: /^\s*\[\s*\"*([^\]]*)\s*\"*\]\s*$/,
    param: /^\s*([^=]+?)\s*=\s*\"*(.*?)\s*\"*$/,
    comment: /^\s*;.*$/
  };
  var value = {};
  var lines = data.split(/[\r\n]+/);
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
// unraid animated logo
var unraid_logo = '<?readfile("$docroot/webGui/images/animated-logo.svg")?>';

var defaultPage = new NchanSubscriber('/sub/session,var<?=$entity?",notify":""?>',{subscriber:'websocket'});
defaultPage.on('message', function(msg,meta) {
  switch (meta.id.channel()) {
  case 0:
    // stale session, force login
    if (csrf_token != msg) location.replace('/');
    break;
  case 1:
    // message field in footer
    var ini = parseINI(msg);
    switch (ini['fsState']) {
      case 'Stopped'   : var status = "<span class='red strong'><i class='fa fa-stop-circle'></i> <?=_('Array Stopped')?></span>"; break;
      case 'Started'   : var status = "<span class='green strong'><i class='fa fa-play-circle'></i> <?=_('Array Started')?></span>"; break;
      case 'Formatting': var status = "<span class='green strong'><i class='fa fa-play-circle'></i> <?=_('Array Started')?></span>&bullet;<span class='orange strong'><?=_('Formatting device(s)')?></span>"; break;
      default          : var status = "<span class='orange strong'><i class='fa fa-pause-circle'></i> "+_('Array '+ini['fsState'])+"</span>";
    }
    if (ini['mdResyncPos'] > 0) {
      var resync = ini['mdResyncAction'].split(/\s+/);
      switch (resync[0]) {
        case 'recon': var action = resync[1]=='P' ? "<?=_('Parity-Sync')?>" : "<?=_('Data-Rebuild')?>"; break;
        case 'check': var action = resync.length>1 ? "<?=_('Parity-Check')?>" : "<?=_('Read-Check')?>"; break;
        case 'clear': var action = "<?=_('Disk-Clear')?>"; break;
        default     : var action = '';
      }
      action += " "+(ini['mdResyncPos']/(ini['mdResyncSize']/100+1)).toFixed(1)+" %";
      status += "&bullet;<span class='orange strong'>"+action.replace('.','<?=$display['number'][0]?>');
      if (ini['mdResyncDt']==0) status += " &bullet; <?=_('Paused')?>";
      status += "</span>";
    }
    if (ini['fsProgress']) status += "&bullet;<span class='blue strong'>"+_(ini['fsProgress'])+"</span>";
    $('#statusbar').html(status);
    break;
  case 2:
    // notifications
    var tub1 = 0, tub2 = 0, tub3 = 0;
    var data = $.parseJSON(msg);
    $.each(data, function(i, notify) {
      switch (notify.importance) {
        case 'alert'  : tub1++; break;
        case 'warning': tub2++; break;
        case 'normal' : tub3++; break;
      }
      if (notify.show) {
        $.jGrowl(notify.subject+'<br>'+notify.description, {
          group: notify.importance,
          header: notify.event+': '+notify.timestamp,
          theme: notify.file,
          click: function(e,m,o) {if (notify.link) location.replace(notify.link);},
          beforeOpen: function(e,m,o){if ($('div.jGrowl-notification').hasClass(notify.file)) return(false);},
          afterOpen: function(e,m,o){if (notify.link) $(e).css("cursor","pointer");},
          close: function(e,m,o){$.post('/webGui/include/Notify.php',{cmd:'hide',file:"<?=$notify['path'].'/unread/'?>"+notify.file,csrf_token:csrf_token});}
        });
      }
    });
    $('#box-tub1').text(tub1);
    $('#box-tub2').text(tub2);
    $('#box-tub3').text(tub3);
    if (tub1) $('#box-tub1').removeClass('graybar').addClass('redbar'); else $('#box-tub1').removeClass('redbar').addClass('graybar');
    if (tub2) $('#box-tub2').removeClass('graybar').addClass('orangebar'); else $('#box-tub2').removeClass('orangebar').addClass('graybar');
    if (tub3) $('#box-tub3').removeClass('graybar').addClass('greenbar'); else $('#box-tub3').removeClass('greenbar').addClass('graybar');
    break;
  }
});

var nchan_plugins = new NchanSubscriber('/sub/plugins',{subscriber:'websocket'});
nchan_plugins.on('message', function(data) {
  if (!data || openDone(data)) return;
  var box = $('pre#swaltext');
  const text = box.html().split('<br>');
  if (data.slice(-1) == '\r') {
    text[text.length-1] = data.slice(0,-1);
  } else {
    text.push(data.slice(0,-1));
  }
  box.html(text.join('<br>')).scrollTop(box[0].scrollHeight);
});

var nchan_docker = new NchanSubscriber('/sub/docker',{subscriber:'websocket'});
nchan_docker.on('message', function(data) {
  if (!data || openDone(data)) return;
  var box = $('pre#swaltext');
  data = data.split('\0');
  switch (data[0]) {
  case 'addLog':
    var rows = document.getElementsByClassName('logLine');
    if (rows.length) {
      var row = rows[rows.length-1];
      row.innerHTML += data[1]+'<br>';
    }
    break;
  case 'progress':
    var rows = document.getElementsByClassName('progress-'+data[1]);
    if (rows.length) {
      rows[rows.length-1].textContent = data[2];
    }
    break;
  case 'addToID':
    var rows = document.getElementById(data[1]);
    if (rows === null) {
      rows = document.getElementsByClassName('logLine');
      if (rows.length) {
        var row = rows[rows.length-1];
        row.innerHTML += '<span id="'+data[1]+'">IMAGE ID ['+data[1]+']: <span class="content">'+data[2]+'</span><span class="progress-'+data[1]+'"></span>.</span><br>';
      }
    } else {
      var rows_content = rows.getElementsByClassName('content');
      if (!rows_content.length || rows_content[rows_content.length-1].textContent != data[2]) {
        rows.innerHTML += '<span class="content">'+data[2]+'</span><span class="progress-'+data[1]+'"></span>.';
      }
    }
    break;
  case 'show_Wait':
    progress_span[data[1]] = document.getElementById('wait-'+data[1]);
    progress_dots[data[1]] = setInterval(function(){if (((progress_span[data[1]].innerHTML += '.').match(/\./g)||[]).length > 9) progress_span[data[1]].innerHTML = progress_span[data[1]].innerHTML.replace(/\.+$/,'');},500);
    break;
  case 'stop_Wait':
    clearInterval(progress_dots[data[1]]);
    progress_span[data[1]].innerHTML = '';
    break;
  default:
    box.html(box.html()+data[0]);
    break;
  }
  box.scrollTop(box[0].scrollHeight);
});

var backtotopoffset = 250;
var backtotopduration = 500;
$(window).scroll(function() {
  if ($(this).scrollTop() > backtotopoffset) {
    $('.back_to_top').fadeIn(backtotopduration);
  } else {
    $('.back_to_top').fadeOut(backtotopduration);
  }
<?if ($themes1):?>
  var top = $('div#header').height()-1; // header height has 1 extra pixel to cover overlap
  $('div#menu').css($(this).scrollTop() > top ? {position:'fixed',top:'0'} : {position:'absolute',top:top+'px'});
  // banner
  $('div.upgrade_notice').css($(this).scrollTop() > 24 ? {position:'fixed',top:'0'} : {position:'absolute',top:'24px'});
<?endif;?>
});
$('.back_to_top').click(function(event) {
  event.preventDefault();
  $('html,body').animate({scrollTop:0},backtotopduration);
  return false;
});

<?if ($entity):?>
$.post('/webGui/include/Notify.php',{cmd:'init',csrf_token:csrf_token});
<?endif;?>
$(function() {
  defaultPage.start();
  $('div.spinner.fixed').html(unraid_logo);
  setTimeout(function(){$('div.spinner').not('.fixed').each(function(){$(this).html(unraid_logo);});},500); // display animation if page loading takes longer than 0.5s
  shortcut.add('F1',function(){HelpButton();});
<?if ($var['regTy']=='unregistered'):?>
  $('#licensetype').addClass('orange-text');
<?elseif (!in_array($var['regTy'],['Trial','Basic','Plus','Pro'])):?>
  $('#licensetype').addClass('red-text');
<?endif;?>
  $('input[value="<?=_("Apply")?>"],input[value="Apply"],input[name="cmdEditShare"],input[name="cmdUserEdit"]').prop('disabled',true);
  $('form').find('select,input[type=text],input[type=number],input[type=password],input[type=checkbox],input[type=radio],input[type=file],textarea').not('.lock').each(function(){$(this).on('input change',function() {
    var form = $(this).parentsUntil('form').parent();
    form.find('input[value="<?=_("Apply")?>"],input[value="Apply"],input[name="cmdEditShare"],input[name="cmdUserEdit"]').not('input.lock').prop('disabled',false);
    form.find('input[value="<?=_("Done")?>"],input[value="Done"]').not('input.lock').val("<?=_('Reset')?>").prop('onclick',null).off('click').click(function(){formHasUnsavedChanges=false;refresh(form.offset().top);});
  });});
  // add leave confirmation when form has changed without applying (opt-in function)
  if ($('form.js-confirm-leave').length>0) {
    $('form.js-confirm-leave').on('change',function(e){formHasUnsavedChanges=true;}).on('submit',function(e){formHasUnsavedChanges=false;});
    $(window).on('beforeunload',function(e){if (formHasUnsavedChanges) return '';}); // note: the browser creates its own popup window and warning message
  }
  // form parser: add escapeQuotes protection
  $('form').each(function(){
    var action = $(this).prop('action').actionName();
    if (action=='update.htm' || action=='update.php') {
      var onsubmit = $(this).attr('onsubmit')||'';
      $(this).attr('onsubmit','clearTimeout(timers.flashReport);escapeQuotes(this);'+onsubmit);
    }
  });
  var top = ($.cookie('top')||0) - $('.tabs').offset().top - 75;
  if (top>0) {$('html,body').scrollTop(top);}
  $.removeCookie('top');
  if ($.cookie('addAlert') != null) bannerAlert(addAlert.text,addAlert.cmd,addAlert.plg,addAlert.func);
<?if ($safemode):?>
  showNotice("<?=_('System running in')?> <b><?=('safe mode')?></b>");
<?else:?>
<?$readme = @file_get_contents("$docroot/plugins/unRAIDServer/README.md",false,null,0,20)??'';?>
<?if (strpos($readme,'REBOOT REQUIRED')!==false):?>
  showUpgrade("<b><?=_('Reboot Now')?></b> <?=_('to upgrade Unraid OS')?>",true);
<?elseif (strpos($readme,'DOWNGRADE')!==false):?>
  showUpgrade("<b><?=_('Reboot Now')?></b> <?=_('to downgrade Unraid OS')?>",true);
<?elseif ($version = plugin_update_available('unRAIDServer',true)):?>
  showUpgrade("Unraid OS v<?=$version?> <?=_('is available')?>. <?if (is_file('/tmp/plugins/unRAIDServer.txt')):?><span class='fa fa-info-circle fa-fw big blue-text' onclick='showUpgradeChanges()' title=\"<?=_('Release Notes')?>\"></span> <?endif;?><a><?=_('Update Now')?></a>");
<?endif;?>
<?if (!$notify['system']):?>
  addBannerWarning("<?=_('System notifications are')?> <b><?=_('disabled')?></b>. <?=_('Click')?> <a href='/Settings/Notifications'><?=_('here')?></a> <?=_('to change notification settings')?>.",true,true);
<?endif;?>
<?endif;?>
  var opts = [];
  context.init({preventDoubleContext:false,left:true,above:false});
  opts.push({text:"<?=_('View')?>",icon:'fa-folder-open-o',action:function(e){e.preventDefault();openNotifier('alert');}});
  opts.push({divider:true});
  opts.push({text:"<?=_('History')?>",icon:'fa-file-text-o',action:function(e){e.preventDefault();viewHistory('alert');}});
  opts.push({divider:true});
  opts.push({text:"<?=_('Acknowledge')?>",icon:'fa-check-square-o',action:function(e){e.preventDefault();closeNotifier('alert');}});
  context.attach('#nav-tub1',opts);

  var opts = [];
  context.init({preventDoubleContext:false,left:true,above:false});
  opts.push({text:"<?=_('View')?>",icon:'fa-folder-open-o',action:function(e){e.preventDefault();openNotifier('warning');}});
  opts.push({divider:true});
  opts.push({text:"<?=_('History')?>",icon:'fa-file-text-o',action:function(e){e.preventDefault();viewHistory('warning');}});
  opts.push({divider:true});
  opts.push({text:"<?=_('Acknowledge')?>",icon:'fa-check-square-o',action:function(e){e.preventDefault();closeNotifier('warning');}});
  context.attach('#nav-tub2',opts);

  var opts = [];
  context.init({preventDoubleContext:false,left:true,above:false});
  opts.push({text:"<?=_('View')?>",icon:'fa-folder-open-o',action:function(e){e.preventDefault();openNotifier('normal');}});
  opts.push({divider:true});
  opts.push({text:"<?=_('History')?>",icon:'fa-file-text-o',action:function(e){e.preventDefault();viewHistory('normal');}});
  opts.push({divider:true});
  opts.push({text:"<?=_('Acknowledge')?>",icon:'fa-check-square-o',action:function(e){e.preventDefault();closeNotifier('normal');}});
  context.attach('#nav-tub3',opts);

  if (location.pathname.search(/\/(AddVM|UpdateVM|AddContainer|UpdateContainer)/)==-1) {
    $('blockquote.inline_help').each(function(i) {
      $(this).attr('id','helpinfo'+i);
      var pin = $(this).prev();
      if (!pin.prop('nodeName')) pin = $(this).parent().prev();
      while (pin.prop('nodeName') && pin.prop('nodeName').search(/(table|dl)/i)==-1) pin = pin.prev();
      pin.find('tr:first,dt:last').each(function() {
        var node = $(this);
        var name = node.prop('nodeName').toLowerCase();
        if (name=='dt') {
          while (!node.html() || node.html().search(/(<input|<select|nbsp;)/i)>=0 || name!='dt') {
            if (name=='dt' && node.is(':first-of-type')) break;
            node = node.prev();
            name = node.prop('nodeName').toLowerCase();
          }
          node.css('cursor','help').click(function(){$('#helpinfo'+i).toggle('slow');});
        } else {
          if (node.html() && (name!='tr' || node.children('td:first').html())) node.css('cursor','help').click(function(){$('#helpinfo'+i).toggle('slow');});
        }
      });
    });
  }
  $('form').append($('<input>').attr({type:'hidden', name:'csrf_token', value:csrf_token}));
});
</script>
</body>
</html>
