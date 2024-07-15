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
  $com = curl_init("http://localhost/pub/$endpoint?buffer_length=$len");
  curl_setopt_array($com,[
    CURLOPT_UNIX_SOCKET_PATH => "/var/run/nginx.socket",
    CURLOPT_HTTPHEADER => ['Accept:text/json'],
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => $message,
    CURLOPT_RETURNTRANSFER => 1
  ]);
  $reply = curl_exec($com);
  curl_close($com);
  if ($reply===false) my_logger("curl to $endpoint failed", 'publish');
  return $reply;
}
?>
