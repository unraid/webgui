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
require_once "$docroot/webGui/include/Helpers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'tools';
require_once "$docroot/webGui/include/Translations.php";

$pci_device_diffs = comparePCIData();

function usb_physical_port($usbbusdev) {
  if (preg_match('/^Bus (?P<bus>\S+) Device (?P<dev>\S+): ID (?P<id>\S+)(?P<name>.*)$/', $usbbusdev, $usbMatch)) {
    //udevadm info -a --name=/dev/bus/usb/003/002 | grep KERNEL==
    $udevcmd = "udevadm info -a --name=/dev/bus/usb/".$usbMatch['bus']."/".$usbMatch['dev']." | grep KERNEL==";
    $physical_busid = _("None");
    exec($udevcmd , $udev);
    if (isset($udev)) {
      $physical_busid = trim(substr($udev[0], 13) , '"');
      if (substr($physical_busid,0,3) =='usb') {
        $physical_busid = substr($physical_busid,3).'-0';
      }
    }
  }
  return($physical_busid);
}

switch ($_POST['table']) {
case 't1':
  exec('for group in $(ls /sys/kernel/iommu_groups/ -1|sort -n);do echo "IOMMU group $group";for device in $(ls -1 "/sys/kernel/iommu_groups/$group"/devices/);do echo -n $\'\t\';lspci -ns "$device"|awk \'BEGIN{ORS=" "}{print "["$3"]"}\';lspci -s "$device";done;done',$groups);
  if (empty($groups)) {
    exec('lspci -n|awk \'{print "["$3"]"}\'',$iommu);
    exec('lspci',$lspci);
    $i = 0;
    foreach ($lspci as $line) echo "<tr><td>",$iommu[$i++],"</td><td>$line</td></tr>";
    $noiommu = true;
  } else {
    $BDF_VD_REGEX = '/^[[:xdigit:]]{2}:[[:xdigit:]]{2}\.[[:xdigit:]](\|[[:xdigit:]]{4}:[[:xdigit:]]{4})?$/';
    $DBDF_VD_REGEX = '/^[[:xdigit:]]{4}:[[:xdigit:]]{2}:[[:xdigit:]]{2}\.[[:xdigit:]](\|[[:xdigit:]]{4}:[[:xdigit:]]{4})?$/';
    $BDF_REGEX = '/^[[:xdigit:]]{2}:[[:xdigit:]]{2}\.[[:xdigit:]]$/';
    $DBDF_PARTIAL_REGEX = '/[[:xdigit:]]{4}:[[:xdigit:]]{2}:[[:xdigit:]]{2}\.[[:xdigit:]]/';
    $vfio_cfg_devices = array ();
    if (is_file("/boot/config/vfio-pci.cfg")) {
      // accepts space-separated list of <Bus:Device.Function> or <Domain:Bus:Device.Function> followed by an optional "|" and <Vendor:Device>
      // example: BIND=03:00.0 0000:03:00.0 03:00.0|8086:1533 0000:03:00.0|8086:1533
      // this front-end does not accept <Vendor:Device> by itself, altough the underlying vfio-pci script does
      $file = file_get_contents("/boot/config/vfio-pci.cfg");
      $file = trim(str_replace("BIND=", "", $file));
      $file_contents = explode(" ", $file);
      foreach ($file_contents as $vfio_cfg_device) {
        if (preg_match($BDF_VD_REGEX, $vfio_cfg_device)) {
          // only <Bus:Device.Function> was provided, assume Domain is 0000 (may be followed by optional <Vendor:Device> too)
          $vfio_cfg_devices[] = "0000:".$vfio_cfg_device;
        } else if (preg_match($DBDF_VD_REGEX, $vfio_cfg_device)) {
          // full <Domain:Bus:Device.Function> was provided (may be followed by optional <Vendor:Device> too)
          $vfio_cfg_devices[] = $vfio_cfg_device;
        } else {
          // entry in wrong format, discard
        }
      }
      $vfio_cfg_devices = array_values(array_unique($vfio_cfg_devices, SORT_STRING));
    }
    $disks = (array)parse_ini_file('state/disks.ini',true);
    $devicelist = array_column($disks, 'device');
    $lines = array ();
    foreach ($devicelist as $line) {
      if (!empty($line)) {
        exec('udevadm info --path=$(udevadm info -q path /dev/'.$line.' | cut -d / -f 1-7) --query=path',$linereturn);
        if(isset($linereturn[0])) {
          preg_match_all($DBDF_PARTIAL_REGEX, $linereturn[0], $inuse);
          foreach ($inuse[0] as $line) {
            $lines[] = $line;
          }
        }
        unset($inuse);
        unset($linereturn);
      }
    }
    $networks = (array)parse_ini_file('state/network.ini',true);
    $networklist = array_merge(array_column($networks, 'BRNICS'), array_column($networks, 'BONDNICS'));
    foreach ($networklist as $niclist) {
      if (!empty($niclist)) {
        $nics = explode(",", $niclist);
        if (!empty($nics)) {
          foreach ($nics as $line) {
            if (!empty($line)) {
              exec('readlink /sys/class/net/'.$line,$linereturn);
              if(isset($linereturn[0])) {
                preg_match_all($DBDF_PARTIAL_REGEX, $linereturn[0], $inuse);
                foreach ($inuse[0] as $line) {
                  $lines[] = $line;
                }
              }
              unset($inuse);
              unset($linereturn);
            }
          }
        }
      }
    }
    $lines = array_values(array_unique($lines, SORT_STRING));
    $iommuinuse = array ();
    foreach ($lines as $pciinuse){
      $string = exec("ls /sys/kernel/iommu_groups/*/devices/$pciinuse -1 -d");
      $string = substr($string,25,2);
      $iommuinuse[] = (strpos($string,'/')) ? strstr($string, '/', true) : $string;
    }
    exec('lsscsi -s',$lsscsi);
    // Filter for 'removed' devices
    $removedArr = array_filter($pci_device_diffs, function($entry) {
      return isset($entry['status']) && $entry['status'] === 'removed';
    });
    foreach ($removedArr as $removedpci => $removeddata) {
      $groups[] = "IOMMU "._("Removed");
      $groups[] = "\tR[{$removeddata['device']['vendor_id']}:{$removeddata['device']['device_id']}] ".str_replace("0000:","",$removedpci)." ".trim($removeddata['device']['description'],"\n");
    }
    $ackparm = "";
    foreach ($groups as $line) {
      if (!$line) continue;
      if ($line[0]=='I') {
        if (isset($spacer)) echo "<tr><td colspan='2' class='thin'></td>"; else $spacer = true;
        echo "</tr><tr><td>$line:</td><td>";
        $iommu = substr($line, 12);
        $append = true;
      } else {
        $line = preg_replace("/^\t/","",$line);
        $vd = trim(explode(" ", $line)[0], "[]");
        $pciaddress = explode(" ", $line)[1];
        $removed = $line[0]=='R' ? true : false;
        if ($removed) $line=preg_replace('/R/', '', $line, 1);
        if (preg_match($BDF_REGEX, $pciaddress)) {
          // By default lspci does not output the <Domain> when the only domain in the system is 0000. Add it back.
          $pciaddress = "0000:".$pciaddress;
        }
        echo ($append) ? "" : "<tr><td></td><td>";
        exec("lspci -v -s $pciaddress", $outputvfio);
        if (preg_grep("/vfio-pci/i", $outputvfio)) {
          echo "<i class=\"fa fa-circle orb green-orb middle\" title=\"",_('Kernel driver in use: vfio-pci'),"\"></i>";
          $isbound = "true";
        }
        echo "</td><td>";
        if ((strpos($line, 'Host bridge') === false) && (strpos($line, 'PCI bridge') === false)) {
          if (file_exists('/sys/kernel/iommu_groups/'.$iommu.'/devices/'.$pciaddress.'/reset')) echo "<i class=\"fa fa-retweet grey-orb middle\" title=\"",_('Function Level Reset (FLR) supported'),".\"></i>";
          echo "</td><td>";
          if (!$removed) {
          echo in_array($iommu, $iommuinuse) ? '<input type="checkbox" value="" title="'._('In use by Unraid').'" disabled ' : '<input type="checkbox" class="iommu'.$iommu.'" value="'.$pciaddress."|".$vd.'" ';
          // check config file for two formats: <Domain:Bus:Device.Function>|<Vendor:Device> or just <Domain:Bus:Device.Function>
          echo (in_array($pciaddress."|".$vd, $vfio_cfg_devices) || in_array($pciaddress, $vfio_cfg_devices)) ? " checked>" : ">";
          } 
        } else { echo "</td><td>"; }
        echo '</td><td title="';
        foreach ($outputvfio as $line2) echo htmlentities($line2,ENT_QUOTES)."&#10;";
        echo '">',$line,'</td></tr>';
        if (array_key_exists($pciaddress,$pci_device_diffs)) {
          echo "<tr><td></td><td><td></td><td></td><td>";
          echo "<i class=\"fa fa-warning fa-fw orange-text\" title=\""._('PCI Change')."\n"._('Click to acknowledge').".\" onclick=\"ackPCI('".htmlentities($pciaddress)."','".htmlentities($pci_device_diffs[$pciaddress]['status'])."')\"></i>";
          echo _("PCI Device change");
          echo " "._("Action").":".ucfirst(_($pci_device_diffs[$pciaddress]['status']))." ";
          $ackparm .= $pciaddress.",".$pci_device_diffs[$pciaddress]['status'].";";
          if ($pci_device_diffs[$pciaddress]['status']!="removed") echo $pci_device_diffs[$pciaddress]['device']['description'];
          echo "</td></tr>";
          if ($pci_device_diffs[$pciaddress]['status']=="changed") {
            echo "<tr><td></td><td><td></td><td></td><td>";
            echo _("Differences");
            foreach($pci_device_diffs[$pciaddress]['differences'] as $key => $changes){
              echo " $key "._("before").":{$changes['old']} "._("after").":{$changes['new']} ";
            }
            echo "</td></tr>";
          }
        }
        unset($outputvfio);
        switch (true) {
          case (strpos($line, 'USB controller') !== false):
            if (isset($isbound)) {
              echo '<tr><td></td><td></td><td></td><td></td><td>',_('This controller is bound to vfio, connected USB devices are not visible'),'.</td></tr>';
            } else {
              exec('for usb_ctrl in $(find /sys/bus/usb/devices/usb* -maxdepth 0 -type l);do path="$(realpath "${usb_ctrl}")";if [[ $path == *'.$pciaddress.'* ]];then bus="$(cat "${usb_ctrl}/busnum")";lsusb -s $bus:|sort;fi;done',$getusb);
              foreach($getusb as $usbdevice) {
                [$bus,$id] = my_explode(':',$usbdevice);
                $usbport = usb_physical_port($usbdevice);
                if (strlen($usbport) > 7 ) {$usbport .= "\t"; } else { $usbport .= "\t\t"; }
                echo "<tr><td></td><td></td><td></td><td></td><td>$bus Port $usbport",trim($id),"</td></tr>";
              }
              unset($getusb);
            }
            break;
          case (strpos($line, 'SATA controller') !== false):
          case (strpos($line, 'Serial Attached SCSI controller') !== false):
          case (strpos($line, 'RAID bus controller') !== false):
          case (strpos($line, 'SCSI storage controller') !== false):
          case (strpos($line, 'IDE interface') !== false):
          case (strpos($line, 'Mass storage controller') !== false):
          case (strpos($line, 'Non-Volatile memory controller') !== false):
            if (isset($isbound)) {
              echo '<tr><td></td><td></td><td></td><td></td><td>',_('This controller is bound to vfio, connected drives are not visible'),'.</td></tr>';
            } else {
              exec('ls -al /sys/block/sd* /sys/block/hd* /sys/block/sr* /sys/block/nvme* 2>/dev/null | grep -i "'.$pciaddress.'"',$getsata);
              foreach($getsata as $satadevice) {
                $satadevice = substr($satadevice, strrpos($satadevice, '/', -1)+1);
                $search = preg_grep('/'.$satadevice.'.*/', $lsscsi);
                foreach ($search as $deviceline) {
                  echo '<tr><td></td><td></td><td></td><td></td><td>',$deviceline,'</td></tr>';
                }
              }
              unset($search);
              unset($getsata);
            }
            break;
        }
        unset($isbound);
        $append = false;
      }
    }
    echo '<tr><td></td><td></td><td></td><td></td><td><br>';
    if (file_exists("/var/log/vfio-pci") && filesize("/var/log/vfio-pci")) {
      echo '<input id="viewlog" type="button" value="'._('View VFIO-PCI Log').'" onclick="openTerminal(\'log\',\'vfio-pci\',\'vfio-pci\')">';
    }
    if ($ackparm == "") $ackdisable =" disabled "; else $ackdisable = "";
    echo '<input id="applycfg" type="submit" disabled value="'._('Bind selected to VFIO at Boot').'" onclick="applyCfg();" '.(isset($noiommu) ? "style=\"display:none\"" : "").'>';
    echo '<span id="warning"></span>';
    echo '<input id="applypci" type="submit"'.$ackdisable.' value="'._('Acknowledge all PCI changes').'" onclick="ackPCI(\''.htmlentities($ackparm).'\',\'all\')" >';
    echo '</td></tr>';
    echo <<<EOT
<script>
$("#t1 input[type='checkbox']").change(function() {
  var matches = document.querySelectorAll("." + this.className);
  for (var i=0, len=matches.length|0; i<len; i=i+1|0) {
    matches[i].checked = this.checked ? true : false;
  }
  $("#applycfg").attr("disabled", false);
});
</script>
EOT;
  }
  break;
case 't2':
  $is_intel_cpu = is_intel_cpu();
  $core_types = $is_intel_cpu ? get_intel_core_types() : [];
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list|sort -nu',$pairs);
  $i = 1;
  foreach ($pairs as $line) {
    $line2 = $line;
    $line = preg_replace(['/(\d+)[-,](\d+)/','/(\d+)\b/'],['$1 / $2','cpu $1'],$line);
    if ($is_intel_cpu && count($core_types) > 0) {
      [$cpu1, $cpu2] = my_preg_split('/[,-]/',$line2);
      $core = $cpu1;
      $core_type = "({$core_types[$core]})";
    } else $core_type = "";
    echo "<tr><td>".(strpos($line,'/')===false?"Single":"Pair ".$i++).":</td><td>$line $core_type</td></tr>";
  }
  break;
case 't3':
  exec('lsusb|sort',$lsusb);
  foreach ($lsusb as $line) {
    [$bus,$id] = my_explode(':',$line);
    $usbport = usb_physical_port($line);
    echo "<tr><td>$bus Port $usbport</td><td>".trim($id)."</td></tr>";
  }
  break;
case 't4':
  exec('lsscsi -s',$lsscsi);
  foreach ($lsscsi as $line) {
    if (strpos($line,'/dev/')===false) continue;
    echo "<tr><td>",preg_replace('/\]  +/',']</td><td>',$line),"</td></tr>";
  }
  break;
}
?>
