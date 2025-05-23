<?php
/**
 * This code was originally in DefaultPageLayout.php
 * It has been moved here to help make DefaultPageLayout.php more maintainable.
 * Please reference DefaultPageLayout.php for any historical git blame information.
 */
?>
<script>
String.prototype.actionName = function(){return this.split(/[\\/]/g).pop();}
String.prototype.channel = function(){return this.split(':')[1].split(',').findIndex((e)=>/\[\d\]/.test(e));}
NchanSubscriber.prototype.monitor = function(){subscribers.push(this);}

Shadowbox.init({skipSetup:true});
context.init();

// list of nchan subscribers to start/stop at focus change
var subscribers = [];

// server uptime
var uptime = <?=strtok(exec("cat /proc/uptime"),' ')?>;
var expiretime = <?=_var($var,'regTy')=='Trial'||strstr(_var($var,'regTy'),'expired')?_var($var,'regTm2'):0?>;
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
var csrf_token = "<?=_var($var,'csrf_token')?>";

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

function initab(page) { // @todo remove in the future
  $.removeCookie('one');
  $.removeCookie('tab');
  if (page != null) location.replace(page);
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
    swal({title:"_(Unsupported Feature)_",text:"_(Sorry, this feature is not supported by MSIE/Edge)_.<br>_(Please try a different browser)_",type:'error',html:true,animation:'none',confirmButtonText:"_(Ok)_"});
    return;
  }
  // open terminal window (run in background)
  name = name.replace(/[ #]/g,"_");
  tty_window = makeWindow(name+(more=='.log'?more:''),Math.min(screen.availHeight,800),Math.min(screen.availWidth,1200));
  var socket = ['ttyd','syslog'].includes(tag) ? '/webterminal/'+tag+'/' : '/logterminal/'+name+(more=='.log'?more:'')+'/';
  $.get('/webGui/include/OpenTerminal.php',{tag:tag,name:name,more:more},function(){setTimeout(function(){tty_window.location=socket; tty_window.focus();},200);});
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

function openVMAction(cmd,title,plg,func,start=0,button=0) {
  // start  = 0 : run command only when not already running (default)
  // start  = 1 : run command unconditionally
  // button = 0 : hide CLOSE button (default)
  // button = 1 : show CLOSE button
  nchan_vmaction.start();
  $.post('/webGui/include/StartCommand.php',{cmd:cmd,start:start},function(pid) {
    if (pid==0) {
      nchan_vmaction.stop();
      $('div.spinner.fixed').hide();
      $(".upgrade_notice").addClass('alert');
      return;
    }
    swal({title:title,text:"<pre id='swaltext'></pre><hr>",html:true,animation:'none',showConfirmButton:button!=0,confirmButtonText:"<?=_('Close')?>"},function(close){
      nchan_vmaction.stop();
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
  $('div.spinner.fixed').show();
  // button = 0 : hide CLOSE button (default)
  // button = 1 : show CLOSE button
  // nchan argument is not used, exists for backward compatibility
  $.post('/webGui/include/StartCommand.php',{cmd:cmd,start:2},function(data) {
    $('div.spinner.fixed').hide();
    swal({title:title,text:"<pre id='swalbody'></pre><hr>",html:true,animation:'none',showConfirmButton:button!=0,confirmButtonText:"<?=_('Close')?>"},function(close){
      $('.sweet-alert').hide('fast').removeClass('nchan');
      if ($('#submit_button').length > 0) $('#submit_button').remove();
    });
    $('.sweet-alert').addClass('nchan');
    $('pre#swalbody').html(data);
    $('button.confirm').text("<?=_('Done')?>").prop('disabled',false).show();
  });
}

function openAlert(cmd,title,func) {
  $.post('/webGui/include/StartCommand.php',{cmd:cmd,start:2},function(data) {
    $('div.spinner.fixed').hide();
    swal({title:title,text:"<pre id='swalbody'></pre><hr>",html:true,animation:'none',showCancelButton:true,closeOnConfirm:false,confirmButtonText:"<?=_('Proceed')?>",cancelButtonText:"<?=_('Cancel')?>"},function(proceed){
      if (proceed) setTimeout(func+'()');
    });
    $('.sweet-alert').addClass('nchan');
    $('pre#swalbody').html(data);
  });
}

function openDone(data) {
  if (data == '_DONE_') {
    $('div.spinner.fixed').hide();
    $('button.confirm').text("<?=_('Done')?>").prop('disabled',false).show();
    if (typeof ca_done_override !== 'undefined') {
      if (ca_done_override == true) {
        $("button.confirm").trigger("click");
        ca_done_override = false;
      }
    }
    return true;
  }
  return false;
}

function openError(data) {
  if (data == '_ERROR_') {
    $('div.spinner.fixed').hide();
    $('button.confirm').text("<?=_('Error')?>").prop('disabled',false).show();
    return true;
  }
  return false;
}

function showStatus(name, plugin, job) {
  $.post('/webGui/include/ProcessStatus.php',
    {
      name,
      plugin,
      job,
    },
    function(status) {
      $('.title .right').eq(0).append(status);
    }
  );
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

function showUpgradeChanges() { /** @note can likely be removed, not used in webgui or api repos */
  openChanges("showchanges /tmp/plugins/unRAIDServer.txt","<?=_('Release Notes')?>");
}

function showUpgrade(text,noDismiss=false) { /** @note can likely be removed, not used in webgui or api repos */
  if ($.cookie('os_upgrade')==null) {
    if (osUpgradeWarning) removeBannerWarning(osUpgradeWarning);
    osUpgradeWarning = addBannerWarning(text.replace(/<a>(.+?)<\/a>/,"<a href='#' onclick='openUpgrade()'>$1</a>").replace(/<b>(.*)<\/b>/,"<a href='#' onclick='document.rebootNow.submit()'>$1</a>"),false,noDismiss);
  }
}

function hideUpgrade(set) { /** @note can likely be removed, not used in webgui or api repos */
  removeBannerWarning(osUpgradeWarning);
  if (set)
    $.cookie('os_upgrade','true');
  else
    $.removeCookie('os_upgrade');
}

function confirmUpgrade(confirm) {
  if (confirm) {
    swal({title:"<?=_('Update')?> Unraid OS",text:"<?=_('Do you want to update to the new version')?>?",type:'warning',html:true,animation:'none',showCancelButton:true,closeOnConfirm:false,confirmButtonText:"<?=_('Proceed')?>",cancelButtonText:"<?=_('Cancel')?>"},function(){
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

function viewHistory() {
  location.replace('/Tools/NotificationsArchive');
}

function flashReport() {
  $.post('/webGui/include/Report.php',{cmd:'config'},function(check){
    if (check>0) addBannerWarning("<?=_('Your flash drive is corrupted or offline').'. '._('Post your diagnostics in the forum for help').'.'?> <a target='_blank' href='https://docs.unraid.net/go/changing-the-flash-device/'><?=_('See also here')?></a>");
  });
}

$(function() {
  let tab;
<?switch ($myPage['name']):?>
<?case'Main':?>
  tab = $.cookie('tab')||'tab1';
<?break;?>
<?case'Cache':case'Data':case'Device':case'Flash':case'Parity':?>
  tab = $.cookie('one')||'tab1';
<?break;?>
<?default:?>
  tab = $.cookie('one')||'tab1';
<?endswitch;?>
  /* Check if the tab is 'tab0' */
  if (tab === 'tab0') {
    /* Set tab to the last available tab based on input[name$="tabs"] length */
    tab = 'tab' + $('input[name$="tabs"]').length;
  } else if ($('#' + tab).length === 0) {
    /* If the tab element does not exist, initialize a tab and set to 'tab1' */
    initab();
    tab = 'tab1';
  }
  $('#'+tab).attr('checked', true);
  updateTime();

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

<?if (isset($myPage['Load']) && $myPage['Load'] > 0):?>
  // Reload page every X minutes during extended viewing?
  function setTimerReload() {
      timers.reload = setInterval(function(){
        if (nchanPaused === false && ! dialogOpen() ) {
          location.reload();
        }
      },<?=$myPage['Load'] * 60000?>);
    }
    $(document).click(function(e) {
      clearInterval(timers.reload);
      setTimerReload();
    });
    function dialogOpen() {
        return ($('.sweet-alert').is(':visible') || $('.swal-overlay--show-modal').is(':visible') );
    }
    setTimerReload();
<?endif;?>
</script>
