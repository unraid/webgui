<?PHP
/* Copyright 2005-2016, Lime Technology
 * Copyright 2012-2016, Bergware International.
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
#load emhttp variables if needed.
$docroot = $docroot ?: @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
if (!isset($var)) {
  if (!is_file("$docroot/state/var.ini")) shell_exec("wget -qO /dev/null localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')");
  $var = @parse_ini_file("$docroot/state/var.ini");
}
echo $var['csrf_token'];
?>
