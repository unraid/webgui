#!/usr/bin/env php
<?php
declare(strict_types=1);

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (strpos($haystack, $needle) === false) throw new RuntimeException($message);
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
  if (strpos($haystack, $needle) !== false) throw new RuntimeException($message);
}

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/ProcessStatus.php');
if ($source === false) throw new RuntimeException('Could not read ProcessStatus.php.');

assertContainsText("-v device='.escapeshellarg(\$device)", $source, 'The device must be passed as an awk variable.');
assertContainsText("substr(\$0, length(\$0)-length(device)+1) == device", $source, 'The awk program must compare the device as data.');
assertNotContainsText("{\$_POST['device']}$/{print \$1;exit}", $source, 'The request device must not be interpolated into awk source.');

echo "Process status input regression test passed.\n";
