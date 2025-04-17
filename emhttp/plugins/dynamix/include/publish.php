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
require_once "$docroot/webGui/include/Wrappers.php";

function curl_socket($socket, $url, $message='') {
  $com = curl_init($url);
  curl_setopt_array($com, [CURLOPT_UNIX_SOCKET_PATH => $socket, CURLOPT_RETURNTRANSFER => 1]);
  if ($message) curl_setopt_array($com, [CURLOPT_POSTFIELDS => $message, CURLOPT_POST => 1]);
  $reply = curl_exec($com);
  curl_close($com);
  if ($reply===false) my_logger("curl to $url failed", 'curl_socket');
  return $reply;
}
function publish($endpoint, $message, $len=1) {
  static $com  = [];
  static $lens = [];

  if ( is_file("/tmp/publishPaused") )
    return false;

  // Check for the unlikely case of a buffer length change
  if ( (($lens[$endpoint] ?? 1) !== $len) && isset($com[$endpoint]) ) {
    curl_close($com[$endpoint]);
    unset($com[$endpoint]);
  }
  if ( !isset($com[$endpoint]) ) {
    $lens[$endpoint] = $len;
    $com[$endpoint] = curl_init("http://localhost/pub/$endpoint?buffer_length=$len");
    curl_setopt_array($com[$endpoint],[
      CURLOPT_UNIX_SOCKET_PATH => "/var/run/nginx.socket",
      CURLOPT_HTTPHEADER       => ['Accept:text/json'],
      CURLOPT_POST             => 1,
      CURLOPT_RETURNTRANSFER   => 1,
      CURLOPT_FAILONERROR      => true
    ]);
  }
  curl_setopt($com[$endpoint], CURLOPT_POSTFIELDS, $message);
  $reply = curl_exec($com[$endpoint]);
  $err   = curl_error($com[$endpoint]);
  if ($err) {
    curl_close($com[$endpoint]);
    unset($com[$endpoint]);

    preg_match_all("/[0-9]+/",$err,$matches);
    // 500: out of shared memory when creating a channel
    // 507: out of shared memory publishing a message

    if ( ($matches[0][0] ?? "") == 507 || ($matches[0][0] ?? "") == 500 ) {
      my_logger("Nchan out of shared memory.  Reloading nginx");
      // prevent multiple attempts at restarting from other scripts using publish.php
      touch("/tmp/publishPaused");
      exec("/etc/rc.d/rc.nginx restart");
      @unlink("/tmp/publishPaused");
    }
  }
  if ($reply===false) my_logger("curl to $endpoint failed", 'publish');
  return $reply;
}
?>
