<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2015-2018, Derek Macias, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2018, Bergware International.
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
$prefs = '/boot/config/plugins/dynamix.vm.manager';
$names = explode(';',$_POST['names']);
$index = explode(';',$_POST['index']);
$save  = []; $i = 0;

foreach ($names as $name) if ($name) $save[] = $index[$i++]."=\"".$name."\""; else $i++;
if (!is_dir($prefs)) mkdir($prefs);
file_put_contents("$prefs/userprefs.cfg", implode("\n",$save)."\n");
?>
