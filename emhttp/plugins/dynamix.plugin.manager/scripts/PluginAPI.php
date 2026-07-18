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
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginOperationLock.php";
require_once "$docroot/plugins/dynamix/include/Secure.php";

//add translations
$_SERVER['REQUEST_URI'] = "plugins";
require_once "$docroot/plugins/dynamix/include/Translations.php";

function download_url($url, $path = "", &$receipt = null) {
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
	if ( !is_string($out) || $out === '' ) return false;
	if ( $path ) {
		$receipt = plugin_manager_write_complete_download($path,$out);
		if ( !is_array($receipt) ) return false;
	}
	return $out;
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
			$response = null;
			$generation = null;
			$lock_entered = false;
			try {
				$file = realpath("/boot/config/plugins/$plugin");
				if ( $file === false ) throw new RuntimeException("Plugin disappeared");
				$url = plugin_attribute_uncached("pluginURL",$file);
				$generation = plugin_manager_reserve_plugin_check_generation($plugin);
				plugin_manager_prepare_shared_artifact_directory();
				$download = plugin_manager_create_private_download_file();
				$download_receipt = null;
				if ( download_url($url,$download,$download_receipt) === false ||
					!is_array($download_receipt) ) {
					@unlink($download);
					throw new RuntimeException("Plugin download failed");
				}
				try {
					$latest = "/tmp/plugins/$plugin";
					$response = plugin_manager_with_plugin_check_operation_lock(
						$plugin,
						$generation,
						$latest,
						function() use ($plugin, $name, $file, $url, $generation, $download_receipt, $latest, &$lock_entered) {
							$lock_entered = true;
							$current = realpath("/boot/config/plugins/$plugin");
							if ( $current !== $file || plugin_attribute_uncached("pluginURL",$current) !== $url ) return null;
							if ( !plugin_manager_publish_plugin_check_artifact($plugin,$generation,$download_receipt,$latest) ) return null;
							$changes = plugin("changes",$latest);
							$alerts = plugin("alert",$latest);
							$version = plugin("version",$latest);
							$installedVersion = plugin("version","/boot/config/plugins/$plugin");
							if ( $version === false || $installedVersion === false ) return null;
							$min = plugin("min",$latest) ?: "6.4.0";
							if ( !plugin_manager_finalize_plugin_check_artifact($plugin,$generation,$latest) ) return null;
							$changes_path = "/tmp/plugins/".pathinfo($plugin, PATHINFO_FILENAME).".txt";
							if ( $changes ) {
								if ( !plugin_manager_write_shared_artifact($changes_path,$changes) ) {
									throw new RuntimeException("Unable to publish plugin changes");
								}
							} else {
								if ( !plugin_manager_remove_shared_artifact($changes_path) ) {
									throw new RuntimeException("Unable to remove plugin changes");
								}
							}
							if ( $alerts ) {
								if ( !plugin_manager_write_shared_artifact('/tmp/plugins/my_alerts.txt',$alerts) ) {
									throw new RuntimeException("Unable to publish plugin alerts");
								}
							} else {
								if ( !plugin_manager_remove_shared_artifact('/tmp/plugins/my_alerts.txt') ) {
									throw new RuntimeException("Unable to remove plugin alerts");
								}
							}
							$update = false;
							if ( strcmp($version,$installedVersion) > 0 ) {
								$unraid = parse_ini_file("/etc/unraid-version");
								$update = version_compare($min,$unraid['version'],'<=');
							}
							$updateMessage = sprintf(_("%s: An update is available."),$name);
							$linkMessage = sprintf(_("Click here to install version %s"),$version);
							return ["updateAvailable"=>$update, "version"=>$version, "min"=>$min, "alert"=>$alerts, "changes"=>$changes, "installedVersion"=>$installedVersion, "updateMessage"=>$updateMessage, "linkMessage"=>$linkMessage];
						}
					);
				} finally {
					@unlink($download);
				}
		} catch (Throwable) {
			$response = null;
		}
		if ( $response === null && is_int($generation) && !$lock_entered ) {
			try {
				plugin_manager_with_operation_lock(
					fn() => plugin_manager_invalidate_plugin_check_artifact(
						$plugin,
						$generation,
						"/tmp/plugins/$plugin"
					)
				);
			} catch (Throwable) {
				// A failed invalidation remains fail-closed through the update gate.
			}
		}
		if ( $response === null ) {
			echo json_encode(["updateAvailable"=>false]);
			break;
		}
		echo json_encode($response);
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
