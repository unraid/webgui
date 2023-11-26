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
$file  = $_POST['file']??'';
$model = $_POST['model']??'';
$root  = "/boot/config/plugins/dynamix";
$name  = "$root/$file";
if (realpath(dirname($name)) == $root) {
  switch ($_POST['mode']??'') {
  case 'set':
    if ($model) file_put_contents($name,$model);
    break;
  case 'get':
    if (is_file($name)) echo file_get_contents($name);
    break;
  case 'file':
    $case = 'case-model.png';
    file_put_contents($name,$case);
    file_put_contents("$root/$case",base64_decode(str_replace('data:image/png;base64,','',$_POST['data']??'')));
    break;
  }
}
?>
