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
 * Scheduling rule: at most one queue-owned active task per type at any time.
 * Additional same-type operations are queued and auto-started by the `tasks`
 * daemon when the active one finishes. Unrelated legacy processes can still
 * publish on the shared type channels and are outside this queue invariant.
 */

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/publish.php";
require_once "$docroot/webGui/include/Secure.php";

define('TASK_DIR', '/var/local/emhttp/tasks');
define('TASK_DAEMON', 'plugins/dynamix/nchan/tasks');
define('TASK_DONE_TTL', 86400); // prune done/error tasks after 1 day
define('TASK_ABORT_GRACE', 5);   // seconds before an abort escalates TERM -> KILL
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

function task_type_lock($type) {
  if (!in_array($type, TASK_TYPES, true)) return false;
  $lock = @fopen(task_dir()."/.$type.lock", 'c');
  if (!$lock) return false;
  if (!flock($lock, LOCK_EX)) { fclose($lock); return false; }
  return $lock;
}

function task_type_unlock($lock) {
  flock($lock, LOCK_UN);
  fclose($lock);
}

// Read the Linux process identity fields needed to distinguish a task's
// session leader from a later process that reused the same numeric PID.
function task_proc_stat($pid) {
  if (!ctype_digit((string)$pid) || (int)$pid <= 1) return null;
  $stat = @file_get_contents("/proc/$pid/stat");
  if (!is_string($stat)) return null;
  $end = strrpos($stat, ')');
  if ($end === false) return null;
  $fields = preg_split('/\s+/', trim(substr($stat, $end + 1)));
  if (count($fields) < 20) return null;
  return [
    'pid'       => (int)$pid,
    'state'     => (string)$fields[0], // proc field 3
    'pgrp'      => (int)$fields[2],  // proc field 5
    'session'   => (int)$fields[3],  // proc field 6
    'starttime' => (string)$fields[19], // proc field 22
  ];
}

function task_process_identity($handshake) {
  for ($i = 0; $i < 200; $i++) {
    $claimed = trim((string)@file_get_contents($handshake));
    $stat = task_proc_stat($claimed);
    if ($stat && $stat['state']==='T' && $stat['pgrp']===(int)$claimed && $stat['session']===(int)$claimed) return $stat;
    usleep(10000);
  }
  return null;
}

function task_process_group_alive($task) {
  $pid = (int)($task['pid'] ?? 0);
  $pgrp = (int)($task['pgrp'] ?? 0);
  $session = (int)($task['session'] ?? 0);
  $starttime = (string)($task['pid_start'] ?? '');
  if ($pid <= 1 || $pgrp !== $pid || $session !== $pid || $starttime === '') return false;

  // If the leader PID exists but its birth time changed, the id was reused and
  // must never be treated as (or signalled as) this task's process group.
  $leader = task_proc_stat($pid);
  if ($leader && $leader['starttime'] !== $starttime) return false;
  if ($leader && $leader['state']!=='Z' && $leader['pgrp']===$pgrp && $leader['session']===$session) return true;
  // The leader can exit before descendants finish. In that case scan for a
  // surviving member of the original session/process group.
  foreach (glob('/proc/[0-9]*/stat', GLOB_NOSORT) ?: [] as $file) {
    $member = task_proc_stat(basename(dirname($file)));
    if ($member && $member['state']!=='Z' && $member['pgrp']===$pgrp && $member['session']===$session) return true;
  }
  return false;
}

function task_signal_group($task, $signal) {
  if (!task_process_group_alive($task)) return false;
  $pgrp = (int)$task['pgrp'];
  exec('kill -'.$signal.' -'.$pgrp.' 2>/dev/null', $out, $rc);
  return $rc === 0;
}

function task_wait_group_exit($task, $attempts = 100) {
  for ($i = 0; $i < $attempts; $i++) {
    if (!task_process_group_alive($task)) return true;
    usleep(10000);
  }
  return !task_process_group_alive($task);
}

// Fail closed after an owned launcher exists but cannot be safely started.
// Never release its type slot until the group is confirmed gone; if it survives
// KILL, persist an aborting sentinel for the daemon to keep monitoring.
function task_fail_launch(&$task, $handshake) {
  task_signal_group($task, 'KILL');
  if (task_wait_group_exit($task, 200)) {
    $task['status'] = 'error';
    $task['finished'] = time();
    if (!task_write($task)) @unlink(task_path($task['id']));
    @unlink($handshake);
    return false;
  }

  $task['status'] = 'aborting';
  $task['abort_requested'] = 0; // daemon escalates immediately
  // A prior state write may have failed. Keep the caller's type lock and retry
  // until either the sentinel is durable or the stopped group is confirmed gone.
  while (!task_write($task)) {
    task_signal_group($task, 'KILL');
    if (task_wait_group_exit($task, 100)) {
      @unlink($handshake);
      @unlink(task_path($task['id']));
      return false;
    }
  }
  return false;
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
    if ($t['type']===$type && in_array($t['status'], ['running','aborting'], true)) return $t;
  return null;
}

