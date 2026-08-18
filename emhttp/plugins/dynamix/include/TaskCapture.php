<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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
// Internal target for nchan_publisher_upstream_request in rc.nginx. Requiring
// both the internal URI and marker prevents this script's public filesystem URL
// from being used to inject output into a running task.
$internal = preg_match(
  '#^/task-capture/(plugins|docker|vmaction)$#',
  $_SERVER['REQUEST_URI'] ?? '',
  $match
);
if (!$internal || ($_SERVER['HTTP_X_TASK_CAPTURE'] ?? '') !== '1') {
  http_response_code(404);
  die();
}

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/plugins/dynamix/include/TaskQueue.php";

$type = $_GET['type'] ?? $match[1];
$message = file_get_contents('php://input');
if (is_string($message)) task_capture($type, $message);

// Nchan interprets 304 as "publish the original message unchanged".
http_response_code(304);
die();
?>
