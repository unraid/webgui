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
extract(parse_plugin_cfg('dynamix',true));

$path    = _var($notify,'path','/tmp/notifications');
$unread  = "$path/unread/";
$archive = "$path/archive";
$log     = $_POST['log']??'';
$filter  = $_POST['filter']??false;
$files   = strpos($log,'*')===false ? [realpath("$archive/$log")] : glob("$archive/$log",GLOB_NOSORT);

function delete_file(...$file) {
  array_map('unlink',array_filter($file,'file_exists'));
}

foreach ($files as $file) {
  // check file path
  if (strncmp($file,$archive,strlen($archive))!==0) continue;
  $list = $unread.basename($file);
  if (!$filter) {
    // delete all files
    delete_file($file,$list);
  } else {
    // delete selective files
    if (exec("grep -om1 'importance=$filter' ".escapeshellarg($file))) delete_file($file,$list);
  }
}
?>
