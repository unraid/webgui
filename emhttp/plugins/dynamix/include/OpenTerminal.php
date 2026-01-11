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
function bre_escape($s, $delimiter = null) {
  // escape BRE meta characters: . * [ ] ^ $ \
  $escaped = preg_replace('/([.*\[\]^$\\\\])/', '\\\\$1', $s);
  // additionally escape delimiter if provided
  if ($delimiter !== null) {
    $escaped = str_replace($delimiter, '\\' . $delimiter, $escaped);
  }
  return $escaped;
}
function sed_escape($s) {
  // escape sed replacement meta characters: & and \
  return str_replace(['\\', '&'], ['\\\\', '\\&'], $s);
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
  
  $more = $_GET['more'] ?? '';
  if (!empty($more) && substr($more, 0, 1) === '/') {
    // Terminal at specific path - use 'more' parameter to pass path
    // Note: openTerminal(tag, name, more) in JS only has 3 params, so we reuse 'more'
    // Note: Used by File Manager to open terminal at specific folder
    
    // Validate path
    $real_path = realpath($more);
    if ($real_path === false) {
      // Path doesn't exist - fall back to home directory
      $real_path = '/root';
    }
    
    // Set script variables
    $exec = "/var/tmp/file.manager.terminal.sh";
    $escaped_path = str_replace("'", "'\\''", $real_path);
    $sed_escaped = sed_escape($escaped_path);
    
    // Get user's shell (same as standard terminal)
    $user_shell = posix_getpwuid(0)['shell'];
    
    // Create startup script similar to ~/.bashrc
    // Note: We can not use ~/.bashrc as it loads /etc/profile which does 'cd $HOME'
    // Note: Script deletes itself before exec (bash has already loaded the script into memory)
    $script_content = <<<BASH
#!/bin/bash
# Modify /etc/profile to replace 'cd \$HOME' with our target path
sed 's#^cd \$HOME#cd '\''$sed_escaped'\''#' /etc/profile > /tmp/file.manager.terminal.profile
source /tmp/file.manager.terminal.profile
source /root/.bash_profile 2>/dev/null
rm /tmp/file.manager.terminal.profile
# Delete this script and exec shell (bash has already loaded this into memory)
{ rm -f '$exec'; exec $user_shell --norc -i; }
BASH;
    
    file_put_contents($exec, $script_content);
    chmod($exec, 0755);
    exec("ttyd-exec -i '$sock' $exec");

  // Standard login shell
  } else {
    if ($retval != 0) exec("ttyd-exec -i '$sock' '" . posix_getpwuid(0)['shell'] . "' --login");
  }
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
