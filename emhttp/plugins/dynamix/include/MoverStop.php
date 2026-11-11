<?PHP
/* Copyright 2005-2026, Lime Technology
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
// Stop button of the Mover (Main > Array Operation, Settings > Scheduler).
// POST only: local_prepend.php checks the csrf_token of every POST, a GET would bypass that.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  exit;
}
// same syslog tag as the Mover's own lines ("move: mover: started")
exec('logger -t move -- '.escapeshellarg('mover: stop requested from the webGUI'));
exec('/usr/local/sbin/mover stop 2>&1', $output, $code);
$result = trim(implode(' ', $output)) ?: "mover stop returned $code";
exec('logger -t move -- '.escapeshellarg($result));
header('Content-Type: application/json');
echo json_encode(['result' => $result, 'code' => $code]);
?>
