<?PHP
/* Copyright 2005-2025, Lime Technology
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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

$charts = '/var/tmp/charts_data.tmp';
$cookie = '/boot/config/dashboard_settings.json';

switch ($_POST['cmd']) {
case 'get':
  echo @file_get_contents($charts) ?: '{"cpu":"","rxd":"","txd":""}';
  break;
case 'set':
  file_put_contents($charts,$_POST['data']);
  break;
case 'cookie':
  file_put_contents($cookie,$_POST['data']);
  break;
}
?>
