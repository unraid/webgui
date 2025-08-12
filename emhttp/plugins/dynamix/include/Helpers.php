<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/Secure.php";

// Helper functions
function my_scale($value, &$unit, $decimals=NULL, $scale=NULL, $kilo=1000) {
  global $display, $language;
  $scale = $scale ?? $display['scale'];
  $number = _var($display,'number','.,');
  $units = explode(' ', ' '.($kilo==1000 ? ($language['prefix_SI'] ?? 'K M G T P E Z Y') : ($language['prefix_IEC'] ?? 'Ki Mi Gi Ti Pi Ei Zi Yi')));
  $size = count($units);
  if ($scale == 0 && ($decimals === NULL || $decimals < 0)) {
    $decimals = 0;
    $unit = '';
  } else {
    $base = $value ? intval(floor(log($value, $kilo))) : 0;
    if ($scale > 0 && $base > $scale) $base = $scale;
    if ($base > $size) $base = $size - 1;
    $value /= pow($kilo, $base);
    if ($decimals === NULL) $decimals = $value >= 100 ? 0 : ($value >= 10 ? 1 : (round($value*100)%100 === 0 ? 0 : 2));
    elseif ($decimals < 0) $decimals = $value >= 100 || round($value*10)%10 === 0 ? 0 : abs($decimals);
    if ($scale < 0 && round($value,-1) == 1000) {$value = 1; $base++;}
    $unit = $units[$base]._('B');
  }
  return number_format($value, $decimals, $number[0], $value > 9999 ? $number[1] : '');
}

function my_number($value) {
  global $display;
  $number = _var($display,'number','.,');
  return number_format($value, 0, $number[0], ($value >= 10000 ? $number[1] : ''));
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
  return is_numeric($value) ? (($unit == 'F' ? fahrenheit($value) : str_replace('.', $number[0], $value)).'&#8201;&#176;'.$unit) : $value;
}

function my_disk($name, $raw=false) {
  global $display;
  return _var($display,'raw') || $raw ? $name : ucfirst(preg_replace('/(\d+)$/',' $1',$name));
}

function my_disks($disk) {
  return strpos(_var($disk,'status'),'_NP') === false;
}

function my_hyperlink($text, $link) {
  return str_replace(['[',']'],["<a href=\"$link\">","</a>"],$text);
}

function main_only($disk) {
  return _var($disk,'type') == 'Parity' || _var($disk,'type') == 'Data';
}

function parity_only($disk) {
  return _var($disk,'type') == 'Parity';
}

function data_only($disk) {
  return _var($disk,'type') == 'Data';
}

function cache_only($disk) {
  return _var($disk,'type') == 'Cache';
}

function luks_only($disk) {
  return _var($disk,'type') == 'Data' || _var($disk,'type') == 'Cache';
}

function main_filter($disks) {
  return array_filter($disks, 'main_only');
}

function parity_filter($disks) {
  return array_filter($disks, 'parity_only');
}

function data_filter($disks) {
  return array_filter($disks, 'data_only');
}

function cache_filter($disks) {
  return array_filter($disks, 'cache_only');
}

function luks_filter($disks) {
  return array_filter($disks, 'luks_only');
}

function pools_filter($disks) {
  return array_unique(array_map('prefix', array_keys(cache_filter($disks))));
}

function my_id($id) {
  global $display;
  $len = strlen($id);
  $wwn = substr($id,-18);
  return (_var($display,'wwn') || substr($wwn,0,2) != '_3' || preg_match('/.[_-]/',$wwn)) ? $id : substr($id,0,$len-18);
}

function my_word($num) {
  $words = ['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty','twenty-one','twenty-two','twenty-three','twenty-four','twenty-five','twenty-six','twenty-seven','twenty-eight','twenty-nine','thirty'];
  return $num < count($words) ? _($words[$num],1) : $num;
}

