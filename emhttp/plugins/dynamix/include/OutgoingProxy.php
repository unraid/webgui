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

require_once("plugins/dynamix/include/OutgoingProxyLib.php");

function get_proxy_status($proxy_url, $proxy_active, $index) {
    if (!$proxy_url) {
        return "";
    }

    return proxy_online($proxy_url) 
        ? ($proxy_active == $index ? "Active" : "") 
        : ($proxy_active == $index ? "Offline" : "Not Available");
}

$action = htmlspecialchars($_POST['action'] ?? '', ENT_QUOTES, 'UTF-8');

switch ($action) {
	case 'proxy_status':
		/* Sanitize inputs. */
		$proxy_active = htmlspecialchars($_POST['proxy_active'] ?? '', ENT_QUOTES, 'UTF-8');
		$proxy_urls = [
			'1' => filter_var($_POST['proxy_1_url'] ?? '', FILTER_SANITIZE_URL),
			'2' => filter_var($_POST['proxy_2_url'] ?? '', FILTER_SANITIZE_URL),
			'3' => filter_var($_POST['proxy_3_url'] ?? '', FILTER_SANITIZE_URL),
		];

		/* Generate response. */
		$response = [];
		foreach ($proxy_urls as $key => $url) {
			$response["proxy_status_{$key}"] = get_proxy_status($url, $proxy_active, $key);
		}

		/* Output response as JSON. */
		echo json_encode($response);
		break;

	default:
		outgoingproxy_log("Undefined POST action - " . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . ".");
		break;
}
?>
