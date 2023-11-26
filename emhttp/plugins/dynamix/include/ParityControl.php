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
$stamps = '/var/tmp/stamps.ini';

switch ($_POST['action']) {
case 'pause':
  if (!file_exists($stamps)) file_put_contents($stamps,parse_ini_file('/var/local/emhttp/var.ini')['sbSynced']);
case 'resume':
  file_put_contents($stamps,','.time(),FILE_APPEND);
  break;
}
?>
