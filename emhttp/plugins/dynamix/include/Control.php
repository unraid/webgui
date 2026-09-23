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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix/include/PopularDestinations.php";

// add translations
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

function age($number,$time) {
  return sprintf(_('%s '.($number==1 ? $time : $time.'s').' ago'),$number);
}

function my_age($time) {
  if (!is_numeric($time)) $time = time();
  $age = new DateTime('@'.$time);
  $age = date_create('now')->diff($age);
  if ($age->y > 0) return age($age->y,'year');
  if ($age->m > 0) return age($age->m,'month');
  if ($age->d > 0) return age($age->d,'day');
  if ($age->h > 0) return age($age->h,'hour');
  if ($age->i > 0) return age($age->i,'minute');
  return age($age->s,'second');
}

function validname($name) {
  $path = realpath(dirname($name));
  return in_array(explode('/',$path)[1]??'',['mnt','boot']) ? $path.'/'.basename($name) : '';
}

function escape($name) {return escapeshellarg(validname($name));}
function quoted($name) {return is_array($name) ? implode(' ',array_map('escape',$name)) : escape($name);}

function valid_upload_target($name) {
  $name = (string)$name;
  $file = validname($name);
  $base = basename($name);
  return $file && $base === basename($file) && preg_match('/\\A[A-Za-z0-9][A-Za-z0-9._ -]{0,254}\\z/D',$base) ? $file : '';
}

function upload_state_path($uploadId) {
  return preg_match('/\\A[a-f0-9]{48}\\z/D',(string)$uploadId) ? "/var/tmp/file-upload-$uploadId.json" : '';
}

function upload_session_key() {
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  return session_id() !== '' ? hash('sha256',session_id()) : '';
}

function open_upload_state($uploadId) {
  $path = upload_state_path($uploadId);
  if (!$path || !($handle = @fopen($path,'c+')) || !@flock($handle,LOCK_EX)) return [false,false,$path];
  rewind($handle);
  $state = json_decode(stream_get_contents($handle),true);
  return [is_array($state) ? $state : false,$handle,$path];
}

function remove_upload_state($handle,$path,$file='') {
  if (is_resource($handle)) {flock($handle,LOCK_UN); fclose($handle);}
  if ($path) @unlink($path);
  if ($file) @unlink($file);
}

