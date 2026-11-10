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

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix.vm.manager/include/libvirt_helpers.php');
if ($source === false) throw new RuntimeException('Could not read libvirt_helpers.php.');

assertContainsText('escapeshellarg($repsrc)', $source, 'VM clone source paths must be shell-escaped.');
assertContainsText('escapeshellarg($reptgt)', $source, 'VM clone target paths must be shell-escaped.');
assertNotContainsText(<<<'SOURCE'
'$repsrc' '$reptgt'
SOURCE, $source, 'VM clone paths must not use literal shell quotes.');

echo "VM clone path escaping regression test passed.\n";
