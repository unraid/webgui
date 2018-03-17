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
$disks = []; $var = [];
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/CustomMerge.php";
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/Preselect.php";

function normalize($text, $glue='_') {
  $words = explode($glue,$text);
  foreach ($words as &$word) $word = $word==strtoupper($word) ? $word : preg_replace(['/^(ct|cnt)$/','/^blk$/'],['count','block'],strtolower($word));
  return "<td>".ucfirst(implode(' ',$words))."</td>";
}
function duration(&$hrs) {
  $time = ceil(time()/3600)*3600;
  $now = new DateTime("@$time");
  $poh = new DateTime("@".($time-$hrs*3600));
  $age = date_diff($poh,$now);
  $hrs = "$hrs (".($age->y?"{$age->y}y, ":"").($age->m?"{$age->m}m, ":"").($age->d?"{$age->d}d, ":"")."{$age->h}h)";
}
function spindownDelay($port) {
  $disks = parse_ini_file('state/disks.ini',true);
  foreach ($disks as $disk) {
    $name = substr($disk['device'],-2)!='n1' ? $disk['device'] : substr($disk['device'],0,-2);
    if ($name==$port) {file_put_contents("/var/tmp/diskSpindownDelay.{$disk['idx']}", $disk['spindownDelay']); break;}
  }
}
function append(&$ref, &$info) {
  if ($info) $ref .= ($ref ? " " : "").$info;
}
$name = $_POST['name'] ?? '';
$port = $_POST['port'] ?? '';
if ($name) {
  $disk = &$disks[$name];
  $type = get_value($disk,'smType','');
  get_ctlr_options($type, $disk);
} else {
  $disk = [];
  $type = '';
}
$port = port_name($disk['smDevice'] ?? $port);
switch ($_POST['cmd']) {
case "attributes":
  $select = get_value($disk,'smSelect',0);
  $level  = get_value($disk,'smLevel',1);
  $events = explode('|',get_value($disk,'smEvents',$numbers));
  $unraid = parse_plugin_cfg('dynamix',true);
  $max = $disk['maxTemp'] ?? $unraid['display']['max'];
  $hot = $disk['hotTemp'] ?? $unraid['display']['hot'];
  $top = $_POST['top'] ?? 120;
  exec("smartctl -A $type ".escapeshellarg("/dev/$port")."|awk 'NR>4'",$output);
  if (strpos($output[0], 'SMART Attributes Data Structure')===0) {
    $output = array_slice($output, 3);
    $empty = true;
    foreach ($output as $line) {
      if (!$line) continue;
      $info = explode(' ', trim(preg_replace('/\s+/',' ',$line)), 10);
      $color = "";
      $highlight = strpos($info[8],'FAILING_NOW')!==false || ($select ? $info[5]>0 && $info[3]<=$info[5]*$level : $info[9]>0);
      if (in_array($info[0], $events) && $highlight) $color = " class='warn'";
      elseif (in_array($info[0], [190,194])) {
        if (exceed($info[9],$max,$top)) $color = " class='alert'"; elseif (exceed($info[9],$hot,$top)) $color = " class='warn'";
      }
      if ($info[8]=='-') $info[8] = 'Never';
      if ($info[0]==9 && is_numeric($info[9])) duration($info[9]);
      echo "<tr{$color}>".implode('',array_map('normalize', $info))."</tr>";
      $empty = false;
    }
    if ($empty) echo "<tr><td colspan='10' style='text-align:center;padding-top:12px'>Can not read attributes</td></tr>";
  } else {
    // probably a NMVe or SAS device that smartmontools doesn't know how to parse in to a SMART Attributes Data Structure
    foreach ($output as $line) {
      if (strpos($line,':')===false) continue;
      list($name,$value) = explode(':', $line);
      $name = ucfirst(strtolower($name));
      $value = trim($value);
      $color = '';
      switch ($name) {
      case 'Temperature':
        $temp = strtok($value,' ');
        if (exceed($temp,$max)) $color = " class='alert'"; elseif (exceed($temp,$hot)) $color = " class='warn'";
        break;
      case 'Power on hours':
        if (is_numeric($value)) duration($value);
        break;
      }
      echo "<tr{$color}><td>-</td><td>$name</td><td colspan='8'>$value</td></tr>";
    }
  }
  break;
case "capabilities":
  exec("smartctl -c $type ".escapeshellarg("/dev/$port")."|awk 'NR>5'",$output);
  $row = ['','',''];
  $empty = true;
  foreach ($output as $line) {
    if (!$line) continue;
    $line = preg_replace('/^_/','__',preg_replace(['/__+/','/_ +_/'],'_',str_replace([chr(9),')','('],'_',$line)));
    $info = array_map('trim', explode('_', preg_replace('/_( +)_ /','__',$line), 3));
    append($row[0],$info[0]);
    append($row[1],$info[1]);
    append($row[2],$info[2]);
    if (substr($row[2],-1)=='.') {
      echo "<tr><td>${row[0]}</td><td>${row[1]}</td><td>${row[2]}</td></tr>";
      $row = ['','',''];
      $empty = false;
    }
  }
  if ($empty) echo "<tr><td colspan='3' style='text-align:center;padding-top:12px'>Can not read capabilities</td></tr>";
  break;
case "identify":
  $passed = ['PASSED','OK'];
  $failed = ['FAILED','NOK'];
  exec("smartctl -i $type ".escapeshellarg("/dev/$port")."|awk 'NR>4'",$output);
  exec("smartctl -H $type ".escapeshellarg("/dev/$port")."|grep -Pom1 '^SMART.*: [A-Z]+'|sed 's:self-assessment test result::'",$output);
  $empty = true;
  foreach ($output as $line) {
    if (!$line) continue;
    if (strpos($line,'VALID ARGUMENTS')!==false) break;
    list($title,$info) = array_map('trim', explode(':', $line, 2));
    if (in_array($info,$passed)) $info = "<span class='green-text'>Passed</span>";
    if (in_array($info,$failed)) $info = "<span class='red-text'>Failed</span>";
    echo "<tr>".normalize(preg_replace('/ is:$/',':',"$title:"),' ')."<td>$info</td></tr>";
    $empty = false;
  }
  if ($empty) {
    echo "<tr><td colspan='2' style='text-align:center;padding-top:12px'>Can not read identification</td></tr>";
  } else {
    $file = '/boot/config/disk.log';
    $disks = parse_ini_file('state/disks.ini',true);
    $extra = file_exists($file) ? parse_ini_file($file,true) : [];
    $disk = $disks[$name]['id'];
    $info = &$extra[$disk];
    $periods = ['6','12','18','24','36','48','60'];
    echo "<tr><td>Manufacturing date:</td><td><input type='date' class='narrow' value='{$info['date']}' onchange='disklog(\"$disk\",\"date\",this.value)'></td></tr>";
    echo "<tr><td>Date of purchase:</td><td><input type='date' class='narrow' value='{$info['purchase']}' onchange='disklog(\"$disk\",\"purchase\",this.value)'></td></tr>";
    echo "<tr><td>Warranty period:</td><td><select size='1' class='noframe' onchange='disklog(\"$disk\",\"warranty\",this.value)'><option value=''>unknown</option>";
    foreach ($periods as $period) echo "<option value='$period'".($info['warranty']==$period?" selected":"").">$period months</option>";
    echo "</select></td></tr>";
  }
  break;
case "save":
  exec("smartctl -x $type ".escapeshellarg("/dev/$port")." >".escapeshellarg("$docroot/{$_POST['file']}"));
  break;
case "delete":
  if (strpos(realpath("/var/tmp/{$_POST['file']}"), "/var/tmp/") === 0) {
    @unlink("/var/tmp/{$_POST['file']}");
  }
  break;
case "short":
  spindownDelay($port);
  exec("smartctl -t short $type ".escapeshellarg("/dev/$port"));
  break;
case "long":
  spindownDelay($port);
  exec("smartctl -t long $type ".escapeshellarg("/dev/$port"));
  break;
case "stop":
  exec("smartctl -X $type ".escapeshellarg("/dev/$port"));
  break;
case "update":
  if (!exec("hdparm -C ".escapeshellarg("/dev/$port")."|grep -Pom1 'active|unknown'")) {
    $cmd = $_POST['type']=='New' ? "cmd=/webGui/scripts/hd_parm&arg1=up&arg2=$name" : "cmdSpinup=$name";
    echo "<a href='/update.htm?$cmd&csrf_token={$_POST['csrf']}' class='info' target='progressFrame'><input type='button' value='Spin Up'></a><span class='big orange-text'>Unavailable - disk must be spun up</span>";
    break;
  }
  $progress = exec("smartctl -c $type ".escapeshellarg("/dev/$port")."|grep -Pom1 '\d+%'");
  if ($progress) {
    echo "<span class='big'><i class='fa fa-spinner fa-pulse'></i> self-test in progress, ".(100-substr($progress,0,-1))."% complete</span>";
    break;
  }
  $result = trim(exec("smartctl -l selftest $type ".escapeshellarg("/dev/$port")."|grep -m1 '^# 1'|cut -c26-55"));
  if (!$result) {
    echo "<span class='big'>No self-tests logged on this disk</span>";
    break;
  }
  if (strpos($result, "Completed without error")!==false) {
    echo "<span class='big green-text'>$result</span>";
    break;
  }
  if (strpos($result, "Aborted")!==false or strpos($result, "Interrupted")!==false) {
    echo "<span class='big orange-text'>$result</span>";
    break;
  }
  echo "<span class='big red-text'>Errors occurred - Check SMART report</span>";
  break;
case "selftest":
  echo shell_exec("smartctl -l selftest $type ".escapeshellarg("/dev/$port")."|awk 'NR>5'");
  break;
case "errorlog":
  echo shell_exec("smartctl -l error $type ".escapeshellarg("/dev/$port")."|awk 'NR>5'");
  break;
}
?>