// Persist and mirror one message published on a shared task-type channel.
// Capture happens from Nginx's publisher hook instead of publish.php because
// third-party plugin scripts commonly POST straight to /pub/plugins. The task
// queue guarantees at most one queue-owned active task per type. A publication
// from an unrelated process of the same type remains a known attribution limit.
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
  if (!flock($fh, LOCK_EX)) {
    fclose($fh);
    my_logger("Task capture failed to lock log for {$task['id']}");
    return false;
  }
  fseek($fh, 0, SEEK_END);
  $offset = ftell($fh);
  $record = $message."\x1e";
  $written = $offset !== false ? fwrite($fh, $record) : false;
  $complete = $written === strlen($record);
  if (!$complete && $offset !== false) ftruncate($fh, $offset);
  fflush($fh);
  if (!$complete) {
    flock($fh, LOCK_UN);
    fclose($fh);
    my_logger("Task capture failed to append log for {$task['id']}");
    return false;
  }

  // A small retained buffer lets a foreground subscriber joining mid-stream
  // catch up; byte offsets make any redelivery harmless. Keep the log lock
  // through the mirror publish so concurrent writers preserve offset order.
  publish("task-{$task['id']}", $offset."\x1f".$message, 10);
  flock($fh, LOCK_UN);
  fclose($fh);
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
    if (!task_write($task)) @unlink(task_path($task['id']));
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
  // The wrapper writes its own PID and stops before the operation begins. PHP
  // validates + persists that process identity, then resumes the group. This
  // handshake means no privileged payload can run untracked and cleanup never
  // has to signal an unverified numeric PID.
  $handshake = task_dir().'/.launch-'.$task['id'];
  @unlink($handshake);
  $gate = 'printf "%s\\n" "$$" > '.escapeshellarg($handshake).' || exit 125; kill -STOP $$; rm -f '.escapeshellarg($handshake).'; ';
  $payload = $gate.'sleep .3 && '.$name.' '.$args.$suffix.$stamp;
  // setsid runs the operation in its own session + process group (pid == pgid),
  // so Abort (TaskCommand.php) can signal the whole tree via a negative-pid group
  // kill and actually stop the underlying command and every child it spawned.
  // With a plain nohup the wrapper shell stays in php-fpm's process group: killing
  // just its pid orphaned the real worker (e.g. a docker update), which then ran
  // to completion after the task was already marked error.
  exec($env.'setsid bash -c '.escapeshellarg($payload).' 1>/dev/null 2>&1 &');
  $identity = task_process_identity($handshake);
  if (!$identity) {
    // A matching handshake file plus a stopped process proves ownership even
    // when setsid failed to establish the expected group. Kill only that owned
    // launcher PID; never fall back to an unverified numeric PID or group.
    $claimed = trim((string)@file_get_contents($handshake));
    $stopped = task_proc_stat($claimed);
    if ($stopped && $stopped['state']==='T') exec('kill -KILL '.(int)$claimed.' 2>/dev/null');
    @unlink($handshake);
    $task['status'] = 'error';
    $task['finished'] = time();
    if (!task_write($task)) @unlink(task_path($task['id']));
    return false;
  }
  $pid = (string)$identity['pid'];
  $task['pid']     = $pid;
  $task['pid_start'] = $identity['starttime'];
  $task['pgrp']    = $identity['pgrp'];
  $task['session'] = $identity['session'];
  $task['status']  = 'running';
  $task['started'] = time();
  if (!task_write($task)) return task_fail_launch($task, $handshake);
  if (!task_signal_group($task, 'CONT')) return task_fail_launch($task, $handshake);
  @unlink($handshake);
  return $pid;
}

// Caller holds the per-type lock.
function task_advance_locked($type) {
  if (task_running_type($type)) return false;
  foreach (task_list() as $task) {
    if ($task['type']===$type && $task['status']==='queued') return task_launch($task);
  }
  return false;
}

// start the next queued task of a type if nothing of that type is running.
// Takes the per-type lock so the check-and-launch is atomic against task_create
// and task_complete; without it two advancers (e.g. the daemon and a task's own
// completion stamp firing at the same instant) could both pass task_running_type
// and double-launch the next queued task. Callers must NOT already hold the lock.
function task_advance($type) {
  $lock = task_type_lock($type);
  if (!$lock) return false;
  task_advance_locked($type);
  task_type_unlock($lock);
  return true;
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
// an _ERROR_ record. Marking and selection of the next queued task are atomic
// under the per-type lock. An already-finalized task (or one marked by the
// daemon first) is left untouched.
function task_complete($id, $rc) {
  $task = task_read($id);
  if (!$task) return;
  $type = $task['type'];
  $lock = task_type_lock($type);
  if (!$lock) return false;
  $task = task_read($id); // re-read under lock
  $changed = false;
  if ($task && in_array($task['status'], ['running','aborting'], true)) {
    $task['status']   = ($task['status']==='aborting' || (int)$rc !== 0 || task_log_has_error($id)) ? 'error' : 'done';
    $task['finished'] = time();
    $changed = (bool)task_write($task);
    if ($changed) task_advance_locked($type);
  }
  task_type_unlock($lock);
  if ($changed) task_publish();
  return $changed;
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
  if (!in_array($type, TASK_TYPES, true)) return null;
  // Serialize the dedupe -> create -> launch sequence per type. Without this,
  // two concurrent requests (e.g. a double click) could both pass the dedupe
  // and task_running_type() checks and each call task_launch(), violating the
  // "at most one running task per type" invariant the whole design relies on.
  $lock = task_type_lock($type);
  if (!$lock) return null;
  // dedupe: unless unconditional (start==1), don't queue an identical pending/running op
  if ((int)$start !== 1) {
    foreach (task_list() as $t) {
      if ($t['type']===$type && $t['cmd']===$cmd && in_array($t['status'],['queued','running','aborting'], true)) {
        task_type_unlock($lock);
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
    'pid_start'=> '',
    'pgrp'     => 0,
    'session'  => 0,
    'status'   => 'queued',
    'created'  => time(),
    'started'  => 0,
    'finished' => 0,
  ];
  if (!task_write($task)) { task_type_unlock($lock); return null; }
  task_advance_locked($type);
  task_type_unlock($lock);
  task_daemon_start();
  task_publish();
  return task_read($task['id']);
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
