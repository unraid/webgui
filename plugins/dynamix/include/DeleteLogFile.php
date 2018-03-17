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
require_once "$docroot/webGui/include/Wrappers.php";

$dynamix = parse_plugin_cfg('dynamix',true);
$killfiles = [];
if (strpos($_POST['log'],'*')===false) $killfiles = [ realpath("{$dynamix['notify']['path']}/archive/{$_POST['log']}") ]; else $killfiles = glob("{$dynamix['notify']['path']}/archive/{$_POST['log']}",GLOB_NOSORT);
foreach ($killfiles as $killfile) {
	if (strpos($killfile, "{$dynamix['notify']['path']}/archive/") === 0) {
		@unlink($killfile);
	}
}
?>
