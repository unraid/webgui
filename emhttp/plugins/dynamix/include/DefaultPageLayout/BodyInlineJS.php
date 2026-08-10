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

// ===========================================================================
//  Multi-task background operation system (shared across clients/subsystems)
// ---------------------------------------------------------------------------
//  The backend (TaskQueue.php + the `tasks` daemon) owns the queue: at most one
//  RUNNING op per type, so the legacy shared /sub/<type> channels never
//  interleave. The full task list is broadcast on /sub/tasks; per-task output
//  is captured to a server-side log (replayed on foreground) and mirrored on a
//  per-task channel /sub/task-<id> that the foreground modal streams from.
//  Capture occurs at Nginx's shared publisher boundary, so direct legacy
//  publishers and WebGUI's publish() helper follow the same task-owned path.
// ===========================================================================
const TASK_ENDPOINT = '/plugins/dynamix/include/TaskCommand.php';
var taskList = [];
const taskPrev = {};
const taskCallbackFired = {};
const taskCallbackOwned = {};
var foregroundTaskId = null;

// SweetAlert reuses one global .sweet-alert node. When a task sheet is being
// minimized, do not let a second dialog reuse that node until SweetAlert's
// asynchronous close handoff has completed. Otherwise an ordinary warning
// (notably Abort) can inherit the task-sheet class and be hidden by the stale
// close callback from the previous dialog.
var taskModalClosing = false;
var taskModalCloseToken = 0;
var taskModalCloseTimer = null;
var taskModalCloseQueue = [];

function taskById(id)  { for (var i=0;i<taskList.length;i++) if (taskList[i].id==id)  return taskList[i]; return null; }
function taskByPid(pid){ for (var i=0;i<taskList.length;i++) if (taskList[i].pid==pid) return taskList[i]; return null; }
function escapeTaskHtml(s){ return $('<div>').text(s==null?'':String(s)).html(); }

// NchanSubscriber.start()/stop() throw when called in the wrong run-state
// ("Can't stop NchanSubscriber, it's not running."). Guard every transition so
// a no-op start/stop can't raise and abort the surrounding handler.
function nchanStart(sub){ try { if (sub && !sub.running) sub.start(); } catch(e) {} }
function nchanStop(sub) { try { if (sub &&  sub.running) sub.stop();  } catch(e) {} }

// Live output for the foreground modal comes from the task's OWN channel
// (/sub/task-<id>), not the shared per-type channels. task_capture() mirrors
// every captured message there, prefixed with the message's byte offset in the
// task log ("<offset>\x1f<message>"). Two problems with the shared /sub/<type>
// channels made them unusable for the modal:
//   - they don't identify the originating task, so nchan's retained message
//     (generic /pub/ channels never expire) leaks across tasks: a new running
//     task's modal would consume the PREVIOUS task's retained _DONE_ and flip
//     to Finished while the new op was still running
//   - the retained last message re-delivered on subscribe duplicated the last
//     record already rendered by the log replay (N records -> N+1 rows)
// The offset tag solves the second exactly: the log replay reports how many
// bytes it covered (X-Task-Log-Size) and every live message whose offset falls
// inside that range is already on screen, so it's dropped. The shared type
// channels are still published for any external subscriber.
var taskSub = null, taskSubId = null, replayedBytes = 0;
var taskReplayReady = false, taskPendingMessages = [], taskReplayRequest = 0;
function startTaskChannel(id, onConnect) {
  if (taskSub && taskSubId === id) return;
  stopTaskChannel();
  taskSubId = id;
  taskSub = new NchanSubscriber('/sub/task-'+id,{subscriber:'websocket', reconnectTimeout:5000});
  taskSub.on('message', function(data){ routeTaskMessage(id, data); });
  if (onConnect) taskSub.once('connect', onConnect);
  nchanStart(taskSub);
}
function stopTaskChannel() {
  if (taskSub) { nchanStop(taskSub); taskSub = null; taskSubId = null; }
  taskReplayRequest++;
  taskReplayReady = false;
  taskPendingMessages = [];
}

