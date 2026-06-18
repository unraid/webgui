<?PHP
/* Copyright 2005-2025, Lime Technology
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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?? '/usr/local/emhttp');

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

// $endpoint: the name of the endpoint to publish to (string)
// $message: the message to publish (string)
// $len: the length of the buffer (default 1)
// $abort: if true, the script will exit if the endpoint is without subscribers on the next publish attempt after $abortTime seconds (default true)
// $abortTime: the time in seconds to wait before exiting the script if the endpoint is without subscribers (default 30)
function publish($endpoint, $message, $len=1, $abort=false, $abortTime=30) {
  static $abortStart = [], $com = [], $lens = [];

  if ( is_file("/tmp/publishPaused") )
    return false;


  // Check for the unlikely case of a buffer length change
  if ( (($lens[$endpoint] ?? 1) !== $len) && isset($com[$endpoint]) ) {
    curl_close($com[$endpoint]);
    unset($com[$endpoint],$abortStart[$endpoint]);
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
    my_logger("curl error: $err endpoint: $endpoint");
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
  if ($reply===false) 
    my_logger("curl to $endpoint failed", 'publish');

  if ($abort) {
    $json = @json_decode($reply,true);
    if ($reply===false || ( is_array($json) && ($json['subscribers']??false) === 0) ) {
      if ( ! ($abortStart[$endpoint]??false) ) 
        $abortStart[$endpoint] = time();
      if ( (time() - $abortStart[$endpoint]) > $abortTime) {
        $script = removeNChanScript();
        exit();
      }
      $reply = false; // if no subscribers, force return value to false
    } else {
      // a subscriber is present.  Reset the abort timer if it's set
      $abortStart[$endpoint] = null;
    }
  }
  return $reply;
}

// Function to not continually republish the same message if it hasn't changed since the last publish
function publish_noDupe($endpoint, $message, $noListenerAbort=false, $abortTime=30) {
  static $msg_old = [];
  static $msg_time = [];
  static $listener = [];

  if ( !isset($listener[$endpoint]) ) {
    $listener[$endpoint] = false;
  }

  // if abort is set, republish the message even if it hasn't changed after $timeout seconds to check for subscribers and exit accordingly
  if ( $noListenerAbort ) {
    if ( (time() - ($msg_time[$endpoint]??0)) > $abortTime ) {
      $msg_old[$endpoint] = null;
    }
  }
  
  if ( ($message !== ($msg_old[$endpoint]??null)) || !$listener[$endpoint]) {
    $msg_old[$endpoint] = $message;
    $msg_time[$endpoint] = time();

    $return = publish($endpoint, $message,1,$noListenerAbort);

    // if no listener, keep publishing whether or not its the same message.
    $listener[$endpoint] = $return ? true : false;

    return $return;
  }
}

// Wrapper to publish a ping message to the endpoint with occasional republishing and checking if anyone is listening
function ping($endpoint) {
  publish_noDupe($endpoint,"ping",true);
}

// Removes the script calling this function from nchan.pid
function removeNChanScript() {
  global $docroot, $argv;

  $script = trim(str_replace("$docroot/","",(php_sapi_name() === 'cli') ? $argv[0] : $_SERVER['argv'][0]));
  $nchan = @file("/var/run/nchan.pid",FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  $nchan = array_filter($nchan,function($x) use ($script) {
     return $script !== trim(explode(":",$x)[0]);
  });
  if (count($nchan) > 0) {
    file_put_contents_atomic("/var/run/nchan.pid",implode("\n",$nchan)."\n");
  } else {
    @unlink("/var/run/nchan.pid");
  }
  return $script;
}
?>
