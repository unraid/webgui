<?php
// read flashbackup ini file
$flashbackup_ini = '/var/local/emhttp/flashbackup.ini';
$flashbackup_status = (file_exists($flashbackup_ini)) ? @parse_ini_file($flashbackup_ini) : [];

$nginx = parse_ini_file('/var/local/emhttp/nginx.ini');

// base OS only, plugin not installed • show ad for plugin
$connectPluginInstalled = '';
if (file_exists('/var/lib/pkgtools/packages/dynamix.unraid.net')) $connectPluginInstalled = 'dynamix.unraid.net.plg';
if (file_exists('/var/lib/pkgtools/packages/dynamix.unraid.net.staging')) $connectPluginInstalled = 'dynamix.unraid.net.staging.plg';
// plugin install failed if the unraid-api file doesn't fully install • append failure detected so we can show warning about failed install via UPC
if ($connectPluginInstalled && !file_exists('/usr/local/sbin/unraid-api')) $connectPluginInstalled .= '_installFailed';

$connectPluginVersion = file_exists('/var/log/plugins/dynamix.unraid.net.plg')
    ? trim(@exec('/usr/local/sbin/plugin version /var/log/plugins/dynamix.unraid.net.plg 2>/dev/null'))
    : (file_exists('/var/log/plugins/dynamix.unraid.net.staging.plg')
        ? trim(@exec('/usr/local/sbin/plugin version /var/log/plugins/dynamix.unraid.net.staging.plg 2>/dev/null'))
        : 'base-' . $var['version']);

$myservers_flash_cfg_path='/boot/config/plugins/dynamix.my.servers/myservers.cfg';
$myservers = file_exists($myservers_flash_cfg_path) ? @parse_ini_file($myservers_flash_cfg_path,true) : [];

$configErrorEnum = [
    "error" => 'UNKNOWN_ERROR',
    "invalid" => 'INVALID',
    "nokeyserver" => 'NO_KEY_SERVER',
    "withdrawn" => 'WITHDRAWN',
];

$registered = !empty($myservers['remote']['username']) && $connectPluginInstalled;

$serverState = [
    "apiKey" => $myservers['upc']['apikey'] ?? '',
    "apiVersion" => $myservers['api']['version'] ?? '',
    "avatar" => (!empty($myservers['remote']['avatar']) && $connectPluginInstalled) ? $myservers['remote']['avatar'] : '',
    "config" => [
        'valid' => ($var['configValid'] === 'yes'),
        'error' => isset($configErrorEnum[$var['configValid']]) ? $configErrorEnum[$var['configValid']] : 'UNKNOWN_ERROR',
    ],
    "connectPluginInstalled" => $connectPluginInstalled,
    "connectPluginVersion" => $connectPluginVersion,
    "csrf" => $var['csrf_token'],
    "description" => $var['COMMENT'] ? htmlspecialchars($var['COMMENT']) : '',
    "deviceCount" => $var['deviceCount'],
    "email" => $myservers['remote']['email'] ?? '',
    "expireTime" => 1000 * (($var['regTy'] === 'Trial' || strstr($var['regTy'], 'expired')) ? $var['regTm2'] : 0),
    "extraOrigins" => explode(',', $myservers['api']['extraOrigins'] ?? ''),
    "flashProduct" => $var['flashProduct'],
    "flashVendor" => $var['flashVendor'],
    "flashBackupActivated" => empty($flashbackup_status['activated']) ? '' : 'true',
    "guid" => $var['flashGUID'],
    "hasRemoteApikey" => !empty($myservers['remote']['apikey']),
    "internalPort" => $_SERVER['SERVER_PORT'],
    "keyfile" => empty($var['regFILE']) ? '' : str_replace(['+', '/', '='], ['-', '_', ''], trim(base64_encode(@file_get_contents($var['regFILE'])))),
    "lanIp" => ipaddr(),
    "locale" => ($_SESSION['locale']) ? $_SESSION['locale'] : 'en_US',
    "model" => $var['SYS_MODEL'],
    "name" => htmlspecialchars($var['NAME']),
    "osVersion" => $var['version'],
    "protocol" => $_SERVER['REQUEST_SCHEME'],
    "regGen" => (int)$var['regGen'],
    "regGuid" => $var['regGUID'],
    "registered" => $registered,
    "registeredTime" => $myservers['remote']['regWizTime'] ?? '',
    "site" => $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'],
    "state" => strtoupper(empty($var['regCheck']) ? $var['regTy'] : $var['regCheck']),
    "theme" => [
        "banner" => !empty($display['banner']),
        "bannerGradient" => $display['showBannerGradient'] === 'yes' ?? false,
        "bgColor" => ($display['background']) ? '#' . $display['background'] : '',
        "descriptionShow" => (!empty($display['headerdescription']) && $display['headerdescription'] !== 'no'),
        "metaColor" => ($display['headermetacolor'] ?? '') ? '#' . $display['headermetacolor'] : '',
        "name" => $display['theme'],
        "textColor" => ($display['header']) ? '#' . $display['header'] : '',
    ],
    "ts" => time(),
    "uptime" => 1000 * (time() - round(strtok(exec("cat /proc/uptime"), ' '))),
    "username" => $myservers['remote']['username'] ?? '',
    "wanFQDN" => $nginx['NGINX_WANFQDN'] ?? '',
];
