<?php
/**
 * This code was originally in DefaultPageLayout.php
 * It has been moved here to help make DefaultPageLayout.php more maintainable.
 * Please reference DefaultPageLayout.php for any historical git blame information.
 */
?>
<script>
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
// unraid animated logo
var unraid_logo = '<?readfile("$docroot/webGui/images/animated-logo.svg")?>';

var defaultPage = new NchanSubscriber('/sub/session,var<?=$entity?",notify":""?>',{subscriber:'websocket', reconnectTimeout:5000});
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
      case 'Formatting': var status = "<span class='green strong'><i class='fa fa-play-circle'></i> <?=_('Array Started')?></span>&bullet;<span class='orange strong tour'><?=_('Formatting device(s)')?></span>"; break;
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
      status += "&bullet;<span class='orange strong tour'>"+action.replace('.','<?=_var($display,'number','.,')[0]?>');
      if (ini['mdResyncDt']==0) status += " &bullet; <?=_('Paused')?>";
      status += "</span>";
    }
    if (ini['fsProgress']) status += "&bullet;<span class='blue strong tour'>"+_(ini['fsProgress'])+"</span>";
    $('#statusbar').html(status);
    break;
  case 2:
    // notifications - moved to the Unraid API
    break;
  }
});

<?if ($wlan0):?>
function wlanSettings() {
  $.cookie('one','tab<?=count(glob("$docroot/webGui/Eth*.page"))?>');
  window.location = '/Settings/NetworkSettings';
}

var nchan_wlan0 = new NchanSubscriber('/sub/wlan0',{subscriber:'websocket', reconnectTimeout:5000});
nchan_wlan0.on('message', function(msg) {
  var wlan = JSON.parse(msg);
  $('#wlan0').removeClass().addClass(wlan.color).attr('title',wlan.title);
});
nchan_wlan0.start();
<?endif;?>

var nchan_plugins = new NchanSubscriber('/sub/plugins',{subscriber:'websocket', reconnectTimeout:5000});
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

var nchan_docker = new NchanSubscriber('/sub/docker',{subscriber:'websocket', reconnectTimeout:5000});
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

