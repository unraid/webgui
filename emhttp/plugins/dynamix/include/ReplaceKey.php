<?php
$webguiGlobals = $GLOBALS;
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
        $KeyInstaller->installKey($key);
    }

    private function writeJsonFile($file, $data)
    {
        if (!is_dir(dirname($file))) { // prevents errors when directory doesn't exist
            mkdir(dirname($file));
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function check()
    {
        // we don't need to check
        if (empty($this->guid) || empty($this->keyfile) || empty($this->regExp)) {
            return;
        }

        // set $isAlreadyExpired to true if regExp is in the past
        $isAlreadyExpired = $this->regExp <= time();
        // if regExp is seven days or less from now, we need to check
        $isWithinSevenDays = $this->regExp <= strtotime('+7 days');

        $shouldCheck = $isAlreadyExpired || $isWithinSevenDays;
        if (!$shouldCheck) {
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
                ]
            );
            return;
        }
        $this->installNewKey($latestKey);
    }
}
