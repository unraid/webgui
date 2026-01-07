#!/usr/bin/env php
<?php
$cfg = "/boot/config/plugins/dynamix.vm.manager/vms.json";
$vms = json_decode(file_get_contents($cfg),true);
if ($argv[2] == 'stopped'){
    $vm = $argv[1];
    $from_file = "/etc/libvirt/qemu/$vm.xml";
    $to_file = $vms[$argv[1]]['path']."/$vm.xml";
    #echo " from:$from_file     to:$to_file";
    copy($from_file,$to_file);
}
?>
