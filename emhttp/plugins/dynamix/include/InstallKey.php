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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";

/**
 * @name response_complete
 * @param {HTTP Response Status Code} $httpcode https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
 * @param {String|Array} $result - strings are assumed to be encoded JSON. Arrays will be encoded to JSON.
 * @param {String} $cli_success_msg
 */

function response_complete($httpcode, $result, $cli_success_msg='') {
  global $cli;
  $mutatedResult = is_array($result) ? json_encode($result) : $result;
  if ($cli) {
    $json = @json_decode($mutatedResult,true);
    if (!empty($json['error'])) {
      echo 'Error: '.$json['error'].PHP_EOL;
      exit(1);
    }
    exit($cli_success_msg.PHP_EOL);
  }
  header('Content-Type: application/json');
  http_response_code($httpcode);
  exit((string)$mutatedResult);
}

$cli  = php_sapi_name()=='cli';
$url  = unscript(_var($_GET,'url'));
$host = parse_url($url)['host']??'';

if ($host && in_array($host,['keys.lime-technology.com','lime-technology.com'])) {
  $key_file = basename($url);
  exec("/usr/bin/wget -q -O ".escapeshellarg("/boot/config/$key_file")." ".escapeshellarg($url), $output, $return_var);
  if ($return_var === 0) {
    $var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
    if (_var($var,'mdState')=="STARTED") {
      response_complete(200, array('status' => _('Please Stop array to complete key installation')), _('success').', '._('Please Stop array to complete key installation'));
    } else {
      response_complete(200, array('status' => ''), _('success'));
    }
  } else {
    response_complete(406, array('error' => _('download error') . " $return_var"));
  }
} else {
  response_complete(406, array('error' => _('bad or missing key file') . ": $url"));
}
?>
