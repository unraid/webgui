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
$cfg = '/boot/config/network-rules.cfg';
foreach ($_POST as $name => $mac) {
  if (!preg_match('/^eth\d+$/', (string)$name)) continue;
  if (!preg_match('/^[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}$/', (string)$mac)) continue;
  $row = exec('grep -n -- '.escapeshellarg($mac).' '.escapeshellarg($cfg).'|cut -d: -f1');
  if (ctype_digit((string)$row) && (int)$row > 0) {
    $expression = (int)$row.'s/(NAME=")[^\"]+/\\1'.$name.'/';
    exec('sed -ri '.escapeshellarg($expression).' '.escapeshellarg($cfg));
  }
}
touch('/tmp/network-rules.tmp');
$save = false;
?>
