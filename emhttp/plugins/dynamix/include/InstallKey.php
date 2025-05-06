<?php
/* Copyright 2005-2023, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$docroot = ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/Translations.php";

class KeyInstaller
{
    private $isGetRequest;
    private $getHasUrlParam;

    public function __construct()
    {
        $this->isGetRequest = !empty($_SERVER) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
        $this->getHasUrlParam = $_GET !== null && !empty($_GET) && isset($_GET['url']);

        if ($this->isGetRequest && $this->getHasUrlParam) {
            $this->installKey();
        }
    }

    /**
     * @param int $httpcode https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @param string|array $result - strings are assumed to be encoded JSON. Arrays will be encoded to JSON.
     */
    private function responseComplete($httpcode, $result): string
    {
        $mutatedResult = is_array($result) ? json_encode($result) : $result;

        if ($this->isGetRequest && $this->getHasUrlParam) { // return JSON to the caller
            header('Content-Type: application/json');
            http_response_code($httpcode);
            exit((string)$mutatedResult);
        } else { // return the result to the caller
            return $mutatedResult;
        }
    }

    public function installKey($keyUrl = null): string
    {
        $url = unscript($keyUrl ?? _var($_GET, 'url'));
        $host = parse_url($url)['host'] ?? '';

        if (!function_exists('_')) {
            function _($text) {return $text;}
        }

        if ($host && in_array($host, ['keys.lime-technology.com', 'lime-technology.com'])) {
            $keyFile = basename($url);
            exec("/usr/bin/wget -q -O " . escapeshellarg("/boot/config/$keyFile") . " " . escapeshellarg($url), $output, $returnVar);

            if ($returnVar === 0) {
                $var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
                if (_var($var, 'mdState') == "STARTED") {
                    return $this->responseComplete(200, [
                        'status' => 'success',
                        'message' => _('Please Stop array to complete key installation'),
                    ]);
                } else {
                    return $this->responseComplete(200, ['status' => 'success']);
                }
            } else {
                @unlink(escapeshellarg("/boot/config/$keyFile"));
                return $this->responseComplete(406, ['error' => _('download error') . " $returnVar"]);
            }
        } else {
            return $this->responseComplete(406, ['error' => _('bad or missing key file') . ": $url"]);
        }
    }
}

$isGetRequest = !empty($_SERVER) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
$getHasUrlParam = $_GET !== null && !empty($_GET) && isset($_GET['url']);

if ($isGetRequest && $getHasUrlParam) {
    $keyInstaller = new KeyInstaller();
    $keyInstaller->installKey();
}