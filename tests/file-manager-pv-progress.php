#!/usr/bin/env php
<?php
/*
* Regression test for nchan/file_manager -> parse_pv_progress()
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
* - pv lines with an almost identical timestamp must not result in absurd speed values
* - speed and ETA are only shown when the rolling window spans enough time
* - single-file operations show the file name
*/

function extractFunction(string $source, string $name): string
{
  $tokens = token_get_all(preg_replace('/<\?(?!php|=|xml)/i', '<?php', $source) ?? $source);
  $capturing = false;
  $braces = 0;
  $inDoubleQuote = false;
  $code = '';
  $tokenCount = count($tokens);

  for ($i = 0; $i < $tokenCount; $i++) {
    $token = $tokens[$i];
    if (!$capturing && is_array($token) && $token[0] === T_FUNCTION) {
      $j = $i + 1;
      while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
      if ($j >= $tokenCount || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) continue;
      $capturing = true;
    }

    if (!$capturing) continue;

    $code .= is_array($token) ? $token[1] : $token;
    if (is_string($token)) {
      if ($token === '"') {
        $inDoubleQuote = !$inDoubleQuote;
      } elseif (!$inDoubleQuote && $token === '{') {
        $braces++;
      } elseif (!$inDoubleQuote && $token === '}' && --$braces === 0) {
        break;
      }
    }
  }

  if ($code === '') throw new RuntimeException("Could not extract $name");
  return $code;
}

function check(bool $condition, string $message): void
{
  if (!$condition) throw new RuntimeException($message);
}

// helpers which are provided by the WebGUI or PHP extensions in production
if (!function_exists('mb_strlen')) {
  function mb_strlen($s) { return strlen($s); }
}
if (!function_exists('mb_substr')) {
  function mb_substr($s, $start, $length = null) { return $length === null ? substr($s, $start) : substr($s, $start, $length); }
}
if (!function_exists('my_number')) {
  function my_number($value, $decimals = 0) { return (string)round($value, $decimals); }
}
if (!function_exists('_')) {
  function _($text) { return $text; }
}

$source = file_get_contents(dirname(__DIR__) . '/emhttp/plugins/dynamix/nchan/file_manager');
if ($source === false) throw new RuntimeException('Could not read the File Manager source.');

foreach (['PV_MIN_EVENT_GAP_SECONDS', 'PV_MIN_SPEED_WINDOW_SECONDS'] as $constant) {
  if (!preg_match("/^define\('$constant', [\d.]+\);/m", $source, $m)) throw new RuntimeException("Could not find $constant");
  eval($m[0]);
}
define('CURRENT_FILE', tempnam(sys_get_temp_dir(), 'pv-current-file-'));
foreach (['mb_strimhalf', 'seconds_to_time', 'bytes_to_size', 'parse_pv_progress'] as $function) {
  eval(extractFunction($source, $function));
}

$status = tempnam(sys_get_temp_dir(), 'pv-progress-');

function feed(string $status, array $lines, int $total, ?string $single = null): array
{
  file_put_contents($status, implode("\n", $lines) . "\n", FILE_APPEND);
  return parse_pv_progress($status, $total, 'Compressing', false, $single);
}

function reset_progress(string $status): void
{
  parse_pv_progress(null, 0, null, true);
  file_put_contents($status, '');
  @unlink(CURRENT_FILE);
}

function speed_of(array $text): ?float
{
  if (!preg_match('/Speed: ([\d.]+)(B|KB|MB|GB)\/s/', $text[1] ?? '', $m)) return null;
  return (float)$m[1] * ['B' => 1, 'KB' => 1024, 'MB' => 1024 ** 2, 'GB' => 1024 ** 3][$m[2]];
}

function eta_of(array $text): ?int
{
  if (!preg_match('/ETA: (\d+):(\d\d):(\d\d)/', $text[1] ?? '', $m)) return null;
  return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
}

try {
  $total = 3000000000;

  // pv output of "tar | pv | xz" (taken from a real run): the lines come in pairs with an almost identical timestamp
  reset_progress($status);
  $text = feed($status, ['3.1263 151132160', '3.1264 151197696'], $total);
  check(speed_of($text) === null, 'No speed may be shown for the first pair of lines (time span ~0).');
  check(str_contains($text[1], 'ETA: N/A'), 'The ETA must be N/A while the window is too short.');
  check(str_contains($text[1], 'Completed: 5%'), 'The percent must be shown even without speed.');

  $text = feed($status, ['4.6710 276963328'], $total);
  check(speed_of($text) === null, 'No speed may be shown for a window of 1.5 seconds.');

  $text = feed($status, ['6.8701 302069760', '6.8702 302110720'], $total);
  $speed = speed_of($text);
  check($speed !== null, 'Speed must be shown once the window spans at least 2 seconds.');
  check($speed > 30e6 && $speed < 50e6, sprintf('Speed must reflect the real rate (~40 MB/s), got %.1f MB/s.', $speed / 1e6));
  check((eta_of($text) ?? 0) > 60, 'ETA must be based on the real rate (more than a minute).');

  // regular pv output: speed appears with the third line (window of 2 seconds)
  reset_progress($status);
  $text = feed($status, ['1.0000 10485760', '2.0000 20971520'], $total);
  check(speed_of($text) === null, 'No speed may be shown for a window of 1 second.');
  $text = feed($status, ['3.0000 31457280'], $total);
  check(abs(speed_of($text) - 10485760) < 1024, 'Speed must be 10 MB/s for 10 MB per second.');

  // lines which are closer than PV_MIN_EVENT_GAP_SECONDS are merged
  reset_progress($status);
  feed($status, ['1.0000 10485760', '1.0001 10590000', '2.0000 20971520', '3.0000 31457280'], $total);
  $text = feed($status, ['4.0000 41943040'], $total);
  $speed = speed_of($text);
  check($speed !== null && $speed < 12e6, 'Nearly identical timestamps must not result in a huge speed.');

  // single-file operations show the file name, tar/zip operations show the current file from CURRENT_FILE
  reset_progress($status);
  $text = feed($status, ['1.0000 10485760'], $total, 'big.bin');
  check($text[0] === 'Compressing... big.bin', 'The single-file name must be shown, got "' . $text[0] . '".');

  reset_progress($status);
  file_put_contents(CURRENT_FILE, "./files/a.bin\n./files/b.bin\n");
  $text = feed($status, ['1.0000 10485760'], $total, 'ignored.bin');
  check($text[0] === 'Compressing... files/b.bin', 'The current tar file must be shown, got "' . $text[0] . '".');

  // pvzip writes the name without a trailing newline
  reset_progress($status);
  file_put_contents(CURRENT_FILE, 'files/c.bin');
  $text = feed($status, ['1.0000 10485760'], $total);
  check($text[0] === 'Compressing... files/c.bin', 'The name without newline must be shown, got "' . $text[0] . '".');

  // nothing to show before pv wrote its first line
  reset_progress($status);
  check(parse_pv_progress($status, $total, 'Compressing', false, 'big.bin') === [], 'No text may be returned before pv wrote a line.');
} finally {
  @unlink($status);
  @unlink(CURRENT_FILE);
}

echo "File Manager pv progress regression test passed.\n";
