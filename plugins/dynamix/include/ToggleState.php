<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
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
$device = $_POST['device'];
$name   = $_POST['name'];
$action = $_POST['action'];
$state  = $_POST['state'];
$csrf   = $_POST['csrf'];

function prefix($key) {
  return preg_replace('/\d+$/','',$key);
}
function emhttpd($cmd) {
  global $state, $csrf;
  $ch = curl_init("http://127.0.0.1/update");
  $options = array(CURLOPT_UNIX_SOCKET_PATH => '/var/run/emhttpd.socket',
                   CURLOPT_POST => 1,
                   CURLOPT_POSTFIELDS => "$cmd&startState=$state&csrf_token=$csrf");
  curl_setopt_array($ch, $options);
  curl_exec($ch);
  curl_close($ch);
}

switch ($device) {
case 'New':
  emhttpd("cmdSpin$action=$name");
  break;
case 'Clear':
  emhttpd("clearStatistics=true");
  break;
default:
  if (!$name) {
    // spin up/down all devices
    emhttpd("cmdSpin{$device}All=true");
    break;
  }
  if (substr($name,-1) != '*') {
    // spin up/down single device
    emhttpd("cmdSpin$action=$name");
    break;
  }
  // spin up/down group of devices
  $disks = (array)parse_ini_file('state/disks.ini',true);
  $name = substr($name,0,-1);
  foreach ($disks as $disk) {
    if ($disk['status'] != 'DISK_OK') continue;
    $array = ($name=='array' && in_array($disk['type'],['Parity','Data']));
    if ($array || prefix($disk['name'])==$name) emhttpd("cmdSpin$action={$disk['name']}");
  }
  break;
}
?>
