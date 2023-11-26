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

// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";

$save   = false;
$disks  = parse_ini_file('state/disks.ini',true);
$newkey = parse_ini_file('state/var.ini')['luksKeyfile'] ?: '/root/keyfile';
$oldkey = dirname($newkey).'/oldfile';
$delkey = !is_file($newkey);
$crypto = [];

foreach (glob('/dev/disk/by-id/*CRYPT-LUKS*',GLOB_NOSORT) as $disk) {
  $disk = explode('-',$disk);
  $crypto[] = array_pop($disk);
}
if (count($crypto)==0) die();

function delete_file(...$file) {
  array_map('unlink',array_filter($file,'is_file'));
}
function removeKey($key,$disk) {
  $match = $slots = 0;
  $dump = popen("cryptsetup luksDump /dev/$disk",'r');
  while (($row = fgets($dump))!==false) {
    if (strncmp($row,'Version:',8)==0) {
      switch (trim(explode(':',$row)[1])) {
        case 1: $match = '/^Key Slot \d+: ENABLED$/'; break;
        case 2: $match = '/^\s+\d+: luks2$/'; break;
      }
    }
    if ($match && preg_match($match,$row)) $slots++;
  }
  pclose($dump);
  if ($slots > 1) exec("cryptsetup luksRemoveKey /dev/$disk $key &>/dev/null");
}
function diskname($name) {
  global $disks;
  foreach ($disks as $disk) if (strncmp($name,$disk['device'],strlen($disk['device']))==0) return $disk['name'];
  return $name;
}
function reply($text,$type) {
  global $oldkey,$newkey,$delkey;
  $reply = _var($_POST,'#reply');
  if (realpath(dirname($reply))=='/var/tmp') file_put_contents($reply,$text."\0".$type);
  delete_file($oldkey);
  if (_var($_POST,'newinput','text')=='text' || $delkey) delete_file($newkey);
  die();
}

if (isset($_POST['oldinput'])) {
  switch ($_POST['oldinput']) {
  case 'text':
    file_put_contents($oldkey,base64_decode(_var($_POST,'oldluks')));
    break;
  case 'file':
    file_put_contents($oldkey,base64_decode(explode(';base64,',_var($_POST,'olddata','x;base64,'))[1]));
    break;
  }
} else {
  if (is_file($newkey)) copy($newkey,$oldkey);
}

if (is_file($oldkey)) {
  $disk = $crypto[0]; // check first disk only (key is the same for all disks)
  exec("cryptsetup luksOpen --test-passphrase --key-file $oldkey /dev/$disk &>/dev/null",$null,$error);
} else $error = 1;

if ($error > 0) reply(_('Incorrect existing key'),'warning');

if (isset($_POST['newinput'])) {
  switch ($_POST['newinput']) {
  case 'text':
    file_put_contents($newkey,base64_decode(_var($_POST,'newluks')));
    $luks = 'luksKey';
    $data = _var($_POST,'newluks');
    break;
  case 'file':
    file_put_contents($newkey,base64_decode(explode(';base64,',_var($_POST,'newdata','x;base64,'))[1]));
    $luks = 'luksKey=&luksKeyfile';
    $data = $newkey;
    break;
  }
  $good = $bad = [];
  foreach ($crypto as $disk) {
    exec("cryptsetup luksAddKey --key-file $oldkey /dev/$disk $newkey &>/dev/null",$null,$error);
    if ($error==0) $good[] = $disk; else $bad[] = diskname($disk);
  }
  if (count($bad)==0) {
    // all okay, remove the old key
    foreach ($good as $disk) removeKey($oldkey,$disk);
    exec("emcmd 'changeDisk=apply&$luks=$data'");
    reply(_('Key successfully changed'),'success');
  } else {
    // something went wrong, restore key
    foreach ($good as $disk) removeKey($newkey,$disk);
    reply(_('Changing key failed for disks').': '.implode(' ',$bad),'error');
  }
}
reply(_('Missing new key'),'warning');
?>
