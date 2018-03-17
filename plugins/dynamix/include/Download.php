<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
$file = $_POST['file'];
switch ($_POST['cmd']) {
case 'save':
  if (is_file("$docroot/$file") && strpos(realpath("$docroot/$file"), $docroot.'/') !== 0) exit;
  $source = $_POST['source'];
  if (pathinfo($source, PATHINFO_EXTENSION) == 'txt') {
    exec("zip -qlj ".escapeshellarg("$docroot/$file")." ".escapeshellarg($source));
  } else {
    $tmp = "/var/tmp/".basename($source).".txt";
    copy($source, $tmp);
    exec("zip -qlj ".escapeshellarg("$docroot/$file")." ".escapeshellarg($tmp));
    @unlink($tmp);
  }
  echo "/$file";
  break;
case 'delete':
  if (strpos(realpath("$docroot/$file"), $docroot.'/') === 0) @unlink("$docroot/$file");
  break;
case 'diag':
  if (is_file("$docroot/$file") && strpos(realpath("$docroot/$file"), $docroot.'/') !== 0) exit;
  $anon = empty($_POST['anonymize']) ? '' : escapeshellarg($_POST['anonymize']);
  exec("$docroot/webGui/scripts/diagnostics $anon ".escapeshellarg("$docroot/$file"));
  echo "/$file";
  break;
case 'unlink':
  $backup = readlink("$docroot/$file");
  exec("rm -f '$docroot/$file' '$backup'");
  break;
case 'backup':
  echo exec("$docroot/webGui/scripts/flash_backup");
  break;
}  
?>
