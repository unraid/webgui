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

$scripts = ['update_2','update_3'];
$pidfile = '/var/run/nchan.pid';
$nchan   = 'webGui/nchan';

if (!is_file($pidfile)) exit;

foreach ($scripts as $script) {
  if (exec("grep -Pom1 '^$nchan/$script' $pidfile")) {
    // restart selected script
    exec("pkill -f $nchan/$script");
    exec("$docroot/$nchan/$script &>/dev/null &");
  }
}
?>
