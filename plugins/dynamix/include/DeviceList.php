<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
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
// add translations
$_SERVER['REQUEST_URI'] = 'main';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";

$path  = $_POST['path'];
$var   = (array)parse_ini_file('state/var.ini');
$devs  = (array)parse_ini_file('state/devs.ini',true);
$disks = (array)parse_ini_file('state/disks.ini',true);
$sec   = (array)parse_ini_file('state/sec.ini',true);
$diskio= @(array)parse_ini_file('state/diskload.ini');
extract(parse_plugin_cfg('dynamix',true));

function initSum() {
  return ['count'=>0, 'temp'=>0, 'fsSize'=>0, 'fsUsed'=>0, 'fsFree'=>0, 'ioReads'=>0, 'ioWrites'=>0, 'numReads'=>0, 'numWrites'=>0, 'numErrors'=>0];
}
function model($id) {
  return substr($id,0,strrpos($id,'_'));
}
// sort unassigned devices on disk identification
if (count($devs)>1) array_multisort(array_column($devs,'sectors'),SORT_DESC,array_map('model',array_column($devs,'id')),SORT_NATURAL|SORT_FLAG_CASE,array_column($devs,'device'),$devs);

require_once "$docroot/webGui/include/CustomMerge.php";

function in_parity_log($log,$timestamp) {
  if (file_exists($log)) {
    $handle = fopen($log, 'r');
    while (($line = fgets($handle))!==false) {
      if (strpos($line,$timestamp)!==false) break;
    }
    fclose($handle);
  }
  return !empty($line);
}
function device_info(&$disk,$online) {
  global $pools, $path, $var, $crypto;
  $name = $disk['name'];
  $fancyname = compress(_(my_disk($name),3),16,5);
  $type = $disk['type']=='Flash' ? $disk['type'] : 'Device';
  $action = strpos($disk['color'],'blink')===false ? 'down' : 'up';
  switch ($disk['color']) {
    case 'green-on': $orb = 'circle'; $color = 'green'; $help = _('Normal operation, device is active'); break;
    case 'green-blink': $orb = 'circle'; $color = 'grey'; $help = _('Device is in standby mode (spun-down)'); break;
    case 'blue-on': $orb = 'square'; $color = 'blue'; $help = _('New device'); break;
    case 'blue-blink': $orb = 'square'; $color = 'grey'; $help = _('New device, in standby mode (spun-down)'); break;
    case 'yellow-on': $orb = 'warning'; $color = 'yellow'; $help = $disk['type']=='Parity' ? _('Parity is invalid') : _('Device contents emulated'); break;
    case 'yellow-blink': $orb = 'warning'; $color = 'grey'; $help = $disk['type']=='Parity' ? _('Parity is invalid, in standby mode (spun-down)') : _('Device contents emulated, in standby mode (spun-down)'); break;
    case 'red-on': case 'red-blink': $orb = 'times'; $color = 'red'; $help = $disk['type']=='Parity' ? _('Parity device is disabled') : _('Device is disabled, contents emulated'); break;
    case 'red-off': $orb = 'times'; $color = 'red'; $help = $disk['type']=='Parity' ? _('Parity device is missing') : _('Device is missing (disabled), contents emulated'); break;
    case 'grey-off': $orb = 'square'; $color = 'grey'; $help = _('Device not present'); break;
  }
  $ctrl = '';
  if ($var['fsState']=='Started' && $type!='Flash' && strpos($disk['status'],'_NP')===false) {
    $ctrl = " style='cursor:pointer' onclick=\"toggle_state('$type','$name','$action')\"";
    $help .= "<br>"._("Click to spin $action device");
  }
  $status = "<a class='info'><i ".($ctrl?"id='dev-$name' ":"")."class='fa fa-$orb orb $color-orb'$ctrl></i><span>$help</span></a>";
  $link = ($disk['type']=='Parity' && strpos($disk['status'],'_NP')===false) ||
          ($disk['type']=='Data' && $disk['status']!='DISK_NP') ||
          ($disk['type']=='Cache' && $disk['status']!='DISK_NP') ||
          ($disk['name']=='flash') || in_array($disk['name'],$pools) ||
           $disk['type']=='New' ? "<a href=\"".htmlspecialchars("$path/$type?name=$name")."\">$fancyname</a>" : $fancyname;
  if ($crypto) switch ($disk['luksState']) {
    case 0:
      if (!vfs_luks($disk['fsType']))
        $luks = "<i class='nolock fa fa-lock'></i>";
      else
        $luks = "<a class='info'><i class='padlock fa fa-unlock orange-text'></i><span>"._('Device to be encrypted')."</span></a>";
      break;
    case 1:
      if ($online) {
        $luks = "<a class='info'><i class='padlock fa fa-unlock-alt green-text'></i><span>"._('Device encrypted and unlocked')."</span></a>";
        break;
      }
      /* fall thru */
    case 2:
      $luks = "<a class='info'><i class='padlock fa fa-lock green-text'></i><span>"._('Device encrypted')."</span></a>";
      break;
    case 3:
      $luks = "<a class='info'><i class='padlock fa fa-lock red-text'></i><span>"._('Device locked: wrong encryption key')."</span></a>";
      break;
   default:
      $luks = "<a class='info'><i class='padlock fa fa-lock red-text'></i><span>"._('Device locked: unknown error')."</span></a>";
      break;
  } else $luks = '';
  return $status.$luks.$link;
}
function device_browse(&$disk) {
  global $path;
  $dir = $disk['name']=='flash' ? "/boot" : "/mnt/{$disk['name']}";
  return "<a href=\"/$path/Browse?dir=".htmlspecialchars($dir)."\"><img src='/webGui/images/explore.png' title='"._('Browse')." $dir'></a>";
}
function device_desc(&$disk) {
  global $var;
  $size = my_scale($disk['size'] ? $disk['size']*1024 : $disk['sectors']*$disk['sector_size'],$unit,-1);
  switch ($disk['type']) {
    case 'Flash' : $type = 'usb'; break;
    case 'Parity': $type = $disk['rotational'] ? 'disk' : 'nvme'; break;
    case 'Data'  :
    case 'Cache' : $type = $disk['rotational'] ? ($disk['luksState'] ? 'disk-encrypted' : 'disk') : 'nvme'; break;
    default      : $type = 'disk'; break;
  }
  $log = $var['fsState']=='Started'
       ? "<a class='info hand' onclick=\"openBox('/webGui/scripts/disk_log&arg1={$disk['device']}','"._('Disk Log Information')."',600,900,false);return false\"><i class='icon-$type icon'></i><span>"._('Disk Log Information')."</span></a>"
       : "<a class='static'><i class='icon-$type icon'></i></a>";
  return  $log."<span style='font-family:bitstream'>".my_id($disk['id'])."</span> - $size $unit ({$disk['device']})";
}
function assignment(&$disk) {
  global $var, $devs;
  $out = "<form method='POST' id=\"{$disk['name']}Form\" action='/update.htm' target='progressFrame'>";
  $out .= "<input type='hidden' name='changeDevice' value='apply'>";
  $out .= "<input type='hidden' name='csrf_token' value='{$var['csrf_token']}'>";
  $out .= "<select class='slot' name='slotId.{$disk['idx']}' onChange='\$(\"#{$disk['name']}Form\").submit()'>";
  $empty = $disk['idSb']!='' ? _('no device') : _('unassigned');
  if ($disk['id']!='') {
    $out .= "<option value=\"{$disk['id']}\" selected>".device_desc($disk)."</option>";
    $out .= "<option value=''>$empty</option>";
  } else
    $out .= "<option value='' selected>$empty</option>";
  foreach ($devs as $dev) $out .= "<option value=\"{$dev['id']}\">".device_desc($dev)."</option>";
  return "$out</select></form>";
}
function vfs_type($fs) {
  return str_replace('luks:','',$fs);
}
function vfs_luks($fs) {
  return ($fs != vfs_type($fs));
}
function fs_info(&$disk) {
  global $display;
  if (empty($disk['fsStatus'])) {
    echo ($disk['type']=='Cache') ? "<td colspan='4'>"._('Device is part of a pool')."</td><td></td>" : "<td colspan='5'></td>";
    return;
  } elseif ($disk['fsStatus']=='Mounted') {
    echo "<td>".vfs_type($disk['fsType'])."</td>";
    echo "<td>".my_scale(($disk['fsSize']??0)*1024,$unit,-1)." $unit</td>";
    if ($display['text']%10==0) {
      echo "<td>".my_scale($disk['fsUsed']*1024,$unit)." $unit</td>";
    } else {
      $used = isset($disk['fsSize']) && $disk['fsSize']>0 ? 100-round(100*$disk['fsFree']/$disk['fsSize']) : 0;
      echo "<td><div class='usage-disk'><span style='width:$used%' class='".usage_color($disk,$used,false)."'></span><span>".my_scale($disk['fsUsed']*1024,$unit)." $unit</span></div></td>";
    }
    if ($display['text']<10 ? $display['text']%10==0 : $display['text']%10!=0) {
      echo "<td>".my_scale($disk['fsFree']*1024,$unit)." $unit</td>";
    } else {
      $free = isset($disk['fsSize']) && $disk['fsSize']>0 ? round(100*$disk['fsFree']/$disk['fsSize']) : 0;
      echo "<td><div class='usage-disk'><span style='width:$free%' class='".usage_color($disk,$free,true)."'></span><span>".my_scale($disk['fsFree']*1024,$unit)." $unit</span></div></td>";
    }
    echo "<td>".device_browse($disk)."</td>";
  } else
    echo "<td>".vfs_type($disk['fsType'])."</td><td colspan='4' style='text-align:center'>"._($disk['fsStatus']);
}
function my_diskio($data) {
  return my_scale($data,$unit,1)." $unit/s";
}
function array_offline(&$disk,$pool='') {
  global $var, $disks;
  if (strpos($var['mdState'],'ERROR:')===false) {
    $text = "<span class='red-text'><em>"._('All existing data on this device will be OVERWRITTEN when array is Started')."</em></span>";
    if ($disk['type']=='Cache') {
      if (!empty($disks[$pool]['uuid']) && $disk['status']=='DISK_NEW') $warning = $text;
    } else {
      if ($var['mdState']=='NEW_ARRAY') {
        if ($disk['type']=='Parity') $warning = $text;
      } else if ($var['mdNumInvalid']<=1) {
        if (in_array($disk['status'],['DISK_INVALID','DISK_DSBL_NEW','DISK_WRONG','DISK_NEW'])) $warning = $text;
      }
    }
  }
  echo "<tr>";
  switch ($disk['status']) {
  case 'DISK_NP':
  case 'DISK_NP_DSBL':
    echo "<td>".device_info($disk,false)."</td>";
    echo "<td>".assignment($disk)."</td>";
    echo "<td colspan='9'></td>";
    break;
  case 'DISK_NP_MISSING':
    echo "<td>".device_info($disk,false)."<br><span class='diskinfo'><em>"._('Missing')."</em></span></td>";
    echo "<td>".assignment($disk)."<em>{$disk['idSb']} - ".my_scale($disk['sizeSb']*1024,$unit)." $unit</em></td>";
    echo "<td colspan='9'></td>";
    break;
  case 'DISK_OK':
  case 'DISK_DSBL':
  case 'DISK_INVALID':
  case 'DISK_DSBL_NEW':
  case 'DISK_NEW':
    echo "<td>".device_info($disk,false)."</td>";
    echo "<td>".assignment($disk)."</td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td colspan='8'>$warning</td>";
    break;
  case 'DISK_WRONG':
    echo "<td>".device_info($disk,false)."<br><span class='diskinfo'><em>"._('Wrong')."</em></span></td>";
    echo "<td>".assignment($disk)."<em>{$disk['idSb']} - ".my_scale($disk['sizeSb']*1024,$unit)." $unit</em></td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td colspan='8'>$warning</td>";
    break;
  }
  echo "</tr>";
}
function array_online(&$disk) {
  global $pools, $sum, $diskio;
  if ($disk['device']!='') {
    $dev = $disk['device'];
    $data = explode(' ',$diskio[$dev] ?? '0 0');
    $sum['ioReads'] += $data[0];
    $sum['ioWrites'] += $data[1];
  }
  if (is_numeric($disk['temp'])) {
    $sum['count']++;
    $sum['temp'] += $disk['temp'];
  }
  $sum['numReads'] += $disk['numReads'];
  $sum['numWrites'] += $disk['numWrites'];
  $sum['numErrors'] += $disk['numErrors'];
  if (isset($disk['fsFree'])) {
    $sum['fsSize'] += $disk['fsSize'];
    $sum['fsUsed'] += $disk['fsUsed'];
    $sum['fsFree'] += $disk['fsFree'];
  }
  echo "<tr>";
  switch ($disk['status']) {
  case 'DISK_NP':
    if (in_array($disk['name'],$pools)) {
      echo "<td>".device_info($disk,true)."</td>";
      echo "<td><a class='static'><i class='icon-disk icon'></i><span></span></a><em>"._('Not installed')."</em></td>";
      echo "<td colspan='4'></td>";
      fs_info($disk);
    }
    break;
  case 'DISK_NP_DSBL':
    echo "<td>".device_info($disk,true)."</td>";
    echo "<td><a class='static'><i class='icon-disk icon'></i><span></span></a><em>"._('Not installed')."</em></td>";
    echo "<td colspan='4'></td>";
    fs_info($disk);
    break;
  case 'DISK_DSBL':
  default:
    echo "<td>".device_info($disk,true)."</td>";
    echo "<td>".device_desc($disk)."</td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td><span class='diskio'>".my_diskio($data[0])."</span><span class='number'>".my_number($disk['numReads'])."</span></td>";
    echo "<td><span class='diskio'>".my_diskio($data[1])."</span><span class='number'>".my_number($disk['numWrites'])."</span></td>";
    echo "<td>".my_number($disk['numErrors'])."</td>";
    fs_info($disk);
    break;
  }
  echo "</tr>";
}
function my_clock($time) {
  if (!$time) return _('less than a minute');
  $days = floor($time/1440);
  $hour = $time/60%24;
  $mins = $time%60;
  return plus($days,'day',($hour|$mins)==0).plus($hour,'hour',$mins==0).plus($mins,'minute',true);
}
function show_totals($text,$array,$name) {
  global $var, $display, $sum;
  $ctrl1 = "onclick=\"toggle_state('Device','$name','down')\"";
  $ctrl2 = "onclick=\"toggle_state('Device','$name','up')\"";
  $help1 = _('Spin Down').' '._(ucfirst(substr($name,0,-1)));
  $help2 = _('Spin Up').' '._(ucfirst(substr($name,0,-1)));
  echo "<tr class='tr_last'>";
  echo "<td><a class='info'><i class='fa fa-fw fa-toggle-down control' $ctrl1></i><span>$help1</span></a><a class='info'><i class='fa fa-fw fa-toggle-up control' $ctrl2></i><span>$help2</span></a></td>";
  echo "<td><a class='static'><i class='icon-disks icon'></i></a><span></span>$text</td>";
  echo "<td>".($sum['count']>0 ? my_temp(round($sum['temp']/$sum['count'],1)) : '*')."</td>";
  echo "<td><span class='diskio'>".my_diskio($sum['ioReads'])."</span><span class='number'>".my_number($sum['numReads'])."</span></td>";
  echo "<td><span class='diskio'>".my_diskio($sum['ioWrites'])."</span><span class='number'>".my_number($sum['numWrites'])."</span></td>";
  echo "<td>".my_number($sum['numErrors'])."</td>";
  echo "<td></td>";
  if ($array && ($var['startMode']=='Normal')) {
    echo "<td>".my_scale($sum['fsSize']*1024,$unit,-1)." $unit</td>";
    if ($display['text']%10==0) {
      echo "<td>".my_scale($sum['fsUsed']*1024,$unit)." $unit</td>";
    } else {
      $used = $sum['fsSize'] ? 100-round(100*$sum['fsFree']/$sum['fsSize']) : 0;
      echo "<td><div class='usage-disk'><span style='width:$used%' class='".usage_color($display,$used,false)."'></span><span>".my_scale($sum['fsUsed']*1024,$unit)." $unit</span></div></td>";
    }
    if ($display['text']<10 ? $display['text']%10==0 : $display['text']%10!=0) {
      echo "<td>".my_scale($sum['fsFree']*1024,$unit)." $unit</td>";
    } else {
      $free = $sum['fsSize'] ? round(100*$sum['fsFree']/$sum['fsSize']) : 0;
      echo "<td><div class='usage-disk'><span style='width:$free%' class='".usage_color($display,$free,true)."'></span><span>".my_scale($sum['fsFree']*1024,$unit)." $unit</span></div></td>";
    }
    echo "<td></td>";
  } else
    echo "<td colspan=4></td>";
  echo "</tr>";
}
function array_slots() {
  global $var;
  $min = max($var['sbNumDisks'], 3);
  $max = $var['MAX_ARRAYSZ'];
  $out = "<form method='POST' action='/update.htm' target='progressFrame'>";
  $out .= "<input type='hidden' name='csrf_token' value='{$var['csrf_token']}'>";
  $out .= "<input type='hidden' name='changeSlots' value='apply'>";
  $out .= "<select class='narrow' name='SYS_ARRAY_SLOTS' onChange='this.form.submit()'>";
  for ($n=$min; $n<=$max; $n++) {
    $selected = ($n == $var['SYS_ARRAY_SLOTS'])? ' selected' : '';
    $out .= "<option value='$n'{$selected}>$n</option>";
  }
  $out .= "</select></form>";
  return $out;
}
function cache_slots($off,$pool,$min,$slots) {
  global $var;
  $off = $off && $min ? ' disabled' : '';
  $max = $var['MAX_CACHESZ'];
  $out = "<form method='POST' action='/update.htm' target='progressFrame'>";
  $out .= "<input type='hidden' name='csrf_token' value='{$var['csrf_token']}'>";
  $out .= "<input type='hidden' name='changeSlots' value='apply'>";
  $out .= "<input type='hidden' name='poolName' value='$pool'>";
  $out .= "<select class='narrow' name='poolSlots' onChange='this.form.submit()'{$off}>";
  for ($n=$min; $n<=$max; $n++) {
    $option = $n ?: _('none');
    $selected = ($n==$slots)? ' selected' : '';
    $out .= "<option value='$n'{$selected}>$option</option>";
  }
  $out .= "</select></form>";
  return $out;
}
$crypto = false;
$pools  = pools_filter($disks);
$sum    = initSum();
switch ($_POST['device']) {
case 'array':
  $parity = parity_filter($disks);
  $data = data_filter($disks);
  foreach ($data as $disk) $crypto |= $disk['luksState']!=0 || vfs_luks($disk['fsType']);
  if ($var['fsState']=='Stopped') {
    foreach ($parity as $disk) array_offline($disk);
    echo "<tr class='tr_last'><td style='height:12px' colspan='11'></td></tr>";
    foreach ($data as $disk) array_offline($disk);
    echo "<tr class='tr_last'><td>"._('Slots').":</td><td colspan='9'>".array_slots()."</td><td></td></tr>";
  } else {
    foreach ($parity as $disk) if ($disk['status']!='DISK_NP_DSBL') array_online($disk);
    foreach ($data as $disk) array_online($disk);
    if ($display['total'] && $var['mdNumDisks']>1) show_totals(sprintf(_('Array of %s devices'),my_word($var['mdNumDisks'])),true,'array*');
  }
  break;
case 'flash':
  $disk = &$disks['flash'];
  $data = explode(' ',$diskio[$disk['device']] ?? '0 0');
  $flash = &$sec['flash']; $share = "";
  if ($var['shareSMBEnabled']=='yes' && $flash['export']=='e' && $flash['security']=='public')
    $share = "<a class='info'><i class='fa fa-warning fa-fw orange-text'></i><span>"._('Flash device is set as public share')."<br>"._('Please change share SMB security')."<br>"._('Click on <b>FLASH</b> above this message')."</span></a>";
  echo "<tr>";
  echo "<td>".$share.device_info($disk,true)."</td>";
  echo "<td>".device_desc($disk)."</td>";
  echo "<td>*</td>";
  echo "<td><span class='diskio'>".my_diskio($data[0])."</span><span class='number'>".my_number($disk['numReads'])."</span></td>";
  echo "<td><span class='diskio'>".my_diskio($data[1])."</span><span class='number'>".my_number($disk['numWrites'])."</span></td>";
  echo "<td>".my_number($disk['numErrors'])."</td>";
  fs_info($disk);
  echo "</tr>";
  break;
case 'cache':
  $cache  = cache_filter($disks);
  foreach ($pools as $pool) {
    $tmp = "/var/tmp/$pool.log.tmp";
    foreach ($cache as $disk) if (prefix($disk['name'])==$pool) $crypto |= $disk['luksState']!=0 || vfs_luks($disk['fsType']);
    if ($var['fsState']=='Stopped') {
      $log = @(array)parse_ini_file($tmp);
      $off = false;
      foreach ($cache as $disk) if (prefix($disk['name'])==$pool) {
        array_offline($disk,$pool);
        if (isset($log[$disk['name']])) $off |= ($log[$disk['name']]!=$disk['id']); else $log[$disk['name']] = $disk['id'];
      }
      $data = []; foreach ($log as $key => $value) $data[] = "$key=\"$value\"";
      file_put_contents($tmp,implode("\n",$data));
      echo "<tr class='tr_last'><td>"._('Slots').":</td><td colspan='9'>".cache_slots($off,$pool,$cache[$pool]['devicesSb'],$cache[$pool]['slots'])."</td><td></td></tr>\0";
    } else {
      if ($cache[$pool]['devices']) {
        foreach ($cache as $disk) if (prefix($disk['name'])==$pool) {
          if (substr($cache[$pool]['fsStatus'],0,11)=='Unmountable' && empty($disk['fsStatus'])) $disk['fsStatus'] = $cache[$pool]['fsStatus'];
          array_online($disk);
        }
        if ($display['total'] && $cache[$pool]['devices']>1) show_totals(sprintf(_('Pool of %s devices'),my_word($cache[$pool]['devices'])),false,"$pool*");
        $sum = initSum();
        echo "\0";
      }
      @unlink($tmp);
    }
  }
  break;
case 'open':
  foreach ($devs as $disk) {
    $dev = $disk['device'];
    $data = explode(' ',$diskio[$dev] ?? '0 0 0 0');
    $disk['type'] = 'New';
    $disk['color'] = $disk['spundown']=="0" ? 'blue-on' : 'blue-blink';
    echo "<tr>";
    echo "<td>".device_info($disk,true)."</td>";
    echo "<td>".device_desc($disk)."</td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td><span class='diskio'>".my_diskio($data[0])."</span><span class='number'>".my_number($disk['numReads'])."</span></td>";
    echo "<td><span class='diskio'>".my_diskio($data[1])."</span><span class='number'>".my_number($disk['numWrites'])."</span></td>";
    echo "<td>".my_number($disk['numErrors'])."</td>";
    if (file_exists("/tmp/preclear_stat_$dev")) {
      $text = exec("cut -d'|' -f3 /tmp/preclear_stat_$dev|sed 's:\^n:\<br\>:g'");
      if (strpos($text,'Total time')===false) $text = _('Preclear in progress').'... '.$text;
      echo "<td colspan='5' style='text-align:right'><em>$text</em></td>";
    } else
      echo "<td colspan='5'></td>";
    echo "</tr>";
  }
  break;
case 'parity':
  $data = [];
  if ($var['mdResyncPos']) {
    $data[] = my_scale($var['mdResyncSize']*1024,$unit,-1)." $unit";
    $data[] = _(my_clock(floor((time()-$var['sbSynced'])/60)),2).($var['mdResyncDt'] ? '' : ' ('._('paused').')');
    $data[] = my_scale($var['mdResyncPos']*1024,$unit)." $unit (".number_format(($var['mdResyncPos']/($var['mdResyncSize']/100+1)),1,$display['number'][0],'')." %)";
    $data[] = $var['mdResyncDt'] ? my_scale($var['mdResyncDb']*1024/$var['mdResyncDt'],$unit, 1)." $unit/sec" : '---';
    $data[] = $var['mdResyncDb'] ? _(my_clock(round(((($var['mdResyncDt']*(($var['mdResyncSize']-$var['mdResyncPos'])/($var['mdResyncDb']/100+1)))/100)/60),0)),2) : _('Unknown');
    $data[] = $var['sbSyncErrs'];
    echo implode(';',$data);
  } else {
    if ($var['sbSynced']==0 || $var['sbSynced2']==0) break;
    $log = '/boot/config/parity-checks.log';
    $timestamp = str_replace(['.0','.'],['  ',' '],date('M.d H:i:s',$var['sbSynced2']));
    if (in_parity_log($log,$timestamp)) break;
    $duration = $var['sbSynced2'] - $var['sbSynced'];
    $status = $var['sbSyncExit'];
    $speed = ($status==0) ? my_scale($var['mdResyncSize']*1024/$duration,$unit,1)." $unit/s" : _('Unavailable');
    $error = $var['sbSyncErrs'];
    $year = date('Y',$var['sbSynced2']);
    if ($status==0||file_exists($log)) file_put_contents($log,"$year $timestamp|$duration|$speed|$status|$error\n",FILE_APPEND);
  }
  break;
}
?>
