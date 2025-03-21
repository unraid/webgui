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

// pool name ending in any of these => zfs subpool
$subpools = ['special','logs','dedup','cache','spares'];

// ZFS subpool name separator and replacement
$_tilde_ = '~';
$_proxy_ = '__';
$_arrow_ = '&#187;';

// Wrapper functions
function file_put_contents_atomic($filename,$data) {
  while (true) {
    $suffix = rand();
    if ( ! is_file("$filename$suffix") )
      break;
  }
  $renResult = false;
  $writeResult = @file_put_contents("$filename$suffix",$data) === strlen($data);
  if ( $writeResult )
    $renResult = @rename("$filename$suffix",$filename);
  if ( ! $writeResult || ! $renResult ) {
    my_logger("File_put_contents_atomic failed to write / rename $filename");
    @unlink("$filename$suffix");
    return false;
  }
  return strlen($data);
}

// custom parse_ini_file/string functions to deal with '#' comment lines and remove html/php tags
function my_parse_ini_string($text, $sections=false, $scanner=INI_SCANNER_NORMAL) {
  return parse_ini_string(strip_tags(html_entity_decode(preg_replace('/^#.*$/m','',$text))),$sections,$scanner);
}

function my_parse_ini_file($file, $sections=false, $scanner=INI_SCANNER_NORMAL) {
  return my_parse_ini_string(file_get_contents($file),$sections,$scanner);
}

function parse_plugin_cfg($plugin, $sections=false, $scanner=INI_SCANNER_NORMAL) {
  global $docroot;
  $ram = "$docroot/plugins/$plugin/default.cfg";
  $rom = "/boot/config/plugins/$plugin/$plugin.cfg";
  $cfg = file_exists($ram) ? my_parse_ini_file($ram, $sections, $scanner) : [];
  return file_exists($rom) ? array_replace_recursive($cfg, my_parse_ini_file($rom, $sections, $scanner)) : $cfg;
}

function parse_cron_cfg($plugin, $job, $text = "") {
  $cron = "/boot/config/plugins/$plugin/$job.cron";
  if ($text) file_put_contents($cron, $text); else @unlink($cron);
  exec("/usr/local/sbin/update_cron");
}

function agent_fullname($agent, $state) {
  switch ($state) {
    case 'enabled' : return "/boot/config/plugins/dynamix/notifications/agents/$agent";
    case 'disabled': return "/boot/config/plugins/dynamix/notifications/agents-disabled/$agent";
    default        : return $agent;
  }
}

function get_plugin_attr($attr, $file) {
  global $docroot;
  exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin ".escapeshellarg($attr)." ".escapeshellarg($file), $result, $error);
  if ($error===0) return $result[0];
}

function plugin_update_available($plugin, $os=false) {
  $local  = get_plugin_attr('version', "/var/log/plugins/$plugin.plg");
  $remote = get_plugin_attr('version', "/tmp/plugins/$plugin.plg");
  if ($remote && strcmp($remote,$local)>0) {
    if ($os) return $remote;
    if (!$unraid = get_plugin_attr('Unraid', "/tmp/plugins/$plugin.plg")) return $remote;
    $server = get_plugin_attr('version', "/var/log/plugins/unRAIDServer.plg");
    if (version_compare($server, $unraid, '>=')) return $remote;
  }
}

function _var(&$name, $key=null, $default='') {
  return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default);
}

function celsius($temp) {
  return round(($temp-32)*5/9);
}

function fahrenheit($temp) {
  return round(9/5*$temp)+32;
}

function displayTemp($temp) {
  global $display;
  return (is_numeric($temp) && _var($display,'unit')=='F') ? fahrenheit($temp) : $temp;
}

function get_value(&$name, $key, $default) {
  global $var;
  $value = $name[$key] ?? -1;
  return $value!==-1 ? $value : ($var[$key] ?? $default);
}

function get_ctlr_options(&$type, &$disk) {
  if (!$type) return;
  $ports = [];
  if (isset($disk['smPort1'])) $ports[] = $disk['smPort1'];
  if (isset($disk['smPort2'])) $ports[] = $disk['smPort2'];
  if (isset($disk['smPort3'])) $ports[] = $disk['smPort3'];
  $type .= ($ports ?  ','.implode($disk['smGlue'] ?? ',',$ports) : '');
}

function port_name($port) {
  return substr($port,-2)!='n1' ? $port : substr($port,0,-2);
}

function exceed($value, $limit, $top=100) {
  return is_numeric($value) && $limit>0 ? ($value>$limit && $value<=$top) : false;
}

