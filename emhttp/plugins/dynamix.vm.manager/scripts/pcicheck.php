#!/usr/bin/php
<?php
/* Copyright 2005-2024, Lime Technology
 * Copyright 2024, Simon Fairweather
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

# Command for bash script  /usr/libexec/virtiofsd
# eval exec /usr/bin/virtiofsd $(/usr/local/emhttp/plugins/dynamix.vm.manager/scripts/virtiofsd.php "$@")

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/SriovHelpers.php";


$pci_device_changes = comparePCIData();
$sriov = json_decode(getSriovInfoJson(true), true);
$pcierror = false;
$srioverror = false;
$pci_addresses = [];
foreach ($argv as $arg) {
if (preg_match('/"host"\s*:\s*"([^"]+)"/', $arg, $matches)) {
    $pci_addresses[] = $matches[1];
    }
}
foreach($pci_addresses as $pciid) {
if (isset($pci_device_changes[$pciid])) {
    $pcierror = true;
    }
    // Check if device is an SR-IOV PF with VFs defined
    $check_id = $pciid;
    if (!preg_match('/^[0-9a-fA-F]{4}:/', $check_id)) {
        $check_id = "0000:" . $check_id;
    }
    if (isset($sriov[$check_id]) && !empty($sriov[$check_id]['vfs'])) {
        $srioverror = true;
    }
}
if ($pcierror) { echo $pcierror == true ? "yes" : "no"; exit; }
if ($srioverror) { echo $srioverror == true ? "sriov" : "no"; exit; }
echo "no";
?>