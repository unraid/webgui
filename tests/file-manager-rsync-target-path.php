#!/usr/bin/env php
<?php
declare(strict_types=1);

function extractFunction(string $source, string $name): string
{
  $source = preg_replace('/<\?(?!php|=|xml)/i', '<?php', $source) ?? $source;
  $tokens = token_get_all($source);
  $capturing = false;
  $braces = 0;
  $inDoubleQuote = false;
  $code = '';
  $tokenCount = count($tokens);

  for ($i = 0; $i < $tokenCount; $i++) {
    $token = $tokens[$i];
    if (!$capturing && is_array($token) && $token[0] === T_FUNCTION) {
      $j = $i + 1;
      while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
      if ($j < $tokenCount && $tokens[$j] === '&') $j++;
      while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
      if ($j >= $tokenCount || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) continue;
      $capturing = true;
    }

    if (!$capturing) continue;

    $code .= is_array($token) ? $token[1] : $token;
    if (is_string($token)) {
      if ($token === '"') {
        $inDoubleQuote = !$inDoubleQuote;
      } elseif (!$inDoubleQuote && $token === '{') {
        $braces++;
      } elseif (!$inDoubleQuote && $token === '}' && --$braces === 0) {
        break;
      }
    }
  }

  if ($code === '') throw new RuntimeException("Could not extract $name");
  return $code;
}

function assertSameValue(string $expected, string $actual, string $message): void
{
  if ($expected !== $actual) {
    throw new RuntimeException(sprintf('%s Expected %s, got %s.', $message, $expected, $actual));
  }
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

$fileManager = dirname(__DIR__) . '/emhttp/plugins/dynamix/nchan/file_manager';
$fileManagerSource = file_get_contents($fileManager);
if ($fileManagerSource === false) throw new RuntimeException('Could not read the File Manager source.');

foreach (['truepath', 'validname', 'resolve_rsync_path', 'rsync_target'] as $function) {
  eval(extractFunction($fileManagerSource, $function));
}

assertSameValue('', rsync_target('/tmp/outside/'), 'A target outside the allowed roots must be rejected.');

$copyStart = strpos($fileManagerSource, 'case 3:  // copy folder');
$moveStart = strpos($fileManagerSource, 'case 4: // move folder');
$moveEnd = strpos($fileManagerSource, 'case 11: // change owner', $moveStart ?: 0);
if ($copyStart === false || $moveStart === false || $moveEnd === false || $copyStart >= $moveStart) {
  throw new RuntimeException('Could not isolate copy and move operation bodies.');
}

$copyBody = substr($fileManagerSource, $copyStart, $moveStart - $copyStart);
$moveBody = substr($fileManagerSource, $moveStart, $moveEnd - $moveStart);
assertContainsText('$target = rsync_target($target);', $copyBody, 'Copy must use the rsync target adapter.');
assertContainsText('$target = rsync_target($target);', $moveBody, 'Move must use the rsync target adapter.');

$root = sys_get_temp_dir() . '/unraid-file-manager-path-' . bin2hex(random_bytes(6));
$real = "$root/real";
$link = "$root/link";

try {
  if (!mkdir($real, 0777, true) || !symlink($real, $link)) {
    throw new RuntimeException('Could not create the symlink test fixture.');
  }

  $resolvedReal = realpath($real);
  assertSameValue("$resolvedReal/", resolve_rsync_path("$link/"), 'An existing symlink target must resolve physically.');
  assertSameValue("$resolvedReal/new/deep/", resolve_rsync_path("$link/new/deep/"), 'A missing descendant must remain under the resolved parent.');
} finally {
  if (is_link($link)) unlink($link);
  if (is_dir($real)) rmdir($real);
  if (is_dir($root)) rmdir($root);
}

echo "File Manager rsync target path regression test passed.\n";
