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
require_once "$docroot/webGui/include/Secure.php";
require_once "$docroot/webGui/include/Wrappers.php";

switch ($_POST['cmd']??'') {
case 'config':
  $config = "/boot/config";
  $files  = ['disk:0','docker:1','domain:1','flash:0','ident:1','share:0']; // config files to check
  foreach ($files as $file) {
    [$name,$need] = explode(':',$file);
    for ( $i=0;$i<2;$i++) {
      if (($need && !file_exists("$config/$name.cfg")) || (file_exists("$config/$name.cfg") && !@parse_ini_file("$config/$name.cfg"))) {
        $flag = 1;
        sleep(1);
      } else {
        $flag = 0;
        break;
      }
    }
    if ($flag) break;
  }
  echo $flag;
  break;
case 'notice':
  $tmp = "/tmp/reboot_notifications";
  $notices = file_exists($tmp) ? file($tmp,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : [];
  echo implode("\n",array_map('unbundle',$notices));
  break;
case 'state':
  $pools = explode(',',_var($_POST,'pools'));
  $disks = (array)@parse_ini_file('state/disks.ini',true);
  $error = [];
  foreach ($pools as $pool) if (stripos(_var($disks[$pool],'state'),'ERROR:')===0) $error[] = $pool.' - '.str_ireplace('ERROR:','',$disks[$pool]['state']);
  echo implode('<br>',$error);
  break;
}
?>
