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
$template_repos = $dockerManPaths['template-repos'];
$user_prefs     = $dockerManPaths['user-prefs'];

switch ($_POST['action']) {
case 'autostart':
  // update container autostart setting
  $container = urldecode(($_POST['container']));
  $wait = $_POST['wait'];
  $item = rtrim("$container $wait");
  $autostart = (array)@file($autostart_file, FILE_IGNORE_NEW_LINES);
  $key = array_search($item, $autostart);
  if ($_POST['auto']=='true') {
    if ($key===false) $autostart[] = $item;
  } else {
    unset($autostart[$key]);
  }
  if ($autostart) {
    if (file_exists($user_prefs)) {
      $prefs = parse_ini_file($user_prefs); $sort = [];
      foreach ($autostart as $ct) $sort[] = array_search(var_split($ct),$prefs) ?? 999;
      array_multisort($sort,$autostart);
    } else {
      natcasesort($autostart);
    }
    file_put_contents($autostart_file, implode("\n", $autostart)."\n");
  } else @unlink($autostart_file);
  break;
case 'wait':
  // update wait period used after container autostart
  $container = urldecode(($_POST['container']));
  $wait = $_POST['wait'];
  $item = rtrim("$container $wait");
  $autostart = (array)@file($autostart_file, FILE_IGNORE_NEW_LINES);
  $names = array_map('var_split', $autostart);
  $autostart[array_search($container,$names)] = $item;
  file_put_contents($autostart_file, implode("\n", $autostart)."\n");
  break;
case 'templates':
  // update template
  readfile("$docroot/update.htm");
  file_put_contents($template_repos, $_POST['template_repos']);
  $DockerTemplates = new DockerTemplates();
  $DockerTemplates->downloadTemplates();
  break;
case 'exist':
  // docker file or folder exists?
  $file = $_POST['name'];
  if (substr($file,0,5)=='/mnt/') echo file_exists($file) ? 0 : 1;
  break;
}
?>
