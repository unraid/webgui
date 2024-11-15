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

// Wrapper functions
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
function _var(&$name, $key, $default='') {
  return $name[$key] ?? $default;
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
  return ($value>$limit && $limit>0 && $value<=$top);
}
function ipaddr($ethX='eth0', $prot=4) {
  global $$ethX;
  switch (_var($$ethX,'PROTOCOL:0')) {
  case 'ipv4':
    return _var($$ethX,'IPADDR:0');
  case 'ipv6':
    return _var($$ethX,'IPADDR6:0');
  case 'ipv4+ipv6':
    switch ($prot) {
    case 4: return _var($$ethX,'IPADDR:0');
    case 6: return _var($$ethX,'IPADDR6:0');
    default:return [_var($$ethX,'IPADDR:0'),_var($$ethX,'IPADDR6:0')];}
  default:
    return _var($$ethX,'IPADDR:0');
  }
}
// convert strftime to date format
function my_date($fmt, $time) {
  $legacy = ['%c' => 'D j M Y h:i:s A T','%A' => 'l','%Y' => 'Y','%B' => 'F','%e' => 'j','%d' => 'd','%m' => 'm','%I' => 'h','%H' => 'H','%M' => 'i','%S' => 's','%p' => 'a','%R' => 'H:i', '%F' => 'Y-m-d', '%T' => 'H:i:s'];
  return date(strtr($fmt,$legacy), $time);
}
// ensure params passed to logger are properly escaped
function my_logger($message, $logger='webgui') {
  exec('logger -t '.escapeshellarg($logger).' -- '.escapeshellarg($message));
}
?>
