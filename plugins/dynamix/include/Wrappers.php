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

// Wrapper functions
function parse_plugin_cfg($plugin, $sections=false) {
  global $docroot;
  $ram = "$docroot/plugins/$plugin/default.cfg";
  $rom = "/boot/config/plugins/$plugin/$plugin.cfg";
  $cfg = file_exists($ram) ? parse_ini_file($ram, $sections) : [];
  return file_exists($rom) ? array_replace_recursive($cfg, parse_ini_file($rom, $sections)) : $cfg;
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
  if (strcmp($remote,$local)>0) {
    if ($os) return $remote;
    if (!$unraid = get_plugin_attr('unRAID', "/tmp/plugins/$plugin.plg")) return $remote;
    $server = get_plugin_attr('version', "/var/log/plugins/unRAIDServer.plg");
    if (version_compare($server, $unraid, '>=')) return $remote;
  }
}
function get_value(&$object, $name, $default) {
  global $var;
  $value = $object[$name] ?? -1;
  return $value!==-1 ? $value : ($var[$name] ?? $default);
}
function get_ctlr_options(&$type, &$disk) {
  if (!$type) return;
  $ports = [];
  if (strlen($disk['smPort1'])) $ports[] = $disk['smPort1'];
  if (strlen($disk['smPort2'])) $ports[] = $disk['smPort2'];
  if (strlen($disk['smPort3'])) $ports[] = $disk['smPort3'];
  $type .= ($ports ?  ','.implode($disk['smGlue'] ?? ',',$ports) : '');
}
function port_name($port) {
  return substr($port,-2)!='n1' ? $port : substr($port,0,-2);
}
function exceed($value, $limit, $top=100) {
  return ($value>$limit && $limit>0 && $value<=$top);
}
?>
