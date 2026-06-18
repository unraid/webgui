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
// POST mutations (create/abort/dismiss) are CSRF-protected globally by local_prepend.php.
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/plugins/dynamix/include/TaskQueue.php";

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id     = $_POST['id'] ?? $_GET['id'] ?? '';

switch ($action) {
case 'create':
  $task = task_create(
    $_POST['type']  ?? '',
    rawurldecode($_POST['cmd'] ?? ''),
    rawurldecode($_POST['title'] ?? ''),
    $_POST['plg']   ?? '',
    $_POST['func']  ?? '',
    $_POST['start'] ?? 0,
    $_POST['button'] ?? 0
  );
  header('Content-Type: application/json');
  die(json_encode($task ? ['id'=>$task['id'],'status'=>$task['status']] : ['error'=>'invalid']));

case 'abort':
  $task = task_read($id);
  if ($task) {
    if ($task['status']==='running' && $task['pid'] > 1) {
      exec('kill '.escapeshellarg($task['pid']));
      foreach (glob('/tmp/plugins/pluginPending/*') ?: [] as $file) @unlink($file);
      $task['status']   = 'error';
      $task['finished'] = time();
      task_write($task);
      task_advance($task['type']);
    } else {
      // queued (or already finished) task: just drop it
      task_delete($id);
    }
    task_publish();
  }
  die();

case 'dismiss':
  $task = task_read($id);
  if ($task && in_array($task['status'],['done','error'])) {
    task_delete($id);
    task_publish();
  }
  die();

case 'clear':
  // remove every finished (done/error) task at once
  task_clear_finished();
  task_publish();
  die();

case 'log':
  // output captured so far, for foreground replay
  header('Content-Type: text/plain');
  if (task_valid_id($id) && is_file(task_log($id))) readfile(task_log($id));
  die();

case 'list':
  header('Content-Type: application/json');
  die(json_encode(task_list()));
}
die();
?>
