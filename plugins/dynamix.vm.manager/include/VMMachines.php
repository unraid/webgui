<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2015-2018, Derek Macias, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2018, Bergware International.
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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

$cfg = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';
$vms = $lv->get_domains();
if (empty($vms)) {
  echo '<tr><td colspan="8" style="text-align:center;padding-top:12px">No Virtual Machines installed</td></tr>';
  return;
}
if (file_exists($cfg)) {
  $prefs = parse_ini_file($cfg); $sort = [];
  foreach ($vms as $vm) $sort[] = array_search($vm,$prefs) ?? 999;
  array_multisort($sort,SORT_NUMERIC,$vms);
} else {
  natsort($vms);
}
$i = 0;
$menu = [];
$kvm = ['var kvm=[];'];
foreach ($vms as $vm) {
  $res = $lv->get_domain_by_name($vm);
  $desc = $lv->domain_get_description($res);
  $uuid = $lv->domain_get_uuid($res);
  $dom = $lv->domain_get_info($res);
  $id = $lv->domain_get_id($res) ?: '-';
  $is_autostart = $lv->domain_get_autostart($res);
  $state = $lv->domain_state_translate($dom['state']);
  $vmicon = $lv->domain_get_icon_url($res);
  $arrConfig = domain_to_config($uuid);
  if ($state == 'running') {
    $mem = $dom['memory'] / 1024;
  } else {
    $mem = $lv->domain_get_memory($res) / 1024;
  }
  $mem = round($mem).'M';
  $vcpu = $dom['nrVirtCpu'];
  $auto = $is_autostart ? 'checked':'';
  $template = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
  if (empty($template)) $template = 'Custom';
  $log = (is_file("/var/log/libvirt/qemu/$vm.log") ? "libvirt/qemu/$vm.log" : '');
  $disks = '-';
  $diskdesc = '';
  if (($diskcnt = $lv->get_disk_count($res)) > 0) {
    $disks = $diskcnt.' / '.$lv->get_disk_capacity($res);
    $diskdesc = 'Current physical size: '.$lv->get_disk_capacity($res, true);
  }
  $arrValidDiskBuses = getValidDiskBuses();
  $vncport = $lv->domain_get_vnc_port($res);
  $vnc = '';
  $graphics = '';
  if ($vncport > 0) {
    $wsport = $lv->domain_get_ws_port($res);
    $vnc = '/plugins/dynamix.vm.manager/vnc.html?autoconnect=true&host=' . $_SERVER['HTTP_HOST'] . '&port=&path=/wsproxy/' . $wsport . '/';
    $graphics = 'VNC:'.$vncport;
  } elseif ($vncport == -1) {
    $graphics = 'VNC:auto';
  } elseif (!empty($arrConfig['gpu'])) {
    $arrValidGPUDevices = getValidGPUDevices();
    foreach ($arrConfig['gpu'] as $arrGPU) foreach ($arrValidGPUDevices as $arrDev) {
      if ($arrGPU['id'] == $arrDev['id']) $graphics .= $arrDev['name']."\n";
    }
    $graphics = str_replace("\n", "<br>", trim($graphics));
  }
  unset($dom);
  $menu[] = sprintf("addVMContext('%s','%s','%s','%s','%s','%s');", addslashes($vm),addslashes($uuid),addslashes($template),$state,addslashes($vnc),addslashes($log));
  $kvm[] = "kvm.push({id:'$uuid',state:'$state'});";
  /* VM information */
  echo "<tr style='background-color:".bcolor($i)."'>";
  echo "<td style='width:48px;padding:4px'>".renderVMContentIcon($uuid, $vm, $vmicon, $state)."</td>";
  echo "<td class='vm-name'><a href='#' onclick='return toggle_id(\"name{$i}\")' title='click for more VM info'>$vm</a></td>";
  echo "<td>$desc</td>";
  echo "<td><a class='vcpu{$i}' style='cursor:pointer'>$vcpu</a></td>";
  echo "<td>$mem</td>";
  echo "<td title='$diskdesc'>$disks</td>";
  echo "<td>$graphics</td>";
  echo "<td><input class='autostart' type='checkbox' name='auto_{$vm}' title='Toggle VM auostart' uuid='$uuid' $auto></td>";
  echo "<td><a href='#' title='Move row up'><i class='fa fa-arrow-up up'></i></a>&nbsp;<a href='#' title='Move row down'><i class='fa fa-arrow-down down'></i></a></td></tr>";

  /* Disk device information */
  echo "<tr id='name".($i++)."' style='display:none'>";
  echo "<td colspan='7' style='overflow:hidden'>";
  echo "<table class='tablesorter domdisk' id='domdisk_table'>";
  echo "<thead><tr><th><i class='fa fa-hdd-o'></i><b> Disk devices &nbsp;</b></th><th>Bus</th><th>Capacity</th><th>Allocation</th><th>Actions</th></tr></thead>";
  echo "<tbody id='domdisk_list'>";

  /* Display VM disks */
  foreach ($lv->get_disk_stats($res) as $arrDisk) {
    $capacity = $lv->format_size($arrDisk['capacity'], 0);
    $allocation = $lv->format_size($arrDisk['allocation'], 0);
    $disk = (array_key_exists('file', $arrDisk)) ? $arrDisk['file'] : $arrDisk['partition'];
    $dev = $arrDisk['device'];
    $bus = $arrDisk['bus'];
    echo "<tr><td>$disk</td><td>{$arrValidDiskBuses[$bus]}</td>";
    if ($state == 'shutoff') {
      echo "<td title='Click to increase Disk Size'>";
      echo "<form method='post' action='?subaction=disk-resize&amp;uuid={$uuid}&amp;disk={$disk}&amp;oldcap={$capacity}'>";
      echo "<span class='diskresize' style='width:30px'>";
      echo "<span class='text'><a href='#'>$capacity</a></span>";
      echo "<input class='input' type='text' style='width:46px' name='cap' value='$capacity' val='diskresize' hidden>";
      echo "</span></form></td>";
      echo "<td>$allocation</td>";
      echo "<td>detach <a href='#' onclick=\"swal({title:'Are you sure?',text:'Detach ".basename($disk)." from VM: $vm',type:'warning',showCancelButton:true},function(){ajaxVMDispatch('attached',{action:'disk-remove',uuid:'$uuid',dev:'$dev'});});return false;\" title='detach disk from VM'><i class='fa fa-eject blue'></i></a></td>";
    } else {
      echo "<td>$capacity</td><td>$allocation</td><td>N/A</td>";
    }
    echo "</tr>";
  }
  /* end Display VM disks */

  /* Display VM cdroms */
  foreach ($lv->get_cdrom_stats($res) as $arrCD) {
    $capacity = $lv->format_size($arrCD['capacity'], 0);
    $allocation = $lv->format_size($arrCD['allocation'], 0);
    $disk = (array_key_exists('file', $arrCD)) ? $arrCD['file'] : $arrCD['partition'];
    $dev = $arrCD['device'];
    $bus = $arrCD['bus'];
    echo "<tr><td>$disk</td><td>{$arrValidDiskBuses[$bus]}</td><td>$capacity</td><td>$allocation</td><td>";
    if ($state == 'shutoff')
      echo "detach <a href='#' onclick=\"swal({title:'Are you sure?',text:'Detach ".basename($disk)." from VM: $vm',type:'warning',showCancelButton:true},function(){ajaxVMDispatch('attached',{action:'disk-remove',uuid:'$uuid',dev:'$dev'});});return false;\" title='detach disk from VM'><i class='fa fa-eject blue'></i></a>";
    else
      echo "N/A";
    echo "</td></tr>";
  }

  /* end Display VM cdroms */
  echo "</tbody></table>";
  echo "</td></tr>";
}
echo "\0".implode($menu).implode($kvm);
?>