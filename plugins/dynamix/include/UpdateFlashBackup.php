<?PHP
/* Copyright 2005-2018, Lime Technology
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
$cli = php_sapi_name()=='cli';

function response_complete($httpcode, $result, $cli_success_msg='') {
  global $cli;
  if ($cli) {
    $json = @json_decode($result,true);
    if (!empty($json['error'])) {
      echo 'Error: '.$json['error'].PHP_EOL;
      exit(1);
    }
    if (!empty($cli_success_msg)) $cli_success_msg .= PHP_EOL;
    exit($cli_success_msg);
  }
  header('Content-Type: application/json');
  http_response_code($httpcode);
  exit((string)$result);
}

// command
//  init (default)
//  activate
//  status
//  update
//  reinit
//  deactivate
if ($cli) {
  if ($argc > 1) $command = $argv[1];
} else {
  $command = $_POST['command'];
}
if (empty($command)) $command='init';

// keyfile
$var = parse_ini_file("/var/local/emhttp/var.ini");
$keyfile = @file_get_contents($var['regFILE']);
if ($keyfile === false) {
  response_complete(406, '{"error":"Registration key required"}');
}
$keyfile = @base64_encode($keyfile);

// check if activated
if ($command != 'activate') {
  exec('git -C /boot config --get remote.origin.url', $config_output, $return_var);
  if (($return_var != 0) || (strpos($config_output,'backup.unraid.net') === false)) {
    response_complete(406, '{"error":"Not activated"}');
  }                                              
}                                              

// if deactivate command, just remove our origin
if ($command == 'deactivate') {
  exec('git --git-dir /boot/.git remote remove origin &>/dev/null');
  response_complete(200, '{}');
}

// build a list of sha256 hashes of the bzfiles
$bzfilehashes = [];
$allbzfiles = ['bzimage','bzfirmware','bzmodules','bzroot','bzroot-gui'];
foreach ($allbzfiles as $bzfile) {
  $sha256 = trim(@file_get_contents("/boot/$bzfile.sha256"));
  if (strlen($sha256) != 64) {
    response_complete(406, '{"error":"Invalid or missing '.$bzfile.'.sha256 file"}');
  }
  $bzfilehashes[] = $sha256;
}

$ch = curl_init('https://keys.lime-technology.com/backup/flash/activate');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
  'keyfile' => $keyfile,
  'version' => $var['version'],
  'bzfiles' => implode(',', $bzfilehashes)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($result === false) {
  response_complete(500, '{"error":"'.$error.'"}');
}

$json = json_decode($result, true);
if (empty($json) || empty($json['ssh_privkey'])) {
  response_complete(406, $result);
}

// save the public and private keys
if (!file_exists('/root/.ssh')) {
  mkdir('/root/.ssh', 0700);
}
file_put_contents('/root/.ssh/unraidbackup_id_ed25519', $json['ssh_privkey']);
chmod('/root/.ssh/unraidbackup_id_ed25519', 0600);
file_put_contents('/root/.ssh/unraidbackup_id_ed25519.pub', $json['ssh_pubkey']);
chmod('/root/.ssh/unraidbackup_id_ed25519.pub', 0644);

// add configuration to use our keys
if (!file_exists('/root/.ssh/config') || strpos(file_get_contents('/root/.ssh/config'),'Host backup.unraid.net') === false) {
  file_put_contents('/root/.ssh/config', 'Host backup.unraid.net
IdentityFile ~/.ssh/unraidbackup_id_ed25519
IdentitiesOnly yes
', FILE_APPEND);
  chmod('/root/.ssh/config', 0644);
}

// add our server as a known host
if (!file_exists('/root/.ssh/known_hosts') || strpos(file_get_contents('/root/.ssh/known_hosts'),'backup.unraid.net,54.70.72.154') === false) {
  file_put_contents('/root/.ssh/known_hosts', 'backup.unraid.net,54.70.72.154 ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBKrKXKQwPZTY25MoveIw7fZ3IoZvvffnItrx6q7nkNriDMr2WAsoxu0DrU2QrSLH5zFF1ibv4tChS1hOpiYObiI=', FILE_APPEND);
  chmod('/root/.ssh/known_hosts', 0644);
}

// blow away existing repo if reinit command
if ($command == 'reinit') {
  exec('rm -rf /boot/.git');
}

// ensure git repo is setup on the flash drive
if (!file_exists('/boot/.git/info/exclude')) {
  exec('git init /boot &>/dev/null');
}

// setup git ignore for files we dont need in the flash backup
if (strpos(file_get_contents('/boot/.git/info/exclude'),'Unraid') === false) {
  file_put_contents('/boot/.git/info/exclude', '# Unraid OS Flash Backup

# Blacklist everything
/*

# Whitelist selected root files
!*.sha256
!changes.txt
!license.txt

!EFI*/
EFI*/boot/*
!EFI*/boot/syslinux.cfg

!syslinux/
syslinux/*
!syslinux/syslinux.cfg
!syslinux/syslinux.cfg-

# Whitelist entire config directory except for selected files
!config/
config/drift
config/random-seed
config/plugins/unRAIDServer.plg
');
}

// ensure git user is configured
exec('git --git-dir /boot/.git config user.email \'gitbot@unraid.net\' &>/dev/null');
exec('git --git-dir /boot/.git config user.name \'gitbot\' &>/dev/null');

// ensure upstream git server is configured and in-sync
exec('git --git-dir /boot/.git remote add -f -t master -m master origin git@backup.unraid.net:~/flash.git &>/dev/null');
if ($command != 'reinit') {
  exec('git --git-dir /boot/.git reset origin/master &>/dev/null');
  exec('git --git-dir /boot/.git checkout -B master origin/master &>/dev/null');
}

// establish status
exec('git -C /boot status --porcelain', $status_output, $return_var);
if ($return_var != 0) {
  response_complete(406, '{"error":"'.${status_output[0]}.'"}');
}                                              

if ($command == 'status') {
  $data = implode("\n", $status_output);
  response_complete($httpcode, '{"data":"'.$data.'"}', $data);
}

if (($command == 'update') || ($command == 'reinit')) {
  // push changes upstream
  if (!empty($status_output)) {
    exec('git -C /boot add -A &>/dev/null');
    if ($command == 'reinit') {
      exec('git -C /boot commit -m \'Initial commit\' &>/dev/null');
      exec('git -C /boot push --force origin master &>/dev/null');
    } else {
      exec('git -C /boot commit -m \'Config change\' &>/dev/null');
      exec('git -C /boot push &>/dev/null');
    }                                              
  }                                              
}                                              

response_complete($httpcode, '{}');
?>
