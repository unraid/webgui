<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2019-2023, Andrew Zawadzki.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";
require_once "$docroot/plugins/dynamix/include/Secure.php";

//add translations
$_SERVER['REQUEST_URI'] = "plugins";
require_once "$docroot/plugins/dynamix/include/Translations.php";

function plugin_remote_url_allowed($url) {
	$parts = parse_url(trim((string)$url));
	return is_array($parts)
		&& ($parts['scheme'] ?? '') === 'https'
		&& !isset($parts['user'],$parts['pass'])
		&& !empty($parts['host'])
		&& !isset($parts['fragment']);
}

function plugin_trusted_key($plugin) {
	$base = pathinfo(basename((string)$plugin),PATHINFO_FILENAME);
	if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,127}\z/D',$base)) return false;
	$directory = '/boot/config/plugins/plugin-manager/trusted-keys';
	$root = realpath($directory);
	if ($root === false || !is_dir($root)) return false;
	$key = "$root/$base.pub";
	return !is_link($key) && is_file($key) && realpath(dirname($key)) === $root ? $key : false;
}

function download_url($url, $path = "") {
	if (!plugin_remote_url_allowed($url)) return false;
	$ch = curl_init();
	curl_setopt_array($ch,[
		CURLOPT_URL => $url,
		CURLOPT_FRESH_CONNECT => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_TIMEOUT => 45,
		CURLOPT_ENCODING => "",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
		CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
		CURLOPT_FAILONERROR => true
	]);
	$out = curl_exec($ch);
	curl_close($ch);
	if ($out === false) return false;
	if ($path && file_put_contents($path,$out,LOCK_EX) === false) return false;
	return $out;
}

function download_signed_manifest($url,$plugin,$path) {
	$key = plugin_trusted_key($plugin);
	if (!$key || !plugin_remote_url_allowed($url)) return false;
	$manifest = download_url($url,$path);
	$signature = download_url($url.'.sig');
	if ($manifest === false || $signature === false) return false;
	$trimmed = trim($signature);
	if ($trimmed !== '' && !str_contains($signature,"\0") && preg_match('/\A[A-Za-z0-9+\/\r\n]+={0,2}\z/',$trimmed)) {
		$decoded = base64_decode($trimmed,true);
		if ($decoded !== false) $signature = $decoded;
	}
	$public = @openssl_pkey_get_public(@file_get_contents($key));
	return $public && openssl_verify($manifest,$signature,$public,OPENSSL_ALGO_SHA256) === 1;
}

switch ($_POST['action']) {
	case 'checkPlugin':
		$options = $_POST['options'] ?? '';
		$plugin = $options['plugin'] ?? '';
		$name = unbundle($options['name'] ?? $plugin);
		$file = "/boot/config/plugins/$plugin";
		$file = realpath($file)==$file ? $file : "";
		if ( ! $plugin || ! file_exists($file) ) {
			echo json_encode(["updateAvailable"=>false]);
			break;
		}
		exec("mkdir -p /tmp/plugins");
		@unlink("/tmp/plugins/$plugin");
		$url = plugin("pluginURL","/boot/config/plugins/$plugin");
		if (!$url || !download_signed_manifest($url,$plugin,"/tmp/plugins/$plugin")) {
			@unlink("/tmp/plugins/$plugin");
			echo json_encode(["updateAvailable"=>false]);
			break;
		}
		$changes = plugin("changes","/tmp/plugins/$plugin");
		$alerts = plugin("alert","/tmp/plugins/$plugin");
		$version = plugin("version","/tmp/plugins/$plugin");
		$installedVersion = plugin("version","/boot/config/plugins/$plugin");
		$min = plugin("min","/tmp/plugins/$plugin") ?: "6.4.0";
		if ( $changes ) {
			file_put_contents("/tmp/plugins/".pathinfo($plugin, PATHINFO_FILENAME).".txt",$changes);
		} else {
			@unlink("/tmp/plugins/".pathinfo($plugin, PATHINFO_FILENAME).".txt");
		}
		if ( $alerts ) {
			file_put_contents('/tmp/plugins/my_alerts.txt',$alerts);
		} else {
			@unlink('/tmp/plugins/my_alerts.txt');
		}
		$update = false;
		if ( strcmp($version,$installedVersion) > 0 ) {
			$unraid = parse_ini_file("/etc/unraid-version");
			$update = version_compare($min,$unraid['version'],'<=');
		}
		$updateMessage = sprintf(_("%s: An update is available."),$name);
		$linkMessage = sprintf(_("Click here to install version %s"),$version);
		echo json_encode(["updateAvailable"=>$update, "version"=>$version, "min"=>$min, "alert"=>$alerts, "changes"=>$changes, "installedVersion"=>$installedVersion, "updateMessage"=>$updateMessage, "linkMessage"=>$linkMessage]);
		break;

	case 'addRebootNotice':
		$message = htmlspecialchars(trim($_POST['message']));
		if (!$message) break;
		$existing = (array)@file("/tmp/reboot_notifications",FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		$existing[] = $message;
		file_put_contents("/tmp/reboot_notifications",implode("\n",array_unique($existing)));
		break;

	case 'removeRebootNotice':
		$message = htmlspecialchars(trim($_POST['message']));
		$existing = file_get_contents("/tmp/reboot_notifications");
		$newReboots = str_replace($message,"",$existing);
		file_put_contents("/tmp/reboot_notifications",$newReboots);
		break;
}
?>
