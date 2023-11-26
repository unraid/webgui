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
$ram  = "/var/local/emhttp/monitor.ini";
$rom  = "/boot/config/plugins/dynamix/monitor.ini";
$disk = $_POST['disk'] ?? '';

if ($disk) {
  $text  = "";
  $saved = parse_ini_file($ram,true);
  $saved["smart"]["$disk.ack"] = "true";

  foreach ($saved as $item => $block) {
    if ($block) $text .= "[$item]\n";
    foreach ($block as $key => $value) $text .= "$key=\"$value\"\n";
  }

  file_put_contents($ram, $text);
  file_put_contents($rom, $text);
  echo "200 OK";
} else {
  echo "404 ERROR";
}
?>
