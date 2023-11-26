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
$text    = "";
$keys    = parse_ini_file($_POST['#cfg'], true);
$cleanup = isset($_POST['#cleanup']);

foreach ($_POST as $field => $value) {
  if ($field[0] == '#') continue;
  [$section,$key] = explode('_',$field,2);
  $keys[str_replace('%3E','.',$section)][$key] = $value;
}

foreach ($keys as $section => $block) {
  $pairs = "";
  foreach ($block as $key => $value) if (strlen($value) || !$cleanup) $pairs .= "$key=\"$value\"\n";
  if ($pairs) $text .= "[$section]\n".$pairs;
}

if ($text) file_put_contents($_POST['#cfg'], $text); else @unlink($_POST['#cfg']);
?>
