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
require_once "$docroot/webGui/include/Wrappers.php";

$device = $_POST['device']??'';
$name   = $_POST['name']??'';
$action = $_POST['action']??'';

function emcmd($cmd) {
  exec("emcmd '$cmd'");
}
switch ($device) {
case 'New':
  // unassigned device
  emcmd("cmdSpin{$action}={$name}");
  break;
case 'Clear':
  emcmd("clearStatistics=true");
  break;
default:
  if (!$name) {
    // spin up/down all devices
    emcmd("cmdSpin{$device}All=Apply");
    break;
  }
  if (substr($name,-1) != '*') {
    // spin up/down single device
    emcmd("cmdSpin{$action}={$name}");
    break;
  }
  // spin up/down group of devices
  $name = substr($name,0,-1); // remove trailing '*' from name
  emcmd("cmdSpin{$action}All=Apply&poolName={$name}");
  break;
}
?>
