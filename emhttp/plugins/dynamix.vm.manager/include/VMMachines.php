<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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

// add translations
$_SERVER['REQUEST_URI'] = 'vms';
require_once "$docroot/webGui/include/Translations.php";

$user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';
$vms = $lv->get_domains();
if (empty($vms)) {
  echo '<tr><td colspan="8" style="text-align:center;padding-top:12px">'._('No Virtual Machines installed').'</td></tr>';
  return;
}
if (file_exists($user_prefs)) {
  $prefs = (array)@parse_ini_file($user_prefs);
  $sort = [];
  foreach ($vms as $vm) $sort[] = array_search($vm,$prefs);
  array_multisort($sort,SORT_NUMERIC,$vms);
  unset($sort);
} else {
  natcasesort($vms);
}
$i = 0;
$kvm = ['var kvm=[];'];
$show = explode(',',unscript(_var($_GET,'show')));
$path = _var($domain_cfg,'MEDIADIR');
$pci_device_changes = comparePCIData();

foreach ($vms as $vm) {
  $res = $lv->get_domain_by_name($vm);
  $desc = $lv->domain_get_description($res);
  $uuid = $lv->domain_get_uuid($res);
  $dom = $lv->domain_get_info($res);
  $id = $lv->domain_get_id($res) ?: '-';
  $autostart = $lv->domain_get_autostart($res) ? 'checked' : '';
  $state = $lv->domain_state_translate($dom['state']);
  $icon = $lv->domain_get_icon_url($res);
  $image = substr($icon,-4)=='.png' ? "<img src='$icon' class='img'>" : (substr($icon,0,5)=='icon-' ? "<i class='$icon img'></i>" : "<i class='fa fa-$icon img'></i>");
  $arrConfig = domain_to_config($uuid);
  $snapshots = getvmsnapshots($vm) ;
  $vmpciids = $lv->domain_get_vm_pciids($vm);
  $pcierror = false;
  foreach($vmpciids as $pciid => $pcidetail) {
    if (isset($pci_device_changes["0000:".$pciid])) $pcierror = true;
  }
  $cdroms = $lv->get_cdrom_stats($res,true,true) ;
  if ($state == 'running') {
    $mem = $dom['memory']/1024;
  } else {
    $mem = $lv->domain_get_memory($res)/1024;
  }
  $mem = round($mem).'M';
  $vcpu = $dom['nrVirtCpu'];
  $template = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
  if (empty($template)) $template = 'Custom';
  $log = (is_file("/var/log/libvirt/qemu/$vm.log") ? "libvirt/qemu/$vm.log" : '');
  $disks = '-';
  $diskdesc = '';
  $fstype ="QEMU";
  if (($diskcnt = $lv->get_disk_count($res)) > 0) {
    $disks = $diskcnt.' / '.$lv->get_disk_capacity($res);
    $fstype = $lv->get_disk_fstype($res);
    $diskdesc = 'Current physical size: '.$lv->get_disk_capacity($res, true)."\nDefault snapshot type: $fstype";
  }
  $arrValidDiskBuses = getValidDiskBuses();
  $WebUI = html_entity_decode($arrConfig['template']['webui']);
  $vmrcport = $lv->domain_get_vnc_port($res);
  $autoport = $lv->domain_get_vmrc_autoport($res);
  $vmrcurl = '';
  $graphics = '';
  $virtual = false ;
  if (isset($arrConfig['gpu'][0]['model'])) {$vrtdriver=" "._("Driver").strtoupper(":{$arrConfig['gpu'][0]['model']} "); $vrtmodel =$arrConfig['gpu'][0]['model'];} else $vrtdriver = "";
  if (isset($arrConfig['gpu'][0]['render']) && $vrtmodel == "virtio3d") {
    if (isset($arrConfig['gpu'][0]['render']) && $arrConfig['gpu'][0]['render'] == "auto") $vrtdriver .= "<br>"._("RenderGPU").":"._("Auto"); else $vrtdriver .= "<br>"._("RenderGPU").":{$arrValidGPUDevices[$arrConfig['gpu'][0]['render']]['name']}";
  }
  if ($vmrcport > 0) {
    $wsport = $lv->domain_get_ws_port($res);
    $vmrcprotocol = $lv->domain_get_vmrc_protocol($res);
    if ($vmrcprotocol == "vnc") $vmrcscale = "&resize=scale"; else $vmrcscale = "";
    $vmrcurl = autov('/plugins/dynamix.vm.manager/'.$vmrcprotocol.'.html',true).$vmrcscale.'&autoconnect=true&host='._var($_SERVER,'HTTP_HOST');
    if ($vmrcprotocol == "spice") $vmrcurl .= '&vmname='. urlencode($vm) .'&port=/wsproxy/'.$vmrcport.'/'; else $vmrcurl .= '&port=&path=/wsproxy/'.$wsport.'/';
    $graphics = strtoupper($vmrcprotocol).':'._($auto)."$vrtdriver\n";
    $virtual = true ;
  } elseif ($vmrcport == -1 || $autoport) {
    $vmrcprotocol = $lv->domain_get_vmrc_protocol($res);
    if ($autoport == "yes") $auto = "auto"; else $auto="manual";
    $graphics = strtoupper($vmrcprotocol).':'._($auto)."$vrtdriver\n";
    $virtual = true ;
  }
  if (!empty($arrConfig['gpu'])) {
    $arrValidGPUDevices = getValidGPUDevices();
    foreach ($arrConfig['gpu'] as $arrGPU) {
      if ($arrGPU['id'] == "nogpu") {$graphics .= "No GPU"."\n";continue;}
      foreach ($arrValidGPUDevices as $arrDev) {
        if ($arrGPU['id'] == $arrDev['id']) {
          if (count(array_filter($arrValidGPUDevices, function($v) use ($arrDev) { return $v['name'] == $arrDev['name']; })) > 1) {
            $graphics .= $arrDev['name'].' ('.$arrDev['id'].')'."\n";
            if (!$virtual) $vmrcprotocol = "VGA";
          } else {
            $graphics .= $arrDev['name']."\n";
            if (!$virtual) $vmrcprotocol = "VGA";
          }
        }
      }
    }
    $graphics = str_replace("\n", "<br>", trim($graphics));
  }
  unset($dom);
  if (!isset($domain_cfg["CONSOLE"])) $vmrcconsole = "web" ; else $vmrcconsole = $domain_cfg["CONSOLE"] ;
  if (!isset($domain_cfg["RDPOPT"])) $vmrcconsole .= ";no" ; else $vmrcconsole .= ";".$domain_cfg["RDPOPT"] ;
  $menu = sprintf("onclick=\"addVMContext('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s', '%s', %s)\"", addslashes($vm),addslashes($uuid),addslashes($template),$state,addslashes($vmrcurl),strtoupper($vmrcprotocol),addslashes($log),addslashes($fstype), $vmrcconsole,false,addslashes(str_replace('"',"'",$WebUI)),$pcierror);
  $kvm[] = "kvm.push({id:'$uuid',state:'$state'});";
  switch ($state) {
  case 'running':
    $shape = 'play';
    $status = 'started';
    $color = 'green-text';
    break;
  case 'paused':
  case 'pmsuspended':
    $shape = 'pause';
    $status = 'paused';
    $color = 'orange-text';
    break;
  default:
    $shape = 'square';
    $status = 'stopped';
    $color = 'red-text';
    break;
  }

  /* VM information */
  if ($snapshots != null)  $snapshotstr = '('._('Snapshots').': '.count($snapshots).")"; else $snapshotstr = '('._('Snapshots').': '._('None').")";
  $cdbus = $cdbus2 = $cdfile = $cdfile2 = "";
  $cdromcount = 0;
    foreach ($cdroms as $arrCD) {
    $disk = $arrCD['file'] ?? $arrCD['partition'];
    $dev = $arrCD['device'];
    $bus = $arrValidDiskBuses[$arrCD['bus']] ?? 'VirtIO';
    if ($dev == "hda") {
      $cdbus = $arrValidDiskBuses[$arrCD['bus']] ?? 'VirtIO';
      $cdfile = $arrCD['file'] ?? $arrCD['partition'];
      if ($cdfile != "") $cdromcount++;
    }
    if ($dev == "hdb") {
      $cdbus2 = $arrValidDiskBuses[$arrCD['bus']] ?? 'VirtIO';
      $cdfile2 = $arrCD['file'] ?? $arrCD['partition'];
      if ($cdfile2 != "") $cdromcount++;
    }
  }

  $ipliststr = $iptablestr = "" ;
  $gastate = getgastate($res);
  if ($gastate == "connected") {
  $ip  = $lv->domain_interface_addresses($res, 1);
    if ($ip != false) {
      $duplicates = []; // hide duplicate interface names
      foreach ($ip as $arrIP) {
        $ipname = $arrIP["name"];
        if (preg_match('/^(lo|Loopback)/',$ipname)) continue; // omit loopback interface
        $iphdwadr = $arrIP["hwaddr"] == "" ? _("N/A") : $arrIP["hwaddr"];
        $iplist = $arrIP["addrs"];
        foreach ($iplist as $arraddr) {
          $ipaddrval = $arraddr["addr"];
          if (preg_match('/^f[c-f]/',$ipaddrval)) continue; // omit ipv6 private addresses
          $iptype = $arraddr["type"] ? "ipv6" : "ipv4";
          $ipprefix = $arraddr["prefix"];
          $ipnamemac = "$ipname ($iphdwadr)";
          if (!in_array($ipnamemac,$duplicates)) $duplicates[] = $ipnamemac; else $ipnamemac = "";
          $ipliststr .= "<tr><td>$ipnamemac</td><td></td><td></td><td>$iptype</td><td>$ipaddrval</td><td>$ipprefix</td></tr>";
          $iptablestr .= "$ipaddrval/$ipprefix\n" ;
        }
      }
    }
  } else {
    if ($gastate == "disconnected") {
      $ipliststr .= "<tr><td>"._('Guest agent not installed')."</td><td></td><td></td><td></td></tr>";
      $iptablestr = _('Requires guest agent installed');
    } else {
      $ipliststr =  "<tr><td>"._('Guest not running')."</td><td></td><td></td><td></td><td></td></tr>";
      $iptablestr = _('Requires guest running');
    }
  }
  $iptablestr = str_replace("\n", "<br>", trim($iptablestr));

  $changemedia = "getisoimageboth(\"{$uuid}\",\"hda\",\"{$cdbus}\",\"{$cdfile}\",\"hdb\",\"{$cdbus2}\",\"{$cdfile2}\")";
  $title = _('Select ISO image');
  $cdstr = $cdromcount." / 2<a class='hand' title='$title' href='#' onclick='$changemedia'><i class='fa fa-dot-circle-o'></i></a>";
  echo "<tr parent-id='$i' class='sortable'><td class='vm-name' style='width:220px;padding:8px'><i class='fa fa-arrows-v mover orange-text'></i>";
  echo "<span class='outer'><span id='vm-$uuid' $menu class='hand'>$image</span>";
  echo "<span class='inner'><a href='#' onclick='return toggle_id(\"name-$i\")' title='click for more VM info'>$vm</a>";
  if ($pcierror) echo "<i class=\"fa fa-warning fa-fw orange-text\" title=\""._('PCI Changed')."\n"._('Start disabled')."\"></i>";
  echo "<br><i class='fa fa-$shape $status $color'></i><span class='state'>"._($status)." </span></span></span></td>";
  echo "<td>$desc</td>";
  echo "<td><a class='vcpu-$uuid' style='cursor:pointer'>$vcpu</a></td>";
  echo "<td>$mem</td>";
  echo "<td title='$diskdesc'><span class='state' >$disks&nbsp;&nbsp;&nbsp;&nbsp;$cdstr<br>$snapshotstr</span></td>";
  echo "<td><span class='vmgraphics'>$graphics</td>";
  echo "<td><span class='vmgraphics'>$iptablestr</td>";
  echo "<td><input class='autostart' type='checkbox' name='auto_{$vm}' title=\""._('Toggle VM autostart')."\" uuid='$uuid' $autostart></td></tr>";

  /* Disk device information */
  echo "<tr child-id='$i' id='name-$i".(in_array('name-'.$i++,$show) ? "'>" : "' style='display:none'>");
  echo "<td colspan='8' style='margin:0;padding:0'>";
  echo "<table class='tablesorter domdisk'>";
  echo "<thead class='child'><tr><th><i class='fa fa-hdd-o'></i> <b>",_('Disk devices/Volume'),"</b></th><th>",_('Serial'),"</b></th><th>",_('Bus'),"</th><th>",_('Capacity'),"</th><th>",_('Allocation'),"</th><th>Boot Order</th</tr></thead>";
  echo "<tbody class='child'>";

  /* Display VM disks */
  foreach ($lv->get_disk_stats($res) as $arrDisk) {
    $capacity = $lv->format_size($arrDisk['capacity'], 0);
    $allocation = $lv->format_size($arrDisk['allocation'], 0);
    $disk = $arrDisk['file'] ?? $arrDisk['partition'];
    $dev = $arrDisk['device'];
    $bus = $arrValidDiskBuses[$arrDisk['bus']] ?? 'VirtIO';
    $boot= $arrDisk["boot order"];
    $serial = $arrDisk["serial"];
    if ($boot < 1) $boot = _('Not set');
    $reallocation = trim(get_realvolume($disk));
    if (!empty($reallocation)) $reallocationstr = "($reallocation)"; else $reallocationstr = "";
    echo "<tr><td>$disk $reallocationstr</td><td>$serial</td><td>$bus</td>";
    if ($state == 'shutoff') {
      echo "<td title='Click to increase Disk Size'>";
      echo "<form method='get' action=''>";
      echo "<input type='hidden' name='subaction' value='disk-resize'>";
      echo "<input type='hidden' name='uuid' value='".$uuid."'>";
      echo "<input type='hidden' name='disk' value='".htmlspecialchars($disk)."'>";
      echo "<input type='hidden' name='oldcap' value='".$capacity."'>";
      echo "<span class='diskresize' style='width:30px'>";
      echo "<span class='text'><a href='#' onclick='return false'>$capacity</a></span>";
      echo "<input class='input' type='text' style='width:46px' name='cap' value='$capacity' val='diskresize' hidden>";
      echo "</span></form></td>";
    } else {
      echo "<td>$capacity</td>";
    }
    echo "<td>$allocation</td><td>$boot</td></tr>";
  }

  /* Display VM cdroms */
  foreach ($cdroms as $arrCD) {
    $tooltip = "";
    $capacity = $lv->format_size($arrCD['capacity'], 0);
    $allocation = $lv->format_size($arrCD['allocation'], 0);
    if ($arrCD['spundown']) {$capacity = $allocation = "*"; $tooltip = "Drive spun down ISO volume is ".$arrCD['reallocation'];} else $tooltip = "ISO volume is ".$arrCD['reallocation'];
    $disk = $arrCD['file'] ?? $arrCD['partition'] ?? "" ;
    $dev  = $arrCD['device'];
    $bus  = $arrValidDiskBuses[$arrCD['bus']] ?? 'VirtIO';
    $boot = $arrCD["boot order"] ?? "" ;
    if ($boot < 1) $boot = _('Not set');
    if ($disk != "" ) {
      $title = _('Eject CD Drive');
      $changemedia = "changemedia(\"{$uuid}\",\"{$dev}\",\"{$bus}\", \"--eject\")";
      echo "<tr><td>$disk <a title='$title' href='#' onclick='$changemedia'> <i class='fa fa-eject'></i></a></td><td></td><td>$bus</td><td><span title='$tooltip' data-toggle='tooltip'>$capacity</span></td><td>$allocation</td><td>$boot</td></tr>";
    } else {
      $title = _('Insert CD');
      $changemedia = "changemedia(\"{$uuid}\",\"{$dev}\",\"{$bus}\",\"--select\")";
      $disk = _("No CD image inserted into drive");
      echo "<tr><td>$disk<a title='$title' href='#' onclick='$changemedia'><i class='fa fa-dot-circle-o'></i></a></td><td></td><td>$bus</td><td>$capacity</td><td>$allocation</td><td>$boot</td></tr>";
    }
  }
  echo "</tbody>";
  /* Display VM  IP Addresses "execute":"guest-network-get-interfaces" --pretty */
  echo "<thead class='child'><tr><th><i class='fa fa-sitemap'></i> <b>",_('Interfaces')."</b></th><th></th><th></th><th>",_('Type')."</th><th>",_('IP Address'),"</th><th>",_('Prefix'),"</th></tr></thead>";
  echo "<tbody class='child'>";
  echo $ipliststr ;
  echo "</tbody>";
  /* Display VM  Snapshots */
  if ($snapshots != null) {
    $j = 0;
    $steps = array();
    foreach ($snapshots as $snap) {
      if ($snap['parent'] == "" || $snap['parent'] == "Base") $j++;
      $steps[$j] .= $snap['name'].';';
    }
    echo "<thead class='child' child-id='$i'><tr><th><i class='fa fa-clone'></i> <b>",_('Snapshots'),"</b></th><th></th><th>",_('Date/Time'),"</th><th>",_('Type (Method)'),"</th><th>",_('Parent'),"</th><th>",_('Memory'),"</th></tr></thead>";
    echo "<tbody class='child'child-id='$i'>";
    foreach ($steps as $stepsline) {
      $snapshotlist = explode(";",$stepsline);
      $tab = "&nbsp;&nbsp;&nbsp;&nbsp;";
      foreach ($snapshotlist as  $snapshotitem) {
        if ($snapshotitem == "") continue;
        $snapshot = $snapshots[$snapshotitem] ;
        $snapshotstate = _(ucfirst($snapshot["state"]))." ({$snapshot["method"]})";
        $snapshotdesc = $snapshot["desc"];
        $snapshotmemory = _(ucfirst($snapshot["memory"]["@attributes"]["snapshot"]));
        $snapshotparent = $snapshot["parent"] ? $snapshot["parent"]  : "None";
        $snapshotdatetime = my_time($snapshot["creationtime"],"Y-m-d" )."<br>".my_time($snapshot["creationtime"],"H:i:s");
        $snapmenu = sprintf("onclick=\"addVMSnapContext('%s','%s','%s','%s','%s','%s')\"", addslashes($vm),addslashes($uuid),addslashes($template),$state,$snapshot["name"],$snapshot["method"]);
        echo "<tr><td><span id='vmsnap-$uuid' $snapmenu class='hand'>$tab|__&nbsp;&nbsp;<i class='fa fa-clone'></i></span>&nbsp;",$snapshot["name"],"</td><td>$snapshotdesc</td><td><span class='inner' style='font-size:1.1rem;'>$snapshotdatetime</span></td><td>$snapshotstate</td><td>$snapshotparent</td><td>$snapshotmemory</td></tr>";
        $tab .="&nbsp;&nbsp;&nbsp;&nbsp;";
      }
      echo "</tbody>";
    }
  }
  echo "</table>";
}
echo "\0".implode($kvm);
?>
