<?PHP
/* Copyright 2005-2023, Lime Technology
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
function curl_socket($socket, $url, $message) {
  $com = curl_init($url);
  curl_setopt_array($com,[
    CURLOPT_UNIX_SOCKET_PATH => $socket,
    CURLOPT_POST=> 1,
    CURLOPT_POSTFIELDS => $message,
    CURLOPT_RETURNTRANSFER => true
  ]);
  $reply = curl_exec($com);
  curl_close($com);
  return $reply;
}

function publish($endpoint, $message, $len=1) {
  return curl_socket("/var/run/nginx.socket", "http://localhost/pub/$endpoint?buffer_length=$len", $message);
}
?>
