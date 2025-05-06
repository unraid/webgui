<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 * Copyright 2012-2025, Simon Fairweather.
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
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";


function process_action($pciaddr,$action)
{
  global $saved,$current;
  switch ($action) {
    case 'removed':
      unset($saved[$pciaddr]);
      break;
    case 'changed':
    case 'added':
      $saved[$pciaddr] = $current[$pciaddr];
      break;
    }
}

function build_pci_vm_map() {
  global $lv;
  $pci_device_changes = comparePCIData();
  $vms = $lv->get_domains();
  foreach ($vms as $vm) {
    $vmpciids = $lv->domain_get_vm_pciids($vm);
    foreach($vmpciids as $pciid => $pcidetail) {
      if (isset($pci_device_changes["0000:".$pciid])) {
        $pcitovm["0000:".$pciid][$vm] = $vm;
      }
    }
  }
  return $pcitovm;
}

$savedfile = "/boot/config/savedpcidata.json";
$saved = loadSavedData($savedfile);
if (!$saved) {echo "ERROR"; return;};
$current = loadCurrentPCIData();
$pciaddr = $_POST['pciid'];
$action = $_POST['action']??'';

switch($action) {
case "all":
  $pciaddrs = explode(";", $pciaddr);
  foreach ($pciaddrs as $pciaddraction){
    if ($pciaddraction == "") continue;
    $values = explode(',',$pciaddraction);
    process_action($values[0],$values[1]);
  }
  file_put_contents($savedfile,json_encode($saved,JSON_PRETTY_PRINT));
  break;
case "removed":
case "added":
case "changed":
    process_action($pciaddr,$action);
    file_put_contents($savedfile,json_encode($saved,JSON_PRETTY_PRINT));
    break;
case "getvm":
    $pcimap = build_pci_vm_map();
    $pciaddrs = explode(";", $pciaddr);
    $vmact =[];

    foreach ($pciaddrs as $pcidev) {
      if ($pcidev == "") continue;
      if (strpos($pcidev,",")) {
        $values = explode(',',$pcidev);
        $pcidev = $values[0];
      }    
      foreach ($pcimap[$pcidev] as $key => $vmname) {
        $vmact[$vmname]= $vmname; 
      }
    }
    $ret = implode(";",$vmact);

    echo $ret;
    exit;

}

file_put_contents($savedfile,json_encode($saved,JSON_PRETTY_PRINT));
echo "OK";
?>
