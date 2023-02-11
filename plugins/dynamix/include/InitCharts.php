<?PHP
/* Copyright 2005-2022, Lime Technology
 * Copyright 2012-2022, Bergware International.
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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$charts  = '/var/tmp/charts_data.tmp';

switch ($_POST['cmd']) {
case 'get':
  echo @file_get_contents($charts) ?: '{"cpu":"","rxd":"","txd":""}';
  break;
case 'set':
  file_put_contents($charts,$_POST['data']);
  break;
}
?>
