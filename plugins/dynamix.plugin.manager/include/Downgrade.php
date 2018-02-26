<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2012-2018, Bergware International.
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
exec("cp -rf /boot/previous/* /boot >/dev/null");
exec("rm -rf /boot/previous >/dev/null");
file_put_contents("$docroot/plugins/unRAIDServer/README.md","**DOWNGRADE TO VERSION {$_GET['version']}**");
?>
