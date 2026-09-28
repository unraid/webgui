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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && array_key_exists('getdiagnostics', $_POST)) {
  $anonymize = empty($_POST['anonymize']) ? '-a' : '';
  $diag_file = '/tmp/feedback-diagnostics-'.date('Ymd-Hi').'.zip';
  exec("$docroot/webGui/scripts/diagnostics $anonymize $diag_file");
  echo base64_encode(@file_get_contents($diag_file));
  @unlink($diag_file);
}
?>
