<?PHP
/* Copyright 2005-2023, Lime Technology
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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/Secure.php";

// add translations
$_SERVER['REQUEST_URI'] = 'plugins';
require_once "$docroot/webGui/include/Translations.php";

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  die(_('POST required'));
}

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
header('Content-Type: application/json');

// Issue a short-lived, single-use confirmation nonce before changing boot files.
if ((_var($_POST,'action') ?: '') === 'prepare') {
  $nonce = bin2hex(random_bytes(32));
  $_SESSION['downgrade_nonce'] = $nonce;
  $_SESSION['downgrade_nonce_expires'] = time() + 300;
  die(json_encode(['success' => true, 'nonce' => $nonce]));
}

$nonce = (string)_var($_POST,'nonce');
$expected = (string)($_SESSION['downgrade_nonce'] ?? '');
$expires = (int)($_SESSION['downgrade_nonce_expires'] ?? 0);
unset($_SESSION['downgrade_nonce'], $_SESSION['downgrade_nonce_expires']);
if (!$nonce || !$expected || $expires < time() || !hash_equals($expected,$nonce) || _var($_POST,'confirm') !== 'yes') {
  http_response_code(400);
  die(json_encode(['error' => _('Confirmation required')]));
}

$version = unscript(_var($_POST,'version'));
if (!preg_match('/\\A[0-9]+\\.[0-9]+\\.[0-9]+(?:[-.][A-Za-z0-9]+)?\\z/D',$version)) {
  http_response_code(400);
  die(json_encode(['error' => _('Invalid version')]));
}

$bootDir = realpath('/boot');
$previousDir = realpath('/boot/previous');
if ($bootDir !== '/boot' || $previousDir !== '/boot/previous' || !is_dir($previousDir)) {
  http_response_code(409);
  die(json_encode(['error' => _('Previous boot set is unavailable')]));
}

$previousEntries = [];
foreach (scandir($previousDir) ?: [] as $entry) {
  if ($entry === '.' || $entry === '..' || !preg_match('/\\Abz[A-Za-z0-9._-]*\\z/D',$entry)) continue;
  $source = "$previousDir/$entry";
  if (is_link($source) || !is_file($source)) {
    http_response_code(409);
    die(json_encode(['error' => _('Previous boot set contains an invalid entry')]));
  }
  $previousEntries[$entry] = $source;
}
if (!isset($previousEntries['bzimage'],$previousEntries['bzroot'])) {
  http_response_code(409);
  die(json_encode(['error' => _('Previous boot set is incomplete')]));
}

$currentEntries = [];
foreach (scandir($bootDir) ?: [] as $entry) {
  if ($entry === '.' || $entry === '..' || !preg_match('/\\Abz[A-Za-z0-9._-]*\\z/D',$entry)) continue;
  $source = "$bootDir/$entry";
  if (is_link($source) || !is_file($source)) continue;
  $currentEntries[$entry] = $source;
}

$tmpdir = "$bootDir/deletemedowngrade.".bin2hex(random_bytes(16));
if (!@mkdir($tmpdir,0700)) {
  http_response_code(500);
  die(json_encode(['error' => _('Unable to prepare boot switch')]));
}

$movedCurrent = [];
$movedPrevious = [];
try {
  foreach ($currentEntries as $entry => $source) {
    $target = "$tmpdir/$entry";
    if (!@rename($source,$target)) throw new RuntimeException('current');
    $movedCurrent[$entry] = $target;
  }
  foreach ($previousEntries as $entry => $source) {
    $target = "$bootDir/$entry";
    if (!@rename($source,$target)) throw new RuntimeException('previous');
    $movedPrevious[$entry] = $target;
  }
} catch (Throwable $error) {
  $restoreFailed = false;
  foreach ($movedPrevious as $entry => $source) if (!@rename($source,"$previousDir/$entry")) $restoreFailed = true;
  foreach ($movedCurrent as $entry => $source) if (!@rename($source,"$bootDir/$entry")) $restoreFailed = true;
  http_response_code(500);
  if ($restoreFailed) die(json_encode(['error' => _('Boot switch failed and restore was incomplete'), 'backup' => $tmpdir]));
  @rmdir($tmpdir);
  die(json_encode(['error' => _('Boot switch failed; previous files were restored')]));
}

file_put_contents("$docroot/plugins/unRAIDServer/README.md","**"._('DOWNGRADE TO VERSION')." $version**");
die(json_encode(['success' => true, 'version' => $version]));
?>
