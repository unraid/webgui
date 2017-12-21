#!/usr/bin/php
<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2014-2017, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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

exec("pgrep docker", $pid);
if (count($pid) == 1) exit(0);

require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

$DockerClient = new DockerClient();
$DockerTemplates = new DockerTemplates();

foreach ($argv as $arg) {
  switch ($arg) {
  case '-v'   : $DockerTemplates->verbose = true; break;
  case 'check': $check = true; break;}
}

if (!isset($check)) {
  echo " Updating templates... ";
  $DockerTemplates->downloadTemplates();
  echo " Updating info... ";
  $DockerTemplates->getAllInfo(true);
  echo " Done.";
} else {
  require_once "$docroot/webGui/include/Wrappers.php";
  $notify = "$docroot/webGui/scripts/notify";
  $unraid = parse_plugin_cfg("dynamix",true);
  $var = parse_ini_file("/var/local/emhttp/var.ini");
  $server = strtoupper($var['NAME']);
  $output = $unraid['notify']['docker_notify'];

  $info = $DockerTemplates->getAllInfo(true);
  foreach ($DockerClient->getDockerContainers() as $ct) {
    $name = $ct['Name'];
    $image = $ct['Image'];
    if ($info[$name]['updated'] == "false") {
      $updateStatus = (is_file($dockerManPaths['update-status'])) ? json_decode(file_get_contents($dockerManPaths['update-status']), TRUE) : [];
      $new = str_replace('sha256:', '', $updateStatus[$image]['remote']);
      $new = substr($new, 0, 4).'..'.substr($new, -4, 4);

      exec("$notify -e ".escapeshellarg("Docker - $name [$new]")." -s ".escapeshellarg("Notice [$server] - Docker update $new")." -d ".escapeshellarg("A new version of $name is available")." -i ".escapeshellarg("normal $output")." -x");
    }
  }
}
exit(0);
?>
