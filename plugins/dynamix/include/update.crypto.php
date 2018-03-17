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
$index = $_GET['index'];
$tests = explode(',',$_GET['test']);
if ($index < count($tests)) {
  $test = $tests[$index];
  list($name,$size) = explode(':',$test);
  if (!$size) {
    $default = $test==$_GET['hash'];
    if ($index>0) $test .= '|tail -1';
    if ($default) echo "<b>";
    echo preg_replace(['/^(# Tests.*\n)/','/\n$/'],["$1\n",""],shell_exec("/usr/sbin/cryptsetup benchmark -h $test"));
    echo $default ? " (default)</b>\n" : "\n";
  } else {
    $default = $test==$_GET['luks'];
    if ($index>5) $size .= '|tail -1';
    if ($default) echo "<b>";
    echo preg_replace(['/^# Tests.*\n/','/\n$/'],["\n",""],shell_exec("/usr/sbin/cryptsetup benchmark -c $name -s $size"));
    echo $default ? " (default)</b>\n" : "\n";
  }
} else {
  $bm = popen('/usr/sbin/cryptsetup --help','r');
  while (!feof($bm)) {
    $text = fgets($bm);
    if (strpos($text,'Default PBKDF2 iteration time for LUKS')!==false) echo "\n$text";
    elseif (strpos($text,'Default compiled-in device cipher parameters')!==false) echo "\n$text";
    elseif (strpos($text,'LUKS1:')!==false) echo str_replace("\t"," ",$text);
  }
  pclose($bm);
  echo "<div style='text-align:center;margin-top:12px'><input type='button' value='Done' onclick='top.Shadowbox.close()'></div>";
}
?>
