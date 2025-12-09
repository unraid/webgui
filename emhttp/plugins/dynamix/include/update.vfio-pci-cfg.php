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

function parseVF($str)
{
    $blocks = preg_split('/\s+/', trim($str));
    $result = [];
    foreach ($blocks as $block) {
        if ($block === '') continue;
        $parts = explode('|', $block);
        for ($i = 0; $i < 4; $i++) if (!isset($parts[$i])) $parts[$i] = '';
        $key = $parts[0] . '|' . $parts[1];
        $result[$key] = [$parts[2], $parts[3]];
    }
    return $result;
}

function isValidVF($fields)
{
    list($fn, $mac) = $fields;
    $mac = strtolower(trim($mac));
    $isZeroMac = ($mac === '00:00:00:00:00:00');
    $hasMac = ($mac !== '' && !$isZeroMac);
    if ($fn === 1) return true;
    if ($fn > 1) return true;
    if ($fn === 0) return $hasMac;
    return $hasMac;
}

function updateVFSettings($input, $saved)
{
    $inputParsed = parseVF($input);
    $savedParsed = parseVF($saved);
    $updated = [];
    foreach ($savedParsed as $key => $_) {
        if (isset($inputParsed[$key]) && isValidVF($inputParsed[$key])) $updated[$key] = $inputParsed[$key];
    }
    foreach ($inputParsed as $key => $fields) {
        if (!isset($savedParsed[$key]) && isValidVF($fields)) $updated[$key] = $fields;
    }
    $result = [];
    foreach ($savedParsed as $key => $_) {
        if (!isset($updated[$key])) continue;
        list($pci,$vd) = explode('|',$key);
        list($fn,$mac) = $updated[$key];
        if ($fn === '1' && ($mac === '' || $mac === null)) $mac = '00:00:00:00:00:00';
        $result[] = "$pci|$vd|$fn|$mac";
    }
    foreach ($inputParsed as $key => $_) {
        if (isset($savedParsed[$key])) continue;
        if (!isset($updated[$key])) continue;
        list($pci,$vd) = explode('|',$key);
        list($fn,$mac) = $updated[$key];
        if ($fn === '1' && ($mac === '' || $mac === null)) $mac = '00:00:00:00:00:00';
        $result[] = "$pci|$vd|$fn|$mac";
    }
    return implode(' ', $result);
}


$vfio = '/boot/config/vfio-pci.cfg';
$sriovvfs = '/boot/config/sriovvfs.cfg';

#Save Normal VFIOs
$old  = is_file($vfio) ? rtrim(file_get_contents($vfio)) : '';
$new  = _var($_POST,'cfg');

$reply = 0;
if ($new != $old) {
  if ($old) copy($vfio,"$vfio.bak");
  if ($new) file_put_contents($vfio,$new); else @unlink($vfio);
  $reply |= 1;  
}

#Save SRIOV VFS
$oldvfcfg  = is_file($sriovvfs) ? rtrim(file_get_contents($sriovvfs)) : '';
$newvfcfg  = _var($_POST,'vfcfg');
$oldvfcfg_updated = updateVFSettings($newvfcfg,$oldvfcfg);
if (strpos($oldvfcfg_updated,"VFSETTINGS=") !== 0 && $oldvfcfg_updated != "") $oldvfcfg_updated = "VFSETTINGS=".$oldvfcfg_updated;

#file_put_contents("/tmp/updatevfs",[json_encode($oldvfcfg_updated),json_encode($oldvfcfg)]);
if ($oldvfcfg_updated != $oldvfcfg) {
  if ($oldvfcfg) copy($sriovvfs,"$sriovvfs.bak");
  if ($oldvfcfg_updated) file_put_contents($sriovvfs,$oldvfcfg_updated); else @unlink($sriovvfs);
  $reply |= 2;  
}

echo $reply;
?>
