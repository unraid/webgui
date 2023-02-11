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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

require_once "$docroot/webGui/include/Helpers.php";
/* add translations */
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

$var = (array)parse_ini_file('state/var.ini');
$license_state = strtoupper(empty($var['regCheck']) ? $var['regTy'] : $var['regCheck']);
$key_contents = empty($var['regFILE'])? "" : str_replace(['+','/','='], ['-','_',''], trim(base64_encode(@file_get_contents($var['regFILE']))));
$myservers_flash_cfg_path='/boot/config/plugins/dynamix.my.servers/myservers.cfg';
$myservers = file_exists($myservers_flash_cfg_path) ? @parse_ini_file($myservers_flash_cfg_path,true) : [];

$configErrorEnum = [ // used to map $var['configValid'] value to mimic unraid-api's `configError` ENUM
  "error" => 'UNKNOWN_ERROR',
  "invalid" => 'INVALID',
  "nokeyserver" => 'NO_KEY_SERVER',
  "withdrawn" => 'WITHDRAWN',
];

$arr = [];
if (empty($myservers['remote']['username'])) {
  $arr['registered'] = 0;
  $arr['username'] = '';
  $arr['avatar'] = '';
} else {
  $arr['registered'] = 1;
  $arr['username'] = $myservers['remote']['username'];
  $arr['avatar'] = $myservers['remote']['avatar']??'';
}
$arr['event'] = 'STATE';
$arr['ts'] = time();
$arr['deviceCount'] = $var['deviceCount'];
$arr['guid'] = $var['flashGUID'];
$arr['regGuid'] = $var['regGUID'];
$arr['state'] = $license_state;
$arr['keyfile'] = $key_contents;
$arr['reggen'] = $var['regGen'];
$arr['flashproduct'] = $var['flashProduct'];
$arr['flashvendor'] = $var['flashVendor'];
$arr['servername'] = $var['NAME'];
$arr['serverdesc'] = $var['COMMENT'];
$arr['internalip'] = $_SERVER['SERVER_ADDR'];
$arr['internalport'] = $_SERVER['SERVER_PORT'];
$arr['plgVersion'] = 'base-'.$var['version'];
$arr['protocol'] = $_SERVER['REQUEST_SCHEME'];
$arr['locale'] = $_SESSION['locale'] ?? 'en_US';
$arr['expiretime']=1000*($var['regTy']=='Trial'||strstr($var['regTy'],'expired')?$var['regTm2']:0);
$arr['uptime']=1000*(time() - round(strtok(exec("cat /proc/uptime"),' ')));
$arr['hasRemoteApikey'] = empty($myservers['remote']['apikey']) ? 0 : 1;
$arr['hideMyServers'] = (file_exists('/usr/local/sbin/unraid-api')) ? '' : 'yes';
$arr['config'] = [
  'valid' => $var['configValid'] === 'yes',
  'error' => $var['configValid'] !== 'yes'
    ? (array_key_exists($var['configValid'], $configErrorEnum) ? $configErrorEnum[$var['configValid']] : 'UNKNOWN_ERROR')
    : null,
];

echo json_encode($arr);
?>
