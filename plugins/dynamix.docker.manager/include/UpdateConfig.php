<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2014-2020, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2020, Bergware International.
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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

$autostart_file = $dockerManPaths['autostart-file'];
$template_repos = $dockerManPaths['template-repos'];
$user_prefs     = $dockerManPaths['user-prefs'];

switch ($_POST['action']) {
case 'docker_load_start':
  $daemon = "/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/docker_load";
  if (!exec("pgrep -f $daemon")) passthru("$daemon &>/dev/null &");
  break;
case 'docker_load_stop':
  $daemon = "/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/docker_load";
  if (exec("pgrep -f $daemon")) passthru("pkill -f $daemon &>/dev/null &");
  break;
case 'autostart':
  // update container autostart setting
  $container = urldecode(($_POST['container']));
  $wait = $_POST['wait'];
  $item = rtrim("$container $wait");
  $autostart = @file($autostart_file, FILE_IGNORE_NEW_LINES) ?: [];
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
    file_safeput_contents($autostart_file, implode("\n", $autostart)."\n");
  } else @unlink($autostart_file);
  break;
case 'wait':
  // update wait period used after container autostart
  $container = urldecode(($_POST['container']));
  $wait = $_POST['wait'];
  $item = rtrim("$container $wait");
  $autostart = file($autostart_file, FILE_IGNORE_NEW_LINES) ?: [];
  $names = array_map('var_split', $autostart);
  $autostart[array_search($container,$names)] = $item;
  file_safeput_contents($autostart_file, implode("\n", $autostart)."\n");
  break;
case 'templates':
  // update template
  readfile("$docroot/update.htm");
  $repos = $_POST['template_repos'];
  file_safeput_contents($template_repos, $repos);
  $DockerTemplates = new DockerTemplates();
  $DockerTemplates->downloadTemplates();
  break;
case 'exist':
  // docker file or folder exists?
  echo file_exists($_POST['name']) ? 0 : 1;
  break;
}
?>
