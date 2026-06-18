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
 * capturing the operation's nchan output (written by publish.php when the
 * NCHAN_TASK env var is set). The full task list is broadcast to all clients
 * on the `tasks` nchan channel whenever it changes.
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

// launch a task in the background, capturing its output to <id>.log via NCHAN_TASK
function task_launch(&$task) {
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
  $env = 'NCHAN_TASK='.escapeshellarg($task['id']).' ';
  $pid = exec($env."nohup bash -c 'sleep .3 && $name $args$suffix' 1>/dev/null 2>&1 & echo \$!");
  $task['pid']     = $pid;
  $task['status']  = 'running';
  $task['started'] = time();
  task_write($task);
  return $pid;
}

// start the next queued task of a type if nothing of that type is running
function task_advance($type) {
  if (task_running_type($type)) return;
  foreach (task_list() as $t) {
    if ($t['type']===$type && $t['status']==='queued') { task_launch($t); return; }
  }
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
  // dedupe: unless unconditional (start==1), don't queue an identical pending/running op
  if ((int)$start !== 1) {
    foreach (task_list() as $t) {
      if ($t['type']===$type && $t['cmd']===$cmd && in_array($t['status'],['queued','running']))
        return $t;
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
