<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
    exit($cli_success_msg.PHP_EOL);
  }
  header('Content-Type: application/json');
  http_response_code($httpcode);
  exit((string)$result);
}

// protocol, hostname, internalport
list($protocol, $hostname, $internalport) = explode(":", rtrim(file_get_contents("/var/run/nginx.origin")));
$hostname = substr($hostname, 2);
if (!preg_match('/.*\.unraid\.net$/', $hostname)) {
  response_complete(406, '{"error":"Nothing to do"}');
}

// keyfile
$var = parse_ini_file("/var/local/emhttp/var.ini");
$keyfile = @file_get_contents($var['regFILE']);
if ($keyfile === false) {
  response_complete(406, '{"error":"Registration key required"}');
}
$keyfile = @base64_encode($keyfile);

// internalip
extract(parse_ini_file('/var/local/emhttp/network.ini',true));
$internalip = $eth0['IPADDR:0'];

$ch = curl_init('https://keys.lime-technology.com/account/server/register');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
  'internalip' => $internalip,
  'keyfile' => $keyfile
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($result === false) {
  response_complete(500, '{"error":"'.$error.'"}');
}

response_complete($httpcode, $result, 'success');
?>
