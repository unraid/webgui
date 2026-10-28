<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
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
require_once "$docroot/webGui/include/Secure.php";

function pgrep($proc) {
  return exec('pgrep --ns $$ -f '.escapeshellarg($proc));
}

if (isset($_POST['kill'])) {
  $kill = (string)$_POST['kill'];
  if (ctype_digit($kill) && (int)$kill > 1) {
    exec('kill '.(int)$kill);
    foreach (glob("/tmp/plugins/pluginPending/*") as $file) unlink($file);
  }
  die();
}

$start = $_POST['start'] ?? 0;
[$command,$args] = array_pad(explode(' ',unscript($_POST['cmd']??''),2),2,'');

// find absolute path of command
$name = '';
$path = '';
foreach (glob("$docroot/plugins/*/scripts",GLOB_NOSORT) as $path) {
  if ($name = realpath("$path/$command")) break;
}

$pid = 0; // preset to not started
if ($command && $name && strncmp($name,$path,strlen($path))===0) {
  $commandLine = shell_command_line($name, $args);
  if ($commandLine === null) die((string)$pid);
  if (isset($_POST['pid'])) {
    // return running pid
    $pid = pgrep($name);
  } elseif ($start==2) {
    // execute command and return result - post request
    $run = popen($commandLine,'r');
    while (!feof($run)) echo fgets($run);
    pclose($run);
    $pid = '';
  } elseif ($start==1 or !pgrep($name)) {
    // start command in background and return pid - nchan channel
    $payload = 'sleep .3 && '.$commandLine;
    $pid = exec('nohup bash -c '.escapeshellarg($payload).' 1>/dev/null 2>&1 & echo $!');
  }
}
echo $pid;
?>
