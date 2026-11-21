#!/usr/bin/env php
<?php
/*
* Regression test for the File Manager settings
*
* Copyright 2026 Marc Gutt, Gutt.IT
*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; version 2.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* Tests:
* - archive formats of the compress dialog (include/FileManagerFormats.php): the user selects the formats on the settings page
*   (multi: folders and several files, single: one file), unknown values are ignored and ZIP is the fallback, so the dialog
*   always offers a format for folders
* - speed limit of copy and move (nchan/file_manager): whole MB/s of the settings page are converted to the KiB/s of rsync
* - removing popular destinations (include/PopularDestinations.php)
*/

$file = dirname(__DIR__) . '/emhttp/plugins/dynamix/include/FileManagerFormats.php';
if (!is_file($file)) throw new RuntimeException("Could not find $file");

$GLOBALS['dfm_test_cfg'] = [];
function parse_plugin_cfg($plugin, $sections = false)
{
  return $GLOBALS['dfm_test_cfg'];
}

// the include defines its variables in the scope of the caller
function formats(array $cfg): array
{
  global $file;
  $GLOBALS['dfm_test_cfg'] = $cfg;
  include $file;
  preg_match_all('/<option value="([^"]+)"( class="dfm_single_format")?>/', $dfm_options, $m, PREG_SET_ORDER);
  $offered = [];
  foreach ($m as $option) $offered[$option[1]] = isset($option[2]) && $option[2] !== '';
  return $offered;
}

function check(bool $condition, string $message): void
{
  if (!$condition) throw new RuntimeException($message);
}

function names(array $offered): string
{
  return implode(',', array_keys($offered));
}

$offered = formats(['filemanager' => ['multi' => 'zip,tar.gz,tar.zst', 'single' => 'gz,zst']]);
check(names($offered) === 'zip,tar.gz,tar.zst,gz,zst', 'The default selection must be offered in the order of the format list, got ' . names($offered));
check($offered['gz'] && $offered['zst'], 'The formats for a single file must have the class dfm_single_format.');
check(!$offered['zip'] && !$offered['tar.gz'] && !$offered['tar.zst'], 'The formats for several files must not have the class dfm_single_format.');

$offered = formats(['filemanager' => ['multi' => 'zip,tar,tar.gz,tar.bz2,tar.xz,tar.zst,tar.lz4', 'single' => 'gz,xz,bz2,zst,lz4']]);
check(count($offered) === 12, 'All 12 formats must be offered when all are selected, got ' . count($offered));

$offered = formats(['filemanager' => ['multi' => '', 'single' => 'gz']]);
check(names($offered) === 'zip,gz', 'ZIP must be the fallback if no format for folders is selected, got ' . names($offered));

$offered = formats(['filemanager' => ['multi' => '', 'single' => '']]);
check(names($offered) === 'zip', 'ZIP must be offered if nothing is selected, got ' . names($offered));

$offered = formats([]);
check(names($offered) === 'zip', 'ZIP must be offered if there is no File Manager section, got ' . names($offered));

$offered = formats(['filemanager' => ['multi' => 'tar.zst', 'single' => '']]);
check(names($offered) === 'tar.zst', 'A selected format for folders must not be extended by ZIP, got ' . names($offered));

$offered = formats(['filemanager' => ['multi' => 'zip,"><script>alert(1)</script>,rar', 'single' => '7z,gz']]);
check(names($offered) === 'zip,gz', 'Unknown values must be ignored, got ' . names($offered));

$offered = formats(['filemanager' => ['multi' => ' zip , tar.gz ', 'single' => ' zst ']]);
check(names($offered) === 'zip,tar.gz,zst', 'Spaces around the values must be ignored, got ' . names($offered));

// the statement of file_manager which converts the setting into the option of rsync
$worker = file_get_contents(dirname(__DIR__) . '/emhttp/plugins/dynamix/nchan/file_manager');
if ($worker === false || !preg_match('/^\s*(\$bwlimit = .+;)$/m', $worker, $statement)) throw new RuntimeException('Could not find $bwlimit in file_manager');

