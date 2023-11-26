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

function download_url($url, $path = "") {
	$ch = curl_init();
	curl_setopt_array($ch,[
		CURLOPT_URL => $url,
		CURLOPT_FRESH_CONNECT => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_TIMEOUT => 45,
		CURLOPT_ENCODING => "",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true
	]);
	$out = curl_exec($ch);
	curl_close($ch);
	if ( $path ) file_put_contents($path,$out);
	return $out ?: false;
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
		download_url($url,"/tmp/plugins/$plugin");
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
