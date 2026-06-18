<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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

// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";

$port = ($_POST['port'] ?? 'eth0') ?: 'eth0';
if (exec("ip -br link show ".escapeshellarg($port)." | grep -om1 'NO-CARRIER'")) {
  echo "<b>",_('Interface')," - ",_('Ethernet Port')," ",preg_replace('/[^0-9]/','',$port)," ",_('is down'),". ",_('Check cable'),"!</b>";
}
?>
