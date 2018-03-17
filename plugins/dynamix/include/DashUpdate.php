<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
require_once "$docroot/webGui/include/Wrappers.php";

function normalize($type,$count) {
  $words = explode('_',$type);
  foreach ($words as &$word) $word = $word==strtoupper($word) ? $word : preg_replace(['/^(ct|cnt)$/','/^blk$/'],['count','block'],strtolower($word));
  return ucfirst(implode(' ',$words)).": ".str_replace('_',' ',strtolower($count))."\n";
}
function my_insert(&$source,$string) {
  $source = substr_replace($source,$string,4,0);
}
function my_smart(&$source,$name,$page) {
  global $var,$disks,$path,$failed,$numbers,$saved;
  $disk   = &$disks[$name];
  $select = get_value($disk,'smSelect',0);
  $level  = get_value($disk,'smLevel',1);
  $events = explode('|',get_value($disk,'smEvents',$numbers));
  $title  = '';
  $thumb  = 'good';
  $smart  = "state/smart/$name";
  if (file_exists("$smart.ssa") && in_array(file_get_contents("$smart.ssa"),$failed)) {
    $title = "S.M.A.R.T health-check failed\n"; $thumb = 'bad';
  } else {
    if (empty($saved["smart"]["$name.ack"])) {
      exec("awk 'NR>7{print $1,$2,$4,$6,$9,$10}' ".escapeshellarg($smart)." 2>/dev/null", $codes);
      foreach ($codes as $code) {
        if (!$code || !is_numeric($code[0])) continue;
        list($id,$class,$value,$thres,$when,$raw) = explode(' ',$code);
        $fail = strpos($when,'FAILING_NOW')!==false;
        if (!$fail && !in_array($id,$events)) continue;
        if ($fail || ($select ? $thres>0 && $value<=$thres*$level : $raw>0)) $title .= normalize($class,$fail?$when:$raw);
      }
      if ($title) $thumb = 'alert'; else $title = "No errors reported\n";
    }
  }
  $title .= "Click for context menu";
  my_insert($source, "<span id='smart-$name' name='$page' class='$thumb'><img src=\"$path/$thumb.png\" onmouseover=\"this.style.cursor='pointer'\" title=\"$title\"></span>");
}
function my_usage(&$source,$used) {
  my_insert($source, $used ? "<div class='usage-disk all'><span style='width:$used'>$used</span></div>" : "-");
}
function my_temp($value,$unit) {
  return ($unit=='F' ? round(9/5*$value+32) : $value)." $unit";
}
function my_clock($time) {
  if (!$time) return 'less than a minute';
  $days = floor($time/1440);
  $hour = $time/60%24;
  $mins = $time%60;
  return plus($days,'day',($hour|$mins)==0).plus($hour,'hour',$mins==0).plus($mins,'minute',true);
}
function plus($val,$word,$last) {
  return $val>0?(($val||$last)?($val.' '.$word.($val!=1?'s':'').($last ?'':', ')):''):'';
}
function active_disks($disk) {
  return substr($disk['status'],0,7)!='DISK_NP' && preg_match('/^(Parity|Data|Cache)$/',$disk['type']);
}
$path   = '/webGui/images';
$failed = ['FAILED','NOK'];
switch ($_POST['cmd']) {
case 'disk':
  $i = 1;
  $var = parse_ini_file('state/var.ini');
  $disks = array_filter(parse_ini_file('state/disks.ini',true),'active_disks');
  $devs = parse_ini_file('state/devs.ini',true);
  $saved = @parse_ini_file('state/monitor.ini',true) ?: [];
  require_once "$docroot/webGui/include/CustomMerge.php";
  require_once "$docroot/webGui/include/Preselect.php";
  $slots = $_POST['slots'];
  $row1 = array_fill(0,31,'<td></td>'); my_insert($row1[0],'Encrypted');
  $row2 = array_fill(0,31,'<td></td>'); my_insert($row2[0],'Active');
  $row3 = array_fill(0,31,'<td></td>'); my_insert($row3[0],'Inactive');
  $row4 = array_fill(0,31,'<td></td>'); my_insert($row4[0],'Unassigned');
  $row5 = array_fill(0,31,'<td></td>'); my_insert($row5[0],'Faulty');
  $row6 = array_fill(0,31,'<td></td>'); my_insert($row6[0],'Heat alarm');
  $row7 = array_fill(0,31,'<td></td>'); my_insert($row7[0],'SMART status');
  $row8 = array_fill(0,31,'<td></td>'); my_insert($row8[0],'Utilization');
  $diskRow = function($n,$disk) use (&$row1,&$row2,&$row3,&$row4,&$row5,&$row6,&$row7,&$row8,$path,$var) {
    if ($n>0) {
      if (isset($disk['luksState'])) {
        switch ($disk['luksState']) {
          case 0: $luks = strpos($disk['fsType'],'luks:')===false ? "" : "<i class='fa fa-unlock orange-text'></i>"; break;
          case 1: if ($var['fsState']!='Stopped') {$luks = "<i class='fa fa-unlock-alt green-text'></i>"; break;}
          case 2: $luks = "<i class='fa fa-lock green-text'></i>"; break;
          case 3: $luks = "<i class='fa fa-lock red-text'></i>"; break;
         default: $luks = "<i class='fa fa-lock red-text'></i>"; break;
        }
      } else $luks = "";
      my_insert($row1[$n],$luks);
      $state = $disk['color'];
      switch ($state) {
      case 'grey-off':
      break; //ignore
      case 'green-on':
        my_insert($row2[$n],"<img src=$path/$state.png>");
      break;
      case 'green-blink':
        my_insert($row3[$n],"<img src=$path/$state.png>");
      break;
      case 'blue-on':
      case 'blue-blink':
        my_insert($row4[$n],"<img src=$path/$state.png>");
      break;
      default:
        my_insert($row5[$n],"<img src=$path/$state.png>");
      break;}
      $temp = $disk['temp'];
      $hot = $disk['hotTemp'] ?? $_POST['hot'];
      $max = $disk['maxTemp'] ?? $_POST['max'];
      $top = $_POST['top'] ?? 120;
      $heat = exceed($temp,$max,$top) ? 'max' : (exceed($temp,$hot,$top) ? 'hot' : '');
      if ($heat)
        my_insert($row6[$n],"<span class='heat-img'><img src='$path/$heat.png'></span><span class='heat-text' style='display:none'>".my_temp($temp,$_POST['unit'])."</span>");
      else
        if (!strpos($state,'blink') && $temp>0) my_insert($row6[$n],"<span class='temp-text'>".my_temp($temp,$_POST['unit'])."</span>");
      if ($disk['device'] && !strpos($state,'blink')) my_smart($row7[$n],$disk['name'],'Device');
      my_usage($row8[$n],($disk['type']!='Parity' && $disk['fsStatus']=='Mounted')?(($disk['fsSize'] ? round((1-$disk['fsFree']/$disk['fsSize'])*100):0).'%'):'');
    }
  };
  $devRow = function($n,$disk) use (&$row4,&$row6,&$row7,$path) {
    $hot = $_POST['hot'];
    $max = $_POST['max'];
    $top = $_POST['top'] ?? 120;
    $name = $disk['device'];
    $port = substr($name,-2)!='n1' ? $name : substr($name,0,-2);
    $smart = "state/smart/$name";
    $state = exec("hdparm -C ".escapeshellarg("/dev/$port")."|grep -Po 'active|unknown'") ? 'blue-on' : 'blue-blink';
    if ($state=='blue-on') my_smart($row7[$n],$name,'New');
    $temp = file_exists($smart) ? exec("awk 'BEGIN{s=t=\"*\"}\$1==190{s=\$10};\$1==194{t=\$10;exit};\$1==\"Temperature:\"{t=\$2;exit};/^Current Drive Temperature:/{t=\$4;exit} END{if(t!=\"*\")print t; else print s}' ".escapeshellarg($smart)) : '*';
    $heat = exceed($temp,$max,$top) ? 'max' : (exceed($temp,$hot,$top) ? 'hot' : '');
    if ($heat)
      my_insert($row6[$n],"<span class='heat-img'><img src='$path/$heat.png'></span><span class='heat-text' style='display:none'>".my_temp($temp,$_POST['unit'])."</span>");
    else
      if ($state=='blue-on' && $temp>0) my_insert($row6[$n],"<span class='temp-text'>".my_temp($temp,$_POST['unit'])."</span>");
    my_insert($row4[$n],"<img src=$path/$state.png>");
  };
  foreach ($disks as $disk) if ($disk['type']=='Parity') $diskRow($i++,$disk);
  foreach ($disks as $disk) if ($disk['type']=='Data') $diskRow($i++,$disk);
  if ($slots <= 30) {
    foreach ($disks as $disk) if ($disk['type']=='Cache') $diskRow($i++,$disk);
    foreach ($devs as $dev) $devRow($i++,$dev);
  }
  echo "<tr>".implode('',$row1)."</tr>";
  echo "<tr>".implode('',$row2)."</tr>";
  echo "<tr>".implode('',$row3)."</tr>";
  echo "<tr>".implode('',$row4)."</tr>";
  echo "<tr>".implode('',$row5)."</tr>";
  echo "<tr>".implode('',$row6)."</tr>";
  echo "<tr>".implode('',$row7)."</tr>";
  echo "<tr>".implode('',$row8)."</tr>";
  if ($slots > 30) {
    echo '#'; $i = 1;
    $row1 = array_fill(0,31,'<td></td>'); my_insert($row1[0],'Encrypted');
    $row2 = array_fill(0,31,'<td></td>'); my_insert($row2[0],'Active');
    $row3 = array_fill(0,31,'<td></td>'); my_insert($row3[0],'Inactive');
    $row4 = array_fill(0,31,'<td></td>'); my_insert($row4[0],'Unassigned');
    $row5 = array_fill(0,31,'<td></td>'); my_insert($row5[0],'Faulty');
    $row6 = array_fill(0,31,'<td></td>'); my_insert($row6[0],'Heat alarm');
    $row7 = array_fill(0,31,'<td></td>'); my_insert($row7[0],'SMART status');
    $row8 = array_fill(0,31,'<td></td>'); my_insert($row8[0],'Utilization');
    foreach ($disks as $disk) if ($disk['type']=='Cache') $diskRow($i++,$disk);
    foreach ($devs as $dev) $devRow($i++,$dev);
    echo "<tr>".implode('',$row1)."</tr>";
    echo "<tr>".implode('',$row2)."</tr>";
    echo "<tr>".implode('',$row3)."</tr>";
    echo "<tr>".implode('',$row4)."</tr>";
    echo "<tr>".implode('',$row5)."</tr>";
    echo "<tr>".implode('',$row6)."</tr>";
    echo "<tr>".implode('',$row7)."</tr>";
    echo "<tr>".implode('',$row8)."</tr>";
  }
break;
case 'sys':
  exec("grep -Po '^Mem(Total|Available):\s+\K\d+' /proc/meminfo",$memory);
  exec("df /boot /var/log /var/lib/docker|grep -Po '\d+%'",$sys);
  $mem = max(round((1-$memory[1]/$memory[0])*100),0);
  echo "{$mem}%#".implode('#',$sys);
break;
case 'fan':
  exec("sensors -uA 2>/dev/null|grep -Po 'fan\d_input: \K\d+'",$rpms);
  if ($rpms) echo implode(' RPM#',$rpms).' RPM';
break;
case 'port':
  switch ($_POST['view']) {
  case 'main':
    $ports = explode(',',$_POST['ports']); $i = 0;
    foreach ($ports as $port) {
      $mtu = file_get_contents("/sys/class/net/$port/mtu");
      if (substr($port,0,4)=='bond') {
        $ports[$i++] = exec("grep -Pom1 '^Bonding Mode: \K.+' ".escapeshellarg("/proc/net/bonding/$port")).", mtu $mtu";
      } elseif ($port=='lo') {
        $ports[$i++] = str_replace('yes','loopback',exec("ethtool lo|grep -Pom1 '^\s+Link detected: \K.+'"));
      } else {
        unset($info);
        exec("ethtool ".escapeshellarg($port)."|grep -Po '^\s+(Speed|Duplex|Link\sdetected): \K[^U\\n]+'",$info);
        $ports[$i++] = (array_pop($info)=='yes' && $info[0]) ? str_replace(['M','G'],[' M',' G'],$info[0]).", ".strtolower($info[1])." duplex, mtu $mtu" : "not connected";
      }
    }
  break;
  case 'port': exec("ifconfig -a -s|awk '/^(bond|eth|lo)[0-9]*\s/{print $3\"#\"$7}'",$ports); break;
  case 'link': exec("ifconfig -a -s|awk '/^(bond|eth|lo)[0-9]*\s/{print \"Errors: \"$4\"<br>Drops: \"$5\"<br>Overruns: \"$6\"#Errors: \"$8\"<br>Drops: \"$9\"<br>Overruns: \"$10}'",$ports); break;
  default: $ports = [];}
  echo implode('#',$ports);
break;
case 'parity':
  $var  = parse_ini_file("state/var.ini");
  $mode = '';
  if (strstr($var['mdResyncAction'],"recon")) {
    $mode = 'Parity-Sync / Data-Rebuild';
  } elseif (strstr($var['mdResyncAction'],"clear")) {
    $mode = 'Clearing';
  } elseif ($var['mdResyncAction']=="check") {
    $mode = 'Read-Check';
  } elseif (strstr($var['mdResyncAction'],"check")) {
    $mode = 'Parity-Check';
  }
  echo "<span class='orange p0'><strong>".$mode." in progress... Completed: ".number_format(($var['mdResyncPos']/($var['mdResync']/100+1)),0)." %.</strong></span>";
  echo "<br><em>Elapsed time: ".my_clock(floor((time()-$var['sbUpdated'])/60)).". Estimated finish: ".my_clock(round(((($var['mdResyncDt']*(($var['mdResync']-$var['mdResyncPos'])/($var['mdResyncDb']/100+1)))/100)/60),0))."</em>";
break;
case 'shares':
   $names = explode(',',$_POST['names']);
   switch ($_POST['com']) {
   case 'smb':
     exec("lsof /mnt/user /mnt/disk* 2>/dev/null|awk '/^smbd/ && $0!~/\.AppleD(B|ouble)/ && $5==\"REG\"'|awk -F/ '{print $4}'",$lsof);
     $counts = array_count_values($lsof); $count = [];
     foreach ($names as $name) $count[] =  $counts[$name] ?? 0;
     echo implode('#',$count);
   break;
   case 'afp':
   case 'nfs':
   // not available
   break;}
break;}
