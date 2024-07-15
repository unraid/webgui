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
require_once "$docroot/webGui/include/SysDriversHelpers.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'tools';
require_once "$docroot/webGui/include/Translations.php";

$kernel = shell_exec("uname -r");
$kernel = trim($kernel,"\n");
$lsmod = shell_exec("lsmod");
$supportpage = true;
$modtoplgfile = "/tmp/modulestoplg.json";
$sysdrvfile = "/tmp/sysdrivers.json";
$sysdrvinit = "/tmp/sysdrivers.init";
if (!is_file($modtoplgfile) || !is_file($sysdrvfile)) { modtoplg(); createlist();}
$arrModtoPlg = json_decode(file_get_contents("/tmp/modulestoplg.json") ,TRUE);

switch ($_POST['table']) {
case 't1create':
  if (is_file("/tmp/sysdrvbuild.running")) break;
  touch("/tmp/sysdrvbuild.running");
  modtoplg();
  createlist();
  unlink("/tmp/sysdrvbuild.running");
  break;

case 't1load':
  $list = file_get_contents($sysdrvfile);
  $arrModules = json_decode($list,TRUE);
  $init = false;
  if (is_file($sysdrvinit)) $init = file_get_contents($sysdrvinit);
  $html =  "<thead><tr><th>"._('Driver')."</th><th>"._('Description')."</th><th data-value='System|Inuse|Custom|Disabled|\"Kernel - Inuse\"'>"._('State')."</th><th>"._('Type')."</th><th>"._('Modprobe.d config file')."</th></tr></thead>";
  $html .= "<tbody>";
  ksort($arrModules);
  foreach($arrModules as $modname => $module) {
    if ($modname == "") continue;
    if (is_file("/boot/config/modprobe.d/$modname.conf")) {
      $modprobe = file_get_contents("/boot/config/modprobe.d/$modname.conf");
      $state = strpos($modprobe, "blacklist");
      $modprobe = explode(PHP_EOL,$modprobe);
      if($state !== false) {$state = "Disabled";} else $state="Custom";
      $module['state'] = $state;
      $module['modprobe'] = $modprobe;
    } else {
      if (is_file("/etc/modprobe.d/$modname.conf")) {
        $modprobe = file_get_contents("/etc/modprobe.d/$modname.conf");
        $state = strpos($modprobe, "blacklist");
        $modprobe = explode(PHP_EOL,$modprobe);
        if($state !== false) {$state = "Disabled";} else $state="System";
        $module['state'] = $state;
        $module['modprobe'] = $modprobe;
      }
    }
    $html .=  "<tr id='row$modname'>";
    if ($supportpage) {
      if ($module['support'] == false) {
        $supporthtml = "";
      } else {
        $supporturl = $module['supporturl'];
        $pluginname = $module['plugin'];
        $supporthtml = "<span id='link$modname'><a href='$supporturl' target='_blank'><i title='"._("Support page")." $pluginname' class='fa fa-phone-square'></i></a></span>";
      }
    }
    if (!empty($module["version"])) $version = " (".$module["version"].")"; else $version = "";
    $html .= "<td>$modname$version$supporthtml</td>";
    $html .= "<td>{$module['description']}</td><td id=\"status$modname\">{$module['state']}</td><td>{$module['type']}</td>";
    $text = "";
    if (is_array($module["modprobe"]) && count($module["modprobe"])) {
      $text = implode("\n",$module["modprobe"]);
      $hidden = ($module['state'] == "System") ? "hidden" : "";
      $html .= "<td><span class='pin'>";
      $html .= "<a class='info' href=\"#\"><i title='"._("Edit Modprobe config")."' onclick=\"textedit('".$modname."');return false;\" id=\"icon'.$modname.'\" class='fa fa-edit pin'></i></a>";
      $html .= "<a class='info' href=\"#\" id=\"bin$modname\" $hidden><i title='"._("Delete Modprobe config")."' onclick=\"removecfg('".$modname."',true);return false;\" class='fa fa-trash pin'></i></a>";
      $html .= "<span id=\"save$modname\" hidden onclick=\"textsave('".$modname."');return false;\"><a class='info' href=\"#\"><i title='"._("Save Modprobe config")."' class='fa fa-save pin'></i></a></span></span>";
      $html .= "<textarea id=\"text".$modname."\" rows=2 disabled>$text</textarea></td></tr>";
    } else {
      $html .= "<td><span class='pin'>";
      $html .= "<a class='info' href=\"#\"><i title='"._("Edit Modprobe config")."' onclick=\"textedit('".$modname."');return false;\" id=\"icon'.$modname.'\" class='fa fa-edit pin'></i></a>";
      $html .= "<a class='info' href=\"#\" id=\"bin$modname\" hidden><i title='"._("Delete Modprobe config")."' onclick=\"removecfg('".$modname."',true);return false;\" class='fa fa-trash pin'></i></a>";
      $html .= "<span id=\"save$modname\" hidden onclick=\"textsave('".$modname."');return false;\"><a class='info' href=\"#\"><i title='"._("Save Modprobe config")."' class='fa fa-save pin'></i></a></span></span>";
      $html .= "<textarea id=\"text".$modname."\" rows=2 hidden disabled >$text</textarea></td></tr>";
    }
  }
  $html .=  "</tbody>";
  $rtn = array();
  $rtn['html'] = $html;
  if ($init !== false) {$init = true; unlink($sysdrvinit);}
  $rtn['init'] = $init;
  echo json_encode($rtn);
  break;

case "update":
  $conf = $_POST['conf'];
  $module = $_POST['module'];
  if ($conf == "") $error = unlink("/boot/config/modprobe.d/$module.conf"); else $error = file_put_contents("/boot/config/modprobe.d/$module.conf",$conf);
  getmodules($module);
  $return = $arrModules[$module];
  $return['supportpage'] = $supportpage;
  if (is_array($return["modprobe"]))$return["modprobe"] = implode("\n",$return["modprobe"]);
  if ($error !== false) $return["error"] = false; else $return["error"] = true;
  echo json_encode($return);
  break;
}
?>
