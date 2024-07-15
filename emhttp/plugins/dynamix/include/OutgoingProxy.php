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

$opmPlugin	= "dynamix";
require_once("plugins/".$opmPlugin."/include/OutgoingProxyLib.php");

switch ($_POST['action']) {
	case 'proxy_status':
		/* Get the active proxy. */
		$proxy_active		= urldecode($_POST['proxy_active']);

		/* Get the proxy 1 status. */
		$proxy_1_url	= urldecode($_POST['proxy_1_url']);
		if ($proxy_1_url) {
			$proxy_1_status	= proxy_online($proxy_1_url) ? ($proxy_active == "1" ? "Active" : "") : ($proxy_active == "1" ? "Offline" : "Not Available");
		} else {
			$proxy_1_status = "";
		}

		/* Get the proxy 2 status. */
		$proxy_2_url	= urldecode($_POST['proxy_2_url']);
		if ($proxy_2_url) {
			$proxy_2_status	= proxy_online($proxy_2_url) ? ($proxy_active == "2" ? "Active" : "") : ($proxy_active == "2" ? "Offline" : "Not Available");
		} else {
			$proxy_2_status = "";
		}
		/* Get the proxy 3 status. */
		$proxy_3_url	= urldecode($_POST['proxy_3_url']);
		if ($proxy_3_url) {
			$proxy_3_status	= proxy_online($proxy_3_url) ? ($proxy_active == "3" ? "Active" : "") : ($proxy_active == "3" ? "Offline" : "Not Available");
		} else {
			$proxy_3_status = "";
		}

		echo json_encode(array( 'proxy_status_1' => $proxy_1_status, 'proxy_status_2' => $proxy_2_status, 'proxy_status_3' => $proxy_3_status ));
		break;

	default:
		outgoingproxy_log("Undefined POST action - ".$_POST['action'].".");
		break;
}
?>