function my_usage() {
  global $disks, $var, $display;
  $arraysize = 0;
  $arrayfree = 0;
  foreach ($disks as $disk) {
    if (strpos(_var($disk,'name'),'disk') !== false) {
      $arraysize += _var($disk,'sizeSb',0);
      $arrayfree += _var($disk,'fsFree',0);
    }
  }
  if (_var($var,'fsNumMounted',0) > 0) {
    $used = $arraysize ? 100-round(100*$arrayfree/$arraysize) : 0;
    echo "<div class='usage-bar'><span style='width:{$used}%' class='".usage_color($display,$used,false)."'>{$used}%</span></div>";
  } else {
    echo "<div class='usage-bar'><span style='text-align:center'>".($var['fsState']=='Started'?'Maintenance':'off-line')."</span></div>";
  }
}

function usage_color(&$disk, $limit, $free) {
  global $display;
  if (_var($display,'text',0) == 1 || intval(_var($display,'text',0)/10) == 1) return '';
  $critical = _var($disk,'critical') >= 0 ? $disk['critical'] : (_var($display,'critical') >= 0 ? $display['critical'] : 0);
  $warning = _var($disk,'warning') >= 0 ? $disk['warning'] : (_var($display,'warning') >= 0 ? $display['warning'] : 0);
  if (!$free) {
    if ($critical > 0 && $limit >= $critical) return 'redbar';
    if ($warning > 0 && $limit >= $warning) return 'orangebar';
    return 'greenbar';
  } else {
    if ($critical > 0 && $limit <= 100-$critical) return 'redbar';
    if ($warning > 0 && $limit <= 100-$warning) return 'orangebar';
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
  return plus($days,'day',($hour|$mins|$secs) == 0).plus($hour,'hour',($mins|$secs) == 0).plus($mins,'minute',$secs == 0).plus($secs,'second',true).". "._('Average speed').": ".(is_numeric($speed) ? my_scale($speed,$unit,1)." $unit/s" : $speed);
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
  return "<option value='$value'".($value == $select ? " selected" : "").(strlen($extra) ? " $extra" : "").">$text</option>";
}

function mk_option_check($name, $value, $text="") {
  if ($text) {
    $checked = in_array($value,explode(',',$name)) ? " selected" : "";
    return "<option value='$value'$checked>$text</option>";
  }
  if (strpos($name,'disk') !== false) {
    $checked = in_array($name,explode(',',$value)) ? " selected" : "";
    return "<option value='$name'$checked>".my_disk($name)."</option>";
  }
}

function mk_option_luks($name, $value, $luks) {
  if (strpos($name,'disk') !== false) {
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
  case ($days < 0):
    return;
  case ($days == 0):
    return " <span class='green-text'>("._('today').")</span>";
  case ($days == 1):
    return " <span class='green-text'>("._('yesterday').")</span>";
  case ($days <= 31):
    return " <span class='green-text'>(".sprintf(_('%s days ago'),my_word($days)).")</span>";
  case ($days <= 61):
    return " <span class='orange-text'>(".sprintf(_('%s days ago'),$days).")</span>";
  case ($days > 61):
    return " <span class='red-text'>(".sprintf(_('%s days ago'),$days).")</span>";
  }
}

function plus($val, $word, $last) {
  return $val > 0 ? (($val || $last) ? ($val.' '._($word.($val != 1 ? 's' : '')).($last ? '' : ', ')) : '') : '';
}

function compress($name, $size=18, $end=6) {
  return mb_strlen($name) <= $size ? $name : mb_substr($name, 0, $size-($end ? $end+3 : 0)).'...'.($end ? mb_substr($name,-$end) : '');
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

/* Get the last parity check from the parity history. */
function last_parity_log() {
  $log = '/boot/config/parity-checks.log';
  if (file_exists($log)) {
    [$date, $duration, $speed, $status, $error, $action, $size] = my_explode('|', tail($log), 7);
  } else {
    [$date, $duration, $speed, $status, $error, $action, $size] = array_fill(0, 7, 0);
  }
  if ($date) {
    [$y, $m, $d, $t] = my_preg_split('/ +/', $date, 4);
    $date = strtotime("$d-$m-$y $t");
  }
  return [$date, $duration, $speed, $status, $error, $action, $size];
}


/* Get the last parity check from Unraid. */
function last_parity_check() {
  global $var;
  /* Files for the latest parity check. */
  $stamps = '/var/tmp/stamps.ini';
  $resync = '/var/tmp/resync.ini';
  /* Get the latest parity information from Unraid. */
  $synced   = file_exists($stamps) ? explode(',',file_get_contents($stamps)) : [];
  $sbSynced = array_shift($synced) ?: _var($var,'sbSynced',0);
  $idle   = [];
  while (count($synced) > 1) {
    $idle[] = array_pop($synced) - array_pop($synced);
  }
  $action   = _var($var, 'mdResyncAction');
  $size   = _var($var, 'mdResyncSize', 0);
  if (file_exists($resync)) {
    list($action, $size) = my_explode(',', file_get_contents($resync));
  }
  $duration = $var['sbSynced2']-$sbSynced-array_sum($idle);
  $status   = _var($var,'sbSyncExit');
  $speed    = $status==0 ? round($size*1024/$duration) : 0;
  $error    = _var($var,'sbSyncErrs',0);
  return [$duration, $speed, $status, $error, $action, $size];
}

function urlencode_path($path) {
  return str_replace("%2F", "/", urlencode($path));
}

function pgrep($process_name, $escape_arg=true) {
  $pid = exec('pgrep --ns $$ '.($escape_arg ? escapeshellarg($process_name) : $process_name), $output, $retval);
  return $retval == 0 ? $pid : false;
}

function is_block($path) {
  return (@filetype(realpath($path)) == 'block');
}

function autov($file, $ret=false) {
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
  if (strpos($path,'/mnt/user/') === 0 && file_exists($path)) {
    $realdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($path)." 2>/dev/null"));
    if (!empty($realdisk))
      $path = str_replace('/mnt/user/', "/mnt/$realdisk/", $path);
  }
  return $path;
}

