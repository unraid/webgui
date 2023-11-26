#!/usr/bin/php
<?PHP
function SysDriverslog($m, $type = "NOTICE") {
  if ($type == "DEBUG") return NULL;
  $m = print_r($m,true);
  $m = str_replace("\n", " ", $m);
  $m = str_replace('"', "'", $m);
  exec("logger -t webGUI -- \"$m\"");
}

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/SysDriversHelpers.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

// add translations
require_once "$docroot/webGui/include/Translations.php";

$kernel = shell_exec("uname -r");
$kernel = trim($kernel,"\n");
$lsmod = shell_exec("lsmod");
$supportpage = true;
$modtoplgfile = "/tmp/modulestoplg.json";
$sysdrvfile = "/tmp/sysdrivers.json";
$arrModtoPlg = json_decode(file_get_contents("/tmp/modulestoplg.json") ,TRUE);
file_put_contents("/tmp/sysdrivers.init","1");

SysDriverslog("SysDrivers Build Starting");
modtoplg();
createlist();
SysDriverslog("SysDrivers Build Complete");
?>
