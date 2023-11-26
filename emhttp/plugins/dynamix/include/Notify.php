<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2012, Andrew Hamer-Adams, http://www.pixeleyes.co.nz.
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

$notify = "$docroot/webGui/scripts/notify";

switch ($_POST['cmd']??'') {
case 'init':
  shell_exec("$notify init");
  break;
case 'smtp-init':
  shell_exec("$notify smtp-init");
  break;
case 'cron-init':
  shell_exec("$notify cron-init");
  break;
case 'add':
  foreach ($_POST as $option => $value) {
    switch ($option) {
    case 'e':
    case 's':
    case 'd':
    case 'i':
    case 'm':
      $notify .= " -{$option} ".escapeshellarg($value);
      break;
    case 'x':
    case 't':
      $notify .= " -{$option}";
      break;
    }
  }
  shell_exec("$notify add");
  break;
case 'get':
  echo shell_exec("$notify get");
  break;
case 'hide':
  $file = $_POST['file']??'';
  if (file_exists($file) && $file==realpath($file) && pathinfo($file,PATHINFO_EXTENSION)=='notify') chmod($file,0400);
  break;
case 'archive':
  $file = $_POST['file']??'';
  if ($file && strpos($file,'/')===false) shell_exec("$notify archive ".escapeshellarg($file));
  break;
}
?>
