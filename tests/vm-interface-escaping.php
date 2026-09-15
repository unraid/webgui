#!/usr/bin/env php
<?php
declare(strict_types=1);

function assertSameValue(string $expected, string $actual, string $message): void
{
  if ($expected !== $actual) {
    throw new RuntimeException("$message Expected '$expected', got '$actual'.");
  }
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

$repo = dirname(__DIR__);
$source = file_get_contents("$repo/emhttp/plugins/dynamix.vm.manager/include/VMMachines.php");
if ($source === false) throw new RuntimeException('Could not read VMMachines.php.');

$functionStart = strpos($source, 'function vm_manager_escape_html');
$functionBodyStart = $functionStart === false ? false : strpos($source, '{', $functionStart);
$functionEnd = $functionBodyStart === false ? false : strpos($source, '}', $functionBodyStart);
if ($functionStart === false || $functionBodyStart === false || $functionEnd === false) {
  throw new RuntimeException('Could not find vm_manager_escape_html().');
}
eval(substr($source, $functionStart, $functionEnd - $functionStart + 1));

$payload = '<img src=x onerror="alert(\'f14\')">';
assertSameValue(
  '&lt;img src=x onerror=&quot;alert(&#039;f14&#039;)&quot;&gt;',
  vm_manager_escape_html($payload),
  'Guest-controlled markup must be encoded as HTML text.'
);

$invalidUtf8 = "interface\xFFname";
if (str_contains(vm_manager_escape_html($invalidUtf8), "\xFF")) {
  throw new RuntimeException('Invalid UTF-8 must not pass through the HTML encoder.');
}

assertContainsText(
  '$ipnamemac = vm_manager_escape_html($ipnamemac);',
  $source,
  'The interface name and hardware address must use the HTML encoder.'
);
assertContainsText(
  '$ipaddrval = vm_manager_escape_html($ipaddr);',
  $source,
  'The guest-reported address must use the HTML encoder.'
);
assertContainsText(
  '$ipprefix = vm_manager_escape_html((string)$arraddr["prefix"]);',
  $source,
  'The guest-reported prefix must use the HTML encoder.'
);

echo "VM interface escaping regression test passed.\n";
