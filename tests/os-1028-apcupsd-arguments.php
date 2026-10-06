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

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix.apcupsd/include/update.apcupsd.php');
if ($source === false) throw new RuntimeException('Could not read update.apcupsd.php.');

assertContainsText("escapeshellarg(\$expression)", $source, 'APC UPS replacement expressions must be quoted as one shell argument.');
assertContainsText("escapeshellarg(\$conf)", $source, 'The APC UPS configuration path must be quoted.');
assertContainsText("preg_replace('/[\\r\\n]/'", $source, 'APC UPS values must stay on one configuration line.');
assertContainsText("apcupsd_set('DEVICE', \$new['DEVICE'], \$conf);", $source, 'The device value must use the safe replacement helper.');
assertNotContainsText("str_replace(\"'\",\"\\\\'\",\$new['DEVICE'])", $source, 'The old shell-quote workaround must not be used.');
assertNotContainsText("sed -i -e '/^DEVICE/c", $source, 'The device value must not be interpolated into a shell-quoted sed command.');

echo "APC UPS argument escaping regression test passed.\n";
