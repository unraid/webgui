<?php
/**
 * @todo refactor globals – currently if you try to use $GLOBALS the class will break.
 */
$webguiGlobals = $GLOBALS;
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

require_once "$docroot/plugins/dynamix.my.servers/include/reboot-details.php";
/**
 * ServerState class encapsulates server-related information and settings.
 *
 * Usage:
 * ```
 * $serverStateClass = new ServerState();
 *
 * $serverStateClass->getServerState();
 * or
 * $serverStateClass->getServerStateJson();
 * ```
 */
class ServerState
{
    protected $webguiGlobals;

    private $var;
    private $flashbackupIni;
    private $flashbackupStatus;
    private $nginx;
    private $connectPluginInstalled = '';
    private $connectPluginVersion;
    private $myserversFlashCfgPath;
    private $myservers;
    private $configErrorEnum = [
        "error" => 'UNKNOWN_ERROR',
        "invalid" => 'INVALID',
        "nokeyserver" => 'NO_KEY_SERVER',
        "withdrawn" => 'WITHDRAWN',
    ];
    private $osVersionBranch;
    private $registered;
    private $rebootDetails;

    /**
     * Constructor to initialize class properties and gather server information.
     */
    public function __construct()
    {
        /**
         * @note – necessary evil until full webgui is class based.
         * @see - getWebguiGlobal() for usage
         * */
        global $webguiGlobals;
        $this->webguiGlobals =& $webguiGlobals;
        // echo "<pre>" . json_encode($this->webguiGlobals, JSON_PRETTY_PRINT) . "</pre>";

        $this->var = (array)parse_ini_file('state/var.ini');

        $this->flashbackupIni = '/var/local/emhttp/flashbackup.ini';
        $this->flashbackupStatus = (file_exists($this->flashbackupIni)) ? @parse_ini_file($this->flashbackupIni) : [];
        $this->nginx = parse_ini_file('/var/local/emhttp/nginx.ini');

        if (file_exists('/var/lib/pkgtools/packages/dynamix.unraid.net')) {
            $this->connectPluginInstalled = 'dynamix.unraid.net.plg';
        }
        if (file_exists('/var/lib/pkgtools/packages/dynamix.unraid.net.staging')) {
            $this->connectPluginInstalled = 'dynamix.unraid.net.staging.plg';
        }
        if ($this->connectPluginInstalled && !file_exists('/usr/local/sbin/unraid-api')) {
            $this->connectPluginInstalled .= '_installFailed';
        }

        $this->connectPluginVersion = file_exists('/var/log/plugins/dynamix.unraid.net.plg')
            ? trim(@exec('/usr/local/sbin/plugin version /var/log/plugins/dynamix.unraid.net.plg 2>/dev/null'))
            : (file_exists('/var/log/plugins/dynamix.unraid.net.staging.plg')
                ? trim(@exec('/usr/local/sbin/plugin version /var/log/plugins/dynamix.unraid.net.staging.plg 2>/dev/null'))
                : 'base-' . $this->var['version']);

        $this->myserversFlashCfgPath = '/boot/config/plugins/dynamix.my.servers/myservers.cfg';
        $this->myservers = file_exists($this->myserversFlashCfgPath) ? @parse_ini_file($this->myserversFlashCfgPath, true) : [];

        $this->osVersionBranch = trim(@exec('plugin category /var/log/plugins/unRAIDServer.plg') ?? 'stable');
        $this->registered = !empty($this->myservers['remote']['apikey']) && $this->connectPluginInstalled;

        $this->rebootDetails = new RebootDetails();
    }

    /**
     * Retrieve the value of a webgui global setting.
     */
    public function getWebguiGlobal(string $key) {
        return $this->webguiGlobals[$key];
    }

