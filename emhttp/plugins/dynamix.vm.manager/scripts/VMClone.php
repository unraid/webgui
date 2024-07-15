#!/usr/bin/php -q
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
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

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

function execCommand_nchan_clone($command,$idx,$refcmd=false) {
  $waitID = mt_rand();
  if ($refcmd) {
    [$cmd,$args] = explode(' ',$refcmd,2);
    write("<p class='logLine'></p>","addLog\0<fieldset class='docker'><legend>"._('Command execution')."</legend>".basename($cmd).' '.str_replace(" -","<br>&nbsp;&nbsp;-",htmlspecialchars($args))."<br><span id='wait-$waitID'>"._('Please wait')." </span><p class='logLine'></p></fieldset>","show_Wait\0$waitID");
    $rtn = exec("$refcmd 2>&1", $output,$return) ;
    if ($return == 0) $reflinkok = true ; else {
      $reflinkok = false ;
      write("addLog\0<br><b>{$output[0]}</b>");
    }
    $out = $return ? _('The command failed revert to rsync')."." : _('The command finished successfully').'!';
    write("stop_Wait\0$waitID","addLog\0<br><b>$out</b>");
  }
  if ($reflinkok) {
    return true ;
  } else {
    $waitID = mt_rand();
    [$cmd,$args] = explode(' ',$command,2);
    write("<p class='logLine'></p>","addLog\0<fieldset class='docker'><legend>"._('Command execution')."</legend>".basename($cmd).' '.str_replace(" -","<br>&nbsp;&nbsp;-",htmlspecialchars($args))."<br><span id='wait-$waitID'>"._('Please wait')." </span><p class='logLine'></p></fieldset>","show_Wait\0$waitID");
    write("addToID\0$idx\0Cloning VM: ") ;
    $proc = popen("$command 2>&1 &",'r');
    while ($out = fread($proc,100)) {
      $out = preg_replace("%[\t\n\x0B\f\r]+%", '',$out);
      $out = trim($out);
      $values = explode('  ',$out);
      $string = _('Data copied').': '.$values[0].' '._('Percentage').': '.$values[1].' '._('Transfer Rate').': '.$values[2].' '._('Time remaining').': '.$values[4].$values[5];
      write("progress\0$idx\0".htmlspecialchars($string));
      if ($out) $stringsave=$string;
    }
    $retval = pclose($proc);
    write("progress\0$idx\0".htmlspecialchars($stringsave));
    $out = $retval ? _('The command failed').'.' : _('The command finished successfully').'!';
    write("stop_Wait\0$waitID","addLog\0<br><b>$out</b>");
    return $retval===0;
  }
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
$actiontxt = ucfirst($action) ;
write("<p class='logLine'></p>","addLog\0<fieldset class='docker'><legend>"._("Options for $actiontxt").": </legend><p class='logLine'></p></fieldset>");
write("addLog\0".htmlspecialchars("Cloning $name to $clone"));

switch ($action) {
case "clone":
  $rtn = vm_clone($name,$clone,$overwrite,$start,$edit,$free,$waitID) ;
  break ;
}
write("stop_Wait\0$waitID") ;
if ($rtn) write('_DONE_',''); else write('_ERROR_','');
?>
