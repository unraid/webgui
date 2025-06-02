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
$var  = parse_ini_file('/var/local/emhttp/var.ini');
$luks = $var['luksKeyfile'];
$text = $_POST['text'] ?? false;
$file = $_POST['file'] ?? false;

if ($file) {
  file_put_contents($luks, base64_decode(explode(';base64,',$file)[1]));
} elseif ($text && file_exists($luks)) {
  unlink($luks);
}
$save = false;
?>