function cpu_list() {
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list|sort -nu', $cpus);
  return $cpus;
}

function my_explode($split, $text, $count=2) {
  return array_pad(explode($split, $text, $count), $count, '');
}

function my_preg_split($split, $text, $count=2) {
  return array_pad(preg_split($split, $text, $count), $count, '');
}

function delete_file(...$file) {
  array_map('unlink', array_filter($file,'file_exists'));
}

function my_mkdir($dirname, $permissions=0777, $recursive=false, $own="nobody", $grp="users") {
  write_logging("Check if dir exists\n");
  if (is_dir($dirname)) {write_logging("Dir exists\n"); return(false);}
  write_logging("Dir does not exist\n");
  $parent = $dirname;
  write_logging("Getting $parent\n");
  while (!is_dir($parent)){
    if (!is_dir($parent)) write_logging("Not parent  $parent\n"); else write_logging("Parent $parent is\n");
    if (!$recursive) return(false);
    $pathinfo2 = pathinfo($parent);
    $parent = $pathinfo2["dirname"];
  }
  write_logging("Parent $parent\n");
  if (strpos($dirname,'/mnt/user/') === 0) {
    write_logging("Getting real disks\n");
    $realdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($parent)." 2>/dev/null"));
    if (!empty($realdisk)) {
      $dirname = str_replace('/mnt/user/', "/mnt/$realdisk/", $dirname);
      $parent = str_replace('/mnt/user/', "/mnt/$realdisk/", $parent);
    }
  }
  $fstype = trim(shell_exec(" stat -f -c '%T' $parent"));
  $rtncode = false;
  write_logging("fstype:$fstype parent $parent dir name $dirname\n");
  switch ($fstype) {
    case "zfs":
      if (is_dir($parent.'/.zfs')) {
        write_logging("ZFS Volume\n");
        $zfsdataset = trim(shell_exec("zfs list -H -o name  $parent"));
        write_logging("Shell $zfsdataset\n");
        $zfsdataset .= str_replace($parent,"",$dirname);
        write_logging("Dataset $zfsdataset\n");
        $zfsoutput = array();
        if ($recursive) exec("zfs create -p \"$zfsdataset\"",$zfsoutput,$rtncode);else exec("zfs create \"$zfsdataset\"", $zfsoutput, $rtncode);
        write_logging("Output: {$zfsoutput[0]} $rtncode");
        if ($rtncode == 0)  write_logging( " ZFS Command OK\n"); else  write_logging( "ZFS Command Fail\n");
      } else {write_logging("Not ZFS dataset\n");$rtncode = 1;}
      if ($rtncode > 0) { mkdir($dirname, $permissions, $recursive); write_logging( "created dir:$dirname\n");} else chmod($zfsdataset, $permissions);
      break;
    case "btrfs":
      $btrfsoutput = array();
      if ($recursive) exec("btrfs subvolume create --parents \"$dirname\"",$btrfsoutput,$rtncode); else exec("btrfs subvolume create \"$dirname\"", $btrfsoutput, $rtncode);
      if ($rtncode > 0) mkdir($dirname, $permissions, $recursive); else chmod($dirname, $permissions);
      break;
    default:
      mkdir($dirname, $permissions, $recursive);
      break;
  }
  chown($dirname, $own);
  chgrp($dirname, $grp);
  return($rtncode);
}

