<?PHP
/* Copyright 2005-2026, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 */
?>
<?
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'output' => 'Method not allowed']);
  exit;
}

$args = $_POST['args'] ?? [];
if (!is_array($args)) $args = [];

$docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$script = $docroot.'/plugins/dynamix/scripts/mkbootpool';

if (!is_file($script)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'output' => 'mkbootpool script not found']);
  exit;
}

$cmd = escapeshellcmd($script);
if (!empty($args)) {
  $cmd .= ' '.implode(' ', array_map('escapeshellarg', $args));
}

$output = [];
$rc = 0;
exec($cmd.' 2>&1', $output, $rc);

$body = implode("\n", $output);
if ($body === '') $body = 'No output';

echo json_encode([
  'ok' => ($rc === 0),
  'code' => $rc,
  'output' => $body
]);
?>