    /**
     * Retrieve the server information as an associative array
     *
     * @return array An array containing server information.
     */
    public function getServerState()
    {
        $serverState = [
            "apiKey" => $this->myservers['upc']['apikey'] ?? '',
            "apiVersion" => $this->myservers['api']['version'] ?? '',
            "avatar" => (!empty($this->myservers['remote']['avatar']) && $this->connectPluginInstalled) ? $this->myservers['remote']['avatar'] : '',
            "config" => [
                'valid' => ($this->var['configValid'] === 'yes'),
                'error' => isset($this->configErrorEnum[$this->var['configValid']]) ? $this->configErrorEnum[$this->var['configValid']] : 'UNKNOWN_ERROR',
            ],
            "connectPluginInstalled" => $this->connectPluginInstalled,
            "connectPluginVersion" => $this->connectPluginVersion,
            "csrf" => $this->var['csrf_token'],
            "dateTimeFormat" => [
                "date" => @$this->getWebguiGlobal('display')['date'] ?? '',
                "time" => @$this->getWebguiGlobal('display')['time'] ?? '',
            ],
            "description" => $this->var['COMMENT'] ? htmlspecialchars($this->var['COMMENT']) : '',
            "deviceCount" => $this->var['deviceCount'],
            "email" => $this->myservers['remote']['email'] ?? '',
            "expireTime" => 1000 * (($this->var['regTy'] === 'Trial' || strstr($this->var['regTy'], 'expired')) ? $this->var['regTm2'] : 0),
            "extraOrigins" => explode(',', $this->myservers['api']['extraOrigins'] ?? ''),
            "flashProduct" => $this->var['flashProduct'],
            "flashVendor" => $this->var['flashVendor'],
            "flashBackupActivated" => empty($this->flashbackupStatus['activated']) ? '' : 'true',
            "guid" => $this->var['flashGUID'],
            "hasRemoteApikey" => !empty($this->myservers['remote']['apikey']),
            "internalPort" => $_SERVER['SERVER_PORT'],
            "keyfile" => empty($this->var['regFILE']) ? '' : str_replace(['+', '/', '='], ['-', '_', ''], trim(base64_encode(@file_get_contents($this->var['regFILE'])))),
            "lanIp" => ipaddr(),
            "locale" => (!empty($_SESSION) && $_SESSION['locale']) ? $_SESSION['locale'] : 'en_US',
            "model" => $this->var['SYS_MODEL'],
            "name" => htmlspecialchars($this->var['NAME']),
            "osVersion" => $this->var['version'],
            "osVersionBranch" => $this->osVersionBranch,
            "protocol" => $_SERVER['REQUEST_SCHEME'],
            "rebootType" => $this->rebootDetails->getRebootType(),
            "regDev" => @(int)$this->var['regDev'] ?? 0,
            "regGen" => @(int)$this->var['regGen'],
            "regGuid" => @$this->var['regGUID'] ?? '',
            "regTo" => @htmlspecialchars($this->var['regTo']) ?? '',
            "regTm" => $this->var['regTm'] ? @$this->var['regTm'] * 1000 : '', // JS expects milliseconds
            "regTy" => @$this->var['regTy'] ?? '',
            "regExp" => $this->var['regExp'] ? @$this->var['regExp'] * 1000 : '', // JS expects milliseconds
            "registered" => $this->registered,
            "registeredTime" => $this->myservers['remote']['regWizTime'] ?? '',
            "site" => $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'],
            "state" => strtoupper(empty($this->var['regCheck']) ? $this->var['regTy'] : $this->var['regCheck']),
            "theme" => [
                "banner" => !empty($this->getWebguiGlobal('display')['banner']),
                "bannerGradient" => $this->getWebguiGlobal('display')['showBannerGradient'] === 'yes' ?? false,
                "bgColor" => ($this->getWebguiGlobal('display')['background']) ? '#' . $this->getWebguiGlobal('display')['background'] : '',
                "descriptionShow" => (!empty($this->getWebguiGlobal('display')['headerdescription']) && $this->getWebguiGlobal('display')['headerdescription'] !== 'no'),
                "metaColor" => ($this->getWebguiGlobal('display')['headermetacolor'] ?? '') ? '#' . $this->getWebguiGlobal('display')['headermetacolor'] : '',
                "name" => $this->getWebguiGlobal('display')['theme'],
                "textColor" => ($this->getWebguiGlobal('display')['header']) ? '#' . $this->getWebguiGlobal('display')['header'] : '',
            ],
            "ts" => time(),
            "uptime" => 1000 * (time() - round(strtok(exec("cat /proc/uptime"), ' '))),
            "username" => $this->myservers['remote']['username'] ?? '',
            "wanFQDN" => $this->nginx['NGINX_WANFQDN'] ?? '',
        ];

        return $serverState;
    }

    /**
     * Retrieve the server information as a JSON string
     *
     * @return string A JSON string containing server information.
     */
    public function getServerStateJson() {
        return json_encode($this->getServerState());
    }
}
