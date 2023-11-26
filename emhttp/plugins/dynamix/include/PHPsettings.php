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

switch ($_POST['cmd']??'') {
case 'clear':
  $log = "/var/log/phplog";
  // delete existing file and recreate an empty file
  if (file_exists($log)) unlink($log);
  touch($log);
  break;
case 'reload':
  $ini = "/etc/php.d/errors-php.ini";
  if (file_exists($ini) && filesize($ini)==0) unlink($ini);
  exec("/etc/rc.d/rc.php-fpm reload 1>/dev/null 2>&1");
  break;
case 'logsize':
  $_SERVER['REQUEST_URI'] = '';
  require_once "$docroot/webGui/include/Translations.php";
  require_once "$docroot/webGui/include/Helpers.php";
  extract(parse_plugin_cfg('dynamix',true));
  echo my_scale(filesize("/var/log/phplog"), $unit)," $unit";
  break;
}
?>
