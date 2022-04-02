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

$ncsi = exec("wget --spider --no-check-certificate -nv -T10 -t1 https://www.msftncsi.com/ncsi.txt 2>&1|grep -o 'OK'")=='OK';
$DockerTemplates = new DockerTemplates();
if ($ncsi) $DockerTemplates->downloadTemplates();
$DockerTemplates->getAllInfo($ncsi,$ncsi);
?>
