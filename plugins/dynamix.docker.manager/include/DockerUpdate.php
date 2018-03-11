<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2014-2018, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

$DockerTemplates = new DockerTemplates();

if ($_POST['check']) {
  $DockerTemplates->downloadTemplates();
  $DockerTemplates->getAllInfo(true);
}
foreach ($DockerTemplates->getAllInfo() as $info) {
  if ($info['updated']=='false' && $info['updated']!='undef') {echo 'true'; break;}
}
?>
