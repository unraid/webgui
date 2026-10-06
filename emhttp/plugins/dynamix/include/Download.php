<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
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

$file = $_POST['file']??'';

function validpath($file) {
  global $docroot;
  return is_string($file) && $file !== '' && basename($file) === $file
    && realpath(dirname("$docroot/$file")) === realpath($docroot);
}

function path_in_root($path, $root) {
  $root = realpath($root);
  return $root !== false && ($path === $root || strncmp($path, $root.'/', strlen($root) + 1) === 0);
}

function validsource($source) {
  global $docroot;
  if (!is_string($source) || $source === '') return '';
  $candidates = [$source];
  if ($source[0] !== '/' && basename($source) === $source) $candidates[] = "$docroot/$source";
  foreach (array_unique($candidates) as $candidate) {
    $path = realpath($candidate);
    if ($path === false || !is_file($path)) continue;
    // These are the only local sources exposed by the webGUI download flows.
    if ($source[0] !== '/' && path_in_root($path, $docroot)
      && basename($path) === $source && pathinfo($path, PATHINFO_EXTENSION) === 'txt') return $path;
    if ($path === '/var/log/syslog' || $path === '/boot/logs/syslog-previous') return $path;
    if ((path_in_root($path, '/etc/wireguard') || path_in_root($path, '/boot/config/wireguard'))
      && in_array(pathinfo($path, PATHINFO_EXTENSION), ['conf','png'])) return $path;
    $rsyslog = @parse_ini_file('/boot/config/rsyslog.cfg');
    $folder = $rsyslog['server_folder'] ?? '';
    if (!empty($rsyslog['local_server']) && $folder && path_in_root($path, $folder)
      && preg_match('/^syslog-.*\.log$/', basename($path))) return $path;
  }
  return '';
}

switch ($_POST['cmd']) {
case 'save':
  if (!validpath($file)) break;
  $source = validsource($_POST['source']??'');
  $opts = $_POST['opts'] ?? 'qlj';
  if (!$source || !preg_match('/^[qlj]+$/', (string)$opts)) break;
  $destination = "$docroot/$file";
  if (in_array(pathinfo($source,PATHINFO_EXTENSION),['txt','conf','png'])) {
    exec('zip -'.(string)$opts.' '.escapeshellarg($destination).' '.escapeshellarg($source));
  } else {
    $tmp = tempnam('/var/tmp', 'download-');
    if ($tmp === false || !copy($source, $tmp)) {
      if ($tmp !== false) @unlink($tmp);
      break;
    }
    exec('zip -'.(string)$opts.' '.escapeshellarg($destination).' '.escapeshellarg($tmp));
    @unlink($tmp);
  }
  echo "/$file";
  break;
case 'delete':
  if (validpath($file) && is_file("$docroot/$file")) unlink("$docroot/$file");
  break;
case 'diag':
  if (!validpath($file)) break;
  $anon = empty($_POST['anonymize']) ? '' : escapeshellarg($_POST['anonymize']);
  exec("nohup diagnostics $anon ".escapeshellarg("$docroot/$file")." 1>/dev/null 2>&1 &");
  echo "/$file";
  break;
case 'unlink':
  if (!validpath($file)) break;
  if ($backup = readlink("$docroot/$file")) unlink($backup);
  @unlink("$docroot/$file");
  break;
}
?>
