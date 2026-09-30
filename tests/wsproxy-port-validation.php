#!/usr/bin/env php
<?php
declare(strict_types=1);

function assertSameValues(array $expected, array $actual, string $message): void
{
  sort($expected);
  sort($actual);
  if ($expected !== $actual) {
    throw new RuntimeException(
      $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
    );
  }
}

function loadFunction(string $source, string $functionName): void
{
  $functionStart = strpos($source, "function $functionName");
  $bodyStart = $functionStart === false ? false : strpos($source, '{', $functionStart);
  if ($functionStart === false || $bodyStart === false) {
    throw new RuntimeException("Could not find $functionName().");
  }

  $depth = 0;
  $quote = null;
  $escaped = false;
  $functionEnd = false;
  for ($i = $bodyStart; $i < strlen($source); $i++) {
    $character = $source[$i];
    if ($quote !== null) {
      if ($escaped) {
        $escaped = false;
      } elseif ($character === '\\') {
        $escaped = true;
      } elseif ($character === $quote) {
        $quote = null;
      }
      continue;
    }
    if ($character === "'" || $character === '"') {
      $quote = $character;
    } elseif ($character === '{') {
      $depth++;
    } elseif ($character === '}') {
      $depth--;
      if ($depth === 0) {
        $functionEnd = $i;
        break;
      }
    }
  }

  if ($functionEnd === false) {
    throw new RuntimeException("Could not parse $functionName().");
  }
  eval(substr($source, $functionStart, $functionEnd - $functionStart + 1));
}

$source = file_get_contents(dirname(__DIR__) . '/emhttp/auth-request.php');
if ($source === false) {
  throw new RuntimeException('Could not read auth-request.php.');
}
loadFunction($source, 'wsproxy_ports_from_xml');

$xml = <<<'XML'
<domain>
  <devices>
    <graphics type='vnc' port='5900' websocket='5700'/>
    <graphics type='spice' port='5901' websocket='5701'/>
    <graphics type='spice' port='5999' websocket='5702'/>
    <graphics type='spice' port='6000' websocket='5703'/>
    <graphics type='sdl' port='65534'/>
    <graphics type='vnc' port='5902' websocket='65534'/>
    <graphics type='vnc' port='-1' websocket='-1'/>
  </devices>
</domain>
XML;

assertSameValues(
  [5700, 5901, 5999],
  wsproxy_ports_from_xml($xml),
  'Only the VNC websocket and SPICE graphics ports should be proxy targets.'
);

echo "WebSocket proxy port validation test passed.\n";
