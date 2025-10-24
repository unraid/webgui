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

$cmd  = $_POST['cmd'];
$path = escapeshellarg($_POST['path']);

function btrfs($data) {return "btrfs-$data";}
function zfs($data) {return "zfs-".strtok($data,' ');}

switch ($cmd) {
case 'status':
  exec("ps -C btrfs -o cmd=|awk '/$path\$/{print $2}'",$btrfs);
  exec("/usr/sbin/zpool status $path|grep -Po '(scrub|resilver|expansion of \S+) in progress'",$zfs);
  echo implode(',',array_merge(array_map('btrfs',$btrfs),array_map('zfs',$zfs)));
  break;
case 'btrfs-balance':
case 'btrfs-scrub':
  $cmd = explode('-',$cmd)[1];
  echo shell_exec("/sbin/btrfs $cmd status $path");
  break;
case 'zfs-scrub':
case 'zfs-resilver':
case 'zfs-expansion':
  echo shell_exec("/usr/sbin/zpool status -P $path");
  break;
default:
  switch ($cmd) {
    case 'btrfs-check':
    case 'xfs-check':
    case 'ext-check':
    case 'ntfs-check':
    case 'exfat-check':
    case 'reiserfs-check':
      $fs = explode('-',$cmd)[0];
      [$dev,$id] = array_pad(explode(' ',trim($path,"'")),2,'');
      passthru("$docroot/webGui/scripts/{$fs}_check status $dev $id", $retval);
      if ($retval != 9) echo "\0";
  }
}
?>
