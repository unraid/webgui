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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/Secure.php";

// Helper functions
function my_scale($value, &$unit, $decimals=NULL, $scale=NULL, $kilo=1000) {
  global $display,$language;
  $scale = $scale ?? $display['scale'];
  $number = _var($display,'number','.,');
  $units = explode(' ', ' '.($kilo==1000 ? ($language['prefix_SI'] ?? 'K M G T P E Z Y') : ($language['prefix_IEC'] ?? 'Ki Mi Gi Ti Pi Ei Zi Yi')));
  $size = count($units);
  if ($scale==0 && ($decimals===NULL || $decimals<0)) {
    $decimals = 0;
    $unit = '';
  } else {
    $base = $value ? intval(floor(log($value, $kilo))) : 0;
    if ($scale>0 && $base>$scale) $base = $scale;
    if ($base>$size) $base = $size-1;
    $value /= pow($kilo, $base);
    if ($decimals===NULL) $decimals = $value>=100 ? 0 : ($value>=10 ? 1 : (round($value*100)%100===0 ? 0 : 2));
    elseif ($decimals<0) $decimals = $value>=100||round($value*10)%10===0 ? 0 : abs($decimals);
    if ($scale<0 && round($value,-1)==1000) {$value = 1; $base++;}
    $unit = $units[$base]._('B');
  }
  return number_format($value, $decimals, $number[0], $value>9999 ? $number[1] : '');
}
function my_number($value) {
  global $display;
  $number = _var($display,'number','.,');
  return number_format($value, 0, $number[0], ($value>=10000 ? $number[1] : ''));
}
function my_time($time, $fmt=NULL) {
  global $display;
  if (!$fmt) $fmt = _var($display,'date').(_var($display,'date')!='%c' ? ", "._var($display,'time') : "");
  return $time ? my_date($fmt, $time) : _('unknown');
}
function my_temp($value) {
  global $display;
  $unit = _var($display,'unit','C');
  $number = _var($display,'number','.,');
  return is_numeric($value) ? (($unit=='F' ? round(9/5*$value+32) : str_replace('.', $number[0], $value))." $unit") : $value;
}
function my_disk($name, $raw=false) {
  global $display;
  return _var($display,'raw')||$raw ? $name : ucfirst(preg_replace('/(\d+)$/',' $1',$name));
}
function my_disks($disk) {
  return strpos(_var($disk,'status'),'_NP')===false;
}
function my_hyperlink($text, $link) {
  return str_replace(['[',']'],["<a href=\"$link\">","</a>"],$text);
}
function prefix($key) {
  return preg_replace('/\d+$/','',$key);
}
function parity_only($disk) {
  return _var($disk,'type')=='Parity';
}
function data_only($disk) {
  return _var($disk,'type')=='Data';
}
function cache_only($disk) {
  return _var($disk,'type')=='Cache';
}
function parity_filter($disks) {
  return array_filter($disks,'parity_only');
}
function data_filter($disks) {
  return array_filter($disks,'data_only');
}
function cache_filter($disks) {
  return array_filter($disks,'cache_only');
}
function pools_filter($disks) {
  return array_unique(array_map('prefix',array_keys(cache_filter($disks))));
}
function my_id($id) {
  global $display;
  $len = strlen($id);
  $wwn = substr($id,-18);
  return (_var($display,'wwn') || substr($wwn,0,2)!='_3' || preg_match('/.[_-]/',$wwn)) ? $id : substr($id,0,$len-18);
}
function my_word($num) {
  $words = ['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty','twenty-one','twenty-two','twenty-three','twenty-four','twenty-five','twenty-six','twenty-seven','twenty-eight','twenty-nine','thirty'];
  return $num<count($words) ? _($words[$num],1) : $num;
}
function my_usage() {
  global $disks,$var,$display;
  $arraysize=0;
  $arrayfree=0;
  foreach ($disks as $disk) {
    if (strpos(_var($disk,'name'),'disk')!==false) {
      $arraysize += _var($disk,'sizeSb',0);
      $arrayfree += _var($disk,'fsFree',0);
    }
  }
  if (_var($var,'fsNumMounted',0)>0) {
    $used = $arraysize ? 100-round(100*$arrayfree/$arraysize) : 0;
    echo "<div class='usage-bar'><span style='width:{$used}%' class='".usage_color($display,$used,false)."'>{$used}%</span></div>";
  } else {
    echo "<div class='usage-bar'><span style='text-align:center'>".($var['fsState']=='Started'?'Maintenance':'off-line')."</span></div>";
  }
}
function usage_color(&$disk, $limit, $free) {
  global $display;
  if (_var($display,'text',0)==1 || intval(_var($display,'text',0)/10)==1) return '';
  $critical = _var($disk,'critical')>=0 ? $disk['critical'] : (_var($display,'critical')>=0 ? $display['critical'] : 0);
  $warning = _var($disk,'warning')>=0 ? $disk['warning'] : (_var($display,'warning')>=0 ? $display['warning'] : 0);
  if (!$free) {
    if ($critical>0 && $limit>=$critical) return 'redbar';
    if ($warning>0 && $limit>=$warning) return 'orangebar';
    return 'greenbar';
  } else {
    if ($critical>0 && $limit<=100-$critical) return 'redbar';
    if ($warning>0 && $limit<=100-$warning) return 'orangebar';
    return 'greenbar';
  }
}
function my_check($time, $speed) {
  if (!$time) return _('unavailable (no parity-check entries logged)');
  $days = floor($time/86400);
  $hmss = $time-$days*86400;
  $hour = floor($hmss/3600);
  $mins = floor($hmss/60)%60;
  $secs = $hmss%60;
  return plus($days,'day',($hour|$mins|$secs)==0).plus($hour,'hour',($mins|$secs)==0).plus($mins,'minute',$secs==0).plus($secs,'second',true).". "._('Average speed').": ".(is_numeric($speed) ? my_scale($speed,$unit,1)." $unit/s" : $speed);
}
function my_error($code) {
  switch ($code) {
  case -4:
    return "<em>"._('aborted')."</em>";
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
  if (strpos($name,'disk')!==false) {
    $checked = in_array($name,explode(',',$value)) ? " selected" : "";
    return "<option value='$name'$checked>".my_disk($name)."</option>";
  }
}
function mk_option_luks($name, $value, $luks) {
  if (strpos($name,'disk')!==false) {
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
    return " <span class='green-text'>("._('today').")</span>";
  case ($days==1):
    return " <span class='green-text'>("._('yesterday').")</span>";
  case ($days<=31):
    return " <span class='green-text'>(".sprintf(_('%s days ago'),my_word($days)).")</span>";
  case ($days<=61):
    return " <span class='orange-text'>(".sprintf(_('%s days ago'),$days).")</span>";
  case ($days>61):
    return " <span class='red-text'>(".sprintf(_('%s days ago'),$days).")</span>";
  }
}
function plus($val, $word, $last) {
  return $val>0 ? (($val || $last) ? ($val.' '._($word.($val!=1?'s':'')).($last ?'':', ')) : '') : '';
}
function compress($name, $size=18, $end=6) {
  return mb_strlen($name)<=$size ? $name : mb_substr($name,0,$size-($end?$end+3:0)).'...'.($end?mb_substr($name,-$end):'');
}
function escapestring($name) {
  return "\"$name\"";
}
function tail($file, $rows=1) {
  $file = new SplFileObject($file);
  $file->seek(PHP_INT_MAX);
  $file->seek($file->key()-$rows);
  $echo = [];
  while (!$file->eof()) {
    $echo[] = $file->current();
    $file->next();
  }
  return implode($echo);
}
function last_parity_log() {
  $log = '/boot/config/parity-checks.log';
  [$date,$duration,$speed,$status,$error,$action,$size] = file_exists($log) ? my_explode('|',tail($log),7) : array_fill(0,7,0);
  if ($date) {
    [$y,$m,$d,$t] = my_preg_split('/ +/',$date,4);
    $date = strtotime("$d-$m-$y $t");
  }
  return [$date,$duration,$speed,$status,$error,$action,$size];
}
function urlencode_path($path) {
  return str_replace("%2F", "/", urlencode($path));
}
function pgrep($process_name, $escape_arg=true) {
  $pid = exec("pgrep ".($escape_arg?escapeshellarg($process_name):$process_name), $output, $retval);
  return $retval==0 ? $pid : false;
}
function is_block($path) {
  return (@filetype(realpath($path))=='block');
}
function autov($file,$ret=false) {
  global $docroot;
  $path = $docroot.$file;
  clearstatcache(true, $path);
  $time = file_exists($path) ? filemtime($path) : 'autov_fileDoesntExist';
  $newFile = "$file?v=".$time;
  if ($ret)
    return $newFile;
  else
    echo $newFile;
}
function transpose_user_path($path) {
  if (strpos($path,'/mnt/user/')===0 && file_exists($path)) {
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
function cpu_list() {
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list|sort -nu', $cpus);
  return $cpus;
}
function my_explode($split, $text, $count=2) {
  return array_pad(explode($split,$text,$count),$count,'');
}
function my_preg_split($split, $text, $count=2) {
  return array_pad(preg_split($split,$text,$count),$count,'');
}
function delete_file(...$file) {
  array_map('unlink',array_filter($file,'file_exists'));
}
function getnvmepowerstate($device) {
  $array=[] ;
  exec("nvme  id-ctrl $device |grep -E 'ps |wctemp|cctemp'",$array,$error) ;
  foreach ($array as $line){
      $split = explode(":",$line) ;
      $check=str_replace(" ","",trim($split[0]));
      switch($check){
          case "wctemp":
              $return['wctemp'] = $split[1] - 273;
              break;
          case "cctemp":
              $return['cctemp'] = $split[1] - 273;
              break;
          case "ps0":
          case "ps1":
          case "ps2":
          case "ps3":
          case "ps4":
          case "ps5":
              $power = explode(" ",$split[2]) ;
              $return[$check] = $power[0];
              break;
      }
  }
  $powerstate = shell_exec("nvme get-feature $device -f 02");
  $powersplit = explode(":",$powerstate) ;
  $powerstate = substr(trim($powersplit[2]), -1) ;
  #get-feature:0x02 (Power Management), Current value:0x00000003)
  $return["powerstate"] = $powerstate;
  $return["powerstatevalue"] =  $return['ps'.$return['powerstate']] ;
  return $return;
}
?>
