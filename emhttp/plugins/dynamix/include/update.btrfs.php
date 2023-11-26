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

[$job, $cmd] = explode(';',$_POST['#job']);
$valid = "$docroot/plugins/dynamix/scripts/";
if ($_POST['mode']>0 && substr($cmd,0,strlen($valid))==$valid) {
  $hour = isset($_POST['hour']) ? $_POST['hour'] : '*';
  $min  = isset($_POST['min'])  ? $_POST['min']  : '*';
  $dotm = isset($_POST['dotm']) ? $_POST['dotm'] : '*';
  $day  = isset($_POST['day'])  ? $_POST['day']  : '*';
  $cron = "# Generated btrfs ".str_replace('_',' ',$job)." schedule:\n$min $hour $dotm * $day $cmd &> /dev/null\n\n";
} else {
  $cron = "";
}
parse_cron_cfg('dynamix', $job, $cron);
?>
