#!/usr/bin/php -q
<?PHP
/* Copyright 2005-2024, Lime Technology
 * Copyright 2012-2024, Bergware International.
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
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

// add translations
$_SERVER['REQUEST_URI'] = '';
$login_locale = _var($display,'locale');
require_once "$docroot/webGui/include/Translations.php";

function write(...$messages) {
  $com = curl_init();
  curl_setopt_array($com,[
    CURLOPT_URL => 'http://localhost/pub/vmaction?buffer_length=1',
    CURLOPT_UNIX_SOCKET_PATH => '/var/run/nginx.socket',
    CURLOPT_POST => 1,
    CURLOPT_RETURNTRANSFER => true
  ]);
  foreach ($messages as $message) {
    curl_setopt($com, CURLOPT_POSTFIELDS, $message);
    curl_exec($com);
  }
  curl_close($com);
}

function execCommand_nchan($command,$idx) {
  $waitID = mt_rand();
  [$cmd,$args] = explode(' ',$command,2);
  write("<p class='logLine'></p>","addLog\0<fieldset class='docker'><legend>"._('Command execution')."</legend>".basename($cmd).' '.str_replace(" -","<br>&nbsp;&nbsp;-",htmlspecialchars($args))."<br><span id='wait-$waitID'>"._('Please wait')." </span><p class='logLine'></p></fieldset>","show_Wait\0$waitID");
  write("addLog\0<br>") ;
  #write("addToID\0$idx\0 $action") ;
  $proc = popen("$command 2>&1",'r');
  while ($out = fgets($proc)) {
    $out = preg_replace("%[\t\n\x0B\f\r]+%", '',$out);
    if (substr($out,0,1) == "B") {  ; 
      write("progress\0$idx\0".htmlspecialchars(substr($out,strrpos($out,"Block Pull")))) ;
    } else echo write("addToID\0$idx\0 ".htmlspecialchars($out));
  }
  $retval = pclose($proc);
  $out = $retval ? _('The command failed').'.' : _('The command finished successfully').'!';
  write("stop_Wait\0$waitID","addLog\0<br><b>$out</b>");
  return $retval===0;
  }

#{action:"snap-", uuid:uuid , snapshotname:target , remove:remove, free:free ,removemeta:removemeta ,keep:keep, desc:desc}
#VM ID [ 99]: pull. .Block Pull: [ 0 %]Block Pull: [100 %].Pull complete.
$url = rawurldecode($argv[1]??'');
$waitID = mt_rand();
$style = ["<style>"];
$style[] = ".logLine{font-family:bitstream!important;font-size:1.2rem!important;margin:0;padding:0}";
$style[] = "fieldset.docker{border:solid thin;margin-top:8px}";
$style[] = "legend{font-size:1.1rem!important;font-weight:bold}";
$style[] = "</style>";

foreach (explode('&', $url) as $chunk) {
  $param = explode("=", $chunk);
  if ($param) {
    ${urldecode($param[0])} = urldecode($param[1]) ;
  }
}
$id = 1 ;
write(implode($style)."<p class='logLine'></p>");
$process = " " ;
write("<p class='logLine'></p>","addLog\0<fieldset class='docker'><legend>"._("Options for Block $action").": </legend><p class='logLine'></p><span id='wait-$waitID'>"._('Please wait')." </span></fieldset>");
write("addLog\0".htmlspecialchars("VMName $name "));
write("addLog\0".htmlspecialchars("SNAP $snapshotname "));
write("addLog\0".htmlspecialchars("Base $targetbase "));
if ($action == "commit") {
  write("addLog\0".htmlspecialchars("Top $targettop "));
  write("addLog\0".htmlspecialchars("Pivot $targetpivot "));
  write("addLog\0".htmlspecialchars("Delete $targetdelete "));
}

switch ($action) {
case "commit":  
  vm_blockcommit($name,$snapshotname,$path,$targetbase,$targettop,$targetpivot,$targetdelete) ;
  break ;
case "copy":
  vm_blockcopy($name,$snapshotname,$path,$targetbase,$targettop,$pivot,' ') ;
  break;
case "pull":
  vm_blockpull($name,$snapshotname,$path,$targetbase,$targettop,$pivot,' ') ;
  break ;
} 
#execCommand_nchan("ls /") ;
write("stop_Wait\0$waitID") ;
write('_DONE_','');
?>
