<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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
$user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';

if (isset($_POST['reset'])) {
  @unlink($user_prefs);
} else {
  $names = explode(';',$_POST['names']);
  $index = explode(';',$_POST['index']);
  $save  = []; $i = 0;

  foreach ($names as $name) if ($name) $save[] = $index[$i++]."=\"".$name."\""; else $i++;
  if (!is_dir(dirname($user_prefs))) mkdir(dirname($user_prefs));
  file_put_contents($user_prefs, implode("\n",$save)."\n");
}
?>