var nchan_vmaction = new NchanSubscriber('/sub/vmaction',{subscriber:'websocket', reconnectTimeout:5000});
nchan_vmaction.on('message', function(data) {
  if (!data || openDone(data) || openError(data)) return;
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
        row.innerHTML += '<span id="'+data[1]+'">'+data[1]+': <span class="content">'+data[2]+'</span><span class="progress-'+data[1]+'"></span>.</span><br>';
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

const scrollDuration = 500;
$(window).scroll(function() {
  if ($(this).scrollTop() > 0) {
    $('.back_to_top').fadeIn(scrollDuration);
  } else {
    $('.back_to_top').fadeOut(scrollDuration);
  }
});

$('.move_to_end').click(function(event) {
  event.preventDefault();
  $('html,body').animate({scrollTop:$(document).height()},scrollDuration);
  return false;
});

$('.back_to_top').click(function(event) {
  event.preventDefault();
  $('html,body').animate({scrollTop:0},scrollDuration);
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
<?if (_var($var,'regTy')=='unregistered'):?>
  $('#licensetype').addClass('orange-text');
<?elseif (!in_array(_var($var,'regTy'),['Trial','Basic','Plus','Pro'])):?>
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
  const top = parseInt($.cookie('top') || '0', 10);
  if (top > 0) {
    $('html, body').scrollTop(top);
  }
  $.removeCookie('top');
  if ($.cookie('addAlert') != null) bannerAlert(addAlert.text,addAlert.cmd,addAlert.plg,addAlert.func);
<?if ($safemode):?>
  showNotice("<?=_('System running in')?> <b><?=('safe mode')?></b>");
<?else:?>
<?if (!_var($notify,'system')):?>
  addBannerWarning("<?=_('System notifications are')?> <b><?=_('disabled')?></b>. <?=_('Click')?> <a href='/Settings/Notifications'><?=_('here')?></a> <?=_('to change notification settings')?>.",true,true);
<?endif;?>
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
  $('form').append($('<input>').attr({type:'hidden', name:'csrf_token', value:csrf_token}));
  setInterval(function(){if ($(document).height() > $(window).height()) $('.move_to_end').fadeIn(scrollDuration); else $('.move_to_end').fadeOut(scrollDuration);},250);
});

var gui_pages_available = [];
<?
  $gui_pages = glob("/usr/local/emhttp/plugins/*/*.page");
  array_walk($gui_pages,function($value,$key){ ?>
    gui_pages_available.push('<?=basename($value,".page")?>'); <?
  });
?>

function isValidURL(url) {
  try {
    var ret = new URL(url);
    return ret;
  } catch (err) {
    return false;
  }
}

$('body').on('click','a,.ca_href', function(e) {
  if ($(this).hasClass('ca_href')) {
    var ca_href = true;
    var href=$(this).attr('data-href');
    var target=$(this).attr('data-target');
  } else {
    var ca_href = false;
    var href = $(this).attr('href');
    var target = $(this).attr('target');
  }
  if (href) {
    href = href.trim();
    // Sanitize href to prevent XSS
    href = href.replace(/[<>"]/g, '');
    if (href.match('https?://[^\.]*.(my)?unraid.net/') || href.startsWith('https://unraid.net/') || href == 'https://unraid.net' || href.startsWith('http://lime-technology.com')) {
      if (ca_href) window.open(href,target);
      return;
    }
    if (href !== '#' && !href.startsWith('javascript') && !href.startsWith('blob:')) {
      var dom = isValidURL(href);
      if (dom == false) {
        if (href.startsWith('/')) return;  // all internal links start with "/"
      var baseURLpage = href.split('/');
        if (gui_pages_available.includes(baseURLpage[0])) return;
      }
      if ($(this).hasClass('localURL')) return;
      try {
        var domainsAllowed = JSON.parse($.cookie('allowedDomains'));
      } catch(e) {
        var domainsAllowed = new Object();
      }
      $.cookie('allowedDomains',JSON.stringify(domainsAllowed),{expires:3650}); // rewrite cookie to further extend expiration by 400 days
      if (domainsAllowed[dom.hostname]) return;
      e.preventDefault();
      $('.sweet-alert').removeClass('nchan'); // Prevent GUI issues if clicking external link from a changelog
      swal({
        title: "<?=_('External Link')?>",
        text: "<span title='"+href+"'><?=_('Clicking OK will take you to a 3rd party website not associated with Lime Technology')?><br><br><b>"+href+"<br><br><input id='Link_Always_Allow' type='checkbox'></input><?=_('Always Allow')?> "+dom.hostname+"</span>",
        html: true,
        animation: 'none',
        type: 'warning',
        showCancelButton: true,
        showConfirmButton: true,
        cancelButtonText: "<?=_('Cancel')?>",
        confirmButtonText: "<?=_('OK')?>"
      },function(isConfirm) {
        if (isConfirm) {
          if ($('#Link_Always_Allow').is(':checked')) {
            domainsAllowed[dom.hostname] = true;
            $.cookie('allowedDomains',JSON.stringify(domainsAllowed),{expires:3650});
          }
          var popupOpen = window.open(href,target);
          if (!popupOpen || popupOpen.closed || typeof popupOpen == 'undefined') {
            var popupWarning = addBannerWarning("<?=_('Popup Blocked');?>");
            setTimeout(function(){removeBannerWarning(popupWarning);},10000);
          }
        }
      });
    }
  }
});

/**
 * Calculates and sets the height of a target element to fill the available viewport space.
 * 
 * This function dynamically resizes an element to occupy the remaining vertical space
 * after accounting for other page elements like headers, footers, controls, and their
 * margins/padding. Useful for creating full-height scrollable content areas.
 * 
 * The function includes default elements that are commonly present on pages:
 * - elementsForHeight: '#header', '#menu', '#footer' (plus any additional provided)
 * - elementsForSpacing: '.displaybox' (plus any additional provided)
 * 
 * @param {Object} params - Configuration object for height calculation
 * @param {string} [params.targetElementSelector='.js-fill-available-height'] - CSS selector for the element to resize
 * @param {string[]} [params.elementSelectorsForHeight=[]] - Additional CSS selectors for elements 
 *   whose full height (including margins) should be subtracted from available space.
 *   These are added to the default selectors: '#header', '#menu', '#footer'
 * @param {string[]} [params.elementSelectorsForSpacing=[]] - Additional CSS selectors for elements 
 *   whose spacing (margins and padding only) should be subtracted from available space.
 *   These are added to the default selector: '.displaybox'
 * @param {number} [params.minHeight=330] - Minimum height in pixels for the target element
 * @param {number} [params.manualSpacingOffset=10] - Additional pixels to subtract for manual spacing
 * 
 * @example
 * // Use with default parameters - targets '.js-fill-available-height'
 * fillAvailableHeight();
 * 
 * @example
 * // Custom configuration with additional elements
 * // MUST BE USED IN JQUERY ON READY
 * $(function() { // or $(document).ready(function() {
 *   fillAvailableHeight({
 *     targetElementSelector: '.my-content',
 *     elementSelectorsForHeight: ['.my-controls', '.my-actions'],
 *     elementSelectorsForSpacing: ['.my-content'],
 *     minHeight: 500,
 *     manualSpacingOffset: 20
 *   });
 * });
 */
function fillAvailableHeight(params = { // default params
  targetElementSelector: '.js-fill-available-height',
  elementSelectorsForHeight: [],
  elementSelectorsForSpacing: [],
  minHeight: 330,
  manualSpacingOffset: 10,
}) {  
  const minHeight = params.minHeight || 330;

  // default elementsForHeight
  const elementsForHeight = [
    '#header',
    '#menu',
    '#footer',
    '.title',
    ...(params.elementSelectorsForHeight ? params.elementSelectorsForHeight : []),
  ];

  // elements with a height and margin we want to subtract from the target height
  let targetHeight = window.innerHeight - elementsForHeight.reduce((acc, selector) => {
    const element = document.querySelector(selector);

    if (!element) {
      return acc;
    }

    const computedStyle = getComputedStyle(element);
    const height = element.offsetHeight;
    const marginTop = parseFloat(computedStyle.marginTop) || 0;
    const marginBottom = parseFloat(computedStyle.marginBottom) || 0;
    // we don't need to calculate padding because it's already included in the height
    const totalForElement = height + marginTop + marginBottom;

    return acc + totalForElement;
  }, 0);
  
  // elements with spacing that we want to subtract from the target height, but not their actual height.
  const elementsForSpacing = [
    '#displaybox',
    ...(params.targetElementSelector ? [params.targetElementSelector] : []),
    ...(params.elementSelectorsForSpacing ? params.elementSelectorsForSpacing : []),
  ];
  
  targetHeight -= elementsForSpacing.reduce((acc, selector) => {
    const element = document.querySelector(selector);

    if (!element) {
      return acc;
    }

    const computedStyle = getComputedStyle(element);
    const marginTop = parseFloat(computedStyle.marginTop) || 0;
    const marginBottom = selector !== '#displaybox' ? parseFloat(computedStyle.marginBottom) || 0 : 0;
    const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
    const paddingBottom = selector !== '#displaybox' ? parseFloat(computedStyle.paddingBottom) || 0 : 0;
    // we don't want to subtract paddingBottom or marginBottom for #displaybox b/c it adds unnecessary spacing in the calculations
    // b/c the paddingBottom is accounting for the fixed footer.

    const totalForElement = marginTop + marginBottom + paddingTop + paddingBottom;

    return acc + totalForElement;
  }, 0);

  // subtract addtional spacing from the target height to provide spacing between the actions & the footer
  targetHeight -= params.manualSpacingOffset || 10;

  const finalHeight = Math.max(targetHeight, minHeight);

  $(params.targetElementSelector).height(finalHeight);

  // Set up resize listener to call itself with same params
  // Remove existing listener first to avoid duplicates
  if (window.fillAvailableHeightResizeHandler) {
    window.removeEventListener('resize', window.fillAvailableHeightResizeHandler);
  }
  
  // Create debounced handler that calls this function with same params
  window.fillAvailableHeightResizeHandler = debounce(function() {
    fillAvailableHeight(params);
  }, 150);
  
  // Add the new listener
  window.addEventListener('resize', window.fillAvailableHeightResizeHandler);
}

/**
 * For every a.info element, we see if it has an inner span element.
 * While the CSS will determine visibility, we still need to use JS to set the position of the "tooltip" span.
 * Using the a.info element's offset position, we can calculate the top and left position needed for the span.
 */
$(document).on('mouseenter', 'a.info', function() {
  const tooltip = $(this).find('span');
  if (tooltip.length) {
    const aInfoPosition = $(this).offset();
    const scrollTop = $(window).scrollTop();
    const scrollLeft = $(window).scrollLeft();
    const addtionalOffset = 16;
    const top = aInfoPosition.top - scrollTop + addtionalOffset;
    const left = aInfoPosition.left - scrollLeft + addtionalOffset;
    tooltip.css({ top, left });
  }
});
</script>
