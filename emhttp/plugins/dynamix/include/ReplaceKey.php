<?php
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

class ReplaceKey
{
    private const KEY_SERVER_URL = 'https://keys.lime-technology.com';

    private $docroot;
    private $var;
    private $guid;
    private $keyfile;
    private $regExp;

    public function __construct()
    {
        $this->docroot = $GLOBALS['docroot'] ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

        $this->var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
        $this->guid = @$this->var['regGUID'] ?? null;

        $keyfileBase64 = empty($this->var['regFILE']) ? null : @file_get_contents($this->var['regFILE']);
        if ($keyfileBase64 !== false) {
            $keyfileBase64 = @base64_encode($keyfileBase64);
            $this->keyfile = str_replace(['+', '/', '='], ['-', '_', ''], trim($keyfileBase64));
        }

        $this->regExp = @$this->var['regExp'] ?? null;
    }

    private function request($url, $method, $payload = null, $headers = null)
    {
        $ch = curl_init($url);

        // Set the request method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        // store the response in a variable instead of printing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set the payload if present
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if ($headers !== null) {
            // Set the headers
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Set additional options as needed

        // Execute the request
        $response = curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            $error = [
                'heading' => 'CurlError',
                'message' => curl_error($ch),
                'level' => 'error',
                'ref' => 'curlError',
                'type' => 'request',
            ];
            // @todo store error
        }

        // Close the cURL session
        curl_close($ch);

        return $response;
    }

    private function validateGuid()
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $params = [
            'guid' => $this->guid,
            'keyfile' => $this->keyfile,
        ];

        /**
         * returns {JSON}
         * hasNewerKeyfile : boolean;
         * purchaseable: true;
         * registered: false;
         * replaceable: false;
         * upgradeable: false;
         * upgradeAllowed: string[];
         * updatesRenewable: false;
         */
        $response = $this->request(
            self::KEY_SERVER_URL . '/validate/guid',
            'POST',
            http_build_query($params),
            $headers,
        );

        // Handle the response as needed (parsing JSON, etc.)
        $decodedResponse = json_decode($response, true);

        if (!empty($decodedResponse)) {
            return $decodedResponse;
        }

        // @todo save error response somewhere
        return [];
    }

    private function getLatestKey()
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $params = [
            'keyfile' => $this->keyfile,
        ];

        /**
         * returns {JSON}
         * license: string;
         */
        $response = $this->request(
            self::KEY_SERVER_URL . '/key/latest',
            'POST',
            http_build_query($params),
            $headers,
        );

        // Handle the response as needed (parsing JSON, etc.)
        $decodedResponse = json_decode($response, true);

        if (!empty($decodedResponse) && !empty($decodedResponse['license'])) {
            return $decodedResponse['license'];
        }
        return null;
    }

    private function installNewKey($key)
    {
        require_once "$this->docroot/webGui/include/InstallKey.php";

        $KeyInstaller = new KeyInstaller();
        $installResponse = $KeyInstaller->installKey($key);

        $installSuccess = false;

        if (!empty($installResponse)) {
            $decodedResponse = json_decode($installResponse, true);
            if (isset($decodedResponse['error'])) {
                $this->writeJsonFile(
                    '/tmp/ReplaceKey/error.json',
                    [
                        'error' => $decodedResponse['error'],
                        'ts' => time(),
                    ]
                );
                $installSuccess = false;
            } else {
                $installSuccess = true;
            }
        }

        $keyType = basename($key, '.key');
        $output  = isset($GLOBALS['notify']) ? _var($GLOBALS['notify'],'plugin') : '';
        $script  = '/usr/local/emhttp/webGui/scripts/notify';

        if ($installSuccess) {
            $event = "Installed New $keyType License";
            $subject = "Your new $keyType license key has been automatically installed";
            $description = "";
            $importance = "normal $output";
        } else {
            $event = "Failed to Install New $keyType License";
            $subject = "Failed to automatically install your new $keyType license key";
            $description = isset($decodedResponse['error']) ? $decodedResponse['error'] : "Unknown error occurred";
            $importance = "alert $output";
        }

        exec("$script -e ".escapeshellarg($event)." -s ".escapeshellarg($subject)." -d ".escapeshellarg($description)." -i ".escapeshellarg($importance)." -l '/Tools/Registration' -x");

        return $installSuccess;
    }

    private function writeJsonFile($file, $data)
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file));
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function check(bool $forceCheck = false)
    {
        // we don't need to check
        if (empty($this->guid) || empty($this->keyfile) || empty($this->regExp)) {
            return;
        }

        // Check if we're within the 7-day window before and after regExp
        $now = time();
        $sevenDaysBefore = strtotime('-7 days', $this->regExp);
        $sevenDaysAfter = strtotime('+7 days', $this->regExp);

        $isWithinWindow = ($now >= $sevenDaysBefore && $now <= $sevenDaysAfter);

        if (!$forceCheck && !$isWithinWindow) {
            return;
        }

        // see if we have a new key
        $validateGuidResponse = $this->validateGuid();

        $hasNewerKeyfile = @$validateGuidResponse['hasNewerKeyfile'] ?? false;
        if (!$hasNewerKeyfile) {
            return; // if there is no newer keyfile, we don't need to do anything
        }

        $latestKey = $this->getLatestKey();
        if (!$latestKey) {
            // we supposedly have a new key, but didn't get it back…
            $this->writeJsonFile(
                '/tmp/ReplaceKey/error.json',
                [
                    'error' => 'Failed to retrieve latest key after getting a `hasNewerKeyfile` in the validation response.',
                    'ts' => time(),
                ]
            );
            return;
        }

        $this->installNewKey($latestKey);
    }
}
