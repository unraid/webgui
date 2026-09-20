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

/**
 * Mirrors the clone path derivation in vm_clone(): the directory that is created
 * comes from DOMAINDIR, the directory the image is copied into comes from the
 * source VM's own disk path. They are not the same directory.
 */
function cloneTargetPath(string $sourceDisk, string $vm, string $clone, string $sourceRealDisk): string
{
  $target = str_replace($vm, $clone, $sourceDisk);
  if ($sourceRealDisk === '') return $target;
  return str_replace('/mnt/user/', "/mnt/$sourceRealDisk/", $target);
}

function cloneDir(string $domainDir, string $clone, string $storage): string
{
  if ($storage === 'default') return $domainDir.$clone;
  return str_replace('/mnt/user/', "/mnt/$storage/", $domainDir).$clone;
}

// A VM whose disks live outside the default VM storage path. Both are pools here,
// but the same split happens whenever DOMAINDIR resolves somewhere the source
// disks do not, including a DOMAINDIR user share that lands on the array.
$domainDir = '/mnt/pool_two/domains/';
$sourceDisk = '/mnt/pool_one/domains/Arch/vdisk1.img';
$target = cloneTargetPath($sourceDisk, 'Arch', 'Arch_clone', '');
$dir = cloneDir($domainDir, 'Arch_clone', 'default');

assertSameValue('/mnt/pool_one/domains/Arch_clone/vdisk1.img', $target, 'Clone target follows the source disk.');
assertSameValue('/mnt/pool_two/domains/Arch_clone', $dir, 'Created directory follows DOMAINDIR.');
if (dirname($target) === $dir) {
  throw new RuntimeException('Test scenario is wrong: the two directories must differ here.');
}

// A VM on a user share whose disks are pinned to a pool: same split.
$target = cloneTargetPath('/mnt/user/domains/Kali/vdisk1.img', 'Kali', 'Kali_clone', 'pool_one');
assertSameValue('/mnt/pool_one/domains/Kali_clone/vdisk1.img', $target, 'Clone target is remapped onto the real pool.');
if (dirname($target) === cloneDir('/mnt/user/domains/', 'Kali_clone', 'default')) {
  throw new RuntimeException('Test scenario is wrong: the two directories must differ here.');
}

$repo = dirname(__DIR__);
$source = file_get_contents("$repo/emhttp/plugins/dynamix.vm.manager/include/libvirt_helpers.php");
if ($source === false) throw new RuntimeException('Could not read libvirt_helpers.php.');

$cloneStart = strpos($source, 'function vm_clone');
if ($cloneStart === false) throw new RuntimeException('Could not find vm_clone().');
$body = substr($source, $cloneStart);

assertContainsText(
  '$tgtdir = dirname($reptgt);',
  $body,
  'vm_clone() must derive the directory the image is actually copied into.'
);
assertContainsText(
  'if (!is_dir($tgtdir)) my_mkdir($tgtdir,0777,true);',
  $body,
  'vm_clone() must create the copy target directory.'
);

$mkdirAt = strpos($body, 'my_mkdir($tgtdir,0777,true);');
$copyAt = strpos($body, "\$cmdstr = \"cp --reflink=");
$rsyncAt = strpos($body, '$cmdstr = "rsync -ahPIXS');
if ($mkdirAt === false || $copyAt === false || $rsyncAt === false) {
  throw new RuntimeException('Could not locate the clone copy commands.');
}
if ($mkdirAt > $copyAt || $mkdirAt > $rsyncAt) {
  throw new RuntimeException('The target directory must be created before the image is copied.');
}

echo "VM clone target path regression test passed.\n";
