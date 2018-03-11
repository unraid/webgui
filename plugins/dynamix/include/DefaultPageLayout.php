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
<!DOCTYPE html>
<html lang="en">
<head>
<title><?=$var['NAME']?>/<?=$myPage['name']?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex">
<link type="image/png" rel="shortcut icon" href="/webGui/images/<?=$var['mdColor']?>.png">
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/font-awesome.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/context.standalone.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.sweetalert.css">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-{$display['theme']}.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/dynamix-{$display['theme']}.css")?>">

<style>
.inline_help{display:none}
.upgrade_notice{position:fixed;top:1px;left:0;width:100%;height:40px;line-height:40px;color:#E68A00;background:#FEEFB3;border-bottom:#E68A00 1px solid;text-align:center;font-size:15px;z-index:999}
.upgrade_notice i{margin:14px;float:right;cursor:pointer}
.back_to_top{display:none;position:fixed;bottom:30px;right:12px;color:green;font-size:25px}
<?
$tasks = find_pages('Tasks');
$buttons = find_pages('Buttons');
$banner = '/boot/config/plugins/dynamix/banner.png';
echo "#header.image{background-image:url(";
echo file_exists($banner) ? autov($banner) : '/webGui/images/banner.png';
echo ")}\n";
if (strstr('gray,azure',$display['theme'])) {
  foreach ($tasks as $page) if ($page['Code']) echo "#nav-item a[href='/{$page['name']}']:before{content:'\\{$page['Code']}'}\n";
  foreach ($buttons as $page) if ($page['Code']) echo "#nav-item.{$page['name']} a:before{content:'\\{$page['Code']}'}\n";
}
?>
</style>

<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script src="<?autov('/webGui/javascript/NchanSubscriber.js')?>"></script>
<script>
Shadowbox.init({skipSetup:true});

// server uptime
var uptime = <?=strtok(exec("cat /proc/uptime"),' ')?>;
var expiretime = <?=$var['regTy']=='Trial'||strstr($var['regTy'],'expired')?$var['regTm2']:0?>;
var before = new Date();

// page timer events
var timers = {};

function pauseEvents(){
  $.each(timers, function(i, timer) {
    clearTimeout(timer);
  });
}
function resumeEvents(){
  var startDelay = 50;
  $.each(timers, function(i, timer) {
    timers[i] = setTimeout(i + '()', startDelay);
    startDelay += 50;
  });
}
function plus(value, label, last) {
  return value>0 ? (value+' '+label+(value!=1?'s':'')+(last?'':', ')) : '';
}
function updateTime() {
  var now = new Date();
  var days = parseInt(uptime/86400);
  var hour = parseInt(uptime/3600%24);
  var mins = parseInt(uptime/60%60);
  $('#uptime').html(((days|hour|mins)?plus(days,'day',(hour|mins)==0)+plus(hour,'hour',mins==0)+plus(mins,'minute',true):'less than a minute'));
  uptime += Math.round((now.getTime() - before.getTime())/1000);
  before = now;
  if (expiretime > 0) {
    var remainingtime = expiretime - now.getTime()/1000;
    if (remainingtime <= 0) {
      $('#licenseexpire').html('<em> (click here for details)</em>');
      $('#licensetype').addClass('warning')
    } else {
      days = parseInt(remainingtime/86400);
      hour = parseInt(remainingtime/3600%24);
      mins = parseInt(remainingtime/60%60);
      $('#licenseexpire').html(' - '+((days|hour|mins)?(days?plus(days,'day',true):(hour?plus(hour,'hour',true):plus(mins,'minute',true))):'less than a minute')+' remaining (click here for details)');
    }
  }
  setTimeout(updateTime,1000);
}
function refresh(top) {
  if (typeof top === 'undefined') {
    for (var i=0,element; element=document.querySelectorAll('input,button,select')[i]; i++) { element.disabled = true; }
    for (var i=0,link; link=document.getElementsByTagName('a')[i]; i++) { link.style.color = "gray"; } //fake disable
    location = location;
  } else {
    $.cookie('top',top,{path:'/'});
    location = location;
  }
}
function initab() {
  $.removeCookie('one',{path:'/'});
  $.removeCookie('tab',{path:'/'});
}
function settab(tab) {
<?switch ($myPage['name']):?>
<?case'Main':?>
  $.cookie('tab',tab,{path:'/'});
<?if ($var['fsState']=='Started'):?>
  $.cookie('one','tab1',{path:'/'});
<?endif;?>
<?break;?>
<?case'Cache':case'Data':case'Flash':case'Parity':?>
  $.cookie('one',tab,{path:'/'});
<?break;?>
<?default:?>
  $.cookie(($.cookie('one')==null?'tab':'one'),tab,{path:'/'});
<?endswitch;?>
}
function done(key) {
  var url = location.pathname.split('/');
  var path = '/'+url[1];
  if (key) for (var i=2; i<url.length; i++) if (url[i]==key) break; else path += '/'+url[i];
  $.removeCookie('one',{path:'/'});
  location.replace(path);
}
function chkDelete(form, button) {
  button.value = form.confirmDelete.checked ? 'Delete' : 'Apply';
  button.disabled = false;
}
function openBox(cmd,title,height,width,load,func,id) {
  // open shadowbox window (run in foreground)
  if (id === undefined) id = '';
  var run = cmd.split('?')[0].substr(-4)=='.php' ? cmd : '/logging.htm?cmd='+cmd+'&csrf_token=<?=$var['csrf_token']?>';
  var options = load ? (func ? {modal:true,onClose:function(){setTimeout(func+'('+'"'+id+'")',0);}} : {modal:true,onClose:function(){location=location;}}) : {modal:true};
  Shadowbox.open({content:run, player:'iframe', title:title, height:height, width:width, options:options});
}
function openWindow(cmd,title,height,width) {
  // open regular window (run in background)
  var window_name = title.replace(/ /g,"_");
  var form_html =
  '<form action="/logging.htm" method="post" target="' + window_name + '">' +
  '<input type="hidden" name="csrf_token" value="<?=$var['csrf_token']?>" />' +
  '<input type="hidden" name="title" value="' + title + '" />';
  var vars = cmd.split('&');
  form_html += '<input type="hidden" name="cmd" value="' + vars[0] + '" />';
  for (var i = 1; i < vars.length; i++) {
    var pair = vars[i].split('=');
    form_html += '<input type="hidden" name="' + pair[0] + '" value="' + pair[1] + '" />';
  }
  form_html += '</form>';
  var form = $(form_html);
  $('body').append(form);
  var top = (screen.height-height)/2;
  var left = (screen.width-width)/2;
  var options = 'resizeable=yes,scrollbars=yes,height='+height+',width='+width+',top='+top+',left='+left;
  window.open('', window_name, options);
  form.submit();
}
function showStatus(name,plugin,job) {
  $.post('/webGui/include/ProcessStatus.php',{name:name,plugin:plugin,job:job},function(status){$(".tabs").append(status);});
}
function showFooter(data, id) {
  if (id !== undefined) $('#'+id).remove();
  $('#copyright').prepend(data);
}
function showNotice(data,plugin) {
  if (plugin)
    var href = "href=\"#\" onclick=\"openBox('/plugins/dynamix.plugin.manager/scripts/plugin&arg1=update&arg2="+plugin+".plg','Update Plugin',600,900,true)\"";
  else
    var href = "href=\"/Plugins\"";
  $('#user-notice').html(data.replace(/<a>(.*)<\/a>/,"<a "+href+">$1</a>"));
}
function showUpgrade(data,plugin) {
  var href = "href=\"#\" onclick=\"hideUpgrade();openBox('/plugins/dynamix.plugin.manager/scripts/plugin&arg1=update&arg2="+plugin+".plg','Update Plugin',600,900,true)\"";
  if ($.cookie('os_upgrade')==null)
    $('.upgrade_notice').html(data.replace(/<a(.*)>(.*)<\/a>/,"<a "+href+"$1>$2</a>")+"<i class='fa fa-close' title='Close' onclick='hideUpgrade(true)'></i>").show();
}
function hideUpgrade(set) {
  $('.upgrade_notice').hide();
  if (set)
    $.cookie('os_upgrade','true',{path:'/'});
  else
    $.removeCookie('os_upgrade',{path:'/'});
}
function notifier() {
  var tub1 = 0, tub2 = 0, tub3 = 0;
  $.post('/webGui/include/Notify.php',{cmd:'get'},function(json) {
    var data = $.parseJSON(json);
    $.each(data, function(i, object) {
      var notify = $.parseJSON(object);
<?if ($notify['display']):?>
      switch (notify.importance) {
        case 'alert'  : tub1++; break;
        case 'warning': tub2++; break;
        case 'normal' : tub3++; break;
      }
<?else:?>
      $.jGrowl(notify.subject+'<br>'+notify.description, {
        group: notify.importance,
        header: notify.event+': '+notify.timestamp,
        theme: notify.file,
        beforeOpen: function(e,m,o){if ($('div.jGrowl-notify').hasClass(notify.file)) return(false);},
        beforeClose: function(e,m,o){$.post('/webGui/include/Notify.php',{cmd:'archive',file:notify.file});}
      });
<?endif;?>
    });
<?if ($notify['display']):?>
    $('#txt-tub1').removeClass('one two three').addClass(digits(tub1)).text(tub1);
    $('#txt-tub2').removeClass('one two three').addClass(digits(tub2)).text(tub2);
    $('#txt-tub3').removeClass('one two three').addClass(digits(tub3)).text(tub3);
    if (tub1) $('#box-tub1').removeClass('grey-text').addClass('red-text'); else $('#box-tub1').removeClass('red-text').addClass('grey-text');
    if (tub2) $('#box-tub2').removeClass('grey-text').addClass('orange-text'); else $('#box-tub2').removeClass('orange-text').addClass('grey-text');
    if (tub3) $('#box-tub3').removeClass('grey-text').addClass('green-text'); else $('#box-tub3').removeClass('green-text').addClass('grey-text');
<?endif;?>
    timers.notifier = setTimeout(notifier,5000);
  });
}
function digits(number) {
  if (number < 10) return 'one';
  if (number < 100) return 'two';
  return 'three';
}
function openNotifier(filter) {
  $.post('/webGui/include/Notify.php',{cmd:'get'},function(json) {
    var data = $.parseJSON(json);
    $.each(data, function(i, object) {
      var notify = $.parseJSON(object);
      if (notify.importance == filter) {
        $.jGrowl(notify.subject+'<br>'+notify.description, {
          group: notify.importance,
          header: notify.event+': '+notify.timestamp,
          theme: notify.file,
          beforeOpen: function(e,m,o){if ($('div.jGrowl-notify').hasClass(notify.file)) return(false);},
          beforeClose: function(e,m,o){$.post('/webGui/include/Notify.php',{cmd:'archive',file:notify.file});}
        });
      }
    });
  });
}
function closeNotifier(filter) {
  clearTimeout(timers.notifier);
  $.post('/webGui/include/Notify.php',{cmd:'get'},function(json) {
    var data = $.parseJSON(json);
    $.each(data, function(i, object) {
      var notify = $.parseJSON(object);
      if (notify.importance == filter) $.post('/webGui/include/Notify.php',{cmd:'archive',file:notify.file});
    });
    $('div.jGrowl').find('.'+filter).find('div.jGrowl-close').trigger('click');
    setTimeout(notifier,100);
  });
}
function viewHistory(filter) {
  location.replace('/Tools/NotificationsArchive?filter='+filter);
}
$(function() {
  var tab = $.cookie('one')||$.cookie('tab')||'tab1';
  if (tab=='tab0') tab = 'tab'+$('input[name$="tabs"]').length; else if ($('#'+tab).length==0) {initab(); tab = 'tab1';}
  if ($.cookie('help')=='help') {$('.inline_help').show(); $('#nav-item.HelpButton').addClass('active');}
  $('#'+tab).attr('checked', true);
  updateTime();
  $.jGrowl.defaults.closeTemplate = '<i class="fa fa-close"></i>';
  $.jGrowl.defaults.closerTemplate = '<?=$notify['position'][0]=='b' ? '<div>':'<div class="top">'?>[ close all notifications ]</div>';
  $.jGrowl.defaults.sticky = true;
  $.jGrowl.defaults.check = 100;
  $.jGrowl.defaults.position = '<?=$notify['position']?>';
  $.jGrowl.defaults.themeState = '';
  Shadowbox.setup('a.sb-enable', {modal:true});
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
    s.data += "csrf_token=<?=$var['csrf_token']?>";
  }
});
</script>
</head>
<body class="<?='page_'.strtolower($myPage['name'])?>">
 <div id="template">
  <div class="upgrade_notice" style="display:none"></div>
  <div id="header" class="<?=$display['banner']?>">
   <div class="logo">
   <a href="#" onclick="openBox('/webGui/include/Feedback.php','Feedback',450,450,false);return false;"><img src="/webGui/images/limetech-logo-<?=$display['theme']?>.png" title="Feedback" border="0"/></a><br/>
   <a href="/Tools/Registration"><span id="licensetype"><strong>unRAID Server <em><?=$var['regTy']?></em><span id="licenseexpire"></span></strong></span></a>
   </div>
   <div class="block">
    <span class="text-left">Server<br/>Description<br/>Version<br/>Uptime</span>
    <span class="text-right"><?=$var['NAME']." &bullet; ".$eth0['IPADDR:0']?><br/><?=$var['COMMENT']?><br/><?=$var['version']?><br/><span id="uptime"></span></span>
   </div>
  </div>
  <a href="#" class="back_to_top" title="Back To Top"><i class="fa fa-arrow-circle-up"></i></a>
