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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: "/usr/local/emhttp";

$lang = $_POST['lang'] ?? '';
if ( ! is_file("/boot/config/plugins/dynamix/dynamix.cfg") )
  copy("$docroot/plugins/dynamix/default.cfg","/boot/config/plugins/dynamix/dynamix.cfg");
exec("sed -ri 's/^(locale=\")[^\"]*/\\1$lang/' /boot/config/plugins/dynamix/dynamix.cfg 2>/dev/null");
?>
