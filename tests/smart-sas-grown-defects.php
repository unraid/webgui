#!/usr/bin/env php
<?php
declare(strict_types=1);

function extractFunction(string $source, string $name): string
{
  if (!function_exists('token_get_all')) {
    // Unraid's PHP is built without the tokenizer extension: take the text up to the first closing brace at the start of a line.
    $start = strpos($source, "function $name(");
    $end = $start === false ? false : strpos($source, "\n}\n", $start);
    if ($start === false || $end === false) throw new RuntimeException("Could not extract $name");
    return substr($source, $start, $end + 2 - $start);
  }
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

  if ($code === '') throw new RuntimeException("Could not extract $name");
  return $code;
}

function assertSameValue(string $expected, string $actual, string $message): void
{
  if ($expected !== $actual) {
    throw new RuntimeException(sprintf('%s Expected %s, got %s.', $message, var_export($expected, true), var_export($actual, true)));
  }
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
  if (str_contains($haystack, $needle)) throw new RuntimeException($message);
}

// Helpers the extracted functions call, as defined in webGui/include/Wrappers.php and Helpers.php.
function _var(&$name, $key = null, $default = '')
{
  return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default);
}

function get_value(&$name, $key, $default)
{
  global $var;
  $value = $name[$key] ?? -1;
  return $value !== -1 ? $value : ($var[$key] ?? $default);
}

function get_ctlr_options(&$type, &$disk)
{
}

function my_explode($split, $text, $count = 2)
{
  return array_pad(explode($split, $text ?? '', $count), $count, '');
}

function no_tilde($name)
{
  return $name;
}

if (!function_exists('_')) {
  function _($text)
  {
    return $text;
  }
}

$repo = dirname(__DIR__);
$monitorSource = file_get_contents("$repo/emhttp/plugins/dynamix/scripts/monitor");
$dashboardSource = file_get_contents("$repo/emhttp/plugins/dynamix/nchan/update_2");
if ($monitorSource === false || $dashboardSource === false) throw new RuntimeException('Could not read the monitor and Dashboard sources.');

$root = sys_get_temp_dir() . '/unraid-smart-sas-' . bin2hex(random_bytes(6));
if (!mkdir("$root/smart", 0777, true)) throw new RuntimeException('Could not create the SMART fixture directory.');

// Point both readers at the fixture directory; everything else runs as checked in.
$redirect = static function (string $code, string $path) use ($root): string {
  $code = str_replace($path, '"' . $root . '/smart/$name"', $code, $count);
  if ($count !== 1) throw new RuntimeException("Could not redirect $path to the fixture directory.");
  return $code;
};
eval($redirect(extractFunction($monitorSource, 'check_smart'), '"/var/local/emhttp/smart/$name"'));
eval(extractFunction($dashboardSource, 'normalize'));
eval($redirect(extractFunction($dashboardSource, 'device_smart'), '"state/smart/$name"'));

// The notify tool is replaced by a stub that records the arguments of each call on one line.
$notify = "$root/notify";
$log = "$root/notify.log";
file_put_contents($notify, "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> " . escapeshellarg($log) . "\n");
chmod($notify, 0755);

$server = 'TOWER';
$numbers = '5|187|197|198|199';
$var = [];
$saved = [];

// smartctl -A output of a SAS disk (smartctl 7.5, generic values), with the grown defect count as a parameter.
$sasReport = <<<'REPORT'
smartctl 7.5 2025-04-30 r5714 [x86_64-linux-6.18.58-Unraid] (local build)
Copyright (C) 2002-25, Bruce Allen, Christian Franke, www.smartmontools.org

=== START OF READ SMART DATA SECTION ===
Current Drive Temperature:     32 C
Drive Trip Temperature:        60 C

Accumulated power on time, hours:minutes 10000:00
Manufactured in week 1 of year 2020
Specified cycle count over device lifetime:  50000
Accumulated start-stop cycles:  10
Specified load-unload count over device lifetime:  600000
Accumulated load-unload cycles:  100
Elements in grown defect list: %d

REPORT;

// smartctl -A output of an ATA disk with two pending sectors.
$ataReport = <<<'REPORT'
smartctl 7.5 2025-04-30 r5714 [x86_64-linux-6.18.58-Unraid] (local build)
Copyright (C) 2002-25, Bruce Allen, Christian Franke, www.smartmontools.org

=== START OF READ SMART DATA SECTION ===
SMART Attributes Data Structure revision number: 16
Vendor Specific SMART Attributes with Thresholds:
ID# ATTRIBUTE_NAME          FLAG     VALUE WORST THRESH TYPE      UPDATED  WHEN_FAILED RAW_VALUE
  5 Reallocated_Sector_Ct   0x0033   100   100   010    Pre-fail  Always       -       0
