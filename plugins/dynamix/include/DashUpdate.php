<?PHP
/* Copyright 2005-2018, Lime Technology
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

function normalize($type,$count) {
  $words = explode('_',$type);
  foreach ($words as &$word) $word = $word==strtoupper($word) ? $word : preg_replace(['/^(ct|cnt)$/','/^blk$/'],['count','block'],strtolower($word));
  return ucfirst(implode(' ',$words)).": ".str_replace('_',' ',strtolower($count))."\n";
}
function my_unit($value,$unit) {
  return ($unit=='F' ? round(9/5*$value+32) : $value)." $unit";
}
function my_clock($time) {
  if (!$time) return 'less than a minute';
  $days = floor($time/1440);
  $hour = $time/60%24;
  $mins = $time%60;
  return plus($days,'day',($hour|$mins)==0).plus($hour,'hour',$mins==0).plus($mins,'minute',true);
}
function parity_disks($disk) {
  return $disk['type']=='Parity';
}
function active_disks($disk) {
  return substr($disk['status'],0,7)!='DISK_NP' && in_array($disk['type'],['Parity','Data','Cache']);
}
function find_day($D) {
  global $days;
  if ($days[0] == '*') return $D;
  foreach ($days as $d) if ($d >= $D) return $d;
  return $days[0];
}
function find_month($M) {
  global $months, $Y;
  if ($M > 12) {$M = 1; $Y++;}
  if ($months[0] == '*') return $M;
  foreach ($months as $m) if ($m >= $M) return $m;
  return $months[0];
}
function today($D) {
  global $days, $M, $Y;
  if ($days[0]=='*') return date('w',mktime(0,0,0,$M,$D,$Y));
  for ($d = $D; $d < $D+7; $d++) {
    $day = date('w',mktime(0,0,0,$M,$d,$Y));
    if (in_array($day,$days)) return $day;
  }
}
function next_day($D) {
  return find_day(($D+1)%7);
}
function last_day() {
  global $M, $Y;
  return date('t',mktime(0,0,0,$M,1,$Y));
}
function mkdate($D, $s) {
  global $M, $Y;
  if ($s > last_day()) {$s = 1; $M = find_month($M+1);}
  for ($d = $s; $d < $s+7; $d++) if ($D == date('w',mktime(0,0,0,$M,$d,$Y))) return $d;
}
function stage($i) {
  global $h, $m, $D, $M, $Y, $time, $now;
  if ($i < 0) {
    $d = $now ? $D : today(1);
    $s = $now ? date('j',$time) : 1;
    $D = mkdate($d, $s);
    $t = mktime($h,$m,0,$M,$D,$Y)-$time; // first day
    if ($t < 0) {
      $D = mkdate(next_day($d), $s+1);
      $t = mktime($h,$m,0,$M,$D,$Y)-$time; // next day
    }
    if ($t < 0) {
      $s += 7;
      if ($s > last_day()) {
        $s -= last_day();
        $M = find_month($M+1);
      }
      $D = mkdate(today($d), $s);
      $t = mktime($h,$m,0,$M,$D,$Y)-$time; // next week
    }
  } else {
    $d = $i ? ($now ? $D : today($i)) : today(last_day()-6);
    $s = $i ?: last_day()-6;
    $D = mkdate($d, $s);
    $t = mktime($h,$m,0,$M,$D,$Y)-$time; // first day
    if ($t < 0) {
      $D = mkdate(next_day($d), $s);
      $t = mktime($h,$m,0,$M,$D,$Y)-$time; // next day
    }
    if ($t < 0) {
      $M = find_month($M+1);
      $s = $i ?: last_day()-6;
      $D = mkdate(today($s), $s);
      $t = mktime($h,$m,0,$M,$D,$Y)-$time; // next month
    }
    if ($t < 0) {
      $Y++;
      $M = find_month(1);
      $s = $i ?: last_day()-6;
      $D = mkdate(today($s), $s);
      $t = mktime($h,$m,0,$M,$D,$Y)-$time; // next year
    }
  }
  return $t;
}
function device_name(&$disk, $array) {
  if ($array) {
    switch ($disk['type']) {
      case 'Flash' : $type = 'usb'; break;
      case 'Parity': $type = $disk['rotational'] ? 'disk' : 'nvme'; break;
      case 'Data'  :
      case 'Cache' : $type = $disk['rotational'] ? ($disk['luksState'] ? 'disk-encrypted' : 'disk') : 'nvme'; break;
    }
    return "<i class='icon-$type'></i> ".my_disk($disk['name']);
  } else {
    return "<i class='icon-disk'></i> {$disk['device']}";
  }
}
function device_status(&$disk, &$error, &$warning) {
  global $var;
  if ($var['fsState']=='Stopped') {
    $color = 'green'; $text = 'off-line';
  } else switch ($disk['color']) {
    case 'green-on'    : $color = 'green';  $text = 'active';     break;
    case 'green-blink' : $color = 'grey';   $text = 'standby';    break;
    case 'blue-on'     : $color = 'blue';   $text = 'unassigned'; break;
    case 'blue-blink'  : $color = 'grey';   $text = 'unassigned'; break;
    case 'yellow-on'   : $color = 'yellow'; $text = 'emulated';   $warning++; break;
    case 'yellow-blink': $color = 'grey';   $text = 'emulated';   $warning++; break;
    case 'red-on'      : $color = 'red';    $text = 'disabled';   $error++; break;
    case 'red-blink'   : $color = 'grey';   $text = 'disabled';   $error++; break;
    case 'red-off'     : $color = 'red';    $text = 'faulty';     $error++; break;
    case 'grey-off'    : $color = 'grey';   $text = 'no device';  break;
  }
  return "<i class='fa fa-circle orb $color-orb middle'></i>$text";
}
function device_temp(&$disk, &$red, &$orange) {
  $spin = strpos($disk['color'],'blink')===false;
  $temp = $disk['temp'];
  $hot = $disk['hotTemp'] ?? $_POST['hot'];
  $max = $disk['maxTemp'] ?? $_POST['max'];
  $top = $_POST['top'] ?? 120;
  $heat = false; $color = 'green';
  if (exceed($temp,$max,$top)) {
    $heat = 'fire'; $color = 'red'; $red++;
  } elseif (exceed($temp,$hot,$top)) {
    $heat = 'fire'; $color = 'orange'; $orange++;
  }
  return ($spin && $temp>0) ? "<span class='$color-text'>".my_unit($temp,$_POST['unit'])."</span>".($heat ? "<i class='fa fa-$heat $color-text heat'></i>" : "") : "*";
}
function device_smart(&$disk, $name, &$fail, &$smart) {
  global $numbers,$saved;
  if (!$disk['device'] || strpos($disk['color'],'blink')) return "-";
  $failed = ['FAILED','NOK'];
  $page   = $name ? 'New' : 'Device';
  $name   = $name ?: $disk['name'];
  $select = get_value($name,'smSelect',0);
  $level  = get_value($name,'smLevel',1);
  $events = explode('|',get_value($disk,'smEvents',$numbers));
  $title  = '';
  $thumb  = 'thumbs-o-up';
  $text   = 'healthy';
  $color  = 'green';
  $file   = "state/smart/$name";
  if (file_exists("$file.ssa") && in_array(file_get_contents("$file.ssa"),$failed)) {
    $title = "S.M.A.R.T health-check failed\n"; $thumb = 'thumbs-o-down'; $color = 'red'; $text = 'fail'; $fail++;
  } else {
    if (empty($saved["smart"]["$name.ack"])) {
      exec("awk 'NR>7{print $1,$2,$4,$6,$9,$10}' ".escapeshellarg($file)." 2>/dev/null", $codes);
      foreach ($codes as $code) {
        if (!$code || !is_numeric($code[0])) continue;
        list($id,$class,$value,$thres,$when,$raw) = explode(' ',$code);
        $failing = strpos($when,'FAILING_NOW')!==false;
        if (!$failing && !in_array($id,$events)) continue;
        if ($failing || ($select ? $thres>0 && $value<=$thres*$level : $raw>0)) $title .= normalize($class,$failing?$when:$raw);
      }
      if ($title) {$thumb = 'thumbs-o-down'; $color = 'orange'; $text = 'error'; $smart++;} else $title = "No errors reported\n";
    }
  }
  $title .= "Click for context menu";
  return "<span id='smart-$name' name='$page' class='fa fa-$thumb $color-text' style='margin-right:8px' onmouseover='this.style.cursor=\"pointer\"' title='$title'></span>$text";
}
function device_usage(&$disk, $array, &$full, &$high) {
  if ($array) {
    $text = $_POST['text'];
    $used = ($disk['type']!='Parity' && $disk['fsStatus']=='Mounted') ? (($disk['fsSize'] ? round((1-$disk['fsFree']/$disk['fsSize'])*100):0).'%') : false;
    if ($used && ($text==2 || $text==21)) {
      $load = substr($used,0,-1);
      $critical = $disk['critical'] ?? $_POST['critical'];
      $warning = $disk['warning'] ?? $_POST['warning'];
      if ($load >= $critical) {$class = 'redbar'; $full++;}
      elseif ($load >= $warning) {$class = 'orangebar'; $high++;}
      else $class = 'greenbar';
    } else $class = false;
  } else $used = false;
  if ($used) {
    return $text%10==0 ? $used : "<span class='load'>$used</span><div class='usage-disk sys'><span style='width:$used'".($class?" class='$class'":"")."></span></div>";
  } else {
    return $text%10==0 ? "-" : "<span class='load'>-</span><div class='usage-disk sys none'></div>";
  }
}
function array_group($type) {
  global $disks,$error,$warning,$red,$orange,$fail,$smart,$full,$high;
  foreach ($disks as $disk) if ($disk['type']==$type && strpos($disk['status'],'DISK_NP')===false) {
    echo "<tr><td></td>";
    echo "<td>".device_name($disk,true)."</td>";
    echo "<td>".device_status($disk,$error,$warning)."</td>";
    echo "<td>".device_temp($disk,$red,$orange)."</td>";
    echo "<td>".device_smart($disk,false,$fail,$smart)."</td>";
    echo "<td>".device_usage($disk,true,$full,$high)."</td>";
    echo "<td></td></tr>";
  }
}
function extra_group(&$disks) {
  global $disks,$error,$warning,$red,$orange,$fail,$smart,$full,$high;
  foreach ($disks as $disk) {
    $name = $disk['device'];
    $port = port_name($name);
    $smart = "state/smart/$name";
    $disk['color'] = exec("hdparm -C /dev/$port|grep -Po 'active|unknown'") ? 'blue-on' : 'blue-blink';
    $disk['temp'] = file_exists($smart) ? exec("awk 'BEGIN{s=t=\"*\"}\$1==190{s=\$10};\$1==194{t=\$10;exit};\$1==\"Temperature:\"{t=\$2;exit};/^Current Drive Temperature:/{t=\$4;exit} END{if(t!=\"*\")print t; else print s}' $smart") : '*';
    echo "<tr><td></td>";
    echo "<td>".device_name($disk,false)."</td>";
    echo "<td>".device_status($disk,$error,$warning)."</td>";
    echo "<td>".device_temp($disk,$red,$orange)."</td>";
    echo "<td>".device_smart($disk,$name,$fail,$smart)."</td>";
    echo "<td>".device_usage($disk,false,$full,$high)."</td>";
    echo "<td></td></tr>";
  }
}
switch ($_POST['cmd']) {
case 'array':
  $var = parse_ini_file('state/var.ini');
  $disks = array_filter(parse_ini_file('state/disks.ini',true),'active_disks');
  $saved = @parse_ini_file('state/monitor.ini',true) ?: [];
  require_once "$docroot/webGui/include/CustomMerge.php";
  require_once "$docroot/webGui/include/Preselect.php";
  $error = $warning = $red = $orange = $fail = $smart = $full = $high = 0;
  array_group('Parity');
  array_group('Data');
  echo "\0".($error+$warning)."\0".($red+$orange)."\0".($fail+$smart)."\0".($full+$high);
  break;
case 'cache':
  $var = parse_ini_file('state/var.ini');
  $disks = array_filter(parse_ini_file('state/disks.ini',true),'active_disks');
  $saved = @parse_ini_file('state/monitor.ini',true) ?: [];
  require_once "$docroot/webGui/include/CustomMerge.php";
  require_once "$docroot/webGui/include/Preselect.php";
  $error = $warning = $red = $orange = $fail = $smart = $full = $high = 0;
  array_group('Cache');
  echo "\0".($error+$warning)."\0".($red+$orange)."\0".($fail+$smart)."\0".($full+$high);
  break;
case 'extra':
  $var = parse_ini_file('state/var.ini');
  $devs = parse_ini_file('state/devs.ini',true);
  $saved = @parse_ini_file('state/monitor.ini',true) ?: [];
  require_once "$docroot/webGui/include/CustomMerge.php";
  require_once "$docroot/webGui/include/Preselect.php";
  $error = $warning = $red = $orange = $fail = $smart = $full = $high = 0;
  extra_group($devs);
  echo "\0".($error+$warning)."\0".($red+$orange)."\0".($fail+$smart)."\0".($full+$high);
  break;
case 'sys':
  exec("grep -Po '^Mem(Total|Available):\s+\K\d+' /proc/meminfo",$memory);
  exec("df /boot /var/log /var/lib/docker|grep -Po '\d+%'",$sys);
  $mem = max(round((1-$memory[1]/$memory[0])*100),0);
  echo "{$mem}%\0".implode("\0",$sys);
  break;
case 'fan':
  exec("sensors -uA 2>/dev/null|grep -Po 'fan\d_input: \K\d+'",$rpms);
  if ($rpms) echo implode(" RPM\0",$rpms).' RPM';
  break;
case 'port':
  $i = 0;
  $ports = explode(',',$_POST['ports']);
  switch ($_POST['view']) {
  case 'main':
    foreach ($ports as $port) {
      $int = "/sys/class/net/$port";
      $mtu = file_get_contents("$int/mtu");
      $link = file_get_contents("$int/carrier")==1;
      if (substr($port,0,4)=='bond') {
        if ($link) {
          $bond_mode = str_replace('Bonding Mode: ','',file("/proc/net/bonding/$port",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)[1]);
          $ports[$i++] = "$bond_mode, mtu $mtu";
        } else $ports[$i++] = "bond down";
      } elseif ($port=='lo') {
        $ports[$i++] = $link ? "loopback" : "not set";
      } else {
        if ($link) {
          $speed = file_get_contents("$int/speed");
          $duplex = file_get_contents("$int/duplex");
          $ports[$i++] = "$speed Mbps, $duplex duplex, mtu $mtu";
        } else $ports[$i++] = "interface down";
      }
    }
    break;
  case 'port':
    foreach ($ports as $port) {
      $int = "/sys/class/net/$port";
      $rx_bytes = file_get_contents("$int/statistics/rx_bytes");
      $tx_bytes = file_get_contents("$int/statistics/tx_bytes");
      $ports[$i++] = "{$rx_bytes}\0{$tx_bytes}";
    }
    break;
  case 'link':
    foreach ($ports as $port) {
      $int = "/sys/class/net/$port";
      $rx_errors = file_get_contents("$int/statistics/rx_errors");
      $rx_drops  = file_get_contents("$int/statistics/rx_dropped");
      $rx_fifo   = file_get_contents("$int/statistics/rx_fifo_errors");
      $tx_errors = file_get_contents("$int/statistics/tx_errors");
      $tx_drops  = file_get_contents("$int/statistics/tx_dropped");
      $tx_fifo   = file_get_contents("$int/statistics/tx_fifo_errors");
      $ports[$i++] = "Errors: {$rx_errors}<br>Drops: {$rx_drops}<br>Overruns: {$rx_fifo}\0Errors: {$tx_errors}<br>Drops: {$tx_drops}<br>Overruns: {$tx_fifo}";
    }
    break;
  }
  echo implode("\0",$ports);
  break;
case 'speed':
  $int      = "/sys/class/net/{$_POST['port']}";
  $rx_new   = (float)file_get_contents("$int/statistics/rx_bytes");
  $tx_new   = (float)file_get_contents("$int/statistics/tx_bytes");
  $time_new = microtime(true);
  $time_old = (float)$_POST['timestamp'];
  $rx_old   = (float)$_POST['rx_bytes'];
  $tx_old   = (float)$_POST['tx_bytes'];
  if ($time_old) {
    $rx_speed = my_scale(($rx_new-$rx_old)/($time_new-$time_old)*8,$unit,1,-1).' '.str_replace('B','b',$unit).'ps';
    $tx_speed = my_scale(($tx_new-$tx_old)/($time_new-$time_old)*8,$unit,1,-1).' '.str_replace('B','b',$unit).'ps';
  } else $rx_speed = $tx_speed = '---';
  echo "$rx_speed\0$tx_speed\0$time_new\0$rx_new\0$tx_new";
  break;
case 'status':
  $var = parse_ini_file("state/var.ini");
  $disks = array_filter(parse_ini_file('state/disks.ini',true),'parity_disks');
  $parity_slots = count($disks);
  $parity_disabled = $parity_invalid  = 0;
  foreach ($disks as $disk) {
    if (strpos($disk['status'],"DISK_NP")===0) $parity_disabled++;
    elseif (strpos($disk['status'],"DISK_INVALID")===0) $parity_invalid++;
  }
  if ($var['mdResync']==0) {
    if ($parity_slots==$parity_disabled) {
      echo "<span class='red'>Parity disk".($parity_slots==1?'':'s')." not present</span>";
    } elseif ($parity_slots > $parity_invalid) {
      if ($parity_invalid==0) {
        echo "<span class='green'>Parity is valid</span>";
      } else {
        echo "<span class='orange'>Parity is degraded: $parity_invalid invalid device".($parity_invalid==1?'':'s')."</span>";
      }
    } else {
      if (empty($var['mdInvalidDisk'])) {
        echo "<span class='red strong'>Parity is invalid</span>";
      } else {
        echo "<span class='red strong'>Data is invalid</span>";
      }
    }
  } else {
    $mode = '';
    $number = $_POST['number'] ?? '.,';
    if (strstr($var['mdResyncAction'],"recon")) {
      $mode = 'Parity-Sync/Data-Rebuild';
    } elseif (strstr($var['mdResyncAction'],"clear")) {
      $mode = 'Clearing';
    } elseif ($var['mdResyncAction']=="check") {
      $mode = 'Read-Check';
    } elseif (strstr($var['mdResyncAction'],"check")) {
      $mode = 'Parity-Check';
    }
    echo "<span class='orange'>$mode in progress... Completed: ".number_format(($var['mdResyncPos']/($var['mdResync']/100+1)),1,$number[0],$number[1])." %.</span>";
  }
  break;
case 'parity':
  $var  = parse_ini_file("state/var.ini");
  $time = $_POST['time'];
  $idle = $var['mdResync']==0;
  if ($var['sbSyncExit']!=0) {
    echo "Last check incomplete on <strong>".my_time($var['sbSynced2'],$time).day_count($var['sbSynced2'])."</strong>, finding <strong>{$var['sbSyncErrs']}</strong> error".($var['sbSyncErrs']==1?'.':'s.');
    echo "<br><i class='fa fa-dot-circle-o'></i> Error code: ".my_error($var['sbSyncExit']);
  } elseif ($var['sbSynced']==0) {
    list($date,$duration,$speed,$status,$error) = last_parity_log();
    if (!$date) {
      echo "Parity has not been checked yet.";
    } elseif ($status==0) {
      echo "Last checked on <strong>".my_time($date).day_count($date,$time)."</strong>, finding <strong>$error</strong> error".($error==1?'.':'s.');
      echo "<br><i class='fa fa-clock-o'></i> Duration: ".my_check($duration,$speed);
    } else {
      echo "Last check incomplete on <strong>".my_time($date,$time).day_count($date)."</strong>, finding <strong>$error</strong> error".($error==1?'.':'s.');
      echo "<br><i class='fa fa-dot-circle-o'></i> Error code: ".my_error($status);
    }
  } elseif ($var['sbSynced2']==0) {
    if ($idle) {
      list($entry,$duration,$speed,$status,$error) = explode('|', read_parity_log($var['sbSynced'],!$idle));
      if ($status==0) {
        echo "Last checked on <strong>".my_time($var['sbSynced'],$time).day_count($var['sbSynced'])."</strong>, finding <strong>$error</strong> error".($error==1?'.':'s.');
        echo "<br><i class='fa fa-clock-o'></i> Duration: ".my_check($duration,$speed);
      } else {
        echo "Last check incomplete on <strong>".my_time($var['sbSynced'],$time).day_count($var['sbSynced'])."</strong>, finding <strong>$error</strong> error".($error==1?'.':'s.');
        echo "<br><i class='fa fa-dot-circle-o'></i> Error code: ".my_error($status);
      }
    } else {
      echo "Activity started on <strong>".my_time($var['sbSynced'],$time).day_count($var['sbSynced'])."</strong>, finding <span id='errors'><strong>{$var['sbSyncErrs']}</strong> error".($var['sbSyncErrs']==1?'.':'s.')."</span>";
      echo "<br><i class='fa fa-clock-o'></i> Elapsed time: ".my_clock(floor((time()-$var['sbUpdated'])/60))."<span class='finish'><i class='fa fa-flag-checkered'></i> Estimated finish: ".my_clock(round(((($var['mdResyncDt']*(($var['mdResync']-$var['mdResyncPos'])/($var['mdResyncDb']/100+1)))/100)/60),0))."</span>";
    }
  } else {
    $status = 0;
    $duration = $var['sbSynced2']-$var['sbSynced'];
    $speed = $duration?my_scale($var['mdResyncSize']*1024/$duration,$unit,1)." $unit/sec":'';
    echo "Last checked on <strong>".my_time($var['sbSynced2'],$time).day_count($var['sbSynced2'])."</strong>, finding <strong>{$var['sbSyncErrs']}</strong> error".($var['sbSyncErrs']==1?'.':'s.');
    echo "<br><i class='fa fa-clock-o'></i> Duration: ".my_check($duration,$speed);
  }
  if ($idle) {
    extract(parse_plugin_cfg('dynamix', true));
    list($m,$h) = explode(' ', $parity['hour']);
    $time = time();
    switch ($parity['mode']) {
    case 0: // check disabled
      echo "\0";
      echo "<i class='fa fa-warning'></i> Scheduled parity check is disabled";
      return;
    case 1: // daily check
      $t = mktime($h,$m,0)-$time;
      if ($t < 0) $t += 86400;
      break;
    case 2: // weekly check
      $t = $parity['day']-date('w',$time);
      if ($t < 0) $t += 7;
      $t = mktime($h,$m,0)+$t*86400-$time;
      if ($t < 0) $t += 86400*7;
      break;
    case 3: // monthly check
      $D = $parity['dotm'];
      $M = date('n',$time);
      $Y = date('Y',$time);
      $last = ($D == '28-31');
      if ($last) $D = last_day();
      $t = mktime($h,$m,0,$M,$D,$Y)-$time;
      if ($t < 0) {
        if ($M < 12) $M++; else {$M = 1; $Y++;}
        if ($last) $D = last_day();
        $t = mktime($h,$m,0,$M,$D,$Y)-$time;
      }
      break;
    case 4: // yearly check
      $D = $parity['dotm'];
      $M = $parity['month'];
      $Y = date('Y',$time);
      $last = ($D == '28-31');
      if ($last) $D = last_day();
      $t = mktime($h,$m,0,$M,$D,$Y)-$time;
      if ($t < 0) {
        $Y++;
        if ($last) $D = last_day();
        $t = mktime($h,$m,0,$M,$D,$Y)-$time;
      }
      break;
    case 5: // custom check
      $days = explode(',',$parity['day']);
      $months = explode(',',$parity['month']);
      $today = date('w',$time);
      $date = date('n',$time);
      $D = find_day($today);
      $M = find_month($date);
      $Y = date('Y',$time);
      $now = $M==$date;
      if ($M < $date) $Y++;
      switch ($parity['dotm']) {
      case '*' : $t = stage(-1); break;
      case 'W1': $t = stage(1); break;
      case 'W2': $t = stage(8); break;
      case 'W3': $t = stage(15); break;
      case 'W4': $t = stage(22); break;
      case 'WL': $t = stage(0); break;}
      break;
    }
    echo "\0";
    echo "Next check scheduled on <strong>";
    echo strftime($_POST['time'],$time+$t);
    echo "</strong><br><i class='fa fa-clock-o'></i> Due in: ";
    echo my_clock(floor($t/60));
  } else {
    echo "\0";
  }
  break;
case 'shares':
  $names = explode(',',$_POST['names']);
  switch ($_POST['com']) {
  case 'smb':
    exec("LANG='en_US.UTF8' lsof -Owl /mnt/disk[0-9]* 2>/dev/null|awk '/^shfs/ && \$0!~/\.AppleD(B|ouble)/ && \$5==\"REG\"'|awk -F/ '{print \$4}'",$lsof);
    $counts = array_count_values($lsof); $count = [];
    foreach ($names as $name) $count[] = $counts[$name] ?? 0;
    echo implode("\0",$count);
    break;
  }
  break;
}
