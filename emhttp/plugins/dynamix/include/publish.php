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

// $message: if an array, it will be converted to a JSON string
// $abort: if true, the script will exit if the endpoint is without subscribers on the next publish attempt after $abortTime seconds
// If a script publishes to multiple endpoints, timing out on one endpoint will terminate the entire script even if other enpoints succeed.
// If this is a problem, don't use $abort and instead handle this in the script or utilize a single sript per endpoint.
//
// $opt1: if numeric it's the length of the buffer.  If its true|false it signifies whether to utilise the abort if no listeners.
// $opt2: if $opt1 is numeric, it's a boolean for $abort. If $opt1 is boolean, its the timeout defaulting to $opt3
// $opt3: if $opt1 is numeric, it's a value for $abortTime.  If $opt1 is boolean, this parameter shouldn't be used
function publish($endpoint, $message, $opt1=1, $opt2=false, $opt3=120) {
  static $abortStart = [], $com = [], $lens = [];

  if ( is_file("/tmp/publishPaused") )
    return false;

  if ( is_array($message) ) {
    $message = json_encode($message);
  }

  // handle the $opt1/$opt2/$opt3 parameters while remaining backwards compatible
  if ( is_numeric($opt1) ) {
    $len = $opt1;
    $abort = $opt2;
    $abortTime = $opt3;
  } else {
    $len = 1;
    $abort = $opt1;
    $abortTime = $opt2 ?: $opt3;
  }

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
        my_logger("$endpoint timed out after $abortTime seconds.  Exiting.", 'publish');
        
        removeNChanScript();
        exit();
      }
    } else {
      // a subscriber is present.  Reset the abort timer if it's set
      $abortStart[$endpoint] = null;
    }
  }
  return $reply;
}

// Function to not continually republish the same message if it hasn't changed since the last publish
function publish_md5($endpoint, $message, $opt1=1, $opt2=false, $opt3=120) {
  static $md5_old = [];
  static $md5_time = [];
 
  if ( is_numeric($opt1) ) {
    $timeout = $opt3;
    $abort = $opt2;
  } else {
    $abort = $opt1;
    $timeout = $opt2 ?: $opt3;
  }

  if ( is_array($message) ) {
    $message = json_encode($message);
  }

  // if abort is set, republish the message even if it hasn't changed after $timeout seconds to check for subscribers and exit accordingly
  if ( $abort ) {
    if ( (time() - ($md5_time[$endpoint]??0)) > $timeout ) {
      $md5_old[$endpoint] = null;
    }
  }

  // Always hash the payload to avoid collapsing distinct "falsey" values
  $md5_new = md5((string)$message, true);
  if ($md5_new !== ($md5_old[$endpoint]??null)) {
    $md5_old[$endpoint] = $md5_new;
    $md5_time[$endpoint] = time();

    return publish($endpoint, $message, $opt1, $opt2, $opt3);
  }
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
}
?>