function ipaddr($ethX='eth0', $prot=4) {
  $wlan = $ethX=='eth0' && lan_port('wlan0') && lan_port('wlan0',true);
  switch ($prot) {
  case 4:
    $ipv4 = exec("ip -4 -br addr show $ethX scope global | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    if ($wlan) $ipv4 = $ipv4 ?: exec("ip -4 -br addr show wlan0 scope global | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    return $ipv4;
  case 6:
    $ipv6 = exec("ip -6 -br addr show $ethX scope global -temporary -deprecated | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    if ($wlan) $ipv6 = $ipv6 ?: exec("ip -6 -br addr show wlan0 scope global -temporary -deprecated | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    return $ipv6;
  default:
    $ipv4 = exec("ip -4 -br addr show $ethX scope global | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    $ipv6 = exec("ip -6 -br addr show $ethX scope global -temporary -deprecated | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    if ($wlan) {
      $ipv4 = $ipv4 ?: exec("ip -4 -br addr show wlan0 scope global | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
      $ipv6 = $ipv6 ?: exec("ip -6 -br addr show wlan0 scope global -temporary -deprecated | awk '{print \$3;exit}' | sed -r 's/\/[0-9]+//'");
    }
    return [$ipv4,$ipv6];
  }
}

function no_tilde($name) {
  global $_tilde_ ,$_proxy_;
  return str_replace($_tilde_,$_proxy_,$name);
}

function prefix($key) {
  return preg_replace('/\d+$/','',$key);
}

function pool_name($key) {
  return preg_replace('/(\d+$|~.*$)/', '', $key);
}

function native($name, $full=0) {
  global $_tilde_, $_arrow_;
  switch ($full) {
    case 0: return str_replace($_tilde_," $_arrow_ ",$name);
    case 1: return strpos($name,$_tilde_)!==false ? "$_arrow_ ".explode($_tilde_,$name)[1] : $name;
  }
}

function isSubpool($name) {
  global $subpools, $_tilde_;
  $subpool = my_explode($_tilde_,$name)[1];
  return in_array($subpool,$subpools) ? $subpool : false;
}

function get_nvme_info($device, $info) {
  switch ($info) {
  case 'temp':
    exec("nvme id-ctrl /dev/$device 2>/dev/null | grep -Pom2 '^[wc]ctemp +: \K\d+'",$temp);
    return count($temp) >= 2 ? [$temp[0]-273, $temp[1]-273] : [0, 0];
  case 'cctemp':
    return exec("nvme id-ctrl /dev/$device 2>/dev/null | grep -Pom1 '^cctemp +: \K\d+'")-273;
  case 'wctemp':
    return exec("nvme id-ctrl /dev/$device 2>/dev/null | grep -Pom1 '^wctemp +: \K\d+'")-273;
  case 'state':
    $state = exec("nvme get-feature /dev/$device -f2 2>/dev/null | grep -Pom1 'value:.+\K.$'");
    return exec("nvme id-ctrl /dev/$device 2>/dev/null | grep -Pom1 '^ps +$state : mp:\K\S+ \S+'");
  case 'power':
    $state = exec("nvme get-feature /dev/$device -f2 2>/dev/null | grep -Pom1 'value:.+\K.$'");
    return exec("smartctl -c /dev/$device 2>/dev/null | grep -Pom1 '^ *$state [+-] +\K[^W]+'");
  }
}

// convert strftime to date format
function my_date($fmt, $time) {
  $legacy = ['%c' => 'D j M Y h:i A','%A' => 'l','%Y' => 'Y','%B' => 'F','%e' => 'j','%d' => 'd','%m' => 'm','%I' => 'h','%H' => 'H','%M' => 'i','%S' => 's','%p' => 'a','%R' => 'H:i', '%F' => 'Y-m-d', '%T' => 'H:i:s'];
  return date(strtr($fmt,$legacy), $time);
}

// ensure params passed to logger are properly escaped
function my_logger($message, $logger='webgui') {
  exec('logger -t '.escapeshellarg($logger).' -- '.escapeshellarg($message));
}

// Original PHP code by Chirp Internet: www.chirpinternet.eu
// Please acknowledge use of this code by including this header.
// https://www.the-art-of-web.com/php/http-get-contents/
// Modified for Unraid
/**
 * Fetches URL and returns content
 * @param string $url The URL to fetch
 * @param array $opts Array of options to pass to curl_setopt()
 * @param ?array $getinfo Empty array passed by reference, will contain results of curl_getinfo and curl_error, or null if not needed
 * @return string|false $out The fetched content
 */
function http_get_contents(string $url, array $opts = [], ?array &$getinfo = NULL) {
  $ch = curl_init();
  if(isset($getinfo)) {
    curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
  }
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
  curl_setopt($ch, CURLOPT_TIMEOUT, 45);
  curl_setopt($ch, CURLOPT_ENCODING, "");
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_REFERER, "");
  curl_setopt($ch, CURLOPT_FAILONERROR, true);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Unraid');
  if(is_array($opts) && $opts) {
    foreach($opts as $key => $val) {
      curl_setopt($ch, $key, $val);
    }
  }
  $out = curl_exec($ch);
  if (curl_errno($ch) == 23) {
    // error 23 detected, try CURLOPT_ENCODING = "deflate"
    curl_setopt($ch, CURLOPT_ENCODING, "deflate");
    $out = curl_exec($ch);
  }
  if (isset($getinfo)) {
    $getinfo = curl_getinfo($ch);
  }
  if ($errno = curl_errno($ch)) {
    $msg = "Curl error $errno: " . (curl_error($ch) ?: curl_strerror($errno)) . ". Requested url: '$url'";
    if(isset($getinfo)) {
      $getinfo['error'] = $msg;
    }
    my_logger($msg, "http_get_contents");
  }
  curl_close($ch);
  return $out;
}

/**
 * Detect network connectivity via Network Connectivity Status Indicator
 * @return bool
 */
function check_network_connectivity(): bool {
  $url = 'http://www.msftncsi.com/ncsi.txt';
  $out = http_get_contents($url);
  return ($out=="Microsoft NCSI");
}

function lan_port($port, $state=false) {
  $system = '/sys/class/net';
  $exist = file_exists("$system/$port");
  return !$state ? $exist : ($exist ? (@file_get_contents("$system/$port/carrier") ?: 0) : false);
}
?>
