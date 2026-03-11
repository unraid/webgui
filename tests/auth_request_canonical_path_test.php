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

$tmpRoot = sys_get_temp_dir() . '/auth-request-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
$docroot = $tmpRoot . '/docroot';
$publicFile = $docroot . '/plugins/dynamix.my.servers/unraid-components/standalone/test.js';
$outsideFile = $tmpRoot . '/secret.txt';
$externalCaseModelTarget = $tmpRoot . '/external/case-model.png';
$externalCaseModelLink = $docroot . '/webGui/images/case-model.png';

if (!mkdir(dirname($publicFile), 0777, true) && !is_dir(dirname($publicFile))) {
  fail('Could not create test docroot structure');
}
if (file_put_contents($publicFile, 'console.log("ok");') === false) {
  fail('Could not create test public file');
}
if (file_put_contents($outsideFile, 'secret') === false) {
  fail('Could not create test outside file');
}
if (!mkdir(dirname($externalCaseModelTarget), 0777, true) && !is_dir(dirname($externalCaseModelTarget))) {
  fail('Could not create external case model target directory');
}
if (file_put_contents($externalCaseModelTarget, 'case-model') === false) {
  fail('Could not create external case model target');
}
$canonicalExternalCaseModelTarget = realpath($externalCaseModelTarget);
if (!is_string($canonicalExternalCaseModelTarget)) {
  fail('Could not canonicalize external case model target');
}
if (!mkdir(dirname($externalCaseModelLink), 0777, true) && !is_dir(dirname($externalCaseModelLink))) {
  fail('Could not create case model link directory');
}
if (!symlink($externalCaseModelTarget, $externalCaseModelLink)) {
  fail('Could not create case model symlink');
}

define('AUTH_REQUEST_CASE_MODEL_TARGET', $canonicalExternalCaseModelTarget);

$repoRoot = dirname(__DIR__);
$authRequestFile = $repoRoot . '/emhttp/auth-request.php';
loadAuthRequestHelpers($authRequestFile);
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

$_SERVER['REQUEST_URI'] = '/webGui/images/case-model.png';
assertSame(
  '',
  getCanonicalRequestUri($canonicalDocroot),
  'rejects canonicalization for an externally mapped public asset'
);
assertSame(
  true,
  isAllowedPublicAssetRequest('/webGui/images/case-model.png', $canonicalDocroot, ['/webGui/images/case-model.png']),
  'allows the mapped external case-model asset when explicitly whitelisted'
);
assertSame(
  false,
  isAllowedPublicAssetRequest('/webGui/images/case-model.png', $canonicalDocroot, []),
  'rejects the mapped external case-model asset when it is not whitelisted'
);

rrmdir($tmpRoot);
fwrite(STDOUT, "All auth-request canonical path tests passed.\n");
