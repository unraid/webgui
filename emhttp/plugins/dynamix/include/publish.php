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
function curl_socket($socket, $url, $message) {
    /* Initialize cURL session. */
    $ch = curl_init($url);

    /* Set cURL options. */
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => $socket,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $message,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    /* Execute cURL session and close it. */
    $reply = curl_exec($ch);
    curl_close($ch);

    /* Return the result. */
    return $reply;
}

function publish($endpoint, $message = "", $len = 1) {
    /* Define socket and URL. */
    $socket = "/var/run/nginx.socket";
    $url = "http://localhost/pub/{$endpoint}?buffer_length={$len}";

    $result = false; /* Initialize result variable. */

    /* If there are subscribers, send the message. */
    if (numSubscribers($socket, $url) !== 0) {
        $result = curl_socket($socket, $url, $message);
    }

    /* Return the result. */
    return $result;
}

function numSubscribers($socket, $url) {
    /* Initialize cURL session. */
    $ch = curl_init();

    /* Set cURL options. */
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => $socket,
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
    ]);

    /* Execute cURL session and get the result. */
    $statusData = curl_exec($ch);

    /* Close cURL session. */
    curl_close($ch);

    /* Use preg_match to extract the number of subscribers. */
    preg_match('/subscribers: (\d+)/', $statusData, $matches);

    /* Check if there is a match. */
    return (!empty($matches[1])) ? $matches[1] : 0;
}
?>
