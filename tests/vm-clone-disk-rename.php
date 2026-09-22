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

/** Fails with $message unless $actual matches $expected. */
function assertSameValue(string $expected, string $actual, string $message): void
{
  if ($expected !== $actual) {
    throw new RuntimeException("$message Expected '$expected', got '$actual'.");
  }
}

$repo = dirname(__DIR__);
$source = file_get_contents("$repo/emhttp/plugins/dynamix.vm.manager/include/libvirt_helpers.php");
if ($source === false) throw new RuntimeException('Could not read libvirt_helpers.php.');
eval(extractFunction($source, 'vm_clone_disk_path'));

$cases = [
  // The VM's own directory and a file name that starts with the VM name are renamed.
  ['/mnt/pool/domains/Arch/vdisk1.img', 'Arch', 'Arch_clone', '/mnt/pool/domains/Arch_clone/vdisk1.img'],
  ['/mnt/pool/domains/Arch/Arch-Linux-x86_64.qcow2', 'Arch', 'Arch_clone', '/mnt/pool/domains/Arch_clone/Arch_clone-Linux-x86_64.qcow2'],
  ['/mnt/user/domains/Windows 11/vdisk1.img', 'Windows 11', 'Windows 11 copy', '/mnt/user/domains/Windows 11 copy/vdisk1.img'],
  // Names that also occur elsewhere in the path only touch the VM's own directory.
  ['/mnt/user/domains/user/vdisk1.img', 'user', 'user_clone', '/mnt/user/domains/user_clone/vdisk1.img'],
  ['/mnt/user/domains/domains/vdisk1.img', 'domains', 'domains_clone', '/mnt/user/domains/domains_clone/vdisk1.img'],
  ['/mnt/user/domains/a/vdisk1.img', 'a', 'a_clone', '/mnt/user/domains/a_clone/vdisk1.img'],
  ['/mnt/user/domains/vdisk/vdisk1.img', 'vdisk', 'vdisk_clone', '/mnt/user/domains/vdisk_clone/vdisk1.img'],
  // A file name that merely starts with the same letters is not renamed.
  ['/mnt/pool/domains/Arch/Archive.img', 'Arch', 'Arch_clone', '/mnt/pool/domains/Arch_clone/Archive.img'],
  // Images outside a directory named after the VM are left alone.
  ['/mnt/pool/images/shared.img', 'Arch', 'Arch_clone', '/mnt/pool/images/shared.img'],
];
foreach ($cases as [$path, $vm, $clone, $expected]) {
  assertSameValue($expected, vm_clone_disk_path($path, $vm, $clone), "Clone path for '$vm' from $path.");
}

$body = extractFunction($source, 'vm_clone');
if (!str_contains($body, 'vm_clone_disk_path($config["disk"][$diskid]["new"],$vm,$clone)')) {
  throw new RuntimeException('vm_clone() must rename disk paths with vm_clone_disk_path().');
}
if (str_contains($body, 'str_replace($vm,$clone,$config["disk"]')) {
  throw new RuntimeException('vm_clone() must not rename disk paths with str_replace() over the whole path.');
}

echo "VM clone disk rename regression test passed.\n";
