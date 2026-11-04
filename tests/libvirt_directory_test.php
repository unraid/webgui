#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace LibvirtDirectoryTest;

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix.vm.manager/include/libvirt.php');
$start = strpos($source, 'function is_dir_empty(');
if ($start === false) throw new \RuntimeException('Cannot locate is_dir_empty.');
$end = strpos($source, "\n\tfunction ", $start + 1);
if ($end === false) throw new \RuntimeException('Cannot locate the end of is_dir_empty.');
eval('namespace '.__NAMESPACE__.'; class DirectoryProbe {'.substr($source, $start, $end - $start).'}');

$handles = [];
function opendir($path) {
  global $handles;
  $handle = \opendir($path);
  $handles[] = $handle;
  return $handle;
}

function expect($condition, $message) {
  if (!$condition) throw new \RuntimeException($message);
}

$root = sys_get_temp_dir().'/libvirt-directory-'.bin2hex(random_bytes(6));
mkdir($root);
$probe = new DirectoryProbe();
try {
  expect($probe->is_dir_empty($root) === true, 'An empty directory must report true.');
  expect(!is_resource($handles[0]), 'An empty-directory check must close its handle before returning.');

  file_put_contents($root.'/keep.txt', 'keep');
  expect($probe->is_dir_empty($root) === false, 'A nonempty directory must report false.');
  expect(!is_resource($handles[1]), 'The early return for a nonempty directory must close its handle.');
  expect(file_get_contents($root.'/keep.txt') === 'keep', 'The check must preserve directory contents.');

  $opened = count($handles);
  expect($probe->is_dir_empty($root.'/missing') === null, 'An unreadable path must retain its null result.');
  expect(count($handles) === $opened, 'An unreadable path must not open a handle.');
  echo "Libvirt directory tests passed.\n";
} finally {
  foreach ($handles as $handle) if (is_resource($handle)) \closedir($handle);
  if (is_file($root.'/keep.txt')) unlink($root.'/keep.txt');
  rmdir($root);
}