$mode = $_POST['mode'] ?? $_GET['mode'] ?? '';
if (in_array($mode,['upload','stop'],true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  die('error:post-required');
}

switch ($mode) {
case 'upload':
  $uploadId = (string)($_POST['uploadId'] ?? $_GET['uploadId'] ?? '');
  $start = (int)($_POST['start'] ?? $_GET['start'] ?? 0);
  $cancel = (int)($_POST['cancel'] ?? $_GET['cancel'] ?? 0);
  $total = (int)($_POST['total'] ?? $_GET['total'] ?? 0);
  $sessionKey = upload_session_key();
  if (!$sessionKey || $start < 0) die('error:session');

  if ($cancel === 1) {
    [$state,$stateHandle,$statePath] = open_upload_state($uploadId);
    if (!$state || !hash_equals((string)($state['session'] ?? ''),$sessionKey)) {
      if (is_resource($stateHandle)) {flock($stateHandle,LOCK_UN); fclose($stateHandle);}
      die('stop');
    }
    $target = (string)($state['file'] ?? '');
    $stat = @lstat($target);
    $sameFile = $stat && (int)$stat['dev'] === (int)($state['dev'] ?? -1) && (int)$stat['ino'] === (int)($state['ino'] ?? -1);
    remove_upload_state($stateHandle,$statePath,$sameFile ? $target : '');
    die('stop');
  }

  $newUpload = $uploadId === '' && $start === 0;
  if ($newUpload) {
    $file = valid_upload_target(rawurldecode($_POST['file'] ?? $_GET['file'] ?? ''));
    if (!$file || $total < 0) die('stop');
    $info = pathinfo($file); $n = 0;
    while (file_exists($file) || is_link($file)) $file = $info['dirname'].'/'.preg_replace('/ \(\d+\)$/','',$info['filename']).' ('.++$n.')'.($info['extension'] ? '.'.$info['extension'] : '');
    $handle = @fopen($file,'x+b');
    if (!$handle) die('error:create');
    chmod($file,0600);
    $stat = fstat($handle);
    $uploadId = bin2hex(random_bytes(24));
    $statePath = upload_state_path($uploadId);
    $stateHandle = @fopen($statePath,'x+b');
    if (!$stateHandle) {fclose($handle); @unlink($file); die('error:state');}
    $state = ['file'=>$file,'session'=>$sessionKey,'total'=>$total,'dev'=>(int)$stat['dev'],'ino'=>(int)$stat['ino']];
    fwrite($stateHandle,json_encode($state));
    fflush($stateHandle);
    flock($stateHandle,LOCK_UN);
    fclose($stateHandle);
    unset($stateHandle);
  } else {
    [$state,$stateHandle,$statePath] = open_upload_state($uploadId);
    if (!$state || !hash_equals((string)($state['session'] ?? ''),$sessionKey)) {
      if (is_resource($stateHandle)) {flock($stateHandle,LOCK_UN); fclose($stateHandle);}
      die('error:state');
    }
    $file = (string)($state['file'] ?? '');
    if ($total > 0 && (int)($state['total'] ?? 0) !== $total) {
      remove_upload_state($stateHandle,$statePath);
      die('error:total');
    }
    $total = (int)($state['total'] ?? $total);
    $targetStat = @lstat($file);
    if (!$targetStat || is_link($file) || (int)$targetStat['dev'] !== (int)($state['dev'] ?? -1) || (int)$targetStat['ino'] !== (int)($state['ino'] ?? -1)) {
      remove_upload_state($stateHandle,$statePath);
      die('error:target');
    }
    $handle = @fopen($file,'c+b');
    if (!$handle || !flock($handle,LOCK_EX)) {
      if (is_resource($handle)) fclose($handle);
      remove_upload_state($stateHandle,$statePath);
      die('error:open');
    }
  }

  if (!isset($stateHandle)) [$state,$stateHandle,$statePath] = open_upload_state($uploadId);
  $targetStat = fstat($handle);
  if (!$targetStat || (int)$targetStat['size'] !== $start) {
    flock($handle,LOCK_UN); fclose($handle);
    if (is_resource($stateHandle)) {flock($stateHandle,LOCK_UN); fclose($stateHandle);}
    die('error:offset');
  }
  if (isset($_POST['data'])) {
    $chunk = base64_decode($_POST['data'],true);
  } else {
    $chunk = file_get_contents('php://input');
    if (strlen($chunk) > 21000000) $chunk = false;
  }
  if ($chunk === false || $total > 0 && $start + strlen($chunk) > $total) {
    flock($handle,LOCK_UN); fclose($handle);
    if (is_resource($stateHandle)) {flock($stateHandle,LOCK_UN); fclose($stateHandle);}
    die('error:chunk');
  }
  $written = 0;
  while ($written < strlen($chunk)) {
    $count = fwrite($handle,substr($chunk,$written));
    if ($count === false) break;
    $written += $count;
  }
  fflush($handle);
  $complete = $total === 0 || $start + $written === $total;
  if ($written !== strlen($chunk)) $complete = false;
  if ($complete) {
    chgrp($file,'users');
    chown($file,'nobody');
    chmod($file,0666);
  }
  flock($handle,LOCK_UN); fclose($handle);
  if (is_resource($stateHandle)) {flock($stateHandle,LOCK_UN); fclose($stateHandle);}
  if ($complete) @unlink($statePath);
  die(json_encode(['uploadId'=>$uploadId,'complete'=>$complete]));
case 'stop':
  $uploadId = (string)($_POST['uploadId'] ?? $_GET['uploadId'] ?? '');
  [$state,$stateHandle,$statePath] = open_upload_state($uploadId);
  if ($state && hash_equals((string)($state['session'] ?? ''),upload_session_key())) {
    $target = (string)($state['file'] ?? '');
    $stat = @lstat($target);
    $sameFile = $stat && (int)$stat['dev'] === (int)($state['dev'] ?? -1) && (int)$stat['ino'] === (int)($state['ino'] ?? -1);
    remove_upload_state($stateHandle,$statePath,$sameFile ? $target : '');
  } elseif (is_resource($stateHandle)) {
    flock($stateHandle,LOCK_UN); fclose($stateHandle);
  }
  die('stop');
case 'calc':
  extract(parse_plugin_cfg('dynamix',true));
  $source = explode("\n",rawurldecode($_POST['source'] ?? ''));
  [$null,$root,$main,$rest] = my_explode('/',$source[0],4);
  if ($root=='mnt' && in_array($main,['user','user0'])) {
    $disks = parse_ini_file('state/disks.ini',true);
    $tag = implode('|',array_merge(['disk'],pools_filter($disks)));
    $loc = array_filter(explode(',',preg_replace("/($tag)/",',$1',exec("shopt -s dotglob; getfattr --no-dereference --absolute-names --only-values -n system.LOCATIONS ".quoted($source)."/* 2>/dev/null"))));
  } else {
    $loc = [];
    foreach ($source as $path) {
      [$null,$root,$main,$rest] = my_explode('/',$path,4);
      $loc[] = $root=='mnt' ? ($main ?: '---') : ($root=='boot' ? _('flash') : '---');
    }
  }
  natcasesort($loc);
  $awk = "awk 'BEGIN{ORS=\" \"}/Number of files|Total file size/{if(\$5==\"(reg:\")print \$4,\$8;if(\$5==\"(dir:\")print \$4,\$6;if(\$3==\"size:\")print \$4}'";
  [$files,$dirs,$size] = explode(' ',str_replace([',',')'],'',exec("rsync --stats -naI ".quoted($source)." /var/tmp 2>/dev/null|$awk")));
  $dirs = $dirs ?: 0;
  $files -= $dirs;
  $calc   = [];
  $calc[] = _('Name').": ".implode(', ',array_map('basename',$source));
  $calc[] = _('Location').": ".implode(', ',array_unique($loc));
  $calc[] = _('Last modified').': '.my_age(max(array_map('filemtime',$source)));
  $calc[] = _('Total occupied space').": ".my_scale($size,$unit)." $unit";
  $calc[] = sprintf(_("in %s folder".($dirs==1?'':'s')." and %s file".($files==1?'':'s')),my_number($dirs),my_number($files));
  $calc   = '<div style="text-align:left;margin-left:56px">'.implode('<br>',$calc).'</div>';
  die($calc);
case 'home':
  $source = explode("\n",rawurldecode($_POST['source'] ?? ''));
  $target = rawurldecode($_POST['target'] ?? '');
  $disks = parse_ini_file('state/disks.ini',true);
  $tag  = implode('|',array_merge(['disk'],pools_filter($disks)));
  $loc1 = implode(',',array_unique(array_filter(explode(',',preg_replace("/($tag)/",',$1',exec("getfattr --no-dereference --absolute-names --only-values -n system.LOCATIONS ".quoted($source)." 2>/dev/null"))))));
  $loc2 = exec("getfattr --no-dereference --absolute-names --only-values -n system.LOCATIONS ".quoted($target)." 2>/dev/null");
  $home = $loc1==$loc2 ? '1' : '0';
  die($home);
case 'jobs':
  $jobs = [];
  $file = '/var/tmp/file.manager.jobs';
  $rows = file_exists($file) ? file($file,FILE_IGNORE_NEW_LINES) : [];
  $job  = 1;
  foreach ($rows as $row) {
    if (empty($row)) continue;
    $data = json_decode($row, true);
    if (!$data) continue;
    $task = $data['task'] ?? '';
    $source = explode("\r",$data['source'] ?? '');
    $target = $data['target'] ?? '';
    $jobs[] = [
      'id' => $job++,
      'label' => _('Job'),
      'task' => $task,
      'source' => $source[0],
      'more' => count($source) > 1 ? sprintf(_('and %s more'),count($source)-1) : '',
      'target' => $target
    ];
  }
  die(json_encode($jobs));
case 'edit':
  $file = validname(rawurldecode($_POST['file']));
  die($file ? file_get_contents($file) : '');
case 'save':
  if ($file = validname(rawurldecode($_POST['file']))) file_put_contents($file,rawurldecode($_POST['data']));
  die();
case 'start':
  $active = '/var/tmp/file.manager.active';
  $jobs   = '/var/tmp/file.manager.jobs';
  $start  = '0';
  if (file_exists($jobs)) {
    // read first JSON line from jobs file and write to active
    $lines = file($jobs, FILE_IGNORE_NEW_LINES);
    if (!empty($lines)) {
      // Skip invalid JSON entries (scan once, slice once)
      $skipped = 0;
      $data = null;
      for ($i = 0, $n = count($lines); $i < $n; $i++) {
        $data = json_decode($lines[$i], true);
        if ($data) break;
        $skipped++;
      }
      if ($skipped > 0) {
        exec('logger -t webGUI "Warning: Skipped '.$skipped.' invalid JSON entr'.($skipped===1?'y':'ies').' in file manager job queue"');
        $lines = array_slice($lines, $skipped);
      }
      
      if (empty($lines)) {
        // No valid JSON entries found
        delete_file($jobs);
        die('0');
      }
      
      // Update popular destinations when dequeuing a job
      if (in_array((int)($data['action'] ?? 0), [3, 4, 8, 9]) && !empty($data['target'] ?? '')) {
        updatePopularDestinations($data['target']);
      }
      
      file_put_contents($active, $lines[0]);
      // remove first line from jobs file
      array_shift($lines);
      if (count($lines) > 0) {
        file_put_contents($jobs, implode("\n", $lines)."\n");
        $start = '2';
      } else {
        delete_file($jobs);
        $start = '1';
      }
    }
  }
  die($start);
case 'undo':
  $jobs = '/var/tmp/file.manager.jobs';
  $undo = '0';
  if (file_exists($jobs)) {
    $rows = explode(',', $_POST['row'] ?? '');
    $lines = file($jobs, FILE_IGNORE_NEW_LINES);
    foreach ($rows as $row) {
      $row = trim($row);
      if ($row === '' || !ctype_digit($row)) continue;
      $row = (int)$row;
      if ($row < 1) continue;
      $line_number = $row - 1; // Convert 1-based job number to 0-based array index
      if (isset($lines[$line_number])) {
        unset($lines[$line_number]);
      }
    }
    if (count($lines) > 0) {
      file_put_contents($jobs, implode("\n", $lines)."\n");
      $undo = '2';
    } else {
      delete_file($jobs);
      $undo = '1';
    }
  }
  die($undo);
case 'read':
  $active = '/var/tmp/file.manager.active';
  $read = file_exists($active) ? file_get_contents($active) : '';
  die($read);
case 'file':
  $active = '/var/tmp/file.manager.active';
  $jobs   = '/var/tmp/file.manager.jobs';
  $data = [
    'action' => (int)($_POST['action'] ?? 0),
    'title' => rawurldecode($_POST['title'] ?? ''),
    'source' => rawurldecode($_POST['source'] ?? ''),
    'target' => rawurldecode($_POST['target'] ?? ''),
    'H' => empty($_POST['hdlink']) ? '' : 'H',
    'sparse' => empty($_POST['sparse']) ? '' : '--sparse',
    'exist' => empty($_POST['exist']) ? '--ignore-existing' : '',
    'zfs' => rawurldecode($_POST['zfs'] ?? '')
  ];
  if (isset($_POST['task'])) {
    // add task to queue
    $data['task'] = rawurldecode($_POST['task']);
    file_put_contents($jobs, json_encode($data)."\n", FILE_APPEND);
  } else {
    // start operation
    file_put_contents($active, json_encode($data));
    // Update popular destinations only when an operation actually starts
    // Action types: 3=copy folder, 4=move folder, 8=copy file, 9=move file
    if (in_array((int)$data['action'], [3, 4, 8, 9]) && !empty($data['target'])) {
      updatePopularDestinations($data['target']);
    }
  }
  
  die();
}
?>