function my_rmdir($dirname) {
  if (!is_dir("$dirname")) {
    $return = [
      'rtncode' => "false",
      'type' => "NoDir",
    ];
    return($return);
  }
  if (strpos($dirname,'/mnt/user/') === 0) {
    $realdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($dirname)." 2>/dev/null"));
    if (!empty($realdisk)) {
      $dirname = str_replace('/mnt/user/', "/mnt/$realdisk/", "$dirname");
    }
  }
  $fstype = trim(shell_exec(" stat -f -c '%T' ".escapeshellarg($dirname)));
  $rtncode = false;
  switch ($fstype) {
    case "zfs":
      $zfsoutput = array();
      $zfsdataset = trim(shell_exec("zfs list -H -o name  ".escapeshellarg($dirname))) ;
      $cmdstr = "zfs destroy \"$zfsdataset\"  2>&1 ";
      $error = exec($cmdstr,$zfsoutput,$rtncode);
      $return = [
        'rtncode' => $rtncode,
        'output' => $zfsoutput,
        'dataset' => $zfsdataset,
        'type' => $fstype,
        'cmd' => $cmdstr,
        'error' => $error,
      ];
      break;
    case "btrfs":
    default:
      $rtncode = rmdir($dirname);
      $return = [
        'rtncode' => $rtncode,
        'type' => $fstype,
      ];
      break;
  }
  return($return);
}

function get_realvolume($path) {
  if (strpos($path,"/mnt/user/",0) === 0)
    $reallocation = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($path)." 2>/dev/null"));
  else {
    $realexplode = explode("/",str_replace("/mnt/","",$path));
    $reallocation = $realexplode[0];
  }
  return $reallocation;
}

function write_logging($value) {
  $debug = is_file("/tmp/my_mkdir_debug");
  if (!$debug) return;
  file_put_contents('/tmp/my_mkdir_output', $value, FILE_APPEND);
}

function device_exists($name) {
  global $disks, $devs;
  return (array_key_exists($name, $disks) && !str_contains(_var($disks[$name],'status'),'_NP')) || (array_key_exists($name, $devs));
}

# Check for process Core Types.
function parse_cpu_ranges($file) {
  if (!is_file($file)) return null;
  $ranges = file_get_contents($file);
  $ranges = trim($ranges);
  if ($ranges === '') return null;
  $cores = [];
  foreach (explode(',', $ranges) as $range) {
    if (strpos($range, '-') !== false) {
      list($start, $end) = explode('-', $range);
      $cores = array_merge($cores, range((int)$start, (int)$end));
    } else {
      $cores[] = (int)$range;
    }
  }
  return $cores;
}

