<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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

/* ============================================================
 * VFIO CONFIG
 * Format: BIND=pci|vendor pci|vendor ...
 * Input : BIND=pci|vendor|1 pci|vendor|0 ...
 * ============================================================ */

function parseVFIO($str) {
    if (!preg_match('/^BIND=(.*)$/', trim($str), $m)) return [];
    $out = [];
    foreach (preg_split('/\s+/', trim($m[1])) as $e) {
        if ($e === '') continue;
        [$pci, $vd] = array_pad(explode('|', $e, 2), 2, '');
        if ($pci && $vd) $out[$pci] = $vd;
    }
    return $out;
}

function parseVFIOInput($str) {
    if (!preg_match('/^BIND=(.*)$/', trim($str), $m)) return [];
    $out = [];
    foreach (preg_split('/\s+/', trim($m[1])) as $e) {
        if ($e === '') continue;
        [$pci, $vd, $req] = array_pad(explode('|', $e, 3), 3, '1');
        if ($pci && $vd && ($req === '0' || $req === '1')) {
            $out[$pci] = [$vd, $req];
        }
    }
    return $out;
}

function updateVFIO($input, $saved) {
    $existing  = parseVFIO($saved);
    $requested = parseVFIOInput($input);

    foreach ($requested as $pci => [$vd, $req]) {
        if ($req === '1') {
            $existing[$pci] = $vd;
        } else {
            unset($existing[$pci]);
        }
    }

    if (empty($existing)) return '';

    ksort($existing, SORT_NATURAL);
    $out = [];
    foreach ($existing as $pci => $vd) $out[] = "$pci|$vd";

    return 'BIND=' . implode(' ', $out);
}

/* ============================================================
 * SR-IOV CONFIG
 * Format: VFSETTINGS=pci|vd|fn|mac ...
 * ============================================================ */

function stripVFPrefix($str) {
    return preg_replace('/^VFSETTINGS=/', '', trim($str));
}

function parseVF($str) {
    $str = stripVFPrefix($str);
    if ($str === '') return [];
    $out = [];
    foreach (preg_split('/\s+/', $str) as $b) {
        if ($b === '') continue;
        [$pci, $vd, $fn, $mac] = array_pad(explode('|', $b), 4, '');
        if ($pci && $vd) $out["$pci|$vd"] = [$fn, $mac];
    }
    return $out;
}

function isValidVF($fields) {
    [$fn, $mac] = $fields;
    $mac = strtolower(trim($mac));

    if ($fn === '0' || $fn === '1') return true;
    if (intval($fn) > 1) return true;

    return ($mac !== '' && $mac !== '00:00:00:00:00:00');
}

function updateVFSettings($input, $saved) {
    $inputParsed = parseVF($input);
    $savedParsed = parseVF($saved);

    foreach ($inputParsed as $key => $fields) {
        if (isValidVF($fields)) {
            $savedParsed[$key] = $fields;
        }
    }

    if (empty($savedParsed)) return '';

    ksort($savedParsed, SORT_NATURAL);
    $out = [];
    foreach ($savedParsed as $key => [$fn, $mac]) {
        [$pci, $vd] = explode('|', $key);
        if ($fn === '1' && trim($mac) === '') {
            $mac = '00:00:00:00:00:00';
        }
        $out[] = "$pci|$vd|$fn|$mac";
    }

    return 'VFSETTINGS=' . implode(' ', $out);
}

function normalizeVFIO($str) {
    $parsed = parseVFIO($str);
    ksort($parsed, SORT_NATURAL);
    return $parsed;
}

/* ============================================================
 * FILE PATHS
 * ============================================================ */

$vfio     = '/boot/config/vfio-pci.cfg';
$sriovvfs = '/boot/config/sriovvfs.cfg';
$reply    = 0;

/* ================= VFIO ================= */

$old_vfio = is_file($vfio) ? rtrim(file_get_contents($vfio)) : '';
$new_vfio = _var($_POST, 'cfg');
$merged_vfio = updateVFIO($new_vfio, $old_vfio);

if ($merged_vfio !== $old_vfio) {
    if ($old_vfio) copy($vfio, "$vfio.bak");
    if ($merged_vfio) file_put_contents($vfio, $merged_vfio);
    else @unlink($vfio);
}

/* Reply bit 1: differs from boot */
$boot_vfio_file = dirname($vfio) . '/vfio-pci.boot';
$boot_vfio_raw  = is_file($boot_vfio_file) ? rtrim(file_get_contents($boot_vfio_file)) : '';

$norm_merged = normalizeVFIO($merged_vfio);
$norm_boot   = normalizeVFIO($boot_vfio_raw);

if ($norm_merged !== $norm_boot) {
    $reply |= 1;
}

/* ================= SR-IOV ================= */

$old_vfcfg = is_file($sriovvfs) ? rtrim(file_get_contents($sriovvfs)) : '';
$new_vfcfg = _var($_POST, 'vfcfg');
$merged_vfcfg = updateVFSettings($new_vfcfg, $old_vfcfg);

if ($merged_vfcfg !== $old_vfcfg) {
    if ($old_vfcfg) copy($sriovvfs, "$sriovvfs.bak");
    if ($merged_vfcfg) file_put_contents($sriovvfs, $merged_vfcfg);
    else @unlink($sriovvfs);

    /* Reply bit 2: changed from previous version */
    $reply |= 2;
}

echo $reply;
?>
