<?PHP
/* Copyright 2005-2023, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
function curl_socket($socket, $Url, $message) {
	$com = curl_init($Url);
	curl_setopt_array($com,[
		CURLOPT_UNIX_SOCKET_PATH => $socket,
		CURLOPT_POST=> 1,
		CURLOPT_POSTFIELDS => $message,
		CURLOPT_RETURNTRANSFER => true
	]);
	$reply = curl_exec($com);

	curl_close($com);

	return $reply;
}

function publish($endpoint, $message="", $len=1) {
	$socket		= "/var/run/nginx.socket";
	$Url		= "http://localhost/pub/".$endpoint."?buffer_length=".$len;

	/* If the endpoint is set and there are listeners send the message. */
	if ((numSubscribers($socket, $Url)) != 0) {
		/* Send the message. */
		$result	= curl_socket($socket, $Url, $message);
	} else {
		/* Message was not sent. */
		$result	= false;
	}

	return($result);
}

function numSubscribers($socket, $Url) {
	/* Initialize cURL session. */
	$ch				= curl_init();

	/* Set cURL options. */
	curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $socket);
	curl_setopt($ch, CURLOPT_URL, $Url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	/* Execute cURL session and get the result. */
	$statusData		= curl_exec($ch);

	/* Use preg_match to extract the number of subscribers. */
	preg_match('/subscribers: (\d+)/', $statusData, $matches);

	/* Check if there is a match. */
	if (! empty($matches[1])) {
		$result		= $matches[1];
	} else {
		$result		= 0;
	}

	/* Close cURL session. */
	curl_close($ch);

	return($result);
}
?>
