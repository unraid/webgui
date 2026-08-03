<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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
/**
 * Backend task queue shared across subsystems (plugins / docker / vmaction).
 *
 * State lives in TASK_DIR as one <id>.json per task plus a per-task <id>.log
 * capturing the operation's nchan output. Nginx sends every shared task-type
 * publication through TaskCapture.php, including legacy scripts that POST
 * directly to /pub/<type>. The full task list is broadcast to all clients on
 * the `tasks` nchan channel whenever it changes.
 *
 * Scheduling rule: at most one RUNNING task per type at any time, so the
 * existing shared live channels (/sub/plugins, /sub/docker, /sub/vmaction)
 * never have two concurrent publishers. Additional same-type operations are
 * queued and auto-started by the `tasks` daemon when the running one finishes.
 */

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/publish.php";
require_once "$docroot/webGui/include/Secure.php";

define('TASK_DIR', '/var/local/emhttp/tasks');
define('TASK_DAEMON', 'plugins/dynamix/nchan/tasks');
define('TASK_DONE_TTL', 86400); // prune done/error tasks after 1 day
define('TASK_TYPES', ['plugins','docker','vmaction']);

// task ids are produced by uniqid() => lowercase hex; validate anything used in a path
function task_valid_id($id) {
  return is_string($id) && preg_match('/^[a-f0-9]+$/', $id);
}

function task_dir() {
  if (!is_dir(TASK_DIR)) @mkdir(TASK_DIR, 0770, true);
  return TASK_DIR;
}

function task_path($id) { return TASK_DIR."/$id.json"; }
function task_log($id)  { return TASK_DIR."/$id.log"; }

function task_read($id) {
  if (!task_valid_id($id)) return null;
  $file = task_path($id);
  if (!is_file($file)) return null;
  $data = json_decode(@file_get_contents($file), true);
  return is_array($data) ? $data : null;
}

function task_write($task) {
  task_dir();
  return file_put_contents_atomic(task_path($task['id']), json_encode($task));
}

function task_delete($id) {
  if (!task_valid_id($id)) return;
  delete_file(task_path($id), task_log($id));
  task_channel_delete($id);
}

// Drop the task's mirrored nchan channel (see task_capture()) along with the
// task.
// The generic /pub/ location keeps messages forever (nchan_message_timeout 0),
// so without this every finished task would leave its retained buffer parked in
// nchan shared memory for the life of the nginx process.
function task_channel_delete($id) {
  // buffer_length is required by the /pub/ location config for every method
  // (nchan_message_buffer_length $arg_buffer_length); without it nginx errors
  // out before nchan sees the DELETE
  $com = curl_init("http://localhost/pub/task-$id?buffer_length=1");
  curl_setopt_array($com, [
    CURLOPT_UNIX_SOCKET_PATH => '/var/run/nginx.socket',
    CURLOPT_CUSTOMREQUEST    => 'DELETE',
    CURLOPT_RETURNTRANSFER   => 1,
  ]);
  curl_exec($com);
  curl_close($com);
}

// all tasks, oldest first (FIFO by creation time, id breaks ties)
function task_list() {
  $tasks = [];
  foreach (glob(TASK_DIR.'/*.json') ?: [] as $file) {
    $data = json_decode(@file_get_contents($file), true);
    if (is_array($data) && isset($data['id'])) $tasks[] = $data;
  }
  usort($tasks, function($a,$b) {
    return ($a['created'] <=> $b['created']) ?: strcmp($a['id'],$b['id']);
  });
  return $tasks;
}

// broadcast the full list to every connected client
function task_publish() {
  publish('tasks', json_encode(task_list()));
}

// the single running task of a type, or null
function task_running_type($type) {
  foreach (task_list() as $t)
    if ($t['type']===$type && $t['status']==='running') return $t;
  return null;
}

