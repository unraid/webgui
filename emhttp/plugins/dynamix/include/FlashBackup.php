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
 * so nothing is ever staged on disk or in RAM. The zip is produced by
 * webGui/scripts/flash_backup (writing to stdout); we pipe it to the client and,
 * as bytes flow, publish a percentage to the 'flash_backup' nchan channel so the
 * GUI can show a real progress bar. The percentage is bytes-sent over the total
 * flash size reported by `flash_backup size` (the boot device is mostly already-
 * compressed OS files, so output closely tracks input - the bar is accurate in
 * practice and we cap it at 99% until the stream actually finishes).
 *
 * Query params:
 *   level   zip compression level 0..9 (default 6)
 *   run     opaque per-run token; progress is published as "<run>:<pct>" so the
 *           GUI can ignore stale messages left in the channel by a previous run
 */
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/publish.php";

$channel = 'flash_backup';
$script = "$docroot/webGui/scripts/flash_backup";

$var = (array)@parse_ini_file('/var/local/emhttp/var.ini');
$level = isset($_GET['level']) && is_numeric($_GET['level']) ? max(0, min(9, (int)$_GET['level'])) : 6;
$run = preg_replace('/\D/', '', $_GET['run'] ?? '');

$server = isset($var['NAME']) ? str_replace(' ', '_', strtolower($var['NAME'])) : 'tower';
$osVersion = $var['version'] ?? 'unknown';
$name = "$server-v$osVersion-boot-backup-".date('Ymd-Hi').".zip";

// Total size of the included set, used as the progress denominator.
$total = (int)trim(shell_exec(escapeshellarg($script).' size 2>/dev/null'));

// Stream for as long as it takes, and let zip die if the user cancels.
set_time_limit(0);
ignore_user_abort(false);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('X-Accel-Buffering: no'); // tell nginx to stream, not spool to a temp file
header('Cache-Control: no-store');
while (ob_get_level()) ob_end_clean();

$h = popen(escapeshellarg($script).' '.(int)$level.' 2>/dev/null', 'r');
if ($h) {
  $sent = 0; $last = 0;
  while (!feof($h)) {
    $buf = fread($h, 1 << 18);
    if ($buf === '' || $buf === false) break;
    echo $buf;
    flush();
    $sent += strlen($buf);
    $now = microtime(true);
    if ($total > 0 && $now - $last > 0.4) {
      publish($channel, $run.':'.min(99, intdiv($sent * 100, $total)), 1, false);
      $last = $now;
    }
  }
  pclose($h);
}
publish($channel, $run.':_DONE_', 1, false);
exit;
?>
