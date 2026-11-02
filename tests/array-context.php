#!/usr/bin/env php
<?php
declare(strict_types=1);

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
  if ($expected !== $actual) {
    throw new RuntimeException("$message Expected ".var_export($expected, true).", got ".var_export($actual, true).".");
  }
}

$repo = dirname(__DIR__);
$source = file_get_contents("$repo/emhttp/plugins/dynamix/include/Helpers.php");
if ($source === false) throw new RuntimeException('Could not read Helpers.php.');

$functionStart = strpos($source, 'function array_context');
$functionEnd = $functionStart === false ? false : strpos($source, "\n}\n", $functionStart);
if ($functionStart === false || $functionEnd === false) {
  throw new RuntimeException('Could not find array_context().');
}
eval(substr($source, $functionStart, $functionEnd - $functionStart + 2));

// The default array keeps exactly the paths, channel names and endpoints used before.
$default = [
  'id'     => 1,
  'state'  => '/var/local/emhttp',
  'tmp'    => '/var/tmp',
  'config' => '/boot/config',
  'suffix' => '',
  'update' => '/update.htm',
  'parity' => '/webGui/include/ParityControl.php',
  'toggle' => '/webGui/include/ToggleState.php',
];
assertSameValue($default, array_context(), 'No argument must select the default array.');
foreach ([1, '1', 0, '', null, '0', -1] as $id) {
  assertSameValue($default, array_context($id), 'Id '.var_export($id, true).' must select the default array.');
}

// nchan scripts derive the id from their own name: the stock names select the default array.
$scriptId = fn(string $path) => (int)preg_replace('/^\D*/', '', basename($path));
assertSameValue(0, $scriptId('/usr/local/emhttp/webGui/nchan/device_list'), 'device_list must select the default array.');
assertSameValue(0, $scriptId('/usr/local/emhttp/webGui/nchan/parity_list'), 'parity_list must select the default array.');
assertSameValue(2, $scriptId('/usr/local/emhttp/plugins/example/nchan/device_list2'), 'device_list2 must select array 2.');

// An additional array gets its own paths and channel suffix.
$second = array_context(2);
assertSameValue(2, $second['id'], 'Array 2 must report its id.');
assertSameValue('2', $second['suffix'], 'Array 2 must suffix its channels.');
foreach (['state', 'tmp', 'config'] as $key) {
  if ($second[$key] === $default[$key]) throw new RuntimeException("Array 2 must not share the $key path of the default array.");
}
foreach (['update', 'parity', 'toggle'] as $key) {
  if ($second[$key] === $default[$key]) throw new RuntimeException("Array 2 must not send commands to the default array ($key).");
}

echo "Array context regression test passed.\n";
