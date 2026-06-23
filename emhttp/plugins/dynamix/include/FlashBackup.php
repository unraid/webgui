<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2023, Bergware International.
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
/* Stream a boot-device (flash) backup straight to the browser as it is built,
 * so nothing is ever staged on disk or in RAM. The heavy lifting is done by
 * webGui/scripts/flash_backup, which writes the zip to stdout; we just set the
 * download headers and pass it through.
 *
 * Query params:
 *   level     zip compression level 0..9 (default 6)
 *   download  opaque token echoed back as a cookie once the stream starts, so
 *             the GUI can tell the download began and clear its status spinner.
 */
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

$var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
$level = isset($_GET['level']) && is_numeric($_GET['level']) ? max(0, min(9, (int)$_GET['level'])) : 6;

$server = isset($var['NAME']) ? str_replace(' ', '_', strtolower($var['NAME'])) : 'tower';
$osVersion = $var['version'] ?? 'unknown';
$name = "$server-v$osVersion-boot-backup-".date('Ymd-Hi').".zip";

// Echo the download token back as a (non-HttpOnly) cookie so the page can detect
// the download has started. Numeric-only to keep it harmless. Must be set before
// any body output.
$token = preg_replace('/\D/', '', $_GET['download'] ?? '');
if ($token !== '') setcookie('flashBackup', $token, ['path' => '/']);

// Stream for as long as it takes, and stop zip if the user cancels the download.
set_time_limit(0);
ignore_user_abort(false);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('X-Accel-Buffering: no'); // tell nginx to stream, not spool to a temp file
header('Cache-Control: no-store');
while (ob_get_level()) ob_end_clean();

passthru(escapeshellarg("$docroot/webGui/scripts/flash_backup")." ".(int)$level);
exit;
?>
