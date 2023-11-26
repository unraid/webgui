<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2014-2021, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

$autostart_file = $dockerManPaths['autostart-file'];
$user_prefs = $dockerManPaths['user-prefs'];

if (isset($_POST['reset'])) {
  @unlink($user_prefs);
  if (file_exists($autostart_file)) {
    $allAutoStart = file($autostart_file, FILE_IGNORE_NEW_LINES);
    natcasesort($allAutoStart);
    file_put_contents($autostart_file, implode(PHP_EOL, $allAutoStart).PHP_EOL);
  }
} else {
  $names = explode(';',$_POST['names']);
  $index = explode(';',$_POST['index']);
  $save  = []; $i = 0;

  foreach ($names as $name) if ($name) $save[] = $index[$i++]."=\"".$name."\""; else $i++;
  file_put_contents($user_prefs, implode("\n",$save)."\n");

  // sort containers for start-up
  if (file_exists($autostart_file)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    $allAutoStart = file($autostart_file, FILE_IGNORE_NEW_LINES);
    foreach ($allAutoStart as $ct) $sort[] = array_search(explode(' ',$ct)[0],$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$allAutoStart);
    file_put_contents($autostart_file, implode(PHP_EOL, $allAutoStart).PHP_EOL);
  }
}
?>
