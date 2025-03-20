<?php
/* Copyright 2005-2024, Lime Technology
 * Copyright 2012-2024, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
/**
 * Abstracting this code into a separate file allows us to use it in multiple places without duplicating code.
 * 1. unraidcheck script can call this
 * require_once "$docroot/plugins/dynamix.plugin.manager/include/UnraidCheck.php";
 * $unraidOsCheck = new UnraidOsCheck();
 * $unraidOsCheck->checkForUpdate();
 *
 * 2. Unraid webgui web components can GET this file with action params to get updates, ignore updates, etc.
 * - EX: Unraid webgui web components can check for updates via a GET request and receive a response with the json file directly
 *  - this is useful for the UPC to check for updates and display a model based on the value
 *  - `/plugins/dynamix.plugin.manager/scripts/unraidcheck.php?json=true`
 *  - note the json=true query param to receive a json response
 * 
 * @param action {'check'|'removeAllIgnored'|'removeIgnoredVersion'|'ignoreVersion'} - the action to perform
 * @param version {string} - the version to ignore or remove
 * @param json {string} - if set to true, will return the json response from the external request
 * @param altUrl {URL} - if set, will use this url instead of the default
 */
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/ReplaceKey.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

class UnraidOsCheck
{
    private const BASE_RELEASES_URL = 'https://releases.unraid.net/os';
    private const JSON_FILE_IGNORED = '/tmp/unraidcheck/ignored.json';
    private const JSON_FILE_IGNORED_KEY = 'updateOsIgnoredReleases';
    private const JSON_FILE_RESULT = '/tmp/unraidcheck/result.json';
    private const PLG_PATH = '/usr/local/emhttp/plugins/unRAIDServer/unRAIDServer.plg';
    private const VAR_INI_FILE = '/var/local/emhttp/var.ini';

    public function __construct()
    {
        $isGetRequest = !empty($_SERVER) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
        $getHasAction = $_GET !== null && !empty($_GET) && isset($_GET['action']);

        if ($isGetRequest && $getHasAction) {
            $this->handleGetRequestWithActions();
        }
    }

    private function handleGetRequestWithActions()
    {
        switch ($_GET['action']) {
            case 'check':
                $forceReplaceKeyCheck = (isset($_GET['forceReplaceKeyCheck'])) ? true : false;
                $this->checkForUpdate($forceReplaceKeyCheck);
                break;

            case 'removeAllIgnored':
                $this->removeAllIgnored();
                break;

            case 'removeIgnoredVersion':
                if (isset($_GET['version'])) {
                    $this->removeIgnoredVersion($_GET['version']);
                }
                break;

            case 'ignoreVersion':
                if (isset($_GET['version'])) {
                    $this->ignoreVersion($_GET['version']);
                }
                break;

            default:
                $this->respondWithError(400, "Unhandled action");
                break;
        }
    }

    public function getUnraidOSCheckResult()
    {
        if (file_exists(self::JSON_FILE_RESULT)) {
            return $this->readJsonFile(self::JSON_FILE_RESULT);
        }
    }

    public function getIgnoredReleases()
    {
        if (!file_exists(self::JSON_FILE_IGNORED)) {
            return [];
        }

        $ignoredData = $this->readJsonFile(self::JSON_FILE_IGNORED);

        if (is_array($ignoredData) && array_key_exists(self::JSON_FILE_IGNORED_KEY, $ignoredData)) {
            return $ignoredData[self::JSON_FILE_IGNORED_KEY];
        }

        return [];
    }

