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

function stripVFPrefix($str)
{
    return preg_replace('/^VFSETTINGS=/','', trim($str));
}

function parseVF($str)
{
    $str = stripVFPrefix($str);
    if ($str === '') return [];

    $blocks = preg_split('/\s+/', trim($str));
    $result = [];

    foreach ($blocks as $block) {
        if ($block === '') continue;

        $parts = explode('|', $block);

        // Normalize fields: pci|vd|fn|mac
        $pci = $parts[0] ?? '';
        $vd  = $parts[1] ?? '';
        $fn  = $parts[2] ?? '';
        $mac = $parts[3] ?? '';

        // Unique key: pci + vendor:device
        $key = $pci . '|' . $vd;
        $result[$key] = [$fn, $mac];
    }

    return $result;
}

function isValidVF($fields)
{
    list($fn, $mac) = $fields;
    $mac = strtolower(trim($mac));

    // Common empty MAC
    $isZeroMac = ($mac === '' || $mac === '00:00:00:00:00:00');

    // Rule fixes:
    // - fn=0 is allowed even if empty MAC
    // - fn=1 must always be accepted
    // - fn>1 always accepted
    if ($fn === '0') return true;
    if ($fn === '1') return true;
    if (intval($fn) > 1) return true;

    // fallback: require some MAC
    return !$isZeroMac;
}

function updateVFSettings($input, $saved)
{
    $inputParsed = parseVF($input);
    $savedParsed = parseVF($saved);

    $updated = [];

    // Update existing entries
    foreach ($savedParsed as $key => $oldFields) {
        if (isset($inputParsed[$key]) && isValidVF($inputParsed[$key])) {
            $updated[$key] = $inputParsed[$key];
        }
    }

    // Add new entries not in saved
    foreach ($inputParsed as $key => $fields) {
        if (!isset($savedParsed[$key]) && isValidVF($fields)) {
            $updated[$key] = $fields;
        }
    }

    // Reassemble output
    $result = [];

    foreach ($updated as $key => $fields) {
        list($pci, $vd) = explode('|', $key);
        list($fn, $mac) = $fields;

        // Normalize MAC for fn=1 if empty
        if ($fn === '1' && trim($mac) === '') {
            $mac = '00:00:00:00:00:00';
        }

        $result[] = "$pci|$vd|$fn|$mac";
    }

    if (empty($result)) {
        return "";
    }

    return "VFSETTINGS=" . implode(' ', $result);
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

if ($oldvfcfg_updated != $oldvfcfg) {
  if ($oldvfcfg) copy($sriovvfs,"$sriovvfs.bak");
  if ($oldvfcfg_updated) file_put_contents($sriovvfs,$oldvfcfg_updated); else @unlink($sriovvfs);
  $reply |= 2;  
}

echo $reply;
?>
