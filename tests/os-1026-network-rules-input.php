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

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/update.rules.php');
if ($source === false) throw new RuntimeException('Could not read update.rules.php.');

assertContainsText("\$cfg = '/boot/config/network-rules.cfg';", $source, 'Network rules must use the fixed configuration path.');
assertContainsText("preg_match('/^eth\\d+$/", $source, 'Interface names must be constrained to ethN.');
assertContainsText("escapeshellarg(\$mac)", $source, 'MAC addresses must be quoted before grep.');
assertContainsText("escapeshellarg(\$expression)", $source, 'The sed expression must be quoted as one argument.');
assertNotContainsText("\$cfg = \$_POST['#cfg']", $source, 'The configuration path must not come from the request.');
assertNotContainsText("grep -n '\$mac'", $source, 'MAC addresses must not be interpolated into grep source text.');

echo "Network rules input validation regression test passed.\n";
