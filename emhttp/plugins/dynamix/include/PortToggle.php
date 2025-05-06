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
$port = $_POST['port'];
$cmd = strtolower($_POST['cmd']);
exec("ip link set ".escapeshellarg($port)." ".escapeshellarg($cmd));
$wait = 5;
while ($wait > 0) {
  if (exec("ip -br link show ".escapeshellarg($port)." | awk '{print \$2;exit}'")==strtoupper($cmd)) break;
  sleep(1);
  $wait--;
}
?>
