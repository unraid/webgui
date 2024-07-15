<?php
/* Copyright 2024, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$opmPlugin			= "dynamix";

/* UI config file location. */
$plg_config_file	= "/boot/config/plugins/".$opmPlugin."/outgoingproxy.cfg";

/* Outgoing Proxy Manager logging tag. */
$opm_log	= "Outgoing Proxy Manager";

/* Outgoing Proxy logging. */
function outgoingproxy_log($m) {
	global $opm_log;

	$m		= print_r($m,true);
	$m		= str_replace("\n", " ", $m);
	$m		= str_replace('"', "'", $m);
	exec("/usr/bin/logger"." ".escapeshellarg($m)." -t ".escapeshellarg($opm_log));
}

/* Parse plugin config file. */
function parse_plugin_config() {
	global $plg_config_file;

	$cfg = is_file($plg_config_file) ? @parse_ini_file($plg_config_file, true) : array();

	return($cfg);
}

/* Write values to plugin config file. */
function write_plugin_config($config) {
	global $plg_config_file;

	/* Rewrite config file. */
	/* Convert the array to an INI string. */
	$iniString = '';
	foreach ($config as $key => $value) {
		$iniString .= "$key=\"$value\"\n";
	}

	/* Write the INI string to a file. */
	file_put_contents($plg_config_file, $iniString);
}

/* Check to see if the proxy is online and available. */
function proxy_online($proxyUrl) {

	$rc	= true;

	if ($proxyUrl) {
		/* Initialize cURL session. */
		$ch = curl_init("http://www.msftncsi.com/ncsi.txt");

		/* Set cURL options. */
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);	/* Timeout in seconds. */
		curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
		curl_setopt($ch, CURLOPT_PROXY, $proxyUrl);		/* Url is a proxy. */
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); /* Return transfer as a string. */

		/* Execute cURL request. */
		$response = curl_exec($ch);

		if ($response === false) {
			/* Proxy is not available. */
			$rc	= false;
		}

		/* Close cURL session. */
		curl_close($ch);
	}

	return ($rc);
}

/* Get the URL with the user and password parsed from the url. */
function get_proxy_info($cfg_url, $cfg_user = "", $cfg_pass = "") {
	/* Passed in values:
		cfg_url - can be with or without credentials (user and password).
		cfg_user - user from config file.
		cfg_pass - encrypted password from the config file.
	*/

	/* An array is returned with the following values. */
	$return	= [
		'url' => '',		/* URL without credentials. */
		'user' => '',		/* User. */
		'pass' => '',		/* Unencrypted password. */
		'full_url' => '',	/* Full URL with credentials urlencoded. */
	];

	if ($cfg_url) {
		/* Decrypt password. */
		$cfg_pass	= decrypt_data($cfg_pass);

		/* Parse the URL by removing the user and password. */
		$urlComponents = parse_url($cfg_url);

		/* Parse user, password, host, and port from stored URL. */
		$host		= isset($urlComponents['host']) ? $urlComponents['host'] : '';
		$port		= isset($urlComponents['port']) ? $urlComponents['port'] : '';
		$user		= isset($urlComponents['user']) ? $urlComponents['user'] : '';
		$pass		= isset($urlComponents['pass']) ? $urlComponents['pass'] : '';

		/* Return array of url, user, and password. */
		$return['url']		= "http://".$host.':'.$port;

		/* Extract the credentials. */
		if (strpos($cfg_url, '%') !== false) {
			/* The credentials are urlencoded. */
			$return['user']		= $user ? urldecode($user) : $cfg_user;
			$return['pass']		= $pass ? urldecode($pass) : $cfg_pass;
		} else {
			/* The credentials are not urlencoded. */
			$return['user']		= $user ? $user : $cfg_user;
			$return['pass']		= $pass ? $pass : $cfg_pass;
		}

		/* Put together the full url. */
		if (($return['user']) && ($return['pass'])) {
			$return['full_url']	= "http://".urlencode($return['user']).":".urlencode($return['pass'])."@".$host.":".$port;
		} else {
			$return['full_url']	= $return['url'];
		}
	}

	return($return);
}

/* Get configuration parameter. */
function get_config($variable) {

	$config	= parse_plugin_config();

	return $config[$variable] ?? "";
}

/* Set configuration parameter. */
function set_config($variable, $value) {

	$config	= parse_plugin_config();

	$config[$variable] = $value;

	write_plugin_config($config);
}

/* Encrypt data. */
function encrypt_data($data) {
	$key	= get_config("key");
	if ((! $key) || strlen($key) != 32) {
		$key = substr(base64_encode(openssl_random_pseudo_bytes(32)), 0, 32);
		set_config("key", $key);
	}
	$iv		= get_config("iv");
	if ((! $iv) || strlen($iv) != 16) {
		$iv = substr(base64_encode(openssl_random_pseudo_bytes(16)), 0, 16);
		set_config("iv", $iv);
	}

	/* Encrypt the data using aes256. */
	$value	= trim(openssl_encrypt($data, 'aes256', $key, $options=0, $iv));

	return $value;
}

/* Decrypt data. */
function decrypt_data($data) {
	$key	= get_config("key");
	$iv		= get_config("iv");

    /* Decrypt the data using aes256. */
	$value = openssl_decrypt($data, 'aes256', $key, $options=0, $iv);

	/* Make sure the data is UTF-8 encoded. */
	if (! mb_check_encoding($value, 'UTF-8')) {
		outgoingproxy_log("Warning: Data is not UTF-8 encoded");
		$value = "";
	}

	return $value;
}
?>
