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
// Serve the web app manifest with the server name prefixed to the app name,
// so that app shortcuts of multiple servers can be told apart (e.g. "Tower Unraid").
// The static manifest.json remains the single source for all other fields.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$manifest = json_decode(@file_get_contents("$docroot/manifest.json") ?: '', true);
if (!is_array($manifest)) $manifest = ['name' => 'Unraid', 'short_name' => 'Unraid'];

$var = @parse_ini_file("$docroot/state/var.ini") ?: [];
$server = trim($var['NAME'] ?? '');
if ($server !== '') {
  foreach (['name', 'short_name'] as $key) {
    $manifest[$key] = $server.' '.($manifest[$key] ?? 'Unraid');
  }
}

header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache');
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
