<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
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

// Define root path
$docroot = $_SERVER['DOCUMENT_ROOT'];

require_once "$docroot/webGui/include/Helpers.php";

// Get the webGui configuration preferences
extract(parse_plugin_cfg('dynamix',true));

// Read emhttp status
$var     = (array)parse_ini_file('state/var.ini');
$sec     = (array)parse_ini_file('state/sec.ini',true);
$devs    = (array)parse_ini_file('state/devs.ini',true);
$disks   = (array)parse_ini_file('state/disks.ini',true);
$users   = (array)parse_ini_file('state/users.ini',true);
$shares  = (array)parse_ini_file('state/shares.ini',true);
$sec_nfs = (array)parse_ini_file('state/sec_nfs.ini',true);

// Merge SMART settings
require_once "$docroot/webGui/include/CustomMerge.php";

// Pool devices
$pool_devices = false;
$pools = pools_filter($disks);
foreach ($pools as $pool) $pool_devices |= $disks[$pool]['devices'];

// Read network settings
extract(parse_ini_file('state/network.ini',true));

// Language translations
$_SESSION['locale'] = $display['locale'];
$_SESSION['buildDate'] = date('Ymd',$var['regBuildTime']);
require_once "$docroot/webGui/include/Translations.php";

// Build webGui pages first, then plugins pages
require_once "$docroot/webGui/include/PageBuilder.php";
$site = [];
build_pages('webGui/*.page');
foreach (glob('plugins/*', GLOB_ONLYDIR) as $plugin) {
  if ($plugin != 'plugins/dynamix') build_pages("$plugin/*.page");
}

// Get general variables
$name = $_GET['name'];
$dir = $_GET['dir'];
$path = substr(strtok($_SERVER['REQUEST_URI'],'?'),1);

// The current "task" is the first element of the path
$task = strtok($path,'/');

// Here's the page we're rendering
$myPage = $site[basename($path)];
$pageroot = $docroot.'/'.dirname($myPage['file']);

// Giddyup
require_once "$docroot/webGui/include/DefaultPageLayout.php";
?>
