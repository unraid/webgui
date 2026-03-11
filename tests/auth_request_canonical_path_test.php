<?php

function fail(string $message): void {
  fwrite(STDERR, "FAIL: $message\n");
  exit(1);
}

function pass(string $message): void {
  fwrite(STDOUT, "PASS: $message\n");
}

function assertSame($expected, $actual, string $message): void {
  if ($expected !== $actual) {
    fail($message . " (expected: " . var_export($expected, true) . ", actual: " . var_export($actual, true) . ")");
  }
  pass($message);
}

function rrmdir(string $path): void {
  if (!is_dir($path)) {
    return;
  }

  $items = scandir($path);
  if (!is_array($items)) {
    return;
  }

  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }

    $itemPath = $path . '/' . $item;
    if (is_dir($itemPath) && !is_link($itemPath)) {
      rrmdir($itemPath);
      continue;
    }
    @unlink($itemPath);
  }

  @rmdir($path);
}

function loadAuthRequestHelpers(string $authRequestFile): void {
  $source = file_get_contents($authRequestFile);
  if (!is_string($source)) {
    fail("Unable to read $authRequestFile");
  }

  $start = strpos($source, 'function isPathInDocroot');
  $end = strpos($source, '// Base whitelist of files');
  if ($start === false || $end === false || $end <= $start) {
    fail('Unable to locate helper function block in auth-request.php');
  }

  $helperBlock = substr($source, $start, $end - $start);
  if (!is_string($helperBlock) || trim($helperBlock) === '') {
    fail('Extracted helper block is empty');
  }

  eval($helperBlock);

  if (!function_exists('getCanonicalRequestUri')) {
    fail('getCanonicalRequestUri() was not loaded');
  }
}

$repoRoot = dirname(__DIR__);
$authRequestFile = $repoRoot . '/emhttp/auth-request.php';
loadAuthRequestHelpers($authRequestFile);

$tmpRoot = sys_get_temp_dir() . '/auth-request-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
$docroot = $tmpRoot . '/docroot';
$publicFile = $docroot . '/plugins/dynamix.my.servers/unraid-components/standalone/test.js';
$outsideFile = $tmpRoot . '/secret.txt';

if (!mkdir(dirname($publicFile), 0777, true) && !is_dir(dirname($publicFile))) {
  fail('Could not create test docroot structure');
}
if (file_put_contents($publicFile, 'console.log("ok");') === false) {
  fail('Could not create test public file');
}
if (file_put_contents($outsideFile, 'secret') === false) {
  fail('Could not create test outside file');
}
$canonicalDocroot = realpath($docroot);
if (!is_string($canonicalDocroot)) {
  fail('Could not canonicalize test docroot');
}

$_SERVER['REQUEST_URI'] = '/plugins/dynamix.my.servers/unraid-components/standalone/test.js';
assertSame(
  '/plugins/dynamix.my.servers/unraid-components/standalone/test.js',
  getCanonicalRequestUri($canonicalDocroot),
  'returns canonical URI for existing in-docroot file'
);

$_SERVER['REQUEST_URI'] = '/plugins/dynamix.my.servers/unraid-components/../../../../secret.txt';
assertSame(
  '',
  getCanonicalRequestUri($canonicalDocroot),
  'rejects traversal that escapes docroot'
);

$_SERVER['REQUEST_URI'] = '/plugins/dynamix.my.servers/unraid-components/%2e%2e/%2e%2e/%2e%2e/%2e%2e/secret.txt';
assertSame(
  '',
  getCanonicalRequestUri($canonicalDocroot),
  'rejects encoded traversal path'
);

$_SERVER['REQUEST_URI'] = '/';
assertSame(
  '/',
  getCanonicalRequestUri($canonicalDocroot),
  'returns root URI for docroot path'
);

rrmdir($tmpRoot);
fwrite(STDOUT, "All auth-request canonical path tests passed.\n");
