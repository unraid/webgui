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

// The Main array sections must send their commands through the context, not to the default endpoint.
$page = file_get_contents("$repo/emhttp/plugins/dynamix/ArrayOperation.page");
if ($page === false) throw new RuntimeException('Could not read ArrayOperation.page.');
if (str_contains($page, 'action="/update.htm"')) {
  throw new RuntimeException('ArrayOperation.page must post to $array_ctx[\'update\'], not /update.htm.');
}

// An additional array's page reads that array's own emhttp state, under the names of the host state.
$functionStart = strpos($source, 'function array_context_vars');
$functionEnd = $functionStart === false ? false : strpos($source, "\n}\n", $functionStart);
if ($functionStart === false || $functionEnd === false) {
  throw new RuntimeException('Could not find array_context_vars().');
}
function _var(&$name, $key=null, $default='') { return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default); }
function pools_filter($disks) { return array_keys(array_filter($disks, fn($disk) => ($disk['type'] ?? '') === 'Cache')); }
function flash_filter($disks) { return array_keys(array_filter($disks, fn($disk) => ($disk['type'] ?? '') === 'Flash')); }
eval(substr($source, $functionStart, $functionEnd - $functionStart + 2));

$state = sys_get_temp_dir().'/array-context-'.getmypid();
if (!mkdir($state)) throw new RuntimeException("Could not create $state.");
file_put_contents("$state/var.ini", "mdState=\"STARTED\"\nfsState=\"Started\"\n");
file_put_contents("$state/disks.ini", "[parity]\ntype=\"Parity\"\n[disk1]\ntype=\"Data\"\n[cache]\ntype=\"Cache\"\ndevices=\"1\"\n");
$vars = array_context_vars(['state' => $state] + $second);
array_map('unlink', glob("$state/*.ini"));
rmdir($state);
$keys = array_keys($vars);
sort($keys);
assertSameValue(['bootpool_devices','bootpools','devs','disks','pool_devices','pools','sec','sec_nfs','shares','users','var'], $keys, 'array_context_vars() must return the page globals of template.php.');
assertSameValue('Started', $vars['var']['fsState'], 'array_context_vars() must read var.ini from the array\'s state dir.');
assertSameValue(['parity','disk1','cache'], array_keys($vars['disks']), 'array_context_vars() must read disks.ini from the array\'s state dir.');
assertSameValue([['cache'], 1], [$vars['pools'], $vars['pool_devices']], 'array_context_vars() must derive the pools from the array\'s own disks.');

// Both Main array sections take the array's state only for an additional array, and give the host state back at the end.
$take = "if (\$array_ctx['id'] > 1) {\$array_vars = array_context_vars(\$array_ctx); \$array_host = compact(array_keys(\$array_vars)); extract(\$array_vars);}";
$give = "<?if (\$array_ctx['id'] > 1) extract(\$array_host);?>";
$devicesPage = file_get_contents("$repo/emhttp/plugins/dynamix/ArrayDevices.page");
if ($devicesPage === false) throw new RuntimeException('Could not read ArrayDevices.page.');
foreach (['ArrayDevices.page' => $devicesPage, 'ArrayOperation.page' => $page] as $file => $text) {
  assertSameValue(1, substr_count($text, 'array_context_vars('), "$file must call array_context_vars() exactly once.");
  if (!str_contains($text, $take)) throw new RuntimeException("$file must take the array state only when its id is above 1.");
  if (!str_ends_with(rtrim($text), $give)) throw new RuntimeException("$file must give the host state back as its last line.");
}

// Reboot/Shutdown is host-wide: shown with the default array only.
if (!preg_match('#<\?if \(\$array_ctx\[\'id\'\] == 1\):\?>\n<form name="shutdownOps"[^<]*(?:<(?!/form>)[^<]*)*</form>\n<\?endif;\?>\n#', $page)) {
  throw new RuntimeException('ArrayOperation.page must show the shutdownOps form only for the default array.');
}

echo "Array context regression test passed.\n";
