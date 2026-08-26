#!/usr/bin/env php
<?php
declare(strict_types=1);

function extractFunction(string $source, string $name): string
{
  $source = preg_replace('/<\?(?!php|=|xml)/i', '<?php', $source) ?? $source;
  $tokens = token_get_all($source);
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
      if ($j < $tokenCount && $tokens[$j] === '&') $j++;
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

  if ($code === '') {
    throw new RuntimeException("Could not extract $name");
  }

  return $code;
}

function _var(&$name, $key = null, $default = '')
{
  return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default);
}

function usage_color(&$disk, $limit, $free)
{
  return '';
}

function my_scale($value, &$unit, $decimals = null)
{
  $unit = 'GB';
  return (string)$value;
}

function vfs_type(&$disk, $online = false)
{
  return 'xfs';
}

function effective_fs_size(&$disk)
{
  return _var($disk, 'fsSize', 0);
}

$repo = dirname(__DIR__);
$myUsageFunction = extractFunction(file_get_contents("$repo/emhttp/plugins/dynamix/include/Helpers.php"), 'my_usage');
eval($myUsageFunction);
$fsInfoFunction = extractFunction(file_get_contents("$repo/emhttp/plugins/dynamix/nchan/device_list"), 'fs_info');
eval($fsInfoFunction);

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
  if (str_contains($haystack, $needle)) throw new RuntimeException($message);
}

$display = ['text' => 2, 'critical' => 0, 'warning' => 0];
$disks = [
  ['name' => 'disk1', 'sizeSb' => 100, 'fsFree' => 50],
];
$var = ['fsState' => 'Stopped', 'fsNumMounted' => 1];

ob_start();
my_usage();
$navigationUsage = ob_get_clean();
assertContainsText('offline', $navigationUsage, 'Stopped arrays must show offline in navigation.');
assertNotContainsText('%', $navigationUsage, 'Stopped arrays must not show a navigation usage percentage.');

$var['fsState'] = 'Started';
ob_start();
my_usage();
$navigationUsage = ob_get_clean();
assertContainsText('50%', $navigationUsage, 'Started arrays must keep showing the usage percentage.');

$disk = [
  'fsStatus' => 'Mounted',
  'fsType' => 'xfs',
  'fsSize' => 100,
  'fsUsed' => 25,
  'fsFree' => 75,
];
$var['fsState'] = 'Stopped';
$diskInfo = fs_info($disk, true);
assertContainsText('Mounted', $diskInfo, 'Stopped filesystems must keep showing their status.');
assertNotContainsText('usage-disk', $diskInfo, 'Stopped filesystems must not show a usage bar.');

$var['fsState'] = 'Started';
$diskInfo = fs_info($disk, true);
assertContainsText('usage-disk', $diskInfo, 'Started filesystems must keep showing a usage bar.');

echo "Stopped array utilization regression test passed.\n";
