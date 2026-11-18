<?PHP
/* This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
/* Lists the long-running jobs that a Reboot, Shutdown or array Stop would interrupt.
 * Output: JSON array of {type, pool, action}; an empty array means nothing is running.
 *   type: parity | mover | btrfs | zfs
 *   pool: mount name below /mnt (btrfs, zfs), else ''
 *   action: parity: mdResyncAction (check, recon, ...); btrfs: scrub | balance; else ''
 * This is the single list used by every GUI button that reboots, shuts down or stops the array.
 */
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";

$var  = (array)@parse_ini_file('/var/local/emhttp/var.ini');
$jobs = [];

// parity check, parity sync, disk rebuild
if (_var($var,'fsState') == 'Started' && _var($var,'mdResync',0) > 0) {
  $jobs[] = ['type'=>'parity', 'pool'=>'', 'action'=>_var($var,'mdResyncAction')];
}

// mover
$pid = (int)@file_get_contents('/var/run/mover.pid');
if ($pid > 0 && preg_match('/(^|\s)([^\s]*\/)?mover(\s|$)/', str_replace("\0",' ',(string)@file_get_contents("/proc/$pid/cmdline")))) {
  $jobs[] = ['type'=>'mover', 'pool'=>'', 'action'=>''];
}

// btrfs scrub or balance, per mounted btrfs file system below /mnt
foreach (explode("\n", trim((string)shell_exec('findmnt -rn -t btrfs -o TARGET 2>/dev/null'))) as $mnt) {
  if (strpos($mnt, '/mnt/') !== 0) continue;
  $arg = escapeshellarg($mnt);
  if (preg_match('/^\s*Status:\s+running/m', (string)shell_exec("/sbin/btrfs scrub status $arg 2>/dev/null"))) {
    $jobs[] = ['type'=>'btrfs', 'pool'=>basename($mnt), 'action'=>'scrub'];
  }
  if (strpos((string)shell_exec("/sbin/btrfs balance status $arg 2>/dev/null"), 'is running') !== false) {
    $jobs[] = ['type'=>'btrfs', 'pool'=>basename($mnt), 'action'=>'balance'];
  }
}

// ZFS scrub, per pool
foreach (explode("\n", trim((string)shell_exec('/usr/sbin/zpool list -H -o name 2>/dev/null'))) as $pool) {
  if ($pool === '') continue;
  if (strpos((string)shell_exec('/usr/sbin/zpool status '.escapeshellarg($pool).' 2>/dev/null'), 'scrub in progress') !== false) {
    $jobs[] = ['type'=>'zfs', 'pool'=>$pool, 'action'=>'scrub'];
  }
}

header('Content-Type: application/json');
echo json_encode($jobs);
?>
