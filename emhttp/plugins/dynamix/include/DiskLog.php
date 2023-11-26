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
$file  = '/boot/config/disk.log';
$disks = file_exists($file) ? parse_ini_file($file,true) : [];
$disk  = $_POST['disk'];
$key   = $_POST['key'];
$value = $_POST['value'];
$text  = '';

$disks[$disk][$key] = $value;
foreach ($disks as $disk => $block) {
  $text .= "[$disk]\n";
  foreach ($block as $key => $value) $text .= "$key=\"$value\"\n";
}
file_put_contents($file,$text);
?>