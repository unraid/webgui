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
// Keep $_SESSION to store language selection
session_start();

// Register Nchan scripts
function nchan_merge($root, $script) {
  global $nchan_run, $nchan;
  $nchan_run = "$root/nchan";
  $nchan = array_merge($nchan, array_map(function($n){global $nchan_run; return "$nchan_run/$n";}, explode(',',$script)));
}

// Define root path
$docroot = $_SERVER['DOCUMENT_ROOT'];

require_once "$docroot/webGui/include/Helpers.php";

// Get the webGui configuration preferences
extract(parse_plugin_cfg('dynamix',true));

// Read emhttp status
$devs    = (array)@parse_ini_file('state/devs.ini',true);
$disks   = (array)@parse_ini_file('state/disks.ini',true);
$sec     = (array)@parse_ini_file('state/sec.ini',true);
$sec_nfs = (array)@parse_ini_file('state/sec_nfs.ini',true);
$shares  = (array)@parse_ini_file('state/shares.ini',true);
$users   = (array)@parse_ini_file('state/users.ini',true);
$var     = (array)@parse_ini_file('state/var.ini');

// Merge SMART settings
require_once "$docroot/webGui/include/CustomMerge.php";

// Pool devices
$pool_devices = false;
$pools = pools_filter($disks);
foreach ($pools as $pool) $pool_devices |= _var($disks[$pool],'devices')!='';

// Read network settings
extract(parse_ini_file('state/network.ini',true));

// Language translations
$_SESSION['locale'] = _var($display,'locale');
$_SESSION['buildDate'] = date('Ymd',_var($var,'regBuildTime'));
require_once "$docroot/webGui/include/Translations.php";

// Build webGui pages first, then plugins pages
require_once "$docroot/webGui/include/PageBuilder.php";
$site = [];
build_pages('webGui/*.page');
foreach (glob('plugins/*', GLOB_ONLYDIR) as $plugin) {
  if ($plugin != 'plugins/dynamix') build_pages("$plugin/*.page");
}

// Get general variables
$name = rawurldecode(_var($_GET,'name'));
$dir  = rawurldecode(_var($_GET,'dir'));
$path = substr(strtok(_var($_SERVER,'REQUEST_URI'),'?'),1);

// The current "task" is the first element of the path
$task = strtok($path,'/');

// Add translation for favorites page
if ($locale && $task=='Favorites') {
  foreach(['settings','tools'] as $more) {
    $text = "$docroot/languages/$locale/$more.txt";
    if (!file_exists($text)) continue;
    // additional translations
    $store = "$docroot/languages/$locale/$more.dot";
    if (!file_exists($store)) file_put_contents($store,serialize(parse_lang_file($text)));
    $language = array_merge($language,unserialize(file_get_contents($store)));
  }
}

// Here's the page we're rendering
$myPage   = $site[basename($path)];
$pageroot = $docroot.'/'._var($myPage,'root');

// Nchan script start/stop tracking
$nchan_pid = "/var/run/nchan.pid";
$nchan_run = "";

// Giddyup
require_once "$docroot/webGui/include/DefaultPageLayout.php";
?>
