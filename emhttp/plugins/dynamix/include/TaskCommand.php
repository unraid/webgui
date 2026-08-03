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
    if ($task['status']==='running' && ctype_digit((string)$task['pid']) && (int)$task['pid'] > 1) {
      $pid = (int)$task['pid'];
      // The task runs in its own session/process group (task_launch uses setsid),
      // so signal the whole group with a negative pid to stop the underlying
      // operation AND every child it spawned. Killing only the wrapper's pid left
      // the real worker (e.g. a docker update) orphaned and running to completion.
      // The bare-pid kill is a fallback in case the group was never established.
      exec('kill -TERM -'.$pid.' 2>/dev/null');
      exec('kill -TERM '.$pid.' 2>/dev/null');
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
  // Output captured so far, for foreground replay. X-Task-Log-Size is the
  // exact byte length served: live task-channel messages carry their log
  // byte offset (see task_capture() in TaskQueue.php) and the client drops any
  // live message whose offset falls below this, so the replay/live handoff
  // never duplicates or loses a record. Read under the shared lock
  // (task_capture() appends under the exclusive one) so the length always
  // lands on a record boundary.
  header('Content-Type: text/plain');
  $data = '';
  if (task_valid_id($id) && is_file(task_log($id))) {
    $fh = @fopen(task_log($id), 'rb');
    if ($fh) {
      @flock($fh, LOCK_SH);
      $data = stream_get_contents($fh) ?: '';
      @flock($fh, LOCK_UN);
      fclose($fh);
    }
  }
  header('X-Task-Log-Size: '.strlen($data));
  die($data);

case 'list':
  header('Content-Type: application/json');
  die(json_encode(task_list()));
}
die();
?>