function get_intel_core_types() {
  $core_types = array();
  $cpu_core_file = "/sys/devices/cpu_core/cpus";
  $cpu_atom_file = "/sys/devices/cpu_atom/cpus";
  $p_cores = parse_cpu_ranges($cpu_core_file);
  $e_cores = parse_cpu_ranges($cpu_atom_file);
  if ($p_cores) {
    foreach ($p_cores as $core) {
      $core_types[$core] = _("P-Core");
    }
  }
  if ($e_cores) {
    foreach ($e_cores as $core) {
      $core_types[$core] = _("E-Core");
    }
  }
  return $core_types;
}

function dmidecode($key, $n, $all=true) {
  $entries = array_filter(explode($key, shell_exec("dmidecode -qt$n")??""));
  $properties = [];
  foreach ($entries as $entry) {
    $property = [];
    foreach (explode("\n",$entry) as $line) if (strpos($line,': ') !== false) {
      [$key, $value] = my_explode(': ',trim($line));
      $property[$key] = $value;
    }
    $properties[] = $property;
  }
  return $all ? $properties : $properties[0] ?? null;
}

function is_intel_cpu() {
  $cpu = dmidecode('Processor Information','4',0);
  $cpu_vendor = $cpu['Manufacturer'] ?? "";
  $is_intel_cpu = stripos($cpu_vendor, "intel") !== false ? true : false;
  return $is_intel_cpu;
}

// Load saved PCI data
function loadSavedData($filename) {
  if (file_exists($filename)) {
    $saveddata = file_get_contents($filename);
  } else $saveddata = "";
  return json_decode($saveddata, true);
}

// Run lspci -Dmn to get the current devices
function loadCurrentPCIData() {
  $output = shell_exec('lspci -Dmn');
  $devices = [];
  if (file_exists("/boot/config/current.json")) {
    $devices = loadSavedData("/boot/config/current.json");
  } else {
    foreach (explode("\n", trim($output)) as $line) {
      $parts = explode(" ", $line);
      if (count($parts) < 6) continue; // Skip malformed lines
      $description_str = shell_exec(("lspci -s ".$parts[0]));
      $description = preg_replace('/^\S+\s+/', '', $description_str);
      $device = [
        'class'       => trim($parts[1], '"'),
        'vendor_id'   => trim($parts[2], '"'),
        'device_id'   => trim($parts[3], '"'),
        'description' => trim($description,'"'),
      ];
      $devices[$parts[0]] = $device;
    }
  }
  return $devices;
}

// Compare the saved and current data
function comparePCIData() {
  $changes = [];
  $saved = loadSavedData("/boot/config/savedpcidata.json");
  if (!$saved) return [];
  $current = loadCurrentPCIData();
  // Compare saved devices with current devices
  foreach ($saved as $pci_id => $saved_device) {
    if (!isset($current[$pci_id])) {
      // Device has been removed
      $changes[$pci_id] = [
        'status' => 'removed',
        'device' => $saved_device
      ];
    } else {
      // Device exists in both, check for modifications
      $current_device = $current[$pci_id];
      $differences = [];
      // Compare fields
      foreach (['vendor_id', 'device_id', 'class'] as $field) {
        if (isset($saved_device[$field]) && isset($current_device[$field]) && $saved_device[$field] !== $current_device[$field]) {
          $differences[$field] = [
            'old' => $saved_device[$field],
            'new' => $current_device[$field]
          ];
        }
      }
      if (!empty($differences)) {
        $changes[$pci_id] = [
          'status' => 'changed',
          'device' => $current_device,
          'differences' => $differences
        ];
      }
    }
  }
  // Check for added devices
  foreach ($current as $pci_id => $current_device) {
    if (!isset($saved[$pci_id])) {
      // Device has been added
      $changes[$pci_id] = [
        'status' => 'added',
        'device' => $current_device
      ];
    }
  }
  return $changes;
}

function clone_list($disk) {
  global $pools;
  return strpos($disk['status'],'_NP') === false && ($disk['type'] == 'Data' || in_array($disk['name'], $pools));
}
?>
