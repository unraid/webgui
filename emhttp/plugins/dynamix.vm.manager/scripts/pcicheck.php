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
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

$pci_device_changes = comparePCIData();
$pcierror = false;
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
}

echo $pcierror == true ? "yes" : "no";
?>