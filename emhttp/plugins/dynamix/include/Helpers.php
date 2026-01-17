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

function boot_only($disk) {
  return _var($disk,'type') == 'Boot';
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

function boot_filter($disks) {
  return array_filter($disks, 'boot_only');
}

function luks_filter($disks) {
  return array_filter($disks, 'luks_only');
}

function pools_filter($disks) {
  $cache_pools = array_keys(cache_filter($disks));
  return array_unique(array_map('prefix', $cache_pools));
}

function pools_and_boot_filter($disks) {
  $cache_pools = array_keys(cache_filter($disks));
  $boot_pools = array_keys(boot_filter($disks));
  return array_unique(array_map('prefix', array_merge($cache_pools, $boot_pools)));
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

function check_deprecated_filesystem($disk) {
  $fsType = _var($disk, 'fsType', '');
  $name = _var($disk, 'name', '');
  $warnings = [];
  
  // Check for ReiserFS
  if (stripos($fsType, 'reiserfs') !== false) {
    $warnings[] = [
      'type' => 'reiserfs',
      'severity' => 'critical',
      'message' => _('ReiserFS is deprecated and will not be supported in future Unraid releases')
    ];
  }
  
  // Check for XFS v4 (lacks CRC checksums)
  if (stripos($fsType, 'xfs') !== false) {
    // Check if disk is mounted to determine XFS version
    $mountPoint = "/mnt/$name";
    if (is_dir($mountPoint) && exec("mountpoint -q " . escapeshellarg($mountPoint) . " 2>/dev/null", $output, $ret) && $ret == 0) {
      // Check for crc=0 which indicates XFS v4
      $xfsInfo = shell_exec("xfs_info " . escapeshellarg($mountPoint) . " 2>/dev/null");
      if ($xfsInfo && strpos($xfsInfo, 'crc=0') !== false) {
        $warnings[] = [
          'type' => 'xfs_v4',
          'severity' => 'critical',
          'message' => _('XFS v4 is deprecated and will not be supported in future Unraid releases. Please migrate to XFS v5 immediately')
        ];
      }
    }
  }
  
  return $warnings;
}

function get_filesystem_warning_icon($warnings) {
  if (empty($warnings)) return '';
  
  $hasCritical = false;
  $messages = [];
  
  foreach ($warnings as $warning) {
    if ($warning['severity'] == 'critical') {
      $hasCritical = true;
    }
    $messages[] = $warning['message'];
  }
  
  $icon = $hasCritical ? 'exclamation-triangle' : 'exclamation-circle';
  $color = $hasCritical ? 'red-text' : 'orange-text';
  $tooltip = implode('. ', $messages);
  
  return " <i class='fa fa-$icon $color' title='$tooltip'></i>";
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
  return array_pad(explode($split, $text??"", $count), $count, '');
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
  $cpu_vendor_check = exec("grep -Pom1 '^model name\s+:\s*\K.+' /proc/cpuinfo") ?? "";
  return stripos($cpu_vendor_check, "intel") !== false;
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

// Deprecated filesystem detection and display functions

// Core function to check a single disk for deprecated filesystems
function check_disk_for_deprecated_fs($disk) {
  $deprecated = [];
  $fsType = strtolower(_var($disk, 'fsType', ''));
  
  // Check for ReiserFS
  if (strpos($fsType, 'reiserfs') !== false) {
    $deprecated[] = [
      'name' => _var($disk, 'name'),
      'fsType' => 'ReiserFS',
      'severity' => 'critical',
      'message' => 'ReiserFS is deprecated and will not be supported in future Unraid releases'
    ];
  }
  
  // Check for XFS v4 (lacks CRC checksums)
  if (strpos($fsType, 'xfs') !== false) {
    $name = _var($disk, 'name');
    $mountPoint = "/mnt/$name";
    
    // Check if disk is mounted
    if (is_dir($mountPoint)) {
      exec("mountpoint -q " . escapeshellarg($mountPoint) . " 2>/dev/null", $output, $ret);
      if ($ret == 0) {
        // Get XFS info to check for crc=0 which indicates XFS v4
        $xfsInfo = shell_exec("xfs_info " . escapeshellarg($mountPoint) . " 2>/dev/null");
        if ($xfsInfo && strpos($xfsInfo, 'crc=0') !== false) {
          $deprecated[] = [
            'name' => $name,
            'fsType' => 'XFS v4',
            'severity' => 'notice',
            'message' => 'XFS v4 is deprecated and will not be supported in future Unraid releases. You have until 2030 to migrate to XFS v5.'
          ];
        }
      }
    }
  }
  
  return $deprecated;
}

// Generate inline warning HTML for a single disk
function get_inline_fs_warnings($disk) {
  $warnings = check_disk_for_deprecated_fs($disk);
  $html = '';
  
  foreach ($warnings as $warning) {
    if ($warning['severity'] === 'critical') {
      // ReiserFS - critical warning
      $html .= '<span id="reiserfs" class="warning"><i class="fa fa-exclamation-triangle"></i>&nbsp;' . 
               htmlspecialchars(_($warning['message'])) . '</span>';
    } else {
      // XFS v4 - notice (without .notice class to avoid duplicate icon)
      $html .= '<div id="xfsv4" style="color:#0066cc; margin: 5px 0; line-height: 1.5;">' . 
               '<i class="fa fa-info-circle"></i>&nbsp;' . 
               htmlspecialchars(_($warning['message'])) . '</div>';
    }
  }
  
  return $html;
}

// Check array of disks for deprecated filesystems (used by Main page)
function check_deprecated_filesystems_array($disks, $filter_function) {
  $deprecated = [];
  
  foreach ($filter_function($disks) as $disk) {
    if (substr($disk['status'],0,7) != 'DISK_NP') {
      $disk_warnings = check_disk_for_deprecated_fs($disk);
      $deprecated = array_merge($deprecated, $disk_warnings);
    }
  }
  
  return $deprecated;
}

function display_deprecated_filesystem_warning($deprecated_disks, $type = 'array') {
  if (empty($deprecated_disks)) return '';
  
  // Separate warnings by severity
  $critical_disks = [];
  $notice_disks = [];
  
  foreach ($deprecated_disks as $disk) {
    if (_var($disk, 'severity', 'critical') === 'critical') {
      $critical_disks[] = $disk;
    } else {
      $notice_disks[] = $disk;
    }
  }
  
  $html = '';
  
  // Critical warnings (ReiserFS) - severe styling, reappears on every page load
  if (!empty($critical_disks)) {
    $id = $type === 'array' ? 'array-critical-warning' : 'pool-critical-warning';
    $title = htmlspecialchars($type === 'array' ? 'Critical: Deprecated Filesystem' : 'Critical: Pool Deprecated Filesystem');
    $description = htmlspecialchars($type === 'array' ? 
      'The following array devices are using deprecated filesystems:' : 
      'The following pool devices are using deprecated filesystems:');
    
    $diskList = '';
    foreach ($critical_disks as $disk) {
      $name = htmlspecialchars($disk['name']);
      $fsType = htmlspecialchars($disk['fsType']);
      $message = htmlspecialchars($disk['message']);
      $diskList .= "<li><strong>{$name}:</strong> {$fsType} - {$message}</li>\n";
    }
    
    $html .= <<<HTML
<div id="{$id}" style="margin: 20px 0;">
    <div style="background: #feefb3; border: 1px solid #ff8c2f; border-radius: 4px; padding: 15px; position: relative;">
        <button onclick="$('#{$id}').fadeOut();" 
                style="position: absolute; right: 10px; top: 10px; background: transparent; border: none; color: #ff8c2f; cursor: pointer; font-size: 1.2em;">
            <i class="fa fa-times"></i>
        </button>
        <div style="display: flex; align-items: start;">
            <i class="fa fa-exclamation-triangle" style="color: #ff8c2f; margin-right: 10px; font-size: 1.2em;"></i>
            <div style="flex: 1; color: #000;">
                <div style="font-weight: bold; margin-bottom: 10px; color: #ff8c2f;">
                    {$title}
                </div>
                <div style="margin-bottom: 10px;">
                    {$description}
                </div>
                <ul style="margin: 10px 0 10px 20px;">
                    {$diskList}
                </ul>
                <div style="margin-top: 10px;">
                    <strong>Action Required:</strong> Migrate to a supported filesystem (XFS v5, BTRFS, or ZFS). 
                    <a href="https://docs.unraid.net/go/convert-reiser-and-xfs" 
                       target="_blank" style="color: #ff8c2f;">View migration guide →</a>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
  }
  
  // Notice warnings (XFS v4) - less severe styling, dismissible until reboot via sessionStorage
  if (!empty($notice_disks)) {
    $id = $type === 'array' ? 'array-notice-warning' : 'pool-notice-warning';
    $title = htmlspecialchars($type === 'array' ? 'Notice: Filesystem Update Available' : 'Notice: Pool Filesystem Update Available');
    $description = htmlspecialchars($type === 'array' ? 
      'The following array devices are using older filesystem versions:' : 
      'The following pool devices are using older filesystem versions:');
    
    $diskList = '';
    foreach ($notice_disks as $disk) {
      $name = htmlspecialchars($disk['name']);
      $fsType = htmlspecialchars($disk['fsType']);
      $message = htmlspecialchars($disk['message']);
      $diskList .= "<li><strong>{$name}:</strong> {$fsType} - {$message}</li>\n";
    }
    
    $html .= <<<HTML
<script>
// Check if XFS warning was dismissed this session
if (!sessionStorage.getItem('xfs-{$id}-dismissed')) {
  document.write(`
<div id="{$id}" style="margin: 20px 0;">
    <div style="background: #e7f3ff; border: 1px solid #0066cc; border-radius: 4px; padding: 15px; position: relative;">
        <button onclick="sessionStorage.setItem('xfs-{$id}-dismissed', 'true'); $('#{$id}').fadeOut();" 
                style="position: absolute; right: 10px; top: 10px; background: transparent; border: none; color: #0066cc; cursor: pointer; font-size: 1.2em;"
                title="Dismiss until reboot">
            <i class="fa fa-times"></i>
        </button>
        <div style="display: flex; align-items: start;">
            <i class="fa fa-info-circle" style="color: #0066cc; margin-right: 10px; font-size: 1.2em;"></i>
            <div style="flex: 1; color: #000;">
                <div style="font-weight: bold; margin-bottom: 10px; color: #0066cc;">
                    {$title}
                </div>
                <div style="margin-bottom: 10px;">
                    {$description}
                </div>
                <ul style="margin: 10px 0 10px 20px;">
                    {$diskList}
                </ul>
                <div style="margin-top: 10px;">
                    <strong>Recommendation:</strong> Plan to migrate to XFS v5, BTRFS, or ZFS within the next 5 years. 
                    <a href="https://docs.unraid.net/go/convert-reiser-and-xfs" 
                       target="_blank" style="color: #0066cc;">View migration guide →</a>
                </div>
            </div>
        </div>
    </div>
</div>
  `);
}
</script>
HTML;
  }
  
  return $html;
}

function get_cpu_packages(string $separator = ','): array {
    $packages = [];
    foreach (glob("/sys/devices/system/cpu/cpu[0-9]*/topology/thread_siblings_list") as $path) {
        $pkg_id   = (int)file_get_contents(dirname($path) . "/physical_package_id");
        $siblings = str_replace(",", $separator, trim(file_get_contents($path)));
        if (!in_array($siblings, $packages[$pkg_id] ?? [])) {
            $packages[$pkg_id][] = $siblings;
        }
    }
    foreach ($packages as &$list) {
        $keys = array_map(fn($s) => (int)explode($separator, $s)[0], $list);
        array_multisort($keys, SORT_ASC, SORT_NUMERIC, $list);
    }
    unset($list);
    return $packages;
}


function getIpAddressesByPci(string $pciAddress): array
{
    $base = "/sys/bus/pci/devices/$pciAddress/net";

    if (!is_dir($base)) {
        return [];
    }

    $interfaces = scandir($base);
    $result = [];

    foreach ($interfaces as $iface) {
        if ($iface === '.' || $iface === '..') continue;

        //
        // Walk upward (eth0 → bond0 → br0 → ...)
        //
        $chain = [];
        $curr = $iface;

        while (true) {
            $chain[] = $curr;

            $masterLink = "/sys/class/net/$curr/master";
            if (!is_link($masterLink)) {
                break;
            }

            $curr = basename(readlink($masterLink));
        }

        //
        // Now $chain contains all relevant interfaces
        // Example: [eth0, bond0, br0]
        //

        foreach ($chain as $dev) {
            $cmd = sprintf('ip -o addr show dev %s 2>/dev/null', escapeshellarg($dev));
            $output = shell_exec($cmd);

            if (!$output) continue;

            foreach (explode("\n", trim($output)) as $line) {

                // IPv4
                if (preg_match('/inet\s+(\d+\.\d+\.\d+\.\d+\/\d+)/', $line, $m)) {
                    $result[$dev][] = $m[1];
                }

                // IPv6
                if (preg_match('/inet6\s+([0-9a-fA-F:]+\/\d+)/', $line, $m)) {
                    $result[$dev][] = $m[1];
                }
            }
        }
    }

    //
    // Remove duplicates while preserving interface keys
    //
    foreach ($result as $iface => $ips) {
        $result[$iface] = array_values(array_unique($ips));
    }

    return $result;
}

function getSystemNumaNodeCount() {
    $nodes = glob("/sys/devices/system/node/node*") ?: [];
    $count = count($nodes);
    // Treat “no NUMA directory” as a single-node system
    return $count > 0 ? $count : 1;
}

function normalizeNumaNode($node, $numNodes) {
    // If system has only 1 node, interpret -1 as node 0
    if ($numNodes === 1 && $node === -1) {
        return 0;
    }
    return $node;
}

function getCpuNumaInfo($numNodes) {
    $cpus = [];

    foreach (glob("/sys/devices/system/cpu/cpu[0-9]*") as $cpuPath) {
        $cpu = basename($cpuPath);

        $nodes = glob("$cpuPath/node*");
        $node = -1;

        if (!empty($nodes)) {
            $node = intval(str_replace("node", "", basename($nodes[0])));
        }

        $node = normalizeNumaNode($node, $numNodes);

        $cpus[$cpu] = [
            "cpu_id" => intval(str_replace("cpu", "", $cpu)),
            "numa_node" => $node
        ];
    }

    return $cpus;
}

function getPciNumaInfo($numNodes) {
    $pci = [];

    foreach (glob("/sys/bus/pci/devices/*") as $devPath) {
        $dev = basename($devPath);

        $numaNodeFile = "$devPath/numa_node";
        $node = file_exists($numaNodeFile) ? intval(trim(file_get_contents($numaNodeFile))) : -1;

        $node = normalizeNumaNode($node, $numNodes);

        $desc = trim(shell_exec("lspci -mm -s $dev 2>/dev/null"));

        $pci[$dev] = [
            "pci_address" => $dev,
            "numa_node" => $node,
            "description" => $desc
        ];
    }

    return $pci;
}

function getNumaInfo() {
    $numNodes = getSystemNumaNodeCount();

    $result = [
        "system" => [
            "numa_nodes" => $numNodes,
        ],
        "cpus" => getCpuNumaInfo($numNodes),
        "pci_devices" => getPciNumaInfo($numNodes),
    ];

    if (is_file("/tmp/numain")) {
        $numain  = file_get_contents("/tmp/numain");
        $override = json_decode($numain, true);
        if (is_array($override)) {
            $result = $override;
        }
    }

    return $result;
}
/**
 * Get PCIe link data from sysfs with generation + clean GT/s rate.
 * Suppresses sentinel max‑width value 255 (unreported/invalid); preserves 0 when reported.
 * Downgrade flags are set only for non‑bridge/root‑port devices (PCI class != 0x06).
 *
 * @param string $pciAddress
 * @return array
 */
function getPciLinkInfo($pciAddress)
{
    $base = "/sys/bus/pci/devices/$pciAddress";

    $files = [
        "current_speed" => "$base/current_link_speed",
        "max_speed"     => "$base/max_link_speed",
        "current_width" => "$base/current_link_width",
        "max_width"     => "$base/max_link_width",
    ];

    $out = [
        "current_speed"    => null,
        "max_speed"        => null,
        "current_width"    => null,
        "max_width"        => null,
        "speed_downgraded" => false,
        "width_downgraded" => false,
        "rate"             => "GT/s",
        "generation"       => null,
    ];

    // If the device path doesn't exist, just return empty defaults
    if (!is_dir($base)) {
        return $out;
    }

    // Read speeds
    foreach ($files as $key => $file) {
        if (!file_exists($file)) continue;
        $value = trim(file_get_contents($file));
        // Handle speeds
        if (strpos($key, 'speed') !== false) {
            if (preg_match('/([0-9.]+)/', $value, $m)) {
                $out[$key] = floatval($m[1]);
            }
        }

        // Handle widths (do not apply suppression yet)
        if ($key === 'max_width') {
            $out['max_width_raw'] = intval(str_replace('x', '', $value));
        }
        if ($key === 'current_width') {
            $out['current_width_raw'] = intval(str_replace('x', '', $value));
        }
    }

    // Apply width rules
    $max = $out['max_width_raw'] ?? null;
    $cur = $out['current_width_raw'] ?? null;

    if ($max === 255) {
        // Invalid / not reported
        $out["max_width"] = null;
        $out["current_width"] = null;
    } else {
        // Valid max width → keep 0 as 0
        $out["max_width"] = $max;

        if ($cur === 0) {
            // 0 is valid when max != 255
            $out["current_width"] = 0;
        } else {
            $out["current_width"] = $cur;
        }
    }
    unset($out["max_width_raw"], $out["current_width_raw"]);  // Cleanup
    // Downgrade flags
    if (file_exists("$base/class")) {
        $class_raw   = trim(file_get_contents("$base/class"));
        $class_check = strpos($class_raw, "0x06", 0);
    } else {
        $class_check = false;
    }
    if ($out["current_speed"] && $out["max_speed"] && $class_check === false) {
        $out["speed_downgraded"] = ($out["current_speed"] < $out["max_speed"]);
    }
    if ($out["current_width"] !== null && $out["max_width"]     !== null && $out["current_width"] < $out["max_width"] && $class_check === false) {
        $out["width_downgraded"] = true;
    }
    // PCIe Generation Table
    $genTable = [
        1 => 2.5,
        2 => 5.0,
        3 => 8.0,
        4 => 16.0,
        5 => 32.0,
        6 => 64.0,
    ];
    // Determine generation from max_speed
    if (!empty($out["max_speed"])) {
        $speed = $out["max_speed"];
        foreach ($genTable as $gen => $gt) {
            if (abs($speed - $gt) < 0.5) {
                $out["generation"] = $gen;
                break;
            }
        }
    }
    return $out;
}

function storagePoolsJson(): string
{
    $result = [
        'source' => 'unraid',
        'pools' => [],
        'generated_at' => gmdate('c'),
    ];

    $unraidIni = '/usr/local/emhttp/state/disks.ini';
    if (!is_readable($unraidIni)) {
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    $lines = file($unraidIni, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $sections = [];
    $current = null;

    // Parse disks.ini into sections
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^\["(.+)"\]$/', $line, $m)) {
            $current = $m[1];
            $sections[$current] = [];
            continue;
        }
        if ($current && preg_match('/^([a-zA-Z0-9_]+)="(.*)"$/', $line, $m)) {
            $sections[$current][$m[1]] = $m[2];
        }
    }

    // Collect all pool names for filtering
    $allPoolNames = [];
    foreach ($sections as $name => $data) {
        if (!preg_match('/^disk/i', $name) && 
            isset($data['fsType']) && 
            in_array($data['fsType'], ['btrfs', 'zfs'], true)) {
            $allPoolNames[] = strtolower($name);
        }
    }

    foreach ($sections as $poolName => $s) {
        // Skip array disks (disk0, disk1, etc.)
        if (preg_match('/^disk/i', $poolName)) continue;

        if (
            !isset($s['fsType'], $s['fsMountpoint'], $s['fsStatus']) ||
            !in_array($s['fsType'], ['btrfs', 'zfs'], true) ||
            $s['fsStatus'] !== 'Mounted'
        ) {
            continue;
        }

        $pool = [
            'name' => $poolName,
            'fstype' => $s['fsType'],
            'mountpoint' => $s['fsMountpoint'],
            'uuid' => $s['uuid'] ?? null,
            'role' => $s['type'] ?? null,
            'size' => $s['fsSize'] ?? null,
            'used' => $s['fsUsed'] ?? null,
            'free' => $s['fsFree'] ?? null,
            'members' => [],
            'overall_status' => 'UNKNOWN',
            'source' => 'disks.ini',
        ];
        
        // Find all member disks for this pool (e.g., cache, cache2, cache3)
        $poolPrefix = preg_replace('/\d+$/', '', $poolName); // Remove trailing numbers
        $expectedMembers = [];
        foreach ($sections as $diskName => $diskData) {
            // Check if this disk belongs to our pool
            if (preg_match('/^disk/i', $diskName)) continue; // Skip array disks
            $diskPrefix = preg_replace('/\d+$/', '', $diskName);
            if ($diskPrefix === $poolPrefix) {
                $expectedMembers[$diskName] = $diskData;
            }
        }

        // --- Btrfs ---
        if ($s['fsType'] === 'btrfs') {
            $mount = escapeshellarg($s['fsMountpoint']);
            $uuid = $s['uuid'] ?? '';
            
            // Use UUID to query specific filesystem
            $btrfsShow = [];
            if ($uuid) {
                $uuidEsc = escapeshellarg($uuid);
                exec("btrfs filesystem show $uuidEsc 2>/dev/null", $btrfsShow, $rc);
            } else {
                exec("btrfs filesystem show $mount 2>/dev/null", $btrfsShow, $rc);
            }
            
            if ($rc === 0) {
              $hasMissing = false;
              $hasFailed  = false;
                foreach ($btrfsShow as $line) {
                    if (preg_match('/^\s+devid\s+(\d+)\s+size\s+(\S+)\s+used\s+(\S+)\s+path\s+(\S+)/', $line, $m)) {
                        $devicePath = $m[4];
                  $isMissing = stripos($devicePath, '<missing') === 0;
                  $isZeroSize = $m[2] === '0' || (float)$m[2] == 0.0;
                  $deviceKey = $isMissing ? "missing_devid{$m[1]}" : preg_replace('/p?\d+$/', '', str_replace('/dev/', '', $devicePath));
                  $pool['members'][$deviceKey] = [
                    'devid' => $m[1],
                    'device' => $isMissing ? '<missing>' : $deviceKey,
                    'size' => $m[2],
                    'used' => $m[3],
                    'status' => $isMissing ? 'MISSING' : ($isZeroSize ? 'FAILED' : 'OK'),
                  ];
                  if ($isMissing) {
                    $hasMissing = true;
                  } elseif ($isZeroSize) {
                    $hasFailed = true;
                  }
                    }
                }

                // Check device stats for errors (try both old and new format)
                $stats = [];
                exec("btrfs device stats $mount", $stats, $rc2);
                if ($rc2 === 0) {
                    foreach ($stats as $line) {
                        // New format: [/dev/sda1].write_io_errs 0
                        if (preg_match('/^\[([^\]]+)\]\.(\S+)\s+(\d+)/', $line, $m)) {
                            $devPath = $m[1];
                            $statType = $m[2];
                            $statValue = (int)$m[3];
                            
                            if ($statValue > 0 && in_array($statType, ['write_io_errs', 'read_io_errs', 'flush_io_errs', 'corruption_errs', 'generation_errs'])) {
                                foreach ($pool['members'] as &$member) {
                                    if ($member['device'] === $devPath) {
                                        $member['status'] = 'DEGRADED';
                                        break;
                                    }
                                }
                                unset($member);
                            }
                        }
                        // Old format: /dev/sda1: read 0, write 0, flush 0
                        elseif (preg_match('/^(\S+):\s+read\s+(\d+),\s+write\s+(\d+),\s+flush\s+(\d+)/', $line, $m)) {
                            foreach ($pool['members'] as &$member) {
                                if ($member['device'] === $m[1]) {
                                    if ((int)$m[2] > 0 || (int)$m[3] > 0 || (int)$m[4] > 0) {
                                        $member['status'] = 'DEGRADED';
                                    }
                                    break;
                                }
                            }
                            unset($member);
                        }
                    }
                }

                // Check for DISK_NP_DSBL members (physically removed devices)
                foreach ($expectedMembers as $diskName => $diskData) {
                    $diskKey = preg_replace('/\d+$/', '', $diskName); // Remove number suffix for matching
                    $deviceKey = $diskKey; // Use disk name as device key
                    
                    // Check if this disk is already in members (was found by btrfs)
                    $foundInMembers = false;
                    foreach ($pool['members'] as $memberKey => $member) {
                        if (strpos($memberKey, $diskKey) !== false || strpos($member['device'], $diskName) !== false) {
                            $foundInMembers = true;
                            break;
                        }
                    }
                    
                    // If not found in members and has DISK_NP_DSBL status, add it as REMOVED
                    if (!$foundInMembers && isset($diskData['status']) && $diskData['status'] === 'DISK_NP_DSBL') {
                        $pool['members'][$diskName] = [
                            'devid' => '?',
                            'device' => $diskName,
                            'size' => 'N/A',
                            'used' => 'N/A',
                            'status' => 'REMOVED',
                        ];
                        $hasMissing = true; // Treat as missing for overall status
                    }
                }

                // Overall status
                $memberStatuses = array_column($pool['members'], 'status');
                if ($hasMissing || $hasFailed || in_array('MISSING', $memberStatuses, true) || in_array('FAILED', $memberStatuses, true) || in_array('DEGRADED', $memberStatuses, true) || in_array('REMOVED', $memberStatuses, true)) {
                    $pool['overall_status'] = 'DEGRADED';
                } elseif (!empty($memberStatuses)) {
                    $pool['overall_status'] = 'HEALTHY';
                }
            }
        }

        // --- ZFS ---
        if ($s['fsType'] === 'zfs') {
            $poolNameEsc = escapeshellarg($poolName);
            $members = [];
            $poolOverall = 'UNKNOWN';
            
            // First check if this is a dataset or an actual pool
            // Use zfs get to check if this filesystem exists
            $zfsList = [];
            exec("zfs list -H -o name $poolNameEsc 2>/dev/null", $zfsList, $zfsRc);
            
            // Determine the actual pool name (before first /)
            $actualPoolName = $poolName;
            if ($zfsRc === 0 && !empty($zfsList)) {
                // This is a valid ZFS filesystem
                $fsName = trim($zfsList[0]);
                if (strpos($fsName, '/') !== false) {
                    // This is a dataset, extract the pool name
                    $actualPoolName = substr($fsName, 0, strpos($fsName, '/'));
                }
            }
            
            $actualPoolNameEsc = escapeshellarg($actualPoolName);
            
            // Try JSON output first (ZFS 2.2+)
            $zpoolJson = [];
            exec("zpool status -j $actualPoolNameEsc 2>/dev/null", $zpoolJson, $rc);
            $jsonParsed = false;
            
            if ($rc === 0 && !empty($zpoolJson)) {
                $jsonData = json_decode(implode('', $zpoolJson), true);
                
                if ($jsonData && isset($jsonData['pools'][0])) {
                    $jsonParsed = true;
                    $zpoolData = $jsonData['pools'][0];
                    $poolOverall = strtoupper($zpoolData['state'] ?? 'UNKNOWN');
                    
                    // Extract members from vdev tree
                    if (isset($zpoolData['vdev_tree']['children'])) {
                        foreach ($zpoolData['vdev_tree']['children'] as $vdev) {
                            $vdevType = $vdev['type'] ?? null;
                            
                            // Determine if this is a special vdev type
                            $isSpecial = in_array($vdevType, ['spare', 'cache', 'log', 'special', 'dedup'], true);
                            
                            // Handle leaf devices (single disk) vs vdevs (mirror/raidz)
                            if (isset($vdev['children'])) {
                                // Has children (mirror, raidz, etc.)
                                foreach ($vdev['children'] as $child) {
                                    if (isset($child['path'])) {
                                        $deviceName = preg_replace('/p?\d+$/', '', basename($child['path']));
                                        // Skip if device name is a pool name or zvol
                                        if (in_array(strtolower($deviceName), $allPoolNames)) {
                                            continue;
                                        }
                                        $members[$deviceName] = [
                                            'device' => $deviceName,
                                            'status' => strtoupper($child['state'] ?? 'UNKNOWN'),
                                            'vdev' => $vdevType,
                                            'type' => $isSpecial ? $vdevType : 'data',
                                        ];
                                    }
                                }
                            } elseif (isset($vdev['path'])) {
                                // Direct disk (no vdev wrapper)
                                $deviceName = preg_replace('/p?\d+$/', '', basename($vdev['path']));
                                // Skip if device name is a pool name or zvol
                                if (!in_array(strtolower($deviceName), $allPoolNames)) {
                                    $members[$deviceName] = [
                                        'device' => $deviceName,
                                        'status' => strtoupper($vdev['state'] ?? 'UNKNOWN'),
                                        'vdev' => null,
                                        'type' => $isSpecial ? $vdevType : 'data',
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            
            // Fallback to text parsing if JSON not available or failed
            if (!$jsonParsed) {
                $zpoolStatus = [];
                exec("zpool status $actualPoolNameEsc", $zpoolStatus, $rc);
                if ($rc === 0) {
                    $inConfig = false;
                    $currentVdev = null;

                    foreach ($zpoolStatus as $line) {
                        $line = rtrim($line);

                        // Capture overall pool state
                        if (preg_match('/^\s*state:\s+(\S+)/i', $line, $m)) {
                            $poolOverall = strtoupper($m[1]);
                            continue;
                        }

                        // Enter config section
                        if (preg_match('/^\s*config:/i', $line)) {
                            $inConfig = true;
                            continue;
                        }

                        // Skip header line in config
                        if ($inConfig && preg_match('/^\s*NAME\s+STATE\s+READ\s+WRITE\s+CKSUM/i', $line)) {
                            continue;
                        }

                        // Exit config when we hit 'errors:'
                        if ($inConfig && preg_match('/^\s*errors:/i', $line)) {
                            break;
                        }

                        if (!$inConfig) continue;

                        // Match any line with device name and status in config section
                        if (preg_match('/^\s+(\S+)\s+(ONLINE|DEGRADED|FAULTED|OFFLINE|REMOVED|UNAVAIL|AVAIL)/i', $line, $m)) {
                            $device = $m[1];
                            $status = strtoupper($m[2]);
                            
                            // Skip pool names (including this pool and others)
                            if (in_array(strtolower($device), $allPoolNames)) {
                                continue;
                            }
                            
                            // Check for special vdev types (cache, log, spare, etc.)
                            if (preg_match('/^(mirror|raidz[123]?|draid)-/i', $device)) {
                                // These are redundancy VDEVs, track but don't add
                                $currentVdev = $device;
                                continue;
                            } elseif (preg_match('/^(spare|cache|log|special|dedup)$/i', $device)) {
                                // This line marks the start of a special device section
                                $currentVdev = strtolower($device);
                                continue;
                            }
                            
                            // This is an actual device - add it
                            $deviceType = 'data';
                            if ($currentVdev && preg_match('/^(spare|cache|log|special|dedup)$/i', $currentVdev)) {
                                $deviceType = $currentVdev;
                            }
                            
                            $deviceKey = preg_replace('/p?\d+$/', '', $device);
                            $members[$deviceKey] = [
                                'device' => $deviceKey,
                                'status' => $status,
                                'vdev' => (preg_match('/^(mirror|raidz|draid)-/i', $currentVdev ?: '') ? $currentVdev : null),
                                'type' => $deviceType,
                            ];
                        }
                    }
                }
            }
            
            // Check for DISK_NP_DSBL members (physically removed devices)
            foreach ($expectedMembers as $diskName => $diskData) {
                $diskKey = preg_replace('/\d+$/', '', $diskName); // Remove number suffix for matching
                $deviceKey = $diskKey; // Use disk name as device key
                
                // Check if this disk is already in members (was found by zpool)
                $foundInMembers = false;
                foreach ($members as $memberKey => $member) {
                    if (strpos($memberKey, $diskKey) !== false || strpos($member['device'], $diskName) !== false) {
                        $foundInMembers = true;
                        break;
                    }
                }
                
                // If not found in members and has DISK_NP_DSBL status, add it as MISSING
                if (!$foundInMembers && isset($diskData['status']) && $diskData['status'] === 'DISK_NP_DSBL') {
                    $members[$diskName] = [
                        'device' => $diskName,
                        'status' => 'MISSING',
                        'vdev' => null,
                        'type' => 'data',
                    ];
                    // Update overall status to DEGRADED if we have missing members
                    if ($poolOverall === 'ONLINE' || $poolOverall === 'UNKNOWN') {
                        $poolOverall = 'DEGRADED';
                    }
                }
            }
            
            // Add metadata if this is a dataset
            if ($actualPoolName !== $poolName) {
                $pool['zfs_type'] = 'dataset';
                $pool['parent_pool'] = $actualPoolName;
            } else {
                $pool['zfs_type'] = 'pool';
            }
            
            $pool['members'] = $members;
            $pool['overall_status'] = $poolOverall;
        }

        $result['pools'][$poolName] = $pool;
    }

    return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

?>
