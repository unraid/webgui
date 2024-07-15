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
require_once "$docroot/webGui/include/Preselect.php";

// add translations
$_SERVER['REQUEST_URI'] = 'main';
require_once "$docroot/webGui/include/Translations.php";

$disks = array_merge_recursive(@parse_ini_file('state/disks.ini',true)?:[], @parse_ini_file('state/devs.ini',true)?:[]);
require_once "$docroot/webGui/include/CustomMerge.php";

function normalize($text, $glue='_') {
  $words = explode($glue,$text);
  foreach ($words as &$word) $word = $word==strtoupper($word) ? $word : preg_replace(['/^(ct|cnt)$/','/^blk$/'],['count','block'],strtolower($word));
  return "<td>".ucfirst(implode(' ',$words))."</td>";
}
function size($val) {
  return str_replace(',','',$val);
}
function duration(&$hrs) {
  $time = ceil(time()/3600)*3600;
  $run = size($hrs);
  $now = new DateTime("@$time");
  $poh = new DateTime("@".($time-$run*3600));
  $age = date_diff($poh,$now);
  $hrs = "$hrs (".($age->y?"{$age->y}y, ":"").($age->m?"{$age->m}m, ":"").($age->d?"{$age->d}d, ":"")."{$age->h}h)";
}
function blocks_size(&$blks,$blk_size) {
  $blks = "$blks (".my_scale($blks*$blk_size,$unit)." $unit)";
}
function append(&$ref, &$info) {
  if ($info) $ref .= ($ref ? " " : "").$info;
}
$name = $_POST['name']??'';
$port = $_POST['port']??'';
if ($name) {
  $disk = &$disks[$name];
  $type = get_value($disk,'smType','');
  get_ctlr_options($type, $disk);
} else {
  $disk = [];
  $type = '';
}
$port = port_name($disk['smDevice'] ?? $port);
switch ($_POST['cmd']??'') {
case "attributes":
  $select = get_value($disk,'smSelect',0);
  $level  = get_value($disk,'smLevel',1);
  $events = explode('|',get_value($disk,'smEvents',$numbers));
  extract(parse_plugin_cfg('dynamix',true));
  [$hotNVME,$maxNVME] = _var($disk,'transport')=='nvme' ? get_nvme_info(_var($disk,'device'),'temp') : [-1,-1];
  $hot    = _var($disk,'hotTemp',-1)>=0 ? $disk['hotTemp'] : ($hotNVME>=0 ? $hotNVME : (_var($disk,'rotational',1)==0 && $display['hotssd']>=0 ? $display['hotssd'] : $display['hot']));
  $max    = _var($disk,'maxTemp',-1)>=0 ? $disk['maxTemp'] : ($maxNVME>=0 ? $maxNVME : (_var($disk,'rotational',1)==0 && $display['maxssd']>=0 ? $display['maxssd'] : $display['max']));
  $top    = $_POST['top'] ?? 120;
  $ssd_remaining = NULL;
  $empty  = true;
  exec("smartctl -n standby -A $type ".escapeshellarg("/dev/$port"),$output);
  // remove empty rows
  $output = array_filter($output);
  $start = 0;
  // find start of attributes list (if existing)
  foreach ($output as $row) if (stripos($row,'smart attributes data structure')===false) $start++; else break;
  if ($start < count($output)-3) {
    // remove header part
    $output = array_slice($output, $start+3);
    foreach ($output as $row) {
      $info = explode(' ', trim(preg_replace('/\s+/',' ',$row)), 10);
      if (count($info)<10) continue;
      $color = "";
      $highlight = strpos($info[8],'FAILING_NOW')!==false || ($select ? $info[5]>0 && $info[3]<=$info[5]*$level : $info[9]>0);
      if (in_array($info[0], $events) && $highlight) $color = " class='warn'";
      elseif (in_array($info[0], [190,194])) {
        if (exceed($info[9],$max,$top)) $color = " class='alert'"; elseif (exceed($info[9],$hot,$top)) $color = " class='warn'";
      }
      if ($info[8]=='-') $info[8] = 'Never';
      if ($info[0]==9 && is_numeric(size($info[9]))) duration($info[9]);
      if (str_starts_with($info[1], 'Total_LBAs_')) blocks_size($info[9],512); // Assumes 512 byte sectors
      if (str_ends_with($info[1], '_32MiB')) blocks_size($info[9],32*1024*1024);
      echo "<tr{$color}>".implode('',array_map('normalize', $info))."</tr>";
      $empty = false;
    }
  } else {
    // probably a NVMe or SAS device that smartmontools doesn't know how to parse in to a SMART Attributes Data Structure
    foreach ($output as $row) {
      if (strpos($row,':')===false) continue;
      [$name,$value] = array_map('trim',explode(':', $row));
      $name = ucfirst(strtolower($name));
      $color = '';
      switch ($name) {
      case 'Temperature':
        $temp = strtok($value,' ');
        if (exceed($temp,$max)) $color = " class='alert'"; elseif (exceed($temp,$hot)) $color = " class='warn'";
        break;
      case 'Power on hours':
        if (is_numeric(size($value))) duration($value);
        break;
      case 'Percentage used':
          $ssd_remaining = 100 - str_replace('%', '', $value);
        break;
      }
      if (str_ends_with($name, ', hours') && str_starts_with($value, 'minutes ')) {
        $name = substr($name, 0, -7);
        $value = substr($value, 8);
        if (is_numeric(size($value))) duration($value);
      }
      echo "<tr{$color}><td>-</td><td>$name</td><td colspan='8'>$value</td></tr>";
      $empty = false;
    }
  }
  if (is_null($ssd_remaining)) {
    // Try to look up SSD's 'Percentage Used Endurance Indicator' with special command
    exec("smartctl -n standby -l ssd $type ".escapeshellarg("/dev/$port"), $ssd_out);
    $ssd_out = array_filter($ssd_out);
    foreach ($ssd_out as $row) {
      if (str_ends_with($row, 'Percentage Used Endurance Indicator')) {
        // Probably a SATA SSD
        $info = explode(' ', trim(preg_replace('/\s+/',' ',$row)), 6);
        $ssd_remaining = 100 - $info[3];
      } elseif (str_starts_with($row, 'Percentage used endurance indicator:')) {
        // Probably a SAS SSD
        [$name,$value] = array_map('trim',explode(':', $row));
        $ssd_remaining = 100 - str_replace('%','',$value);
      }
    }    
  }
  if (!is_null($ssd_remaining)) {
    echo "<tr><td>-</td><td>SSD endurance remaining</td><td colspan='8'>$ssd_remaining %</td></tr>";
  }  
  if ($empty) echo "<tr><td colspan='10' style='text-align:center;padding-top:12px'>"._('Attributes not available')."</td></tr>";
  break;
case "capabilities":
  echo '<table id="disk_capabilities_table" class="unraid"><thead><td style="width:33%">'._('Feature').'</td><td>'._('Value').'</td><td>'._('Information').'</td></thead><tbody>' ;
  exec("smartctl -n standby -c $type ".escapeshellarg("/dev/$port")."|awk 'NR>5'",$output);
  $row = ['','',''];
  $empty = true;
  $nvme = substr($port,0,4)=="nvme";
  $nvme_section="info" ;
  foreach ($output as $line) {
    if (!$line) {echo "<tr></tr>" ;continue;}
    $line = preg_replace('/^_/','__',preg_replace(['/__+/','/_ +_/'],'_',str_replace([chr(9),')','('],'_',$line)));
    $info = array_map('trim', explode('_', preg_replace('/_( +)_ /','__',$line), 3));
    if ($nvme && $info[0]=="Supported Power States" ) { $nvme_section="psheading" ;echo "</body></table><div class='title'><span>{$line}</span></div>"; $row = ['','',''] ; continue ;}
    if ($nvme && $info[0]=="Supported LBA Sizes" ) {
      echo "</body></table><div class='title'>{$info[0]} {$info[1]} {$info[2]}</span></div>";
      $row = ['','',''];
      $nvme_section="lbaheading" ;
      continue ;
    }
    append($row[0],$info[0]);
    append($row[1],$info[1]);
    append($row[2],$info[2]);
    if (substr($row[2],-1)=='.' || ($nvme && $nvme_section=="info")) {
      echo "<tr><td>{$row[0]}</td><td>{$row[1]}</td><td>{$row[2]}</td></tr>";
      $row = ['','',''];
      $empty = false;
    }
    if ($nvme && $nvme_section == "psheading") {
      echo '<table id="disk_capabilities_table2" class="unraid"><thead>' ;
      $nvme_section = "psdetail";
      preg_match('/^(?P<data1>.\S+)\s+(?P<data2>\S+)\s+(?P<data3>\S+)\s+(?P<data4>\S+)\s+(?P<data5>\S+)\s+(?P<data6>\S+)\s+(?P<data7>\S+)\s+(?P<data8>\S+)\s+(?P<data9>\S+)\s+(?P<data10>\S+)\s+(?P<data11>\S+)$/',$line, $psheadings);
      for ($i = 1; $i <= 11; $i++) {
      echo "<td>"._var($psheadings,'data'.$i)."</td>" ;
      }
      $row = ['','',''];
      echo '</tr></thead><tbody>' ;
    }
    if ($nvme && $nvme_section == "psdetail") {
      $nvme_section = "psdetail";
      echo '<tr>' ;
      preg_match('/^(?P<data1>.\S+)\s+(?P<data2>\S\s+)\s+(?P<data3>\S+)\s+(?P<data4>\S\s+)\s+(?P<data5>\S+)\s+(?P<data6>\S+)\s+(?P<data7>\S+)\s+(?P<data8>\S+)\s+(?P<data9>\S+)\s+(?P<data10>\S+)\s+(?P<data11>\S+)$/',$line, $psdetails);
      for ($i = 1; $i <= 11; $i++) {
      echo "<td>"._var($psdetails,'data'.$i)."</td>" ;
      }
      $row = ['','',''];
      echo '</tr>' ;
    }
    if ($nvme && $nvme_section == "lbaheading") {
      echo '<table id="disk_capabilities_table3" class="unraid"><thead>' ;
      $nvme_section = "lbadetail";
      preg_match('/^(?P<data1>.\S+)\s+(?P<data2>\S+)\s+(?P<data3>\S+)\s+(?P<data4>\S+)\s+(?P<data5>\S+)$/',$line, $lbaheadings);
      for ($i = 1; $i <= 5; $i++) {
        echo "<td>"._var($lbaheadings,'data'.$i)."</td>" ;
        }
        $row = ['','',''];
      echo '</thead><tbody>' ;
    }
    if ($nvme && $nvme_section == "lbadetail") {
      $nvme_section = "lbadetail";
      preg_match('/^(?P<data1>.\S+)\s+(?P<data2>\S\s+)\s+(?P<data3>\S+)\s+(?P<data4>\S\s+)\s+(?P<data5>\S+)$/',$line, $lbadetails);
      echo '<tr>' ;
      for ($i = 1; $i <= 5; $i++) {
        echo "<td>"._var($lbadetails,'data'.$i)."</td>" ;
        }
        $row = ['','',''];
      echo '</tr>' ;
    }
  }
  if ($empty) echo "<tr><td colspan='3' style='text-align:center;padding-top:12px'>"._('Capabilities not available')."</td></tr>";
  echo "</tbody></table>" ;
  break;
case "identify":
  $passed = ['PASSED','OK'];
  $failed = ['FAILED','NOK'];
  if ($disk["transport"] == "scsi") $standby = " -n standby " ; else $standby = "" ;
  exec("smartctl -i $type $standby ".escapeshellarg("/dev/$port")."|awk 'NR>4'",$output);
  exec("smartctl -n standby -H $type ".escapeshellarg("/dev/$port")."|grep -Pom1 '^SMART.*: [A-Z]+'|sed 's:self-assessment test result::'",$output);
  $empty = true;
  foreach ($output as $line) {
    if (!$line) continue;
    if (strpos($line,'VALID ARGUMENTS')!==false) break;
    [$title,$info] = array_map('trim', my_explode(':',$line));
    if (in_array($info,$passed)) $info = "<span class='green-text'>"._('Passed')."</span>";
    if (in_array($info,$failed)) $info = "<span class='red-text'>"._('Failed')."</span>";
    echo "<tr>".normalize(preg_replace('/ is:$/',':',"$title:"),' ')."<td>$info</td></tr>";
    $empty = false;
  }
  if ($empty) {
    $spundown = $disk['spundown'] ? "(device spundown, spinup to get information)" : "" ;
    echo "<tr><td colspan='2' style='text-align:center;padding-top:12px'>"._('Identification not available'.$spundown)."</td></tr>";
  } else {
    $file = '/boot/config/disk.log';
    $extra = file_exists($file) ? parse_ini_file($file,true) : [];
    $disk = $disks[$name]['id'];
    $info = &$extra[$disk];
    $periods = ['6','12','18','24','36','48','60'];
    echo "<tr><td>"._('Manufacturing date').":</td><td><input type='date' class='narrow' value='"._var($info,'date')."' onchange='disklog(\"$disk\",\"date\",this.value)'></td></tr>";
    echo "<tr><td>"._('Date of purchase').":</td><td><input type='date' class='narrow' value='".($info['purchase']??'')."' onchange='disklog(\"$disk\",\"purchase\",this.value)'></td></tr>";
    echo "<tr><td>"._('Warranty period').":</td><td><select class='noframe' onchange='disklog(\"$disk\",\"warranty\",this.value)'><option value=''>"._('unknown')."</option>";
    foreach ($periods as $period) echo "<option value='$period'".(_var($info,'warranty')==$period?" selected":"").">$period "._('months')."</option>";
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
  exec("smartctl -t short $type ".escapeshellarg("/dev/$port"));
  break;
case "long":
  exec("smartctl -t long $type ".escapeshellarg("/dev/$port"));
  break;
case "stop":
  exec("smartctl -X $type ".escapeshellarg("/dev/$port"));
  break;
case "update":
  $transport = _var($disk,'transport');
  if ($transport == 'scsi' || $transport == 'nvme') {
    $progress = exec("smartctl -n standby -l selftest $type ".escapeshellarg("/dev/$port")."|grep -Pom1 '\d+%'");
    if ($progress) {
      if ($transport == 'nvme') echo "<span class='big'><i class='fa fa-spinner fa-pulse'></i> "._('self-test in progress').", ".(substr($progress,0,-1))."% "._('complete')."</span>"; else echo "<span class='big'><i class='fa fa-spinner fa-pulse'></i> "._('self-test in progress').", ".(100-substr($progress,0,-1))."% "._('complete')."</span>";
      break;
    }
  } else {
    $progress = exec("smartctl -n standby -c $type ".escapeshellarg("/dev/$port")."|grep -Pom1 '\d+%'");
    if ($progress) {
      echo "<span class='big'><i class='fa fa-spinner fa-pulse'></i> "._('self-test in progress').", ".(100-substr($progress,0,-1))."% "._('complete')."</span>";
      break;
    }
  }
  if ($transport == 'scsi') $result = trim(exec("smartctl -n standby -l selftest $type ".escapeshellarg("/dev/$port")."|grep -m1 '^# 1'|cut -c24-50"));
  else if ($transport == 'nvme') $result = trim(exec("smartctl -n standby -l selftest $type ".escapeshellarg("/dev/$port")."|grep -m1 '^ 0'|cut -c24-50"));
  else $result = trim(exec("smartctl -n standby -l selftest $type ".escapeshellarg("/dev/$port")."|grep -m1 '^# 1'|cut -c26-55"));
  if (!$result) {
    $spundown = $disk['spundown'] ? "Device spundown, spinup to get information" : "No self-tests logged on this disk" ;
    echo "<span class='big'>"._($spundown)."</span>";
    break;
  }
  if (strpos($result, "Completed, segment failed")!==false) {
    echo "<span class='big red-text'>"._($result)."</span>";
    break;
  }
  if (strpos($result, "Completed without error")!==false || strpos($result, "Completed")!==false ) {
    echo "<span class='big green-text'>"._($result)."</span>";
    break;
  }
  if (strpos($result, "Aborted")!==false or strpos($result, "Interrupted")!==false) {
    echo "<span class='big orange-text'>"._($result)."</span>";
    break;
  }
  if (strpos($result, "Failed")!==false) {
    echo "<span class='big red-text'>"._($result)."</span>";
    break;
  }
  echo "<span class='big red-text'>"._('Errors occurred - Check SMART report')."</span>";
  break;
case "selftest":
  echo shell_exec("smartctl -n standby -l selftest $type ".escapeshellarg("/dev/$port")."|awk 'NR>5'");
  break;
case "errorlog":
  echo shell_exec("smartctl -n standby -l error $type ".escapeshellarg("/dev/$port")."|awk 'NR>5'");
  break;
}
?>
