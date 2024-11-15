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
require_once "$docroot/webGui/include/Wrappers.php";

// add translations
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

// Get the webGui configuration preferences
extract(parse_plugin_cfg('dynamix',true));

$rows = 90;
$wait = "read -N 1 -p '\n\e[92m** "._('Press ANY KEY to close this window')." ** \e[0m'";
$run  = "$docroot/webGui/scripts/run_cmd";

// set tty window font size
if (!empty($display['tty'])) exec("sed -ri 's/fontSize=[0-9]+/fontSize={$display['tty']}/' /etc/default/ttyd");

function wait($name,$cmd) {
  global $run,$wait;
  $exec = "/var/tmp/$name.run.sh";
  file_put_contents($exec,"#!/bin/bash\n$run $cmd\n$wait\n");
  chmod($exec,0755);
  return $exec;
}
function command($path,$file) {
  global $run,$wait,$rows;
  return (file_exists($file) && substr($file,0,strlen($path))==$path) ? "$run tail -f -n $rows '$file'" : $wait;
}
switch ($_GET['tag']) {
case 'ttyd':
  // check if ttyd already running
  $sock = "/var/run/ttyd.sock";
  exec('pgrep --ns $$ -f '."'$sock'", $ttyd_pid, $retval);
  if ($retval == 0) {
    // check if there are any child processes, ie, curently open tty windows
    exec('pgrep --ns $$ -P '.$ttyd_pid[0], $output, $retval);
    // no child processes, restart ttyd to pick up possible font size change
    if ($retval != 0) exec("kill ".$ttyd_pid[0]);
  }
  if ($retval != 0) exec("ttyd-exec -i '$sock' '" . posix_getpwuid(0)['shell'] . "' --login");
  break;
case 'syslog':
  // read syslog file
  $path = '/var/log/';
  $file = realpath($path.$_GET['name']);
  $sock = "/var/run/syslog.sock";
  exec("ttyd-exec -s9 -om1 -i '$sock' ".command($path,$file));
  break;
case 'disklog':
  // read disk log info (main page)
  $name = unbundle($_GET['name']);
  $sock = "/var/tmp/$name.sock";
  $ata  = exec("ls -n '/sys/block/$name'|grep -Pom1 'ata\d+'");
  $dev  = $ata ? $name.'|'.$ata.'[.:]' : $name;
  exec("ttyd-exec -s9 -om1 -i '$sock' ".wait($name,"grep -P \"'$dev'\" '/var/log/syslog*'"));
  break;
case 'log':
  // read vm log file
  $path = '/var/log/';
  $name = unbundle($_GET['name']);
  $file = realpath($path.$_GET['more']);
  $sock = "/var/tmp/$name.sock";
  exec("ttyd-exec -s9 -om1 -i '$sock' ".command($path,$file));
  break;
case 'docker':
  $name = unbundle($_GET['name']);
  $more = unbundle($_GET['more']) ?: 'sh';
  if ($more=='.log') {
    // read docker container log
    $sock = "/var/tmp/$name.log.sock";
    if (empty(exec("docker ps --filter=name='$name' --format={{.Names}}")))
      $docker = wait($name,"docker logs -n $rows '$name'"); // container stopped
    else
      $docker = "$run docker logs -f -n $rows '$name'"; // container started
    exec("ttyd-exec -s9 -om1 -i '$sock' $docker");
  } else {
    // docker console command
    $sock = "/var/tmp/$name.sock";
    exec("ttyd-exec -s9 -om1 -i '$sock' docker exec -it '$name' $more");
  }
  break;
case 'lxc':
  $name = unbundle($_GET['name']);
  $more = unbundle($_GET['more']);
  $sock = "/var/tmp/$name.sock";
  exec("ttyd-exec -s9 -om1 -i '$sock' lxc-attach '$name' $more");
  break;
}
?>
