#!/usr/bin/env php
<?php
declare(strict_types=1);

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
  if ($expected !== $actual) throw new RuntimeException($message);
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
  if (strpos($haystack, $needle) !== false) throw new RuntimeException($message);
}

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix.vm.manager/include/libvirt_helpers.php');
if ($source === false) throw new RuntimeException('Could not read libvirt_helpers.php.');

assertSameValue(5, substr_count($source, 'qemu-img info --backing-chain -U ".escapeshellarg($file)."'), 'Every snapshot qemu-img path must be shell-escaped.');
assertNotContainsText(<<<'SOURCE'
qemu-img info --backing-chain -U '$file'
SOURCE, $source, 'Snapshot paths must not use literal single quotes.');
assertNotContainsText(<<<'SOURCE'
qemu-img info --backing-chain -U "$file"
SOURCE, $source, 'Snapshot paths must not use literal double quotes.');

echo "VM snapshot path escaping regression test passed.\n";
