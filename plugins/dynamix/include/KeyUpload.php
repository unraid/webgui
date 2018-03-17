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
$var = parse_ini_file('/var/local/emhttp/var.ini');
$ini = '/var/local/emhttp/keyfile.ini';
$luks = $var['luksKeyfile'];
$text = $_POST['text'] ?? false;
$file = $_POST['file'] ?? false;

if ($text) {
  file_put_contents($luks, $text);
} elseif ($file) {
  file_put_contents($luks, base64_decode(preg_replace('/^data:.*;base64,/','',$file)));
} else {
  @unlink($luks);
}
$save = false;
?>
