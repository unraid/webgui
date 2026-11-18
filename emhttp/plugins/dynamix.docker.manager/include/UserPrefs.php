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
  DockerUtil::resetUserOrder();
  if (file_exists($autostart_file)) {
    $allAutoStart = file($autostart_file, FILE_IGNORE_NEW_LINES);
    natcasesort($allAutoStart);
    file_put_contents($autostart_file, implode(PHP_EOL, $allAutoStart).PHP_EOL);
  }
} else {
  // the names arrive in the order of the rows on the Docker tab
  DockerUtil::saveUserOrder(explode(';',$_POST['names']));

  // sort containers for start-up
  if (file_exists($autostart_file)) {
    $allAutoStart = DockerUtil::sortAutostart(file($autostart_file, FILE_IGNORE_NEW_LINES));
    file_put_contents($autostart_file, implode(PHP_EOL, $allAutoStart).PHP_EOL);
  }
}
?>
