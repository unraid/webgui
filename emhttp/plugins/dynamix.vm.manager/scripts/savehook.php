#!/usr/bin/env php
<?php
$cfg = "/boot/config/plugins/dynamix.vm.manager/vms.json";

if (!file_exists($cfg)) {
    error_log("savehook: Configuration file not found: $cfg");
    exit(1);
}

$json = file_get_contents($cfg);
if ($json === false) {
    error_log("savehook: Failed to read configuration file: $cfg");
    exit(1);
}

$vms = json_decode($json, true);
if ($vms === null && json_last_error() !== JSON_ERROR_NONE) {
    error_log("savehook: Invalid JSON in configuration file: " . json_last_error_msg());
    exit(1);
}
if ($argv[2] == 'stopped'){
    $vm = $argv[1];
    $from_file = "/etc/libvirt/qemu/$vm.xml";
    $to_file = $vms[$argv[1]]['path']."/$vm.xml";
    #echo " from:$from_file     to:$to_file";
    copy($from_file,$to_file);
}
?>