// Persist and mirror one message published on a shared task-type channel.
// Capture happens from Nginx's publisher hook instead of publish.php because
// third-party plugin scripts commonly POST straight to /pub/plugins. The task
// queue guarantees at most one running task per type, which makes the shared
// channel -> task association unambiguous.
//
// Messages are RS-delimited in the log. The task channel carries the record's
// byte offset as "<offset>\x1f<message>", allowing the foreground client to
// dedupe precisely against a simultaneous log replay. The append is exclusive
// and TaskCommand.php reads under a shared lock, so offsets always land on
// record boundaries.
function task_capture($type, $message) {
  if (!in_array($type, TASK_TYPES, true)) return false;
  $task = task_running_type($type);
  if (!$task || !task_valid_id($task['id'])) return false;

  $fh = @fopen(task_log($task['id']), 'c');
  if (!$fh) return false;
  @flock($fh, LOCK_EX);
  fseek($fh, 0, SEEK_END);
  $offset = ftell($fh);
  $record = $message."\x1e";
  $written = $offset !== false ? fwrite($fh, $record) : false;
  $complete = $written === strlen($record);
  if (!$complete && $offset !== false) ftruncate($fh, $offset);
  fflush($fh);
  @flock($fh, LOCK_UN);
  fclose($fh);
  if (!$complete) return false;

  // A small retained buffer lets a foreground subscriber joining mid-stream
  // catch up; byte offsets make any redelivery harmless.
  publish("task-{$task['id']}", $offset."\x1f".$message, 10);
  return true;
}

// resolve a command to an absolute script path the same way StartCommand.php does
function task_resolve($cmd) {
  global $docroot;
  [$command,$args] = array_pad(explode(' ', unscript($cmd), 2), 2, '');
  $name = '';
  $path = '';
  foreach (glob("$docroot/plugins/*/scripts", GLOB_NOSORT) as $path) {
    if ($name = realpath("$path/$command")) break;
  }
  if (!$command || !$name || strncmp($name,$path,strlen($path))!==0) return null;
  return [$name, $args];
}

// launch a task in the background; Nginx's publisher hook captures its output
function task_launch(&$task) {
  global $docroot;
  // guard: never run two of the same type at once
  if (task_running_type($task['type'])) return false;
  $resolved = task_resolve($task['cmd']);
  if (!$resolved) {
    $task['status']   = 'error';
    $task['finished'] = time();
    task_write($task);
    return false;
  }
  [$name,$args] = $resolved;
  // plugin scripts publish to nchan only when their last argument is 'nchan'
  $suffix = $task['type']==='plugins' ? ' nchan' : '';
  // Keep the task id available to task-aware scripts and diagnostics. Output
  // capture itself no longer depends on this variable: TaskCapture.php handles
  // every publication on the shared type channel, including legacy scripts.
  $env = 'NCHAN_TASK='.escapeshellarg($task['id']).' ';
  // The command records its own terminal state on exit: capture its exit code
  // and hand it to task_complete, which marks the task done/error, advances the
  // queue and broadcasts. This makes completion authoritative at the source
  // instead of relying on the scheduler daemon to observe the PID disappear
  // (which it can miss on PID reuse or a daemon-restart race). NCHAN_TASK is
  // cleared before the completion helper because the operation itself is over.
  // The daemon stays a fallback for the case where the process is hard-killed
  // before the stamp can run.
  $complete = "$docroot/plugins/dynamix/include/task_complete";
  $stamp = '; rc=$?; NCHAN_TASK= '.escapeshellarg($complete).' '.escapeshellarg($task['id']).' "$rc"';
  // escapeshellarg the whole bash -c payload so a single quote (or other shell
  // metacharacter) in the resolved args cannot break out of the outer shell;
  // bash still word-splits the args internally, preserving multi-arg commands.
  $payload = 'sleep .3 && '.$name.' '.$args.$suffix.$stamp;
  // setsid runs the operation in its own session + process group (pid == pgid),
  // so Abort (TaskCommand.php) can signal the whole tree via a negative-pid group
  // kill and actually stop the underlying command and every child it spawned.
  // With a plain nohup the wrapper shell stays in php-fpm's process group: killing
  // just its pid orphaned the real worker (e.g. a docker update), which then ran
  // to completion after the task was already marked error.
  $pid = exec($env.'setsid bash -c '.escapeshellarg($payload).' 1>/dev/null 2>&1 & echo $!');
  $task['pid']     = $pid;
  $task['status']  = 'running';
  $task['started'] = time();
  task_write($task);
  return $pid;
}

// start the next queued task of a type if nothing of that type is running.
// Takes the per-type lock so the check-and-launch is atomic against task_create
// and task_complete; without it two advancers (e.g. the daemon and a task's own
// completion stamp firing at the same instant) could both pass task_running_type
// and double-launch the next queued task. Callers must NOT already hold the lock.
function task_advance($type) {
  $lock = fopen(task_dir()."/.$type.lock", 'c');
  if ($lock) flock($lock, LOCK_EX);
  if (!task_running_type($type)) {
    foreach (task_list() as $t) {
      if ($t['type']===$type && $t['status']==='queued') { task_launch($t); break; }
    }
  }
  if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
}

