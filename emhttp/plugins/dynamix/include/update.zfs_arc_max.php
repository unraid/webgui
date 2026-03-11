<?PHP
/* Copyright 2005-2026, Lime Technology
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
$arc = trim($_POST['zfs_arc_max'] ?? '');
$sys_dir = '/sys/module/zfs/parameters';

function log_zfs_update($message) {
  if (!$message) return;
  write_log($message);
  syslog(LOG_INFO, $message);
}

function apply_zfs_runtime_params($conf, $sys_dir) {
  if (!$conf || !is_file($conf) || !is_dir($sys_dir)) return;
  $lines = preg_split('/\r\n|\n|\r/', file_get_contents($conf));
  foreach ($lines as $line) {
    if (!preg_match('/^\s*options\s+zfs(\s+|$)/', $line)) continue;
    $params = preg_replace('/^\s*options\s+zfs\s+/', '', $line);
    foreach (preg_split('/\s+/', trim((string)$params)) as $kv) {
      if ($kv === '' || strpos($kv, '=') === false) continue;
      [$key, $value] = explode('=', $kv, 2);
      $key = trim($key);
      $value = trim($value);
      $param = "$sys_dir/$key";
      if (!is_file($param) || !is_writable($param)) continue;
      $current = @file_get_contents($param);
      if ($current !== false && trim($current) === $value) continue;
      if (@file_put_contents($param, $value) !== false) {
        log_zfs_update("Applied runtime ZFS parameter: $key=$value");
      }
    }
  }
}

if ($arc === '') {
  if ($file && is_file($file)) {
    $content = file_get_contents($file);
    $lines = preg_split('/\r\n|\n|\r/', rtrim($content, "\r\n"));
    $changed = false;
    for ($i = 0; $i < count($lines); $i++) {
      if (!preg_match('/^\s*options\s+zfs(\s+|$)/', $lines[$i])) continue;
      if (!preg_match('/\bzfs_arc_max\s*=\s*\S+/', $lines[$i])) continue;
      $lines[$i] = preg_replace('/\s*\bzfs_arc_max\s*=\s*\S+/', '', $lines[$i]);
      $lines[$i] = trim(preg_replace('/\s+/', ' ', $lines[$i]));
      if ($lines[$i] == 'options zfs') $lines[$i] = null;
      $changed = true;
    }
    if ($changed) {
      $lines = array_values(array_filter($lines, fn($line) => $line !== null));
      if (count($lines)) {
        file_put_contents_atomic($file, implode("\n", $lines)."\n");
      } else {
        @unlink($file);
      }
      log_zfs_update("Updated ZFS config: removed zfs_arc_max from $file");
      apply_zfs_runtime_params($file, $sys_dir);
    }
  }
  $save = false;
  return;
}

if (!preg_match('/^[0-9]+$/', $arc)) {
  log_zfs_update('Invalid zfs_arc_max value ignored (must be numeric)');
  $save = false;
  return;
}

if ($file) {
  $content = is_file($file) ? file_get_contents($file) : '';
  $lines = $content === '' ? [] : preg_split('/\r\n|\n|\r/', rtrim($content, "\r\n"));
  $updated = false;
  $first_arc_line = -1;

  for ($i = 0; $i < count($lines); $i++) {
    if (!preg_match('/^\s*options\s+zfs(\s+|$)/', $lines[$i])) continue;
    if (preg_match('/\bzfs_arc_max\s*=\s*\S+/', $lines[$i])) {
      if ($first_arc_line === -1) {
        $lines[$i] = preg_replace('/\bzfs_arc_max\s*=\s*\S+/', "zfs_arc_max=$arc", $lines[$i]);
        $first_arc_line = $i;
        $updated = true;
      } else {
        // Remove duplicate zfs_arc_max entries from additional lines.
        $lines[$i] = preg_replace('/\s*\bzfs_arc_max\s*=\s*\S+/', '', $lines[$i]);
        $lines[$i] = trim(preg_replace('/\s+/', ' ', $lines[$i]));
        if ($lines[$i] == 'options zfs') $lines[$i] = null;
      }
    }
  }

  if (!$updated) {
    $lines[] = "options zfs zfs_arc_max=$arc";
  }

  $lines = array_values(array_filter($lines, fn($line) => $line !== null));

  @mkdir(dirname($file), 0777, true);
  file_put_contents_atomic($file, implode("\n", $lines)."\n");
  log_zfs_update("Updated ZFS config: set zfs_arc_max=$arc in $file");
  apply_zfs_runtime_params($file, $sys_dir);
}

$save = false;
?>