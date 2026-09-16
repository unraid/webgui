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

function getgastate($res): string
{
  return 'connected';
}

$renderStart = strpos($source, '$ipliststr = $iptablestr = "" ;');
$renderEnd = $renderStart === false ? false : strpos($source, '$changemedia =', $renderStart);
if ($renderStart === false || $renderEnd === false) {
  throw new RuntimeException('Could not find the VM interface rendering block.');
}
$renderSource = substr($source, $renderStart, $renderEnd - $renderStart);
$renderInterfaces = static function (array $interfaces) use ($renderSource): array {
  $res = null;
  $lv = new class($interfaces) {
    public function __construct(private array $interfaces) {}

    public function domain_interface_addresses($res, int $source): array
    {
      return $this->interfaces;
    }
  };
  eval($renderSource);
  return [$ipliststr, $iptablestr];
};

$normalInterface = [
  'name' => 'eth0',
  'hwaddr' => '52:54:00:12:34:56',
  'addrs' => [['addr' => '192.0.2.1', 'prefix' => 24, 'type' => 0]],
];
$encodedPayload = '&lt;img src=x onerror=&quot;alert(&#039;f14&#039;)&quot;&gt;';
foreach (['normal', 'name', 'hwaddr', 'addr', 'prefix', 'all'] as $field) {
  $interface = $normalInterface;
  $expected = ['name' => 'eth0', 'hwaddr' => '52:54:00:12:34:56', 'addr' => '192.0.2.1', 'prefix' => '24'];
  foreach (array_keys($expected) as $key) {
    if ($field !== $key && $field !== 'all') continue;
    if ($key === 'name' || $key === 'hwaddr') {
      $interface[$key] = $payload;
    } else {
      $interface['addrs'][0][$key] = $payload;
    }
    $expected[$key] = $encodedPayload;
  }
  [$row, $summary] = $renderInterfaces([$interface]);
  assertSameValue(
    "<tr><td>{$expected['name']} ({$expected['hwaddr']})</td><td></td><td></td><td>ipv4</td><td>{$expected['addr']}</td><td>{$expected['prefix']}</td></tr>",
    $row,
    "The rendered interface row must encode guest metadata ($field)."
  );
  assertSameValue(
    "{$expected['addr']}/{$expected['prefix']}",
    $summary,
    "The rendered IP summary must encode guest metadata ($field)."
  );
}

echo "VM interface escaping regression test passed.\n";