// the operation reported a failure if its captured output contains the _ERROR_
// control record. The log is RS(\x1e)-delimited and _DONE_/_ERROR_ are discrete
// records (see task_capture()), so match _ERROR_ as a whole record the same way
// the live channel does (routeMessage) — a log line that merely contains the
// text must not trip a false failure.
function task_log_has_error($id) {
  $log = task_log($id);
  if (!is_file($log)) return false;
  $fh = @fopen($log, 'rb');
  if (!$fh) return false;
  if (filesize($log) > 65536) fseek($fh, -65536, SEEK_END);
  $tail = stream_get_contents($fh);
  fclose($fh);
  return in_array('_ERROR_', explode("\x1e", (string)$tail), true);
}

// Record a task's terminal state from its command's own exit (invoked by the
// wrapper in task_launch via plugins/dynamix/include/task_complete). $rc is the
// command's exit code; a task is an error when it exited non-zero or published
// an _ERROR_ record. Marking is done under the per-type lock; the lock is then
// released before advancing so the (also-locking) task_advance can't deadlock on
// the same handle. An already-finalized task (e.g. aborted via TaskCommand.php,
// or marked by the daemon first) is left untouched.
function task_complete($id, $rc) {
  $task = task_read($id);
  if (!$task) return;
  $type = $task['type'];
  $lock = fopen(task_dir()."/.$type.lock", 'c');
  if ($lock) flock($lock, LOCK_EX);
  $task = task_read($id); // re-read under lock
  $changed = false;
  if ($task && $task['status']==='running') {
    $task['status']   = ((int)$rc !== 0 || task_log_has_error($id)) ? 'error' : 'done';
    $task['finished'] = time();
    task_write($task);
    $changed = true;
  }
  if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
  if ($changed) { task_advance($type); task_publish(); }
}

// (re)start the scheduling daemon if it isn't already running
function task_daemon_start() {
  global $docroot;
  $script = "$docroot/".TASK_DAEMON;
  exec('pgrep --ns $$ -f '.escapeshellarg($script), $out, $ret);
  if ($ret !== 0) exec(escapeshellarg($script).' >/dev/null 2>&1 &');
}

// create (and possibly immediately start) a task; returns the task record
function task_create($type,$cmd,$title,$plg,$func,$start,$button) {
  if (!in_array($type, TASK_TYPES)) return null;
  // Serialize the dedupe -> create -> launch sequence per type. Without this,
  // two concurrent requests (e.g. a double click) could both pass the dedupe
  // and task_running_type() checks and each call task_launch(), violating the
  // "at most one running task per type" invariant the whole design relies on.
  $lock = fopen(task_dir()."/.$type.lock", 'c');
  if ($lock) flock($lock, LOCK_EX);
  // dedupe: unless unconditional (start==1), don't queue an identical pending/running op
  if ((int)$start !== 1) {
    foreach (task_list() as $t) {
      if ($t['type']===$type && $t['cmd']===$cmd && in_array($t['status'],['queued','running'])) {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        return $t;
      }
    }
  }
  $task = [
    'id'       => uniqid(),
    'type'     => $type,
    'title'    => $title,
    'cmd'      => $cmd,
    'plg'      => $plg,
    'func'     => $func,
    'start'    => (int)$start,
    'button'   => (int)$button,
    'pid'      => '',
    'status'   => 'queued',
    'created'  => time(),
    'started'  => 0,
    'finished' => 0,
  ];
  task_write($task);
  if (!task_running_type($type)) task_launch($task);
  if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
  task_daemon_start();
  task_publish();
  return task_read($task['id']) ?: $task;
}

// remove finished tasks older than the TTL (called by the daemon on startup)
function task_prune() {
  $now = time();
  foreach (task_list() as $t) {
    if (in_array($t['status'],['done','error']) && ($now - ($t['finished'] ?: $t['created'])) > TASK_DONE_TTL)
      task_delete($t['id']);
  }
}

// remove every finished task now (the tray's "Clear finished" action)
function task_clear_finished() {
  foreach (task_list() as $t)
    if (in_array($t['status'],['done','error'])) task_delete($t['id']);
}
?>
