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
$docroot = $docroot ?: $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Markdown.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

$system  = $_GET['system'] ?? false;
$branch  = $_GET['branch'] ?? false;
$audit   = $_GET['audit'] ?? false;
$empty   = true;
$builtin = ['unRAIDServer'];
$https   = ['stable' => 'https://raw.github.com/limetech/\&name;/master/\&name;.plg',
    'next'   => 'https://s3.amazonaws.com/dnld.lime-technology.com/\&category;/\&name;.plg'];

$plugin   = $_GET['plugin'] ?? false;

if ($plugin) {
  //only consider symlinks
  $plugin_file = @readlink("/var/log/plugins/".$plugin);
  if ($plugin_file !== false) make_row($plugin_file, false);
}
if ($empty) echo ""; // i.e. do nothing
?>