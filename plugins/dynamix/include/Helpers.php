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

// Helper functions
function my_scale($value, &$unit, $decimals=NULL, $scale=NULL) {
  global $display;
  $scale = $scale ?? $display['scale'];
  $number = $display['number'];
  $units = ['B','KB','MB','GB','TB','PB','EB','ZB','YB'];
  $size = count($units);
  if ($scale==0 && ($decimals===NULL || $decimals<0)) {
    $decimals = 0;
    $unit = '';
  } else {
    $base = $value ? floor(log($value, 1000)) : 0;
    if ($scale>0 && $base>$scale) $base = $scale;
    if ($base>$size) $base = $size-1;
    $value /= pow(1000, $base);
    if ($decimals===NULL) $decimals = $value>=100 ? 0 : ($value>=10 ? 1 : (round($value*100)%100===0 ? 0 : 2));
    elseif ($decimals<0) $decimals = $value>=100||round($value*10)%10===0 ? 0 : abs($decimals);
    if ($scale<0 && round($value,-1)==1000) {$value = 1; $base++;}
    $unit = $units[$base];
  }
  return number_format($value, $decimals, $number[0], $value>9999 ? $number[1] : '');
}
function my_number($value) {
  global $display;
  $number = $display['number'];
  return number_format($value, 0, $number[0], ($value>=10000 ? $number[1] : ''));
}
function my_time($time, $fmt=NULL) {
  global $display;
  if (!$fmt) $fmt = $display['date'].($display['date']!='%c' ? ", {$display['time']}" : "");
  return $time ? strftime($fmt, $time) : "unknown";
}
function my_temp($value) {
  global $display;
  $unit = $display['unit'];
  $number = $display['number'];
  return is_numeric($value) ? (($unit=='F' ? round(9/5*$value+32) : str_replace('.', $number[0], $value))." $unit") : $value;
}
function my_disk($name) {
  return ucfirst(preg_replace('/^(disk|cache|parity)(\d+)/','$1 $2',$name));
}
function my_disks($disk) {
  return strpos($disk['status'],'_NP')===false;
}
function my_id($id) {
  global $display;
  $len = strlen($id);
  $wwn = substr($id,-18);
  return ($display['wwn'] || substr($wwn,0,2)!='_3' || preg_match('/.[_-]/',$wwn)) ? $id : substr($id,0,$len-18);
}
function my_word($num) {
  $words = ['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty','twenty-one','twenty-two','twenty-three','twenty-four','twenty-five','twenty-six','twenty-seven','twenty-eight','twenty-nine','thirty'];
  return $num<count($words) ? $words[$num] : $num;
}
function my_usage() {
  global $disks,$var,$display;
  $arraysize=0;
  $arrayfree=0;
  foreach ($disks as $disk) {
    if (strpos($disk['name'],'disk')!==false) {
      $arraysize += $disk['sizeSb'];
      $arrayfree += $disk['fsFree'];
    }
  }
  if ($var['fsNumMounted']>0) {
    $used = $arraysize ? 100-round(100*$arrayfree/$arraysize) : 0;
    echo "<div class='usage-bar'><span style='width:{$used}%' class='".usage_color($display,$used,false)."'><span>{$used}%</span></span></div>";
  } else {
    echo "<div class='usage-bar'><span style='text-align:center'>".($var['fsState']=='Started'?'Maintenance':'off-line')."</span></div>";
  }
}
function usage_color(&$disk, $limit, $free) {
  global $display;
  if ($display['text']==1 || intval($display['text']/10)==1) return '';
  $critical = !empty($disk['critical']) ? $disk['critical'] : $display['critical'];
  $warning = !empty($disk['warning']) ? $disk['warning'] : $display['warning'];
  if (!$free) {
    if ($limit>=$critical && $critical>0) return 'redbar';
    if ($limit>=$warning && $warning>0) return 'orangebar';
    return 'greenbar';
  } else {
    if ($limit<=100-$critical && $critical>0) return 'redbar';
    if ($limit<=100-$warning && $warning>0) return 'orangebar';
    return 'greenbar';
  }
}
function my_check($time, $speed) {
  if (!$time) return 'unavailable (no parity-check entries logged)';
  $days = floor($time/86400);
  $hmss = $time-$days*86400;
  $hour = floor($hmss/3600);
  $mins = $hmss/60%60;
  $secs = $hmss%60;
  return plus($days,'day',($hour|$mins|$secs)==0).plus($hour,'hour',($mins|$secs)==0).plus($mins,'minute',$secs==0).plus($secs,'second',true).". Average speed: $speed";
}
function my_error($code) {
  switch ($code) {
  case -4:
    return "<em>aborted</em>";
  default:
    return "<strong>$code</strong>";
  }
}
function mk_option($select, $value, $text, $extra="") {
  return "<option value='$value'".($value==$select ? " selected" : "").(strlen($extra) ? " $extra" : "").">$text</option>";
}
function mk_option_check($name, $value, $text="") {
  if ($text) {
    $checked = in_array($value,explode(',',$name)) ? " selected" : "";
    return "<option value='$value'$checked>$text</option>";
  }
  if (strpos($name, 'disk')!==false) {
    $checked = in_array($name,explode(',',$value)) ? " selected" : "";
    return "<option value='$name'$checked>".my_disk($name)."</option>";
  }
}
function mk_option_luks($name, $value, $luks) {
  if (strpos($name, 'disk')!==false) {
    $checked = in_array($name,explode(',',$value)) ? " selected" : "";
    return "<option luks='$luks' value='$name'$checked>".my_disk($name)."</option>";
  }
}
function day_count($time) {
  global $var;
  if (!$time) return;
  $datetz = new DateTimeZone($var['timeZone']);
  $date = new DateTime("now", $datetz);
  $offset = $datetz->getOffset($date);
  $now  = new DateTime("@".intval((time()+$offset)/86400)*86400);
  $last = new DateTime("@".intval(($time+$offset)/86400)*86400);
  $days = date_diff($last,$now)->format('%a');
  switch (true) {
  case ($days<0):
    return;
  case ($days==0):
    return " (today)";
  case ($days==1):
    return " (yesterday)";
  case ($days<=31):
    return " (".my_word($days)." days ago)";
  case ($days<=61):
    return " <span class='orange-text'>($days days ago)</span>";
  case ($days>61):
    return " <span class='red-text'>($days days ago)</span>";
  }
}
function plus($val, $word, $last) {
  return $val>0 ? (($val || $last) ? ($val.' '.$word.($val!=1?'s':'').($last ?'':', ')) : '') : '';
}
function read_parity_log($epoch, $busy=false) {
  $log = '/boot/config/parity-checks.log';
  if (file_exists($log)) {
    $timestamp = str_replace(['.0','.'],['  ',' '],date('M.d H:i:s',$epoch));
    $handle = fopen($log, 'r');
    while (($line = fgets($handle)) !== false) {
      if (strpos($line,$timestamp)!==false) break;
      if ($busy) $last = $line;
    }
    fclose($handle);
  }
  return $line ?: $last ?: '0|0|0|0|0';
}
function last_parity_log() {
  $log = '/boot/config/parity-checks.log';
  if (!file_exists($log)) return [0,0,0,0,0];
  list($date,$duration,$speed,$status,$error) = explode('|',exec("tail -1 $log"));
  list($y,$m,$d,$t) = preg_split('/ +/',$date);
  return [strtotime("$d-$m-$y $t"), $duration, $speed, $status, $error];
}
function urlencode_path($path) {
  return str_replace("%2F", "/", urlencode($path));
}
function pgrep($process_name, $escape_arg=true) {
  $pid = exec("pgrep ".($escape_arg?escapeshellarg($process_name):$process_name), $output, $retval);
  return $retval == 0 ? $pid : false;
}
function input_secure_users($sec) {
  global $name, $users;
  echo "<table class='settings'>";
  $write_list = explode(",", $sec[$name]['writeList']);
  foreach ($users as $user) {
    $idx = $user['idx'];
    if ($user['name'] == "root") {
      echo "<input type='hidden' name='userAccess.$idx' value='no-access'>";
      continue;
    }
    if (in_array( $user['name'], $write_list))
      $userAccess = "read-write";
    else
      $userAccess = "read-only";
    echo "<tr><td>{$user['name']}</td>";
    echo "<td><select name='userAccess.$idx' size='1'>";
    echo mk_option($userAccess, "read-write", "Read/Write");
    echo mk_option($userAccess, "read-only", "Read-only");
    echo "</select></td></tr>";
  }
  echo "</table>";
}
function input_private_users($sec) {
  global $name, $users;
  echo "<table class='settings'>";
  $read_list = explode(",", $sec[$name]['readList']);
  $write_list = explode(",", $sec[$name]['writeList']);
  foreach ($users as $user) {
    $idx = $user['idx'];
    if ($user['name'] == "root") {
      echo "<input type='hidden' name='userAccess.$idx' value='no-access'>";
      continue;
    }
    if (in_array( $user['name'], $read_list))
      $userAccess = "read-only";
    elseif (in_array( $user['name'], $write_list))
      $userAccess = "read-write";
    else
      $userAccess = "no-access";
    echo "<tr><td>{$user['name']}</td>";
    echo "<td><select name='userAccess.$idx' size='1'>";
    echo mk_option($userAccess, "read-write", "Read/Write");
    echo mk_option($userAccess, "read-only", "Read-only");
    echo mk_option($userAccess, "no-access", "No Access");
    echo "</select></td></tr>";
  }
  echo "</table>";
}
function is_block($path) {
  return (@filetype(realpath($path))=='block');
}
function autov($file) {
  global $docroot;
  $path = $docroot.$file;
  clearstatcache(true, $path);
  echo "$file?v=".filemtime($path);
}
function transpose_user_path($path) {
  if (strpos($path, '/mnt/user/') === 0 && file_exists($path)) {
    $realdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($path)." 2>/dev/null"));
    if (!empty($realdisk))
      $path = str_replace('/mnt/user/', "/mnt/$realdisk/", $path);
  }
  return $path;
}
// custom parse_ini_file/string functions to deal with '#' comment lines
function my_parse_ini_string($text, $sections=false, $scanner=INI_SCANNER_NORMAL) {
  return parse_ini_string(preg_replace('/^#/m',';',$text),$sections,$scanner);
}
function my_parse_ini_file($file, $sections=false, $scanner=INI_SCANNER_NORMAL) {
  return my_parse_ini_string(file_get_contents($file),$sections,$scanner);
}
?>
