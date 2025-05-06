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
  return exec('pgrep --ns $$ -f '."$proc");
}

if (isset($_POST['kill']) && $_POST['kill'] > 1) {
  exec("kill ".$_POST['kill']);
  foreach (glob("/tmp/plugins/pluginPending/*") as $file) unlink($file);
  die();
}

$start = $_POST['start'] ?? 0;
[$command,$args] = array_pad(explode(' ',unscript($_POST['cmd']??''),2),2,'');

// find absolute path of command
foreach (glob("$docroot/plugins/*/scripts",GLOB_NOSORT) as $path) {
  if ($name = realpath("$path/$command")) break;
}

$pid = 0; // preset to not started
if ($command && strncmp($name,$path,strlen($path))===0) {
  if (isset($_POST['pid'])) {
    // return running pid
    $pid = pgrep($name);
  } elseif ($start==2) {
    // execute command and return result - post request
    $run = popen("$name $args",'r');
    while (!feof($run)) echo fgets($run);
    pclose($run);
    $pid = '';
  } elseif ($start==1 or !pgrep($name)) {
    // start command in background and return pid - nchan channel
    $pid = exec("nohup bash -c 'sleep .3 && $name $args' 1>/dev/null 2>&1 & echo \$!");
  }
}
echo $pid;
?>