197 Current_Pending_Sector  0x0012   100   100   000    Old_age   Always       -       2

REPORT;

function runMonitor(array $disk, string $text, string $report, array $savedSmart): string
{
  global $root, $log, $saved;
  file_put_contents("$root/smart/{$disk['name']}", $report);
  $saved = $savedSmart ? ['smart' => $savedSmart] : [];
  @unlink($log);
  check_smart($disk, $disk['device'], $text, "{$disk['id']} ({$disk['device']})");
  return is_file($log) ? (string)file_get_contents($log) : '';
}

function runDashboard(array $disk, string $report, array $savedSmart): array
{
  global $root, $saved;
  file_put_contents("$root/smart/{$disk['name']}", $report);
  $saved = $savedSmart ? ['smart' => $savedSmart] : [];
  $fail = 0;
  $smart = 0;
  return [device_smart($disk, $fail, $smart), $smart];
}

$sasDisk = ['name' => 'disk1', 'id' => 'SAS_DISK_TEST', 'device' => 'sdb', 'transport' => 'scsi'];
$ataDisk = ['name' => 'disk2', 'id' => 'ATA_DISK_TEST', 'device' => 'sdc', 'transport' => 'ata'];

try {
  $calls = runMonitor($sasDisk, 'Disk 1', sprintf($sasReport, 3), []);
  assertContainsText('SMART health [5]', $calls, 'A SAS disk with grown defects must raise a SMART warning for attribute 5.');
  assertContainsText('elements in grown defect list is 3', $calls, 'The warning must report the grown defect count.');
  assertContainsText('-i warning', $calls, 'The grown defect warning must have warning severity.');
  assertSameValue('3', (string)($saved['smart']['disk1.5'] ?? ''), 'The grown defect count must be saved for the next run.');

  [$html, $smart] = runDashboard($sasDisk, sprintf($sasReport, 3), []);
  assertContainsText('orange-text', $html, 'The Dashboard must flag a SAS disk with grown defects.');
  assertContainsText('>error<', $html, 'The Dashboard must show the error state for a SAS disk with grown defects.');
  assertContainsText('Elements in grown defect list: 3', $html, 'The Dashboard title must show the grown defect count.');
  assertSameValue('1', (string)$smart, 'The Dashboard SMART counter must count the flagged disk.');

  assertSameValue('', runMonitor($sasDisk, 'Disk 1', sprintf($sasReport, 0), []), 'A SAS disk without grown defects must not raise a notification.');
  [$html, $smart] = runDashboard($sasDisk, sprintf($sasReport, 0), []);
  assertContainsText('green-text', $html, 'The Dashboard must show a SAS disk without grown defects as healthy.');

  $calls = runMonitor($sasDisk, 'Disk 1', sprintf($sasReport, 0), ['disk1.5' => '3']);
  assertContainsText('SMART message [5]', $calls, 'A grown defect count back at 0 must raise the returned-to-normal notice.');
  assertContainsText('returned to normal value', $calls, 'The notice must say the value returned to normal.');
  assertSameValue('', (string)($saved['smart']['disk1.5'] ?? ''), 'The saved grown defect count must be cleared when it returns to 0.');

  $calls = runMonitor($ataDisk, 'Disk 2', $ataReport, []);
  assertContainsText('SMART health [197]', $calls, 'ATA attribute monitoring must be unchanged.');
  assertNotContainsText('SMART health [5]', $calls, 'An ATA disk must not get a grown defect warning.');
  [$html, $smart] = runDashboard($ataDisk, $ataReport, []);
  assertContainsText('Current pending sector: 2', $html, 'The Dashboard must still report ATA attributes.');
  assertNotContainsText('grown defect', $html, 'An ATA disk must not get a grown defect title.');

  assertSameValue('', runMonitor($ataDisk, 'Disk 2', sprintf($sasReport, 3), []), 'A grown defect line in a non-SCSI disk\'s file must be ignored.');
  [$html, $smart] = runDashboard($ataDisk, sprintf($sasReport, 3), []);
  assertContainsText('green-text', $html, 'The Dashboard must ignore a grown defect line in a non-SCSI disk\'s file.');
} finally {
  foreach (glob("$root/smart/*") ?: [] as $file) unlink($file);
  @unlink($notify);
  @unlink($log);
  @rmdir("$root/smart");
  @rmdir($root);
}

echo "SAS grown defect list regression test passed.\n";
