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

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/update.wireguard.php');
if ($source === false) throw new RuntimeException('Could not read update.wireguard.php.');

assertContainsText("wg-quick '.escapeshellarg(\$state).' '.escapeshellarg(\$vtun)", $source, 'WireGuard state changes must quote both command arguments.');
assertContainsText("--filter name='.escapeshellarg(\$vtun)", $source, 'Docker network lookup must quote the tunnel name.');
assertContainsText("'docker network rm '.escapeshellarg(\$vtun)", $source, 'Docker network removal must quote the tunnel name.');
assertNotContainsText(<<<'SOURCE'
exec("timeout $t1 wg-quick $state $vtun 2>$tmp");
SOURCE, $source, 'Raw WireGuard state arguments are still interpolated.');
assertNotContainsText(<<<'SOURCE'
--filter name='$vtun'
SOURCE, $source, 'Raw tunnel names are still interpolated into Docker filters.');
assertNotContainsText('docker network rm $vtun', $source, 'Raw tunnel names are still interpolated into Docker removal.');

echo "WireGuard argument escaping regression test passed.\n";
