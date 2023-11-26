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
// Merge SMART settings
$smartALL = '/boot/config/smart-all.cfg';
if (file_exists($smartALL)) $var = array_merge($var, parse_ini_file($smartALL));

$smartONE = '/boot/config/smart-one.cfg';
if (file_exists($smartONE)) {
  $smarts = parse_ini_file($smartONE,true);
  foreach ($smarts as $id => $smart) {
    if (isset($disks)) {
      foreach ($disks as $key => $disk) {
        if ($disk['id'] == $id) $disks[$key] = array_merge($disks[$key], $smart);
      }
    }
    if (isset($devs)) {
      foreach ($devs as $key => $disk) {
        if ($disk['id'] == $id) $devs[$key] = array_merge($devs[$key], $smart);
      }
    }
  }
}
?>
