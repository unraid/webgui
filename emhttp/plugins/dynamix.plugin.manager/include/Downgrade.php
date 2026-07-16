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
require_once __DIR__.'/ParityProtectedShrinkGuard.php';

// add translations
$_SERVER['REQUEST_URI'] = 'plugins';
require_once "$docroot/webGui/include/Translations.php";

$protectedShrinkFiles = parity_protected_shrink_files();
$downgradeMarker = '/var/run/unraid-os-downgrade';
$downgradeMarkerHandle = @fopen($downgradeMarker, 'x');

if ($downgradeMarkerHandle === false) {
  http_response_code(409);
  echo _('An Unraid downgrade is already pending.');
  exit;
}

fclose($downgradeMarkerHandle);
chmod($downgradeMarker, 0600);

if (parity_protected_shrink_active($protectedShrinkFiles)) {
  unlink($downgradeMarker);
  http_response_code(409);
  echo _('Finish or recover the active parity-protected disk removal before downgrading Unraid.');
  exit;
}

$tmpdir="/boot/deletemedowngrade.".uniqid();
mkdir($tmpdir);
exec("mv -f /boot/bz* $tmpdir");
exec("mv -f /boot/previous/* /boot");
$version = unscript(_var($_GET,'version'));
file_put_contents("$docroot/plugins/unRAIDServer/README.md","**"._('DOWNGRADE TO VERSION')." $version**");
?>
