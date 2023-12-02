#!/usr/bin/php
<?PHP
function SysDriverslog($m, $type='NOTICE') {
  if ($type == 'DEBUG') return;
  $m = str_replace(["\n",'"'],[" ","'"],print_r($m,true));
  exec("logger -t sysDrivers -- \"$m\"");
}

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/SysDriversHelpers.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

// add translations
require_once "$docroot/webGui/include/Translations.php";

$kernel       = trim(shell_exec("uname -r"));
$lsmod        = shell_exec("lsmod");
$supportpage  = true;
$modtoplgfile = "/tmp/modulestoplg.json";
$sysdrvfile   = "/tmp/sysdrivers.json";
$arrModtoPlg  = file_exists($modtoplgfile) ? json_decode(file_get_contents($modtoplgfile), true) : '';
file_put_contents("/tmp/sysdrivers.init","1");

SysDriverslog("SysDrivers Build Starting");
modtoplg();
createlist();
SysDriverslog("SysDrivers Build Complete");
?>
