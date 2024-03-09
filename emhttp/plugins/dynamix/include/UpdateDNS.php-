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

// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";

function host_lookup_ip($host) {
  $result = @dns_get_record($host, DNS_A);
  $ip = $result ? _var($result[0],'ip') : '';
  return($ip);
}
function rebindDisabled() {
  global $isLegacyCert;
  $rebindtesturl = $isLegacyCert ? "rebindtest.unraid.net" : "rebindtest.myunraid.net";
  // DNS Rebind Protection - this checks the server but clients could still have issues
  $validResponse = ["192.168.42.42", "fd42"];
  $response = host_lookup_ip($rebindtesturl);
  return in_array(explode('::',$response)[0], $validResponse);
}
function format_port($port) {
  return ($port != 80 && $port != 443) ? ':'.$port : '';
}
function anonymize_host($host) {
  global $anon;
  if ($anon) {
    $host = preg_replace('/.*\.myunraid\.net/', '*.hash.myunraid.net', $host);
    $host = preg_replace('/.*\.unraid\.net/', 'hash.unraid.net', $host);
  }
  return $host;
}
function anonymize_ip($ip) {
  global $anon;
  if ($anon && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
    $ip = "[redacted]";
  }
  return $ip;
}
function generate_internal_host($host, $ip) {
  if (strpos($host,'.myunraid.net') !== false) {
    $host = str_replace('*', str_replace('.', '-', $ip), $host);
  }
  return $host;
}
function generate_external_host($host, $ip) {
  if (strpos($host,'.myunraid.net') !== false) {
    $host = str_replace('*', str_replace('.', '-', $ip), $host);
  } elseif (strpos($host,'.unraid.net') !== false) {
    $host = "www.".$host;
  }
  return $host;
}
function verbose_output($httpcode, $result) {
  global $cli, $verbose, $anon, $plgversion, $post, $var, $isRegistered, $myservers, $reloadNginx, $nginx, $isLegacyCert;
  global $remoteaccess;
  global $icon_warn, $icon_ok;
  if (!$cli || !$verbose) return;

  if ($anon) echo "(Output is anonymized, use '-vv' to see full details)".PHP_EOL;
  echo "Unraid OS "._var($var,'version','???').((strpos($plgversion, "base-") === false) ? " with My Servers plugin version {$plgversion}" : '').PHP_EOL;
  echo ($isRegistered) ? "{$icon_ok}Signed in to Unraid.net as {$myservers['remote']['username']}".PHP_EOL : "{$icon_warn}Not signed in to Unraid.net".PHP_EOL ;
  echo "Use SSL is "._var($nginx,'NGINX_USESSL','No').PHP_EOL;
  echo (rebindDisabled()) ? "{$icon_ok}Rebind protection is disabled" : "{$icon_warn}Rebind protection is enabled";
  echo " for ".($isLegacyCert ? "unraid.net" : "myunraid.net").PHP_EOL;
  if ($post) {
    $wanip = trim(@file_get_contents("https://wanip4.unraid.net/"));
    // check the data
    $certhostname = _var($nginx,'NGINX_CERTNAME');
    if ($certhostname) {
      // $certhostname is $nginx['NGINX_CERTNAME'] (certificate_bundle.pem)
      $certhostip = host_lookup_ip(generate_internal_host($certhostname, _var($post,'internalip')));
      $certhosterr = ($certhostip != _var($post,'internalip'));
    }
    if (_var($post,'internalhostname') != $certhostname) {
      // $post['internalhostname'] is $nginx['NGINX_LANMDNS'] (no cert, or Server_unraid_bundle.pem) || $nginx['NGINX_CERTNAME'] (certificate_bundle.pem)
      $internalhostip = host_lookup_ip(generate_internal_host(_var($post,'internalhostname'), _var($post,'internalip')));
      $internalhosterr = ($internalhostip != _var($post,'internalip'));
    }
    if (!empty($post['externalhostname'])) {
      // $post['externalhostname'] is $nginx['NGINX_CERTNAME'] (certificate_bundle.pem)
      $externalhostip = host_lookup_ip(generate_external_host($post['externalhostname'], $wanip));
      $externalhosterr = ($externalhostip != $wanip);
    }
    // anonymize data. no caclulations can be done with this data beyond this point.
    if ($anon) {
      if (!empty($certhostip)) $certhostip = anonymize_ip($certhostip);
      if (!empty($certhostname)) $certhostname = anonymize_host($certhostname);
      if (!empty($internalhostip)) $internalhostip = anonymize_ip($internalhostip);
      if (!empty($externalhostip)) $externalhostip = anonymize_ip($externalhostip);
      if (!empty($wanip)) $wanip = anonymize_ip($wanip);
      if (!empty($post['internalip'])) $post['internalip'] = anonymize_ip($post['internalip']);
      if (!empty($post['internalhostname'])) $post['internalhostname'] = anonymize_host($post['internalhostname']);
      if (!empty($post['externalhostname'])) $post['externalhostname'] = anonymize_host($post['externalhostname']);
      if (!empty($post['externalport'])) $post['externalport'] = "[redacted]";
    }
    // always anonymize the keyfile
    if (!empty($post['keyfile'])) $post['keyfile'] = "[redacted]";
    // output notes
    if (!empty($post['internalprotocol']) && !empty($post['internalhostname']) && !empty($post['internalport'])) {
      $localurl = $post['internalprotocol']."://".generate_internal_host($post['internalhostname'], _var($post,'internalip')).format_port($post['internalport']);
      echo 'Local Access url: '.$localurl.PHP_EOL;
      if ($internalhostip) {
        // $internalhostip will not be defined for .local domains, ok to skip
        echo ($internalhosterr) ? $icon_warn : $icon_ok;
        echo generate_internal_host($post['internalhostname'], _var($post,'internalip'))." resolves to {$internalhostip}";
        echo ($internalhosterr) ? ", it should resolve to "._var($post,'internalip') : "";
        echo PHP_EOL;
      }
      if ($certhostname) {
        echo ($certhosterr) ? $icon_warn : $icon_ok;
        echo generate_internal_host($certhostname, _var($post,'internalip')).' ';
        echo ($certhostip) ? "resolves to {$certhostip}" : "does not resolve to an IP address";
        echo ($certhosterr) ? ", it should resolve to "._var($post,'internalip') : "";
        echo PHP_EOL;
      }
      if ($remoteaccess == 'yes' && !empty($post['externalprotocol']) && !empty($post['externalhostname']) && !empty($post['externalport'])) {
        $remoteurl = $post['externalprotocol']."://".generate_external_host($post['externalhostname'], $wanip).format_port($post['externalport']);
        echo 'Remote Access url: '.$remoteurl.PHP_EOL;
        echo ($externalhosterr) ? $icon_warn : $icon_ok;
        echo generate_external_host($post['externalhostname'], $wanip).' ';
        echo ($externalhosterr) ? "does not resolve to an IP address" : "resolves to ".($externalhostip??'');
        echo PHP_EOL;
      }
      if ($reloadNginx) {
        echo "IP address changes were detected, nginx was reloaded".PHP_EOL;
      }
    }
    // output post data
    echo PHP_EOL.'Request:'.PHP_EOL;
    echo @json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
  }
  if ($result) {
    echo "Response (HTTP $httpcode):".PHP_EOL;
    $mutatedResult = is_array($result) ? json_encode($result) : $result;
    echo @json_encode(@json_decode($mutatedResult, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
  }
}
/**
 * @name response_complete
 * @param {HTTP Response Status Code} $httpcode https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
 * @param {String|Array} $result - strings are assumed to be encoded JSON. Arrays will be encoded to JSON.
 * @param {String} $cli_success_msg
 */
function response_complete($httpcode, $result, $cli_success_msg='') {
  global $cli, $verbose;
  $mutatedResult = is_array($result) ? json_encode($result) : $result;
  if ($cli) {
    if ($verbose) verbose_output($httpcode, $result);
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

$cli = php_sapi_name()=='cli';
$verbose = $anon = false;
if ($cli && ($argc > 1) && $argv[1] == "-v") {
  $verbose = true;
  $anon = true;
}
if ($cli && ($argc > 1) && $argv[1] == "-vv") {
  $verbose = true;
}
$var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
$nginx = (array)@parse_ini_file('/var/local/emhttp/nginx.ini');
$is69 = version_compare(_var($var,'version'),"6.9.9","<");
$reloadNginx = false;
$dnserr = false;
$icon_warn = "⚠️  ";
$icon_ok = "✅  ";

$myservers_flash_cfg_path='/boot/config/plugins/dynamix.my.servers/myservers.cfg';
$myservers = (array)@parse_ini_file($myservers_flash_cfg_path,true);
// ensure some vars are defined here so we don't have to test them later
if (empty($myservers['remote']['apikey'])) {
  $myservers['remote']['apikey'] = "";
}
if (empty($myservers['remote']['wanaccess'])) {
  $myservers['remote']['wanaccess'] = "no";
}
if (empty($myservers['remote']['wanport'])) {
  $myservers['remote']['wanport'] = 443;
}
// remoteaccess, externalport
if ($cli) {
  $remoteaccess = empty($nginx['NGINX_WANFQDN']) ? 'no' : 'yes';
  $externalport = $myservers['remote']['wanport'];
} else {
  $remoteaccess = _var($_POST,'remoteaccess','no');
  $externalport = intval(_var($_POST,'externalport',443));

  if ($remoteaccess != 'yes') {
    $remoteaccess = 'no';
  }

  if ($externalport < 1 || $externalport > 65535) {
    $externalport = 443;
  }

  if ($myservers['remote']['wanaccess'] != $remoteaccess) {
    // update the wanaccess ini value
    $orig = file_exists($myservers_flash_cfg_path) ? parse_ini_file($myservers_flash_cfg_path,true) : [];
    if (!$orig) {
      $orig = ['remote' => $myservers['remote']];
    }
    $orig['remote']['wanaccess'] = $remoteaccess;
    $text = '';
    foreach ($orig as $section => $block) {
      $pairs = "";
      foreach ($block as $key => $value) if (strlen($value)) $pairs .= "$key=\"$value\"\n";
      if ($pairs) $text .= "[$section]\n".$pairs;
    }
    if ($text) file_put_contents_atomic($myservers_flash_cfg_path, $text);
    // need nginx reload
    $reloadNginx = true;
  }
}
$isRegistered = !empty($myservers['remote']['username']);

// protocols, hostnames, ports
$internalprotocol = 'http';
$internalport = _var($nginx,'NGINX_PORT');
$internalhostname = _var($nginx,'NGINX_LANMDNS');
$externalprotocol = 'https';
// keyserver will expand *.hash.myunraid.net or add www to hash.unraid.net as needed
$externalhostname = _var($nginx,'NGINX_CERTNAME');
$isLegacyCert = preg_match('/.*\.unraid\.net$/', _var($nginx,'NGINX_CERTNAME'));
$isWildcardCert = preg_match('/.*\.myunraid\.net$/', _var($nginx,'NGINX_CERTNAME'));
$internalip = _var($nginx,'NGINX_LANIP');

if (_var($nginx,'NGINX_USESSL')=='yes') {
  // When NGINX_USESSL is 'yes' in 6.9, it could be using either Server_unraid_bundle.pem or certificate_bundle.pem
  // When NGINX_USESSL is 'yes' in 6.10, it is is using Server_unraid_bundle.pem
  $internalprotocol = 'https';
  $internalport = _var($nginx,'NGINX_PORTSSL');
  if ($is69 && _var($nginx,'NGINX_CERTNAME')) {
    // this is from certificate_bundle.pem
    $internalhostname = _var($nginx,'NGINX_CERTNAME');  
  }
}
if (_var($nginx,'NGINX_USESSL')=='auto') {
  // NGINX_USESSL cannot be 'auto' in 6.9, it is either 'yes' or 'no'
  // When NGINX_USESSL is 'auto' in 6.10, it is using certificate_bundle.pem
  $internalprotocol = 'https';
  $internalport = _var($nginx,'NGINX_PORTSSL');
  // keyserver will expand *.hash.myunraid.net as needed
  $internalhostname = _var($nginx,'NGINX_CERTNAME');
}

// My Servers version
$plgversion = file_exists("/var/log/plugins/dynamix.unraid.net.plg") ? trim(exec('/usr/local/sbin/plugin version /var/log/plugins/dynamix.unraid.net.plg 2>/dev/null'))
  : (file_exists("/var/log/plugins/dynamix.unraid.net.staging.plg") ? trim(exec('/usr/local/sbin/plugin version /var/log/plugins/dynamix.unraid.net.staging.plg 2>/dev/null'))
  : 'base-'._var($var,'version'));

// only proceed when when signed in or when legacy unraid.net SSL certificate exists
if (!$isRegistered && !$isLegacyCert) {
  response_complete(406, ['error' => _('Nothing to do')]);
}

// keyfile
$keyfile = empty($var['regFILE']) ? false : @file_get_contents($var['regFILE']);
if ($keyfile === false) {
  response_complete(406, ['error' => _('Registration key required')]);
}
$keyfile = @base64_encode($keyfile);

// build post array
$post = [
  'keyfile' => $keyfile,
  'plgversion' => $plgversion
];
if ($isLegacyCert) {
  // sign in not required to maintain local ddns for unraid.net cert
  // enable local ddns regardless of use_ssl value
  $post['internalip'] = $internalip;
  // if host.unraid.net does not resolve to the internalip and DNS Rebind Protection is disabled, disable caching
  if (host_lookup_ip(generate_internal_host(_var($nginx,'NGINX_CERTNAME'), $post['internalip'])) != $post['internalip'] && rebindDisabled()) $dnserr = true;
}
if ($isRegistered) {
  // if signed in, send data needed to maintain My Servers Dashboard
  $post['internalhostname'] = $internalhostname;
  $post['internalport'] = $internalport;
  $post['internalprotocol'] = $internalprotocol;
  $post['remoteaccess'] = $remoteaccess;
  $post['servercomment'] = _var($var,'COMMENT');
  $post['servername'] = _var($var,'NAME');
  if ($isWildcardCert) {
    // keyserver needs the internalip to generate the local access url
    $post['internalip'] = $internalip;
  }
  if ($remoteaccess == 'yes') {
    // include wanip in the cache file so we can track if it changes
    $post['_wanip'] = trim(@file_get_contents("https://wanip4.unraid.net/"));
    $post['externalhostname'] = $externalhostname;
    $post['externalport'] = $externalport;
    $post['externalprotocol'] = $externalprotocol;
    // if wanip.hash.myunraid.net or www.hash.unraid.net does not resolve to the wanip, disable caching
    if (host_lookup_ip(generate_external_host($post['externalhostname'], $post['_wanip'])) != $post['_wanip']) $dnserr = true;
  }
}

// if remoteaccess is enabled in 6.10.0-rc3+ and WANIP has changed since nginx started, reload nginx
if (_var($post,'_wanip') != _var($nginx,'NGINX_WANIP') && version_compare(_var($var,'version'),"6.10.0-rc2",">")) $reloadNginx = true;
// if remoteaccess is currently disabled (perhaps because a wanip was not available when nginx was started)
//    BUT the system is configured to have it enabled AND a wanip is now available
//    then reload nginx
if ($remoteaccess == 'no' && _var($nginx,'NGINX_WANACCESS') == 'yes' && !empty(trim(@file_get_contents("https://wanip4.unraid.net/")))) $reloadNginx = true;
if ($reloadNginx) {
  exec("/etc/rc.d/rc.nginx reload &>/dev/null");
}

// maxage is 36 hours
$maxage = 36*60*60;
if ($dnserr || $verbose) $maxage = 0;
$datafile = "/tmp/UpdateDNS.txt";
$datafiletmp = "/tmp/UpdateDNS.txt.new";
$dataprev = @file_get_contents($datafile) ?: '';
$datanew = implode("\n",$post)."\n";
if ($datanew == $dataprev && (time()-filemtime($datafile) < $maxage)) {
  response_complete(204, null, _('No change to report'));
}
file_put_contents_atomic($datafiletmp,$datanew);
rename($datafiletmp, $datafile);

// do not submit the wanip, it will be captured from the submission if needed for remote access
unset($post['_wanip']);

// report necessary server details to limetech for DNS updates
$ch = curl_init('https://keys.lime-technology.com/account/server/register');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ( ($result === false) || ($httpcode != "200") ) {
  // delete cache file to retry submission on next run
  @unlink($datafile);
  response_complete($httpcode ?? "500", ['error' => $error]);
}

response_complete($httpcode, $result, _('success'));
?>
