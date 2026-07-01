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
/* Boot-device (flash) backup download endpoint.
 *
 *   ?level=0..9   stream a freshly-built zip of the boot device straight to the
 *                 browser as it is built (nothing staged on disk/RAM). The
 *                 browser's own download indicator shows progress.
 *   ?serve=<name> stream an already-saved backup file (the save-to-server
 *                 Download button / notification link). Strictly validated.
 *
 * `X-Accel-Buffering: no` tells nginx to stream the response instead of spooling
 * it to a temp file. nginx requires a logged-in session to reach this endpoint.
 */
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

// "serve" mode: stream an already-saved backup. Strictly validated - only a file
// whose name matches the flash-backup pattern AND that resolves onto a
// pool/array/boot mount - so it can't be coerced into an arbitrary file read.
if (isset($_GET['serve'])) {
  $name = basename($_GET['serve']);
  if (!preg_match('/-boot-backup-[0-9-]+\.zip$/', $name)) { http_response_code(404); exit; }
  $found = null;
  foreach (array_merge(glob("/mnt/*/$name"), glob("/mnt/user/*/$name"), ["/boot/$name"]) as $cand) {
    $rp = realpath($cand);
    if ($rp && is_file($rp) && basename($rp) === $name &&
        (strncmp($rp, '/mnt/', 5) === 0 || strncmp($rp, '/boot/', 6) === 0)) { $found = $rp; break; }
  }
  if (!$found) { http_response_code(404); exit; }
  set_time_limit(0);
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="'.$name.'"');
  header('Content-Length: '.filesize($found));
  header('X-Accel-Buffering: no');
  while (ob_get_level()) ob_end_clean();
  readfile($found);
  exit;
}

// Stream a freshly-built backup. Named server-version-date.zip.
$var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
$level = isset($_GET['level']) && is_numeric($_GET['level']) ? max(0, min(9, (int)$_GET['level'])) : 6;
$server = isset($var['NAME']) ? str_replace(' ', '_', strtolower($var['NAME'])) : 'tower';
$osVersion = $var['version'] ?? 'unknown';
$name = "$server-v$osVersion-boot-backup-".date('Ymd-Hi').".zip";

set_time_limit(0);
ignore_user_abort(false); // let zip die (SIGPIPE) if the user cancels the download

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('X-Accel-Buffering: no');
header('Cache-Control: no-store');
while (ob_get_level()) ob_end_clean();

passthru(escapeshellarg("$docroot/webGui/scripts/flash_backup").' '.(int)$level.' 2>/dev/null');
exit;
?>