<?
// Build page menus
echo "<div id='menu'><div id='nav-block'><div id='nav-left'>";
foreach ($tasks as $page) {
  $pagename = $page['name'];
  echo "<div id='nav-item'";
  echo $pagename==$task ? " class='active'>" : ">";
  echo "<a href='/$pagename' onclick='initab()'>$pagename</a></div>";
}
unset($tasks);
if ($display['usage']) my_usage();
echo "</div>";
echo "<div id='nav-right'>";
foreach ($buttons as $page) {
  eval("?>{$page['text']}");
  if (empty($page['Link'])) {
    $icon = substr($page['Icon'],-4)=='.png' ? "<img src='/{$page['root']}/icons/{$page['Icon']}' class='system'>" : "<i class='system fa fa-{$page['Icon']}'></i>";
    echo "<div id='nav-item' class='{$page['name']}'><a href='".($page['Href'] ?? '#')."' onclick='{$page['name']}();return false;' title='{$page['Title']}'>$icon<span>{$page['Title']}</span></a></div>";
  } else
    echo "<div id='{$page['Link']}'></div>";
}
unset($buttons);
if ($notify['display']) {
  echo "<span id='nav-tub1' class='tub'><i id='box-tub1' class='fa fa-square grey-text'></i><span id='txt-tub1' class='score one'>0</span></span>";
  echo "<span id='nav-tub2' class='tub'><i id='box-tub2' class='fa fa-square grey-text'></i><span id='txt-tub2' class='score one'>0</span></span>";
  echo "<span id='nav-tub3' class='tub'><i id='box-tub3' class='fa fa-square grey-text'></i><span id='txt-tub3' class='score one'>0</span></span>";
}
echo "</div></div></div>";

