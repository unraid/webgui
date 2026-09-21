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
  $root = realpath($path);
  $root = $root===false ? false : rtrim($root,'/').'/';
  return ($root!==false && is_string($file) && file_exists($file) && strncmp($file,$root,strlen($root))===0) ? "$run tail -f -n $rows ".escapeshellarg($file) : $wait;
}
function sed_escape($s) {
  // escape sed replacement meta characters: &, # and \
  return str_replace(['\\', '&', '#'], ['\\\\', '\\&', '\\#'], $s);
}
function terminal_param($name, $default=null) {
  if (!array_key_exists($name,$_GET)) return $default;
  return is_string($_GET[$name]) ? $_GET[$name] : false;
}
function terminal_identifier($value) {
  return is_string($value) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]*\z/', $value)===1;
}
function terminal_shell($value) {
  return is_string($value) && in_array($value,['sh','bash'],true);
}
function terminal_log_file($path,$relative) {
  if (!is_string($relative) || $relative==='' || $relative[0]==='/' || preg_match('/[\x00-\x1F\x7F]/',$relative) || preg_match('#(?:^|/)\.{1,2}(?:/|$)#',$relative)) return false;
  $root = realpath($path);
  if ($root===false) return false;
  $root = rtrim($root,'/').'/';
  $file = realpath($root.$relative);
  return is_string($file) && strncmp($file,$root,strlen($root))===0 ? $file : false;
}
function terminal_abort() {
  http_response_code(400);
  exit;
}
$tag = terminal_param('tag');
if ($tag===false) terminal_abort();
switch ($tag) {
case 'ttyd':
  // check if ttyd already running
  $sock = "/var/run/ttyd.sock";
  $user_shell = escapeshellarg(posix_getpwuid(0)['shell']);
  exec('pgrep --ns $$ -f '."'$sock'", $ttyd_pid, $retval);
  if ($retval == 0) {
    // check if there are any child processes, ie, curently open tty windows
    exec('pgrep --ns $$ -P '.$ttyd_pid[0], $output, $retval);
    // no child processes, restart ttyd to pick up possible font size change
    if ($retval != 0) exec("kill ".$ttyd_pid[0]);
  }
  
  $more = terminal_param('more','');
  if ($more===false || ($more!=='' && preg_match('/[\x00-\x1F\x7F]/',$more))) terminal_abort();
  if (!empty($more) && substr($more, 0, 1) === '/') {
    // Terminal at specific path - use 'more' parameter to pass path
    // Note: openTerminal(tag, name, more) in JS only has 3 params, so we reuse 'more'
    // Note: Used by File Manager to open terminal at specific folder
    
    // Validate path
    $real_path = realpath($more);
    if ($real_path === false || !is_dir($real_path)) terminal_abort();
    
    // Set script variables
    $unique_id = getmypid() . '_' . uniqid(); // prevent race condition with multiple terminals
    $exec = "/var/tmp/file.manager.terminal.$unique_id.sh";
    $profile = "/tmp/file.manager.terminal.$unique_id.profile";
    $escaped_path = str_replace("'", "'\\''", $real_path);
    $sed_escaped = sed_escape($escaped_path);
    
    // Create startup script similar to ~/.bashrc
    // Note: We can not use ~/.bashrc as it loads /etc/profile which does 'cd $HOME'
    // Note: Script deletes itself before exec (bash has already loaded the script into memory)
    $script_content = <<<BASH
#!/bin/bash
# Modify /etc/profile to replace 'cd \$HOME' with our target path
sed 's#^cd \$HOME#cd '\''$sed_escaped'\''#' /etc/profile > '$profile'
source '$profile'
source /root/.bash_profile 2>/dev/null
rm '$profile'
# Delete this script and exec shell (bash has already loaded this into memory)
{ rm -f '$exec'; exec $user_shell --norc -i; }
BASH;
    
    file_put_contents($exec, $script_content);
    chmod($exec, 0755);
    exec("ttyd-exec -i ".escapeshellarg($sock)." ".escapeshellarg($exec));

  // Standard login shell
  } else {
    if ($retval != 0) exec("ttyd-exec -i ".escapeshellarg($sock)." $user_shell --login");
  }
  break;
case 'syslog':
  // read syslog file
  $path = '/var/log/';
  $name = terminal_param('name','');
  if ($name===false) terminal_abort();
  $file = realpath($path.$name);
  $sock = "/var/run/syslog.sock";
  exec("ttyd-exec -s9 -om1 -i ".escapeshellarg($sock)." ".command($path,$file));
  break;
case 'disklog':
  // read disk log info (main page)
  $name = terminal_param('name');
  if (!terminal_identifier($name)) terminal_abort();
  $sock = "/var/tmp/$name.sock";
  $ata  = exec("ls -n ".escapeshellarg("/sys/block/$name")."|grep -Pom1 'ata\\d+'");
  $dev  = $ata ? $name.'|'.$ata.'[.:]' : $name;
  $exec = wait($name,"grep -P ".escapeshellarg("'$dev'")." '/var/log/syslog*'");
  exec("ttyd-exec -s9 -om1 -i ".escapeshellarg($sock)." ".escapeshellarg($exec));
  break;
case 'log':
  // read vm log file
  $path = '/var/log/';
  $name = terminal_param('name');
  $more = terminal_param('more');
  $file = terminal_log_file($path,$more);
  if (!terminal_identifier($name) || $file===false) terminal_abort();
  $sock = "/var/tmp/$name.sock";
  exec("ttyd-exec -s9 -om1 -i ".escapeshellarg($sock)." ".command($path,$file));
  break;
case 'docker':
  $name = terminal_param('name');
  $more = terminal_param('more');
  if ($more===false) terminal_abort();
  if ($more===null || $more==='') $more = 'sh';
  if (!terminal_identifier($name) || ($more!=='.log' && !terminal_shell($more))) terminal_abort();
  if ($more=='.log') {
    // read docker container log
    $sock = "/var/tmp/$name.log.sock";
    if (empty(exec("docker ps --filter=name=".escapeshellarg($name)." --format={{.Names}}")))
      $docker = escapeshellarg(wait($name,"docker logs -n $rows ".escapeshellarg($name))); // container stopped
    else
      $docker = "$run docker logs -f -n $rows ".escapeshellarg($name); // container started
    exec("ttyd-exec -s9 -om1 -i ".escapeshellarg($sock)." $docker");
  } else {
    // docker console command
    $sock = "/var/tmp/$name.sock";
    exec("ttyd-exec -s9 -om1 -i ".escapeshellarg($sock)." docker exec -it ".escapeshellarg($name)." ".escapeshellarg($more));
  }
  break;
case 'lxc':
  $name = terminal_param('name');
  $more = terminal_param('more');
  if (!terminal_identifier($name) || $more===false || ($more!==null && $more!=='' && !terminal_shell($more))) terminal_abort();
  $sock = "/var/tmp/$name.sock";
  $shell = ($more===null || $more==='') ? '' : ' '.escapeshellarg($more);
  exec("ttyd-exec -s9 -om1 -i ".escapeshellarg($sock)." lxc-attach ".escapeshellarg($name).$shell);
  break;
}
?>