    /**
     * Check for Unraid OS updates and new license keys
     * 
     * @param bool $forceReplaceKeyCheck Force check for license key replacement regardless of expiry window
     * @return ?bool Returns:
     *   - true: Successfully checked for updates with no errors
     *   - false: Error occurred during update check
     *   - null: No update check was performed (e.g. invalid params)
     *   Note: If $_GET['json'] is set, outputs JSON response and exits with code 0 instead of returning
     */
    public function checkForUpdate(bool $forceReplaceKeyCheck = false): ?bool
    {
        $var = (array)@parse_ini_file(self::VAR_INI_FILE);
        $initialRegExp = _var($var, 'regExp');

        // checking for a new license key created via auto-extension
        $replaceKey = new ReplaceKey();
        if ($replaceKey->check($forceReplaceKeyCheck)) {
            // if we have a new key, we need to wait for emhttp to update var.ini with the new regExp value
            $startTime = time();
            $timeout = 5; // seconds

            while (time() - $startTime < $timeout) {
                $currentVar = (array)@parse_ini_file(self::VAR_INI_FILE);
                $currentRegExp = _var($currentVar, 'regExp');

                // Handle cases where either value might be undefined or different
                if ((!$initialRegExp && $currentRegExp) || ($initialRegExp && !$currentRegExp) ||
                    ($initialRegExp && $currentRegExp && $currentRegExp !== $initialRegExp)) {
                    $var = $currentVar;
                    break;
                }

                usleep(150000); // Sleep for 0.15 seconds between checks
            }

            if (time() - $startTime >= $timeout) { // if we timeout, use the current value
                $var = (array)@parse_ini_file(self::VAR_INI_FILE);
            }
        }

        $params  = [];
        $params['branch']          = plugin('category', self::PLG_PATH, 'stable');
        $params['current_version'] = plugin('version', self::PLG_PATH) ?: _var($var, 'version');
        if (_var($var, 'regExp')) $params['update_exp'] = date('Y-m-d', _var($var, 'regExp')*1);
        $defaultUrl = self::BASE_RELEASES_URL;
        // pass a param of altUrl to use the provided url instead of the default
        $parsedAltUrl = (array_key_exists('altUrl',$_GET) && $_GET['altUrl']) ? $_GET['altUrl'] : null;
        // if $parsedAltUrl pass to params
        if ($parsedAltUrl) $params['altUrl'] = $parsedAltUrl;

        $urlbase = $parsedAltUrl ?? $defaultUrl;
        $url     = $urlbase.'?'.http_build_query($params);
        $curlinfo = [];
        $response = http_get_contents($url,[],$curlinfo);
        if (array_key_exists('error', $curlinfo)) {
            $response = json_encode(array('error' => $curlinfo['error']), JSON_PRETTY_PRINT);
        }
        $responseMutated = json_decode($response, true);
        if (!$responseMutated) {
            $response = json_encode(array('error' => 'Invalid response from '.$urlbase), JSON_PRETTY_PRINT);
            $responseMutated = json_decode($response, true);
        }

        // add params that were used for debugging
        $responseMutated['params'] = $params;

        // store locally for UPC to access
        $this->writeJsonFile(self::JSON_FILE_RESULT, $responseMutated);

        // if we have a query param of json=true then just output the json
        if (array_key_exists('json',$_GET) && $_GET['json']) {
            header('Content-Type: application/json');
            echo $response;
            exit(0);
        }

        // send notification if a newer version is available and not ignored
        $isNewerVersion = array_key_exists('isNewer',$responseMutated) ? $responseMutated['isNewer'] : false;
        $isReleaseIgnored = array_key_exists('version',$responseMutated) ? in_array($responseMutated['version'], $this->getIgnoredReleases()) : false;

        if ($responseMutated && $isNewerVersion && !$isReleaseIgnored) {
            $output  = _var($notify, 'plugin');
            $server  = strtoupper(_var($var, 'NAME', 'server'));
            $newver = (array_key_exists('version',$responseMutated) && $responseMutated['version']) ? $responseMutated['version'] : 'unknown';
            $script  = '/usr/local/emhttp/webGui/scripts/notify';
            $event = "System - Unraid [$newver]";
            $subject = "Notice [$server] - Version update $newver";
            $description = "A new version of Unraid is available";
            exec("$script -e ".escapeshellarg($event)." -s ".escapeshellarg($subject)." -d ".escapeshellarg($description)." -i ".escapeshellarg("normal $output")." -l '/Tools/Update' -x");
        }

        return !array_key_exists('error', $responseMutated);
    }

    private function removeAllIgnored()
    {
        if (file_exists(self::JSON_FILE_IGNORED)) {
            $this->deleteJsonFile(self::JSON_FILE_IGNORED);
            $this->respondWithSuccess([]);
        }
        // fail silently if file doesn't exist
    }

    private function removeIgnoredVersion($removeVersion)
    {
        if ($this->isValidSemVerFormat($removeVersion)) {
            if (file_exists(self::JSON_FILE_IGNORED)) {
                $existingData = $this->readJsonFile(self::JSON_FILE_IGNORED);

                if (isset($existingData[self::JSON_FILE_IGNORED_KEY])) {
                    $existingData[self::JSON_FILE_IGNORED_KEY] = array_diff($existingData[self::JSON_FILE_IGNORED_KEY], [$removeVersion]);
                    $this->writeJsonFile(self::JSON_FILE_IGNORED, $existingData);
                    $this->respondWithSuccess($existingData);
                } else {
                    $this->respondWithError(400, "No versions to remove in the JSON file");
                }
            } else {
                $this->respondWithError(400, "No JSON file found");
            }
        } else {
            $this->respondWithError(400, "Invalid removeVersion format");
        }
    }

    private function ignoreVersion($version)
    {
        if ($this->isValidSemVerFormat($version)) {
            $newData = [$this::JSON_FILE_IGNORED_KEY => [$version]];
            $existingData = file_exists(self::JSON_FILE_IGNORED) ? $this->readJsonFile(self::JSON_FILE_IGNORED) : [];

            if (isset($existingData[self::JSON_FILE_IGNORED_KEY])) {
                $existingData[self::JSON_FILE_IGNORED_KEY][] = $version;
            } else {
                $existingData[self::JSON_FILE_IGNORED_KEY] = [$version];
            }

            $this->writeJsonFile(self::JSON_FILE_IGNORED, $existingData);
            $this->respondWithSuccess($existingData);
        } else {
            $this->respondWithError(400, "Invalid version format");
        }
    }

    private function isValidSemVerFormat($version)
    {
        return preg_match('/^\d+\.\d+(\.\d+)?(-.+)?$/', $version);
    }

    private function readJsonFile($file)
    {
        return @json_decode(@file_get_contents($file), true) ?? [];
    }

    private function writeJsonFile($file, $data)
    {
        if (!is_dir(dirname($file))) { // prevents errors when directory doesn't exist
            mkdir(dirname($file));
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function deleteJsonFile($file)
    {
        unlink($file);
    }

    private function respondWithError($statusCode, $message)
    {
        http_response_code($statusCode);
        echo $message;
    }

    private function respondWithSuccess($data)
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}

// Instantiate and handle the request for GET requests with actions – vars are duplicated here for multi-use of this file
$isGetRequest = !empty($_SERVER) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
$getHasAction = $_GET !== null && !empty($_GET) && isset($_GET['action']);
if ($isGetRequest && $getHasAction) {
    new UnraidOsCheck();
}