function bwlimit(array $filemanager): string
{
  global $statement;
  eval($statement[1]);
  return $bwlimit;
}

check(bwlimit(['bwlimit' => '10']) === '--bwlimit=10240', 'A limit of 10 MB/s must be 10240 KiB/s.');
check(bwlimit(['bwlimit' => '1']) === '--bwlimit=1024', 'A limit of 1 MB/s must be 1024 KiB/s.');
foreach (['', '0', '-5', 'abc'] as $value) {
  check(bwlimit(['bwlimit' => $value]) === '', "The value '$value' must mean no limit.");
}
check(bwlimit([]) === '', 'A missing value must mean no limit.');
check(bwlimit(['bwlimit' => '2.7']) === '--bwlimit=2048', 'A decimal value must be cut to whole MB/s.');

// removing popular destinations (the real functions, but a temporary file instead of /boot/config/filemanager.json)
$destinations_file = tempnam(sys_get_temp_dir(), 'filemanager-json-');
define('POPULAR_DESTINATIONS_FILE', $destinations_file);
// the include defines the constant as well (PHP warns about the second definition) and uses the short tag "<?" which is enabled on Unraid
$popular = preg_replace('/<\?(?!php|=|xml)/i', '<?php', file_get_contents(dirname(__DIR__) . '/emhttp/plugins/dynamix/include/PopularDestinations.php'));
ob_start();
set_error_handler(function () { return true; });
eval('?>' . $popular);
restore_error_handler();
ob_end_clean();

function save_destinations(array $paths, array $more = []): void
{
  $destinations = [];
  foreach ($paths as $i => $path) $destinations[] = ['path' => $path, 'score' => 100 - $i];
  file_put_contents(POPULAR_DESTINATIONS_FILE, json_encode(['destinations' => $destinations] + $more));
}

function saved_paths(): string
{
  $data = json_decode(file_get_contents(POPULAR_DESTINATIONS_FILE), true);
  return implode('|', array_column($data['destinations'], 'path'));
}

try {
  $quote = '/mnt/user/my "share"';
  save_destinations(['/mnt/user/a', '/mnt/user/b c', $quote, '/mnt/disk1/d']);

  removePopularDestinations(['/mnt/user/b c']);
  check(saved_paths() === "/mnt/user/a|$quote|/mnt/disk1/d", 'One destination must be removed and the order kept, got ' . saved_paths());

  removePopularDestinations([$quote, '/mnt/disk1/d']);
  check(saved_paths() === '/mnt/user/a', 'Several destinations must be removed, got ' . saved_paths());

  removePopularDestinations(['/mnt/user/unknown']);
  check(saved_paths() === '/mnt/user/a', 'An unknown destination must not change anything, got ' . saved_paths());

  removePopularDestinations(['/mnt/user']);
  check(saved_paths() === '/mnt/user/a', 'A part of a path must not remove the destination, got ' . saved_paths());

  save_destinations(['/mnt/user/a', '/mnt/user/b'], ['other' => 'value']);
  removePopularDestinations();
  $data = json_decode(file_get_contents(POPULAR_DESTINATIONS_FILE), true);
  check($data['destinations'] === [], 'All destinations must be removed.');
  check(($data['other'] ?? '') === 'value', 'Other data of the file must be kept.');
  check(getPopularDestinations() === [], 'No destination may be offered after all were removed.');

  save_destinations(['/mnt/user/a', '/mnt/user/b', '/mnt/user/c']);
  removePopularDestinations(['/mnt/user/a']);
  check(getPopularDestinations() === ['/mnt/user/b', '/mnt/user/c'], 'The remaining destinations must be offered in the order of their score.');

  unlink(POPULAR_DESTINATIONS_FILE);
  removePopularDestinations();
  check(!file_exists(POPULAR_DESTINATIONS_FILE), 'Removing must not create the file.');
} finally {
  @unlink(POPULAR_DESTINATIONS_FILE);
}

echo "File Manager settings regression test passed.\n";