// progress_dots / progress_span (declared in HeadInlineJS) are global wait-spinner
// timers keyed by element id. Clear them when (re)opening or closing a modal so a
// re-foregrounded docker/vm op doesn't spawn duplicate tickers or tick a dead node.
function clearProgressDots(){
  for (var k in progress_dots) if (progress_dots[k]) clearInterval(progress_dots[k]);
  progress_dots = []; progress_span = [];
}

// render one raw nchan message into the open modal (#swaltext)
function renderMessage(type, data) {
  var box = $('pre#swaltext');
  if (!box.length) return;
  if (type=='plugins') {
    const text = box.html().split('<br>');
    if (data.slice(-1) == '\r') text[text.length-1] = data.slice(0,-1);
    else text.push(data.slice(0,-1));
    box.html(text.join('<br>')).scrollTop(box[0].scrollHeight);
    return;
  }
  // docker + vmaction share the \0-delimited protocol (differ only in addToID label)
  data = data.split('\0');
  switch (data[0]) {
  case 'addLog':
    var rows = document.getElementsByClassName('logLine');
    if (rows.length) rows[rows.length-1].innerHTML += data[1]+'<br>';
    break;
  case 'progress':
    var rows = document.getElementsByClassName('progress-'+data[1]);
    if (rows.length) rows[rows.length-1].textContent = data[2];
    break;
  case 'addToID':
    var label = type=='docker' ? 'IMAGE ID ['+data[1]+']' : data[1];
    var rows = document.getElementById(data[1]);
    if (rows === null) {
      rows = document.getElementsByClassName('logLine');
      if (rows.length) {
        var row = rows[rows.length-1];
        row.innerHTML += '<span id="'+data[1]+'">'+label+': <span class="content">'+data[2]+'</span><span class="progress-'+data[1]+'"></span>.</span><br>';
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
    if (progress_span[data[1]]) progress_span[data[1]].innerHTML = '';
    break;
  default:
    box.html(box.html()+data[0]);
    break;
  }
  box.scrollTop(box[0].scrollHeight);
}

// live per-task channel messages render only into the foregrounded task's modal
function routeTaskMessage(id, raw) {
  if (foregroundTaskId !== id || !raw) return;
  if (!taskReplayReady) { taskPendingMessages.push(raw); return; }
  var data = raw, sep = raw.indexOf('\x1f');
  // strip the "<log byte offset>\x1f" tag; drop anything the replay already covered
  if (sep > 0 && /^\d+$/.test(raw.slice(0,sep))) {
    if (parseInt(raw.slice(0,sep),10) < replayedBytes) return;
    data = raw.slice(sep+1);
  }
  if (!data) return;
  if (data=='_DONE_')  { openDone(data);  return; }
  if (data=='_ERROR_') { openError(data); return; }
  var t = taskById(id);
  renderMessage(t ? t.type : 'plugins', data);
}

function loadTaskReplay(id, task, reset) {
  var request = ++taskReplayRequest;
  $.get(TASK_ENDPOINT,{action:'log',id:id},function(logdata,_st,xhr){
    if (foregroundTaskId!==id || request!==taskReplayRequest) return;
    if (reset) $('pre#swaltext').html('');
    replayedBytes = parseInt(xhr && xhr.getResponseHeader('X-Task-Log-Size'),10) || 0;
    var msgs = (logdata||'').split('\x1e');
    for (var i=0;i<msgs.length;i++) {
      var m = msgs[i];
      if (m==='' || m==='_DONE_' || m==='_ERROR_') continue;
      renderMessage(task.type, m);
    }
    taskPendingMessages.sort(function(a,b){
      var as = a.indexOf('\x1f'), bs = b.indexOf('\x1f');
      var ao = as>0 ? parseInt(a.slice(0,as),10) : Number.MAX_VALUE;
      var bo = bs>0 ? parseInt(b.slice(0,bs),10) : Number.MAX_VALUE;
      return ao-bo;
    });
    var pending = taskPendingMessages;
    taskPendingMessages = [];
    taskReplayReady = true;
    for (var j=0;j<pending.length;j++) routeTaskMessage(id, pending[j]);
    var fresh = taskById(id);
    if (fresh && fresh.status=='done')  openDone('_DONE_');
    else if (fresh && fresh.status=='error') openError('_ERROR_');
  },'text').fail(function(){
    if (foregroundTaskId!==id || request!==taskReplayRequest) return;
    taskReplayReady = true;
    var pending = taskPendingMessages;
    taskPendingMessages = [];
    for (var i=0;i<pending.length;i++) routeTaskMessage(id, pending[i]);
  });
}

// Legacy per-op reload callback ( (func||'loadlist')(plg) ), suppressed for
// ':return'. The legacy modal fired this whenever plg was non-null, so an
// empty plg with an explicit func (e.g. Apps.page openPlugin(...,'','refresh'),
// CA's ca_openPlugin(...,'','OpenSidebarAndRefreshDisplay')) still refreshed
// the page. Task records serialize plg/func as strings, so "a callback was
// requested" means either field is non-empty -- requiring a truthy plg alone
// silently dropped every empty-identifier refresh.
function fireTaskCallback(t) {
  if (t && taskCallbackOwned[t.id] && !taskCallbackFired[t.id] && t.plg != ':return' && (t.plg || t.func)) {
    var fn = window[t.func || 'loadlist'];
    if (typeof fn === 'function') {
      taskCallbackFired[t.id] = true;
      setTimeout(function(){ fn(t.plg); },250);
    }
  }
}

// Shared chrome for every .nchan "sheet" dialog -- the foreground task modal, the
// changelog / Release Notes viewer (openChanges), and alert prompts (openAlert).
// Each sheet gets a resizable, width-remembering right edge and a corner close
// control, so they look and behave the same and none can end up un-closable.
//
// Width is applied via the --nchan-sheet-width CSS var (scoped to
// .sweet-alert.nchan in CSS) rather than an inline style, so it never leaks onto
// the shared .sweet-alert node that ordinary dialogs reuse. One width preference
// is shared across all sheets: resize any sheet and they all remember it.
var NCHAN_SHEET_WIDTH_KEY = 'unraid.nchanSheet.width';
function nchanSheetWidthBounds() {
  var cap = Math.round(window.innerWidth * 0.9);
  return { min: Math.min(600, cap), max: Math.max(Math.min(600, cap), cap) }; // allow resizing below the 80rem CSS default
}
function applyNchanSheetWidth() {
  var w = parseInt(localStorage.getItem(NCHAN_SHEET_WIDTH_KEY), 10);
  if (!w) return;                                   // no preference -> CSS default (80rem)
  var b = nchanSheetWidthBounds();
  w = Math.max(b.min, Math.min(w, b.max));
  document.documentElement.style.setProperty('--nchan-sheet-width', w + 'px');
}
function ensureNchanResizer() {
  var el = document.querySelector('.sweet-alert.nchan');
  if (!el || el.querySelector('.nchan-resize')) return;   // singleton swal node: attach once
  var handle = document.createElement('div');
  handle.className = 'nchan-resize';
  handle.title = "<?=_('Drag to resize (remembered)')?>";
  el.appendChild(handle);
  var dragging = false;
  handle.addEventListener('pointerdown', function(e){
    dragging = true;
    try { handle.setPointerCapture(e.pointerId); } catch(_){}
    document.body.style.userSelect = 'none';
    e.preventDefault();
  });
  handle.addEventListener('pointermove', function(e){
    if (!dragging) return;
    var b = nchanSheetWidthBounds();
    // .nchan is centered (left:50% + translateX(-50%)); its right edge tracks the
    // pointer when width == 2 * (pointerX - viewportCenterX).
    var w = Math.max(b.min, Math.min(Math.round(2 * (e.clientX - window.innerWidth / 2)), b.max));
    document.documentElement.style.setProperty('--nchan-sheet-width', w + 'px');
  });
  function endDrag(e){
    if (!dragging) return;
    dragging = false;
    try { handle.releasePointerCapture(e.pointerId); } catch(_){}
    document.body.style.userSelect = '';
    var w = parseInt(getComputedStyle(el).width, 10);   // final rendered width
    if (w) localStorage.setItem(NCHAN_SHEET_WIDTH_KEY, String(w));  // persist on release
  }
  handle.addEventListener('pointerup', endDrag);
  handle.addEventListener('pointercancel', endDrag);
}
// Give the current .nchan sheet its shared chrome (resize grip + close control).
// opts.close:
//   'minimize' -> corner control tucks the sheet away but keeps the task in the
//                 tray, so a running op is never killed (foreground task sheets)
//   'dismiss'  -> (default) corner control just closes the dialog
// opts.tip overrides the control's tooltip.
function decorateNchanSheet(opts) {
  opts = opts || {};
  if (!document.querySelector('.sweet-alert.nchan')) return;
  applyNchanSheetWidth();
  ensureNchanResizer();
  var minimize = opts.close === 'minimize';
  var onclick  = minimize ? 'minimizeForegroundTask()' : 'nchanCloseModal(true)';
  var icon     = minimize ? 'fa-minus' : 'fa-times';
  var tip      = opts.tip || (minimize ? "<?=_('Minimize - keeps it in the tray')?>" : "<?=_('Close')?>");
  $('.sweet-alert .nchan-close').remove();
  $('.sweet-alert').append("<a class='nchan-close' title=\""+tip+"\" onclick='"+onclick+"'><i class='fa "+icon+" fa-fw'></i></a>");
}

// bring a task to the foreground: establish the live subscriber first, buffer
// offset-tagged messages while replaying the durable log, then drain anything
// newer than that snapshot. Waiting for the subscriber's connect event closes
// the replay/live loss window even when a burst exceeds nchan's retained buffer.
function foregroundTask(id) {
  var task = taskById(id);
  if (!task) return;
  foregroundTaskId = id;
  stopTaskChannel();
  clearProgressDots();
  // Drive the modal by task status, not the per-type `button` flag (which made
  // docker ops hide the Close button while running and disabled the confirm
  // button, surfacing SweetAlert's la-ball-fall "bouncing dots" loader):
  //   running  -> a top-corner minimize (below) backgrounds it; no disabled
  //               button, so the bouncing-dots loader never shows. The spinning
  //               title icon is the in-progress indicator.
  //   finished -> a primary Dismiss button clears the task from the tray.
  var finished    = task.status=='done' || task.status=='error';
  // status renders as a colored "state" strip below the title (see .nchan-state)
  var stateCls    = task.status=='done'  ? 'nchan-done'
                  : task.status=='error' ? 'nchan-error' : 'nchan-running';
  var stateHtml   = task.status=='done'  ? "<i class='fa fa-check fa-fw'></i> <?=_('Finished')?>"
                  : task.status=='error' ? "<i class='fa fa-warning fa-fw'></i> <?=_('Error')?>"
                  : task.status=='aborting' ? "<i class='fa fa-refresh fa-spin fa-fw'></i> <?=_('Aborting')?>"
                  : "<i class='fa fa-refresh fa-spin fa-fw'></i> <?=_('In Progress')?>";
  swal({title:escapeTaskHtml(task.title),text:"<pre id='swaltext'></pre><hr>",html:true,animation:'none',showConfirmButton:finished,confirmButtonText:"<?=_('Dismiss')?>"},function(close){
    // confirm/Dismiss (or Esc): background while running, clear once finished
    if (foregroundTaskId===id) foregroundTaskId=null;
    stopTaskChannel();
    clearProgressDots();
    var fresh = taskById(id);
    if (fresh && (fresh.status=='done'||fresh.status=='error')) { fireTaskCallback(fresh); dismissTask(id); }
    nchanCloseModal(false);   // swal closes via closeOnConfirm; this just cleans up
    trayRender();
  });
  $('.sweet-alert').addClass('nchan').css('pointer-events','');
  // colored state strip between the title and the log (openDone/openError recolor it)
  $('.sweet-alert .nchan-state').remove();
  $('.sweet-alert > h2').after("<div id='pluginProgressTitle' class='nchan-state "+stateCls+"'>"+stateHtml+"</div>");
  // The corner control is ALWAYS minimize: it tucks the sheet away but keeps the
  // task running in the tray; removal is the separate Dismiss button. openDone/
  // openError swap the tooltip to the finished form. decorateNchanSheet adds this
  // control plus the shared resize grip and restores the remembered width.
  var closeTip = finished || task.status=='aborting' ? "<?=_('Minimize - keeps it in the tray')?>" : "<?=_('Minimize - keeps running in the background')?>";
  decorateNchanSheet({ close:'minimize', tip: closeTip });
  $('pre#swaltext').html('');
  taskReplayReady = false;
  taskPendingMessages = [];
  var replayStarted = false;
  var connectFallback = setTimeout(function(){
    if (foregroundTaskId!==id || (taskSub && taskSub.connected)) return;
    replayStarted = true;
    loadTaskReplay(id, task, false);
  }, 2000);
  startTaskChannel(id, function(){
    clearTimeout(connectFallback);
    // If the bounded fallback already started, replace its snapshot after the
    // subscriber is confirmed ready. Messages arriving from this point are
    // buffered and reconciled against the fresh byte cutoff.
    taskReplayReady = false;
    taskPendingMessages = [];
    loadTaskReplay(id, task, replayStarted);
  });
}

// react to the shared task list pushed on /sub/tasks
function onTaskListUpdate() {
  for (var i=0;i<taskList.length;i++) {
    var t = taskList[i], prev = taskPrev[t.id];
    if (prev !== t.status) {
      if (t.status=='done' || t.status=='error') {
        if (foregroundTaskId==t.id) {
          // Leave the task channel subscribed: the status broadcast (/sub/tasks)
          // and the task's own output channel race over separate sockets, so the
          // last few output records may still be in flight. The modal's close/
          // minimize handlers stop the channel.
          if (t.status=='error') openError('_ERROR_'); else openDone('_DONE_');
          // callback fired when the user closes the modal
        } else if (prev !== undefined) {
          // Only fire the one-shot completion callback when we actually watched
          // this task run and *then* finish during this page's lifetime. A task
          // that is already terminal on first sight (prev === undefined) is
          // stale -- its callback belonged to whoever was watching when it
          // finished, and its effect is already applied server-side. Re-firing
          // it on load is harmful for reload-style callbacks (func:'refresh'):
          // /sub/tasks is an nchan channel that redelivers its retained message
          // to every new subscriber, and taskPrev starts empty on each load, so
          // the page reloads -> re-subscribes -> sees the same done task as
          // "new" -> reloads again, forever (e.g. an OS-update task stuck as
          // done with func:'refresh' == infinite reload / flashing UI). The
          // finished task still shows in the tray for the user to dismiss.
          fireTaskCallback(t);
        }
      } else if ((t.status=='running' || t.status=='aborting') && foregroundTaskId==t.id) {
        var activeText = t.status=='aborting' ? "<?=_('Aborting')?>" : "<?=_('In Progress')?>";
        $('#pluginProgressTitle').attr('class','nchan-state nchan-running').html("<i class='fa fa-refresh fa-spin fa-fw'></i> "+activeText);
        startTaskChannel(t.id);
      }
    }
    taskPrev[t.id] = t.status;
  }
  for (var id in taskPrev) if (!taskById(id)) delete taskPrev[id];
  trayRender();
  // let the current page react to task changes (e.g. Plugins page disables its
  // update buttons while a plugin task is running). No-op where undefined.
  if (typeof window.onTaskListChanged === 'function') { try { window.onTaskListChanged(); } catch(e) {} }
}

var taskChannel = new NchanSubscriber('/sub/tasks',{subscriber:'websocket', reconnectTimeout:5000});
taskChannel.on('message', function(msg){
  try { taskList = JSON.parse(msg) || []; } catch(e) { taskList = []; }
  onTaskListUpdate();
});

// Tray expand/collapse state (mobile only). On desktop the tray is always a
// full vertical stack; on mobile it collapses to a single tappable card with
// the rest peeking behind it (the iOS/Android grouped-notification pattern).
var trayExpanded = false;
function isMobileTray() {
  return !!(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
}
function expandTray() { trayExpanded = true; trayRender(); }
function collapseTray() { trayExpanded = false; trayRender(); }

// render the task tray
function trayRender() {
  var $tray = $('#opTray');
  if (!$tray.length) return;
  if (!taskList.length) { $tray.hide().empty(); trayExpanded = false; return; }
  var rows = '', finished = 0, count = taskList.length;
  // newest first: the most recent task sits at the top of the stack and is the
  // single card shown when the mobile tray is collapsed.
  for (var i=taskList.length-1;i>=0;i--) {
    var t = taskList[i], icon, actions='', safeId = escapeTaskHtml(t.id);
    var top = (i==taskList.length-1) ? ' op-top' : '';
    if (t.status=='done' || t.status=='error') finished++;
    var show = "<a class='op-act' onclick='foregroundTask(\""+safeId+"\")' title=\"<?=_('Show')?>\"><i class='fa fa-window-maximize fa-fw'></i></a>";
    if (t.status=='running') {
      icon = "<i class='fa fa-circle-o-notch fa-spin fa-fw'></i>";
      actions = show + "<a class='op-act' onclick='confirmAbortTask(\""+safeId+"\")' title=\"<?=_('Abort')?>\"><i class='fa fa-stop-circle fa-fw'></i></a>";
    } else if (t.status=='aborting') {
      icon = "<i class='fa fa-circle-o-notch fa-spin fa-fw' title=\"<?=_('Aborting')?>\"></i>";
      actions = show;
    } else if (t.status=='queued') {
      icon = "<i class='fa fa-clock-o fa-fw'></i>";
      actions = "<a class='op-act' onclick='cancelTask(\""+safeId+"\")' title=\"<?=_('Cancel')?>\"><i class='fa fa-times fa-fw'></i></a>";
    } else if (t.status=='done') {
      icon = "<i class='fa fa-check fa-fw green-text'></i>";
      actions = show + "<a class='op-act' onclick='dismissTask(\""+safeId+"\")' title=\"<?=_('Dismiss')?>\"><i class='fa fa-times fa-fw'></i></a>";
    } else {
      icon = "<i class='fa fa-warning fa-fw orange-text'></i>";
      actions = show + "<a class='op-act' onclick='dismissTask(\""+safeId+"\")' title=\"<?=_('Dismiss')?>\"><i class='fa fa-times fa-fw'></i></a>";
    }
    rows += "<div class='op-task op-"+t.status+top+"'><span class='op-icon'>"+icon+"</span><span class='op-title'>"+escapeTaskHtml(t.title)+"</span><span class='op-actions'>"+actions+"</span></div>";
  }
  // Header (only when more than one task): carries the count, a bulk "Clear
  // finished" action when there is more than one finished task, and — on the
  // mobile expanded stack — a chevron to collapse back to the single card.
  var head = '';
  if (count > 1) {
    head = "<div class='op-tray-head'>";
    head += "<span class='op-tray-count'>"+count+" <?=_('operations')?></span>";
    head += "<span class='op-tray-head-acts'>";
    if (finished > 1) {
      head += "<a class='op-act' onclick='clearFinishedTasks()' title=\"<?=_('Clear finished tasks')?>\"><i class='fa fa-check-circle-o fa-fw'></i> <?=_('Clear finished')?></a>";
    }
    head += "<a class='op-act op-collapse' onclick='collapseTray()' title=\"<?=_('Collapse')?>\"><i class='fa fa-chevron-down fa-fw'></i></a>";
    head += "</span></div>";
  }
  // Collapsed badge: a count pill on the top card so a stacked group reads as
  // "N operations" before it's expanded.
  var badge = (count > 1) ? "<span class='op-stack-badge' onclick='expandTray()'>"+count+"</span>" : "";
  // State classes drive the CSS: collapsed shows just the top card with the
  // others peeking; expanded shows the full vertical list with the header.
  var mobile = isMobileTray();
  var collapsed = mobile && !trayExpanded && count > 1;
  $tray.removeClass('op-collapsed op-expanded op-multi');
  if (count > 1) $tray.addClass('op-multi');
  $tray.addClass(collapsed ? 'op-collapsed' : 'op-expanded');
  $tray.html(head + rows + badge).show();
}

// When the mobile tray is collapsed into a single stacked card, a tap anywhere
// on it (other than a row action) expands the full list. Delegated so it
// survives trayRender() re-renders.
$(document).on('click', '#opTray.op-collapsed', function(e) {
  if ($(e.target).closest('.op-act').length) return;
  expandTray();
});
// Re-evaluate collapsed/expanded layout when crossing the mobile breakpoint so
// the tray doesn't get stuck in a mobile-only collapsed state on resize.
if (window.matchMedia) {
  var trayMql = window.matchMedia('(max-width: 767px)');
  var onTrayBreakpoint = function(){ if (!isMobileTray()) trayExpanded = false; trayRender(); };
  if (trayMql.addEventListener) trayMql.addEventListener('change', onTrayBreakpoint);
  else if (trayMql.addListener) trayMql.addListener(onTrayBreakpoint);
}

// minimize the foreground modal: drop the live view but leave the task running
// in the backend (the tray keeps tracking it). Backgrounding, not aborting.
// Fade the modal out with its .nchan styling intact, then strip the class once
// it's gone. Removing .nchan while the modal is still visible snaps it back to
// the default swal look for a frame (the "flash"). pointer-events:none keeps the
// fading (now-invisible but still laid-out) modal from eating clicks; the guard
// avoids stripping .nchan off a modal that was reopened in the meantime.
function clearNchanChrome($sa) {
  $sa = $sa || $('.sweet-alert');
  $sa.removeClass('nchan').css('pointer-events','');
  $sa.children('.nchan-state,.nchan-close,.nchan-resize').remove();
}

function finishTaskModalClose(token) {
  if (!taskModalClosing || token !== taskModalCloseToken) return;
  if (taskModalCloseTimer) clearTimeout(taskModalCloseTimer);
  taskModalCloseTimer = null;
  taskModalClosing = false;
  clearNchanChrome($('.sweet-alert'));
  var queued = taskModalCloseQueue;
  taskModalCloseQueue = [];
  for (var i=0;i<queued.length;i++) setTimeout(queued[i],0);
}

function nchanCloseModal(doClose) {
  var $sa = $('.sweet-alert');
  if (doClose && typeof swal!=='undefined' && swal.close) {
    var token = ++taskModalCloseToken;
    taskModalClosing = true;
    $sa.css('pointer-events','none');
    // Remove task-owned content immediately, but keep the task-sheet class
    // through SweetAlert's close animation so the sheet does not flash back to
    // the ordinary dialog layout while disappearing.
    $sa.children('.nchan-state').remove();
    if (taskModalCloseTimer) clearTimeout(taskModalCloseTimer);
    // The foreground task callback normally runs when SweetAlert has completed
    // its close. Keep a fallback for callers/browsers that do not invoke it.
    taskModalCloseTimer = setTimeout(function(){ finishTaskModalClose(token); },750);
    swal.close();
    return;
  }
  if (taskModalClosing) {
    // This is the foreground task's delayed SweetAlert callback. Defer one tick
    // so any final library cleanup runs before the next dialog is opened.
    var token = taskModalCloseToken;
    setTimeout(function(){ finishTaskModalClose(token); },0);
    return;
  }
  clearNchanChrome($sa);
}

function minimizeForegroundTask() {
  foregroundTaskId=null;
  stopTaskChannel();
  clearProgressDots();
  nchanCloseModal(true);
  trayRender();
}

function cancelTask(id) { $.post(TASK_ENDPOINT,{action:'abort',id:id}); }
function dismissTask(id){ $.post(TASK_ENDPOINT,{action:'dismiss',id:id}); }
function clearFinishedTasks(){ $.post(TASK_ENDPOINT,{action:'clear'}); }
function confirmAbortTask(id) {
  var show = function() {
    // Defensive cleanup keeps this ordinary warning independent of any task
    // chrome left on SweetAlert's shared singleton node.
    clearNchanChrome($('.sweet-alert'));
    swal({title:"<?=_('Abort background operation')?>",text:"<?=_('This may leave an unknown state')?>",html:true,animation:'none',type:'warning',showCancelButton:true,confirmButtonText:"<?=_('Proceed')?>",cancelButtonText:"<?=_('Cancel')?>"},function(){
      $.post(TASK_ENDPOINT,{action:'abort',id:id});
    });
  };
  if (taskModalClosing) taskModalCloseQueue.push(show);
  else show();
}

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
  // subscribe to the shared task list; the tray renders from server state
  taskChannel.start();
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
