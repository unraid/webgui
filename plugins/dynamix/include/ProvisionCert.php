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

$var = parse_ini_file("/var/local/emhttp/var.ini");
extract(parse_ini_file('/var/local/emhttp/network.ini',true));

if (file_exists("/boot/config/ssl/certs/certificate_bundle.pem")) {
  $subject = exec("/usr/bin/openssl x509 -subject -noout -in /etc/ssl/certs/unraid_bundle.pem");
  if (!preg_match('/.*\.unraid\.net$/', $subject)) {
    if ($cli) exit(0);  // cert common name isn't <hash>.unraid.net
    response_complete(406, '{"error":"Cannot provision cert that would overwrite your existing custom cert at /boot/config/ssl/certs/certificate_bundle.pem"}');
  }
  exec("/usr/bin/openssl x509 -checkend 2592000 -noout -in /etc/ssl/certs/unraid_bundle.pem",$arrout,$retval_expired);
  if ($retval_expired === 0) {
    if ($cli) exit(0);  // not within 30 days of cert expire date
    response_complete(406, '{"error":"Cannot renew cert until within 30 days of expiry"}');
  }
}

$keyfile = @file_get_contents($var['regFILE']);
if ($keyfile === false) {
  if ($cli) exit(0);
  response_complete(406, '{"error":"License key required"}');
}
$keyfile = @base64_encode($keyfile);
$internalip = $eth0['IPADDR:0'];
$internalport = $var['PORTSSL'];

$ch = curl_init('https://keys.lime-technology.com/account/ssl/provisioncert');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
  'internalip' => $internalip,
  'internalport' => $internalport,
  'keyfile' => $keyfile
]);
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// go ahead and save the cert then reload nginx for cli
if ($cli) {
  $json = @json_decode($result,true);
  if (empty($json['bundle'])) {
    $strError = 'Server was unable to provision SSL certificate';
    if (!empty($json['error'])) {
      $strError .= ' - '.$json['error'];
    }
    response_complete(406, '{"error":"'.$strError.'"}');
  }
  $_POST['text'] = $json['bundle']; // nice way to leverage CertUpload.php to save the cert
  include(__DIR__.'/CertUpload.php');
  exec("/etc/rc.d/rc.nginx reload");
}

response_complete($httpcode, $result, 'LE Cert Provisioned successfully');
?>