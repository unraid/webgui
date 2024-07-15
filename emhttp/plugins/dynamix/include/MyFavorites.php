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
$permit = ['del','add'];
$action = $_POST['action']??'';
$cfg = '/boot/config/favorites.cfg';

// remove non-existing pages
if ($action=='clear') {
  if (file_exists($cfg)) foreach (file($cfg,FILE_IGNORE_NEW_LINES) as $page) {
    if (!file_exists($page)) {
      $page = str_replace('/','\/',$page);
      exec("sed -i '/$page/d' $cfg 2>/dev/null");
    }
  }
  exit;
}

// validate input
$page = glob("$docroot/plugins/*/{$_POST['page']}.page",GLOB_NOSORT)[0];
if (!$page || !in_array($action,$permit)) exit;

$file = fopen($page,'r');
// get current Menu settings
extract(parse_ini_string(fgets($file)));
fclose($file);

// remove label and escape single quotes for sed command
$Menu = str_replace([' MyFavorites',"'"],['',"'\''"],$Menu);
switch ($action) {
case $permit[0]: // del
  $del = str_replace('/','\/',$page);
  exec("sed -i '/$del/d' $cfg 2>/dev/null");
  break;
case $permit[1]: // add
  $file = fopen($cfg,'a+');
  fseek($file,0,0);
  while (($line = fgets($file))!==false) {
    if (rtrim($line) == $page) break;
  }
  if (feof($file)) fwrite($file, $page."\n");
  fclose($file);
  $Menu .= ' MyFavorites';
  break;
}
// update Menu settings
exec("sed -ri '0,/^Menu=\".+\"$/s//Menu=\"$Menu\"/' $page 2>/dev/null");
