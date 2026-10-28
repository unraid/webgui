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

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/Download.php');
if ($source === false) throw new RuntimeException('Could not read Download.php.');

assertContainsText('function validsource($source)', $source, 'Download sources must pass through an allow-list.');
assertContainsText("realpath(\$candidate)", $source, 'Download sources must be resolved before validation.');
assertContainsText("'/etc/wireguard'", $source, 'WireGuard downloads must remain supported.');
assertContainsText("'/boot/config/wireguard'", $source, 'Persistent WireGuard downloads must remain supported.');
assertContainsText("parse_ini_file('/boot/config/rsyslog.cfg')", $source, 'Syslog server downloads must use the configured folder.');
assertContainsText("syslog-.*\\.log", $source, 'Syslog server downloads must stay within the expected file pattern.');
assertContainsText("preg_match('/^[qlj]+$/', (string)\$opts)", $source, 'Zip options must be constrained to supported flags.');
assertNotContainsText('realpath(dirname("$docroot/$file")) == $docroot', $source, 'Destination validation must not trust a request-supplied path.');
assertNotContainsText("'/mnt/user'", $source, 'Download sources must not allow the entire user share tree.');

echo "Download source validation regression test passed.\n";