// Build page content
echo "<div class='tabs'>";
$tab = 1;
$view = $myPage['name'];
$pages = [];
if ($myPage['text']) $pages[$view] = $myPage;
if ($myPage['Type']=='xmenu') $pages = array_merge($pages, find_pages($view));
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
      echo "<div id='title'><span class='left'>";
      echo tab_title($title,$page['root'],$page['Tag']??false);
      echo "</span></div>";
    }
    $tab++;
  }
  if (isset($page['Type']) && $page['Type']=='menu') {
    $pgs = find_pages($page['name']);
    foreach ($pgs as $pg) {
      @eval("\$title=\"".htmlspecialchars($pg['Title'])."\";");
      $link = "$path/{$pg['name']}";
      $icon = $pg['Icon'] ?? false;
      if ($icon) {
        if (substr($icon,-4)=='.png') {
          $icon = "{$pg['root']}/images/$icon";
          if (!file_exists($icon)) {$icon = "{$pg['root']}/$icon"; if (!file_exists($icon)) $icon = '/webGui/images/default.png';}
          $icon = "<img class=\"PanelImg\" src=\"$icon\">";
        } else {
          $icon = "<i class='PanelIcon fa fa-$icon'></i>";
        }
      } else {
        $icon = "<img class=\"PanelImg\" src=\"/webGui/images/default.png\">";
      }
      echo "<div class=\"Panel\"><a href=\"$link\" onclick=\"$.cookie('one','tab1',{path:'/'})\">$icon<div class=\"PanelText\">$title</div></a></div>";
    }
  }
  empty($page['Markdown']) || $page['Markdown']=='true' ? eval('?>'.Markdown($page['text'])) : eval('?>'.$page['text']);
  if ($close) echo "</div></div>";
}
unset($pages);
?>
 </div></div>
 <iframe id="progressFrame" name="progressFrame" frameborder="0"></iframe>
