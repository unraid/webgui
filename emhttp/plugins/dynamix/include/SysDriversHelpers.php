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


function getplugin($in) {
  $plugins = "/var/log/plugins/";
  $plugin_link = $plugins.$in;
  $plugin_file = @readlink($plugin_link);
  return plugin('support',$plugin_file) ?: '';
}

function getmodules($name) {
  global $arrModules,$lsmod,$kernel,$arrModtoPlg,$modplugins;
  // preset variables
  $modname = $depends = $filename = $desc = $file = $version = $state = $dir = $support = $supporturl = $pluginfile = null;
  $modprobe = $parms = [];
  exec("modinfo $name",$output,$error);
  foreach($output as $outline) {
    if (!$outline) continue;
    [$key,$data] = array_pad(explode(':',$outline,2),2,'');
    $data = trim($data);
    switch ($key) {
    case "name":
      $modname = $data;
      break;
    case "depends":
      $depends = $data;
      break;
    case "filename":
      $filename = $data;
      break;
    case "description":
      $desc = $data;
      break;
    case "parm":
      $parms[] = $data;
      break;
    case "file":
      $file = $data;
      break;
    case "version":
      $version = $data;
      break;
    case "alias":
    case "author":
    case "firmware":
    case "intree":
    case "vermagic":
    case "retpoline":
    case "import_ns":
    case "license":
      // ignore
      break;
    default:
      $parms[] = trim($outline);
      break;
    }
  }
  if ($modname) {
    $state = strpos($lsmod,$modname)!==false ? "Inuse" : "Available";
    if (isset($arrModtoPlg[$modname])) {
      $support = true;
      $supporturl = plugin("support", $modplugins[$arrModtoPlg[$modname]]);
      $pluginfile = "Plugin name: {$arrModtoPlg[$modname]}" ;
    }
  }
  if (is_file("/boot/config/modprobe.d/$modname.conf")) {
    $modprobe = file_get_contents("/boot/config/modprobe.d/$modname.conf");
    $state = strpos($modprobe,"blacklist")!==false ? "Disabled" : "Custom";
    $modprobe = explode(PHP_EOL,$modprobe);
  } elseif (is_file("/etc/modprobe.d/$modname.conf")) {
    $modprobe = file_get_contents("/etc/modprobe.d/$modname.conf");
    $state = strpos($modprobe, "blacklist")!==false ? "Disabled" : "System";
    $modprobe = explode(PHP_EOL,$modprobe);
    $module['state'] = $state;
    $module['modprobe'] = $modprobe;
  }
  if ($filename != "(builtin)") {
    if ($filename) {
      $type = pathinfo($filename);
      $dir = str_replace("/lib/modules/$kernel/kernel/drivers/", "", $type['dirname']);
      $dir = str_replace("/lib/modules/$kernel/kernel/", "", $dir);
    }
  } else {
    $dir = str_replace("drivers/", "", $file);
    $state = ($state=="Inuse") ? "Kernel - Inuse" : "Kernel";
  }
  $arrModules[$modname] = [
    'modname'     => $modname,
    'dependacy'   => $depends,
    'version'     => $version,
    'parms'       => $parms,
    'file'        => $file,
    'modprobe'    => $modprobe,
    'plugin'      => $pluginfile,
    'state'       => $state,
    'type'        => $dir,
    'support'     => $support,
    'supporturl'  => $supporturl,
    'description' => $desc
  ];
}

function modtoplg() {
  global $modtoplgfile,$kernel;
  $files = $list = [];
  $kernelsplit = explode('-',$kernel);
  $kernelvers = trim($kernelsplit[0],"\n");
  $files = glob('/boot/config/plugins/*/packages/'.$kernelvers.'/*.{txz,tgz}', GLOB_BRACE);
  foreach ($files as $f) {
    $plugin = str_replace("/boot/config/plugins/", "", $f);
    $plugin = substr($plugin,0,strpos($plugin,'/') );
    $tar = [];
    exec("tar -tf $f | grep -E '.ko.xz|.ko' ",$tar);
    foreach ($tar as $t) {
      $p = pathinfo($t);
      $filename = str_replace(".ko","",$p["filename"]);
      $list[$filename] = $plugin;
    }
  }
  file_put_contents($modtoplgfile,json_encode($list,JSON_PRETTY_PRINT));
}

function createlist() {
  global $modtoplgfile, $sysdrvfile, $lsmod, $kernel,$arrModules, $modplugins,$arrModtoPlg;
  $arrModtoPlg = json_decode(file_get_contents($modtoplgfile) ,TRUE);
  $builtinmodules = file_get_contents("/lib/modules/$kernel/modules.builtin");
  $builtinmodules = explode(PHP_EOL,$builtinmodules);
  $procmodules =file_get_contents("/lib/modules/$kernel/modules.order");
  $procmodules = explode(PHP_EOL,$procmodules);
  $arrModules = [];
  $list = scandir('/var/log/plugins/');
  foreach($list as $f) $modplugins[plugin("name" , @readlink("/var/log/plugins/$f"))] = @readlink("/var/log/plugins/$f");
  foreach($builtinmodules as $bultin) {
    if (!$bultin) continue;
    getmodules(pathinfo($bultin)["filename"]);
  }
  foreach($procmodules as $line) {
    if (!$line) continue;
    getmodules(pathinfo($line)["filename"]);
  }
  $lsmod2 = explode(PHP_EOL,$lsmod);
  foreach($lsmod2 as $line) {
    if (!$line) continue;
    $line2 = explode(" ",$line);
    getmodules($line2['0']);
  }
  unset($arrModules['null']);
  file_put_contents($sysdrvfile,json_encode($arrModules,JSON_PRETTY_PRINT));
}
?>
