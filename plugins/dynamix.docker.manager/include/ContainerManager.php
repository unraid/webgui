<?PHP
/* Copyright 2005-2022, Lime Technology
 * Copyright 2014-2022, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2022, Bergware International.
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

$user_prefs = $dockerManPaths['user-prefs'];
$action     = $_POST['action'];
$status     = $action=='start' ? 'exited' : ($action=='unpause' ? 'paused' : 'running');
$containers = DockerUtil::docker("ps -a --filter status='$status' --format='{{.Names}}'",true);

if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($containers as $ct) $sort[] = array_search($ct,$prefs) ?? 999;
  array_multisort($sort, ($action=='start'?SORT_ASC:SORT_DESC), SORT_NUMERIC, $containers);
}

foreach ($containers as $ct) {
  DockerUtil::docker("$action $ct >/dev/null");
  addRoute($ct);
}
?>