<?
// Build footer
echo '<div id="footer"><span id="statusraid"><span id="statusbar">';
$progress = ($var['fsProgress']!='')? "&bullet;<span class='blue strong'>{$var['fsProgress']}</span>" : '';
switch ($var['fsState']) {
case 'Stopped':
  echo "<span class='red strong'><i class='fa fa-stop-circle'></i> Array Stopped</span>$progress"; break;
case 'Starting':
  echo "<span class='orange strong'><i class='fa fa-pause-circle'></i> Array Starting</span>$progress"; break;
case 'Stopping':
  echo "<span class='orange strong'><i class='fa fa-pause-circle'></i> Array Stopping</span>$progress"; break;
default:
  echo "<span class='green strong'><i class='fa fa-play-circle'></i> Array Started</span>$progress"; break;
}
echo "</span></span><span id='countdown'></span><span id='user-notice' class='red-text'></span>";
echo "<span id='copyright'>unRAID&reg; webGui &copy;2018, Lime Technology, Inc.";
echo " <a href='http://lime-technology.com/wiki/index.php/Official_Documentation' target='_blank' title='Online manual'><i class='fa fa-book'></i> manual</a>";
echo "</span></div>";
?>
<script>
// Firefox specific workaround
if (typeof InstallTrigger!=='undefined') $('#nav-block').addClass('mozilla');

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
var watchdog = new NchanSubscriber('/sub/var');
watchdog.on('message', function(data) {
  var ini = parseINI(data);
  var state = ini['fsState'];
  var progress = ini['fsProgress'];
  var status;
  if (state=='Stopped') {
    status = "<span class='red strong'><i class='fa fa-stop-circle'></i> Array Stopped</span>";
  } else if (state=='Started') {
    status = "<span class='green strong'><i class='fa fa-play-circle'></i> Array Started</span>";
  } else if (state=='Formatting') {
    status = "<span class='green strong'><i class='fa fa-play-circle'></i> Array Started</span>&bullet;<span class='orange strong'>Formatting device(s)</span>";
  } else {
    status = "<span class='orange strong'><i class='fa fa-pause-circle'></i> Array "+state+"</span>";
  }
  if (ini['mdResync']>0) {
    var action;
    if (ini['mdResyncAction'].indexOf("recon")>=0) action = "Parity-Sync / Data-Rebuild";
    else if (ini['mdResyncAction'].indexOf("clear")>=0) action = "Clearing";
    else if (ini['mdResyncAction']=="check") action = "Read-Check";
    else if (ini['mdResyncAction'].indexOf("check")>=0) action = "Parity-Check";
    action += " "+(ini['mdResyncPos']/(ini['mdResync']/100+1)).toFixed(1)+" %";
    status += "&bullet;<span class='orange strong'>"+action.replace('.','<?=$display['number'][0]?>')+"</span>";
  }
  if (progress) status += "&bullet;<span class='blue strong'>"+progress+"</span>";
  $('#statusbar').html(status);
});
var backtotopoffset = 250;
var backtotopduration = 500;
$(window).scroll(function() {
  if ($(this).scrollTop() > backtotopoffset) {
    $('.back_to_top').fadeIn(backtotopduration);
  } else {
    $('.back_to_top').fadeOut(backtotopduration);
  }
});
$('.back_to_top').click(function(event) {
  event.preventDefault();
  $('html,body').animate({scrollTop:0},backtotopduration);
  return false;
});
$(function() {
  if ($("div#nav-right").css('float').toLowerCase() == 'right') {
    var origNavRightWidth = $('div#nav-right').width();
    var adjustMenuSize = function() {
      if ($('div#nav-block').width() - $('div#nav-left').width() <= origNavRightWidth+6) {
        $('div#nav-right div#nav-item a').css('min-width', 0).find('span').hide();
      } else {
        $('div#nav-right div#nav-item a').css('min-width', '').find('span').show();
      }
    };
    $(window).resize(adjustMenuSize).load(adjustMenuSize);
  }
<?if ($notify['entity'] & 1 == 1):?>
  $.post('/webGui/include/Notify.php',{cmd:'init'},function(){timers.notifier = setTimeout(notifier,0);});
<?endif;?>
  $('input[value="Apply"],input[name="cmdEditShare"],input[name="cmdUserEdit"]').attr('disabled','disabled');
  $('form').find('select,input[type=text],input[type=number],input[type=password],input[type=checkbox],input[type=file],textarea').each(function(){$(this).on('input change',function() {
    var form = $(this).parentsUntil('form').parent();
    form.find('input[value="Apply"],input[name="cmdEditShare"],input[name="cmdUserEdit"]').not('input.lock').removeAttr('disabled');
    form.find('input[value="Done"]').not('input.lock').val('Reset').prop('onclick',null).click(function(){refresh(form.offset().top)});
  });});

  var top = ($.cookie('top')||0) - $('.tabs').offset().top - 75;
  if (top>0) {$('html,body').scrollTop(top);}
  $.removeCookie('top',{path:'/'});
<?if (strpos(file_get_contents('/proc/cmdline'),'unraidsafemode')!==false):?>
  showNotice('System running in <b>safe</b> mode');
<?else:?>
<?$readme = @file_get_contents("$docroot/plugins/unRAIDServer/README.md",false,null,0,20);?>
<?if (strpos($readme,'REBOOT REQUIRED')!==false):?>
  showUpgrade('<b>Reboot required</b> to apply unRAID OS update');
<?elseif (strpos($readme,'DOWNGRADE')!==false):?>
  showUpgrade('<b>Reboot required</b> to downgrade unRAID OS');
<?elseif ($version = plugin_update_available('unRAIDServer',true)):?>
  showUpgrade('unRAID OS v<?=$version?> is available. <a>Download Now</a>','unRAIDServer');
<?endif;?>
<?endif;?>
<?if ($notify['display']):?>
  var opts = [{header:'Alerts', image:'/webGui/icons/alerts.png'}];
  context.init({preventDoubleContext:false});
  opts.push({text:'View',icon:'fa-folder-open-o',action:function(e){e.preventDefault();openNotifier('alert');}});
  opts.push({divider:true});
  opts.push({text:'History',icon:'fa-file-text-o',action:function(e){e.preventDefault();viewHistory('alert');}});
  opts.push({divider:true});
  opts.push({text:'Acknowledge',icon:'fa-check-square-o',action:function(e){e.preventDefault();closeNotifier('alert');}});
  context.attach('#nav-tub1',opts);

  var opts = [{header:'Warnings', image:'/webGui/icons/warnings.png'}];
  context.init({preventDoubleContext:false});
  opts.push({text:'View',icon:'fa-folder-open-o',action:function(e){e.preventDefault();openNotifier('warning');}});
  opts.push({divider:true});
  opts.push({text:'History',icon:'fa-file-text-o',action:function(e){e.preventDefault();viewHistory('warning');}});
  opts.push({divider:true});
  opts.push({text:'Acknowledge',icon:'fa-check-square-o',action:function(e){e.preventDefault();closeNotifier('warning');}});
  context.attach('#nav-tub2',opts);

  var opts = [{header:'Messages', image:'/webGui/icons/messages.png'}];
  context.init({preventDoubleContext:false});
  opts.push({text:'View',icon:'fa-folder-open-o',action:function(e){e.preventDefault();openNotifier('normal');}});
  opts.push({divider:true});
  opts.push({text:'History',icon:'fa-file-text-o',action:function(e){e.preventDefault();viewHistory('normal');}});
  opts.push({divider:true});
  opts.push({text:'Acknowledge',icon:'fa-check-square-o',action:function(e){e.preventDefault();closeNotifier('normal');}});
  context.attach('#nav-tub3',opts);
<?endif;?>
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
  $('form').append($('<input>').attr({type:'hidden', name:'csrf_token', value:'<?=$var['csrf_token']?>'}));
  watchdog.start();
});
</script>
</body>
</html>
