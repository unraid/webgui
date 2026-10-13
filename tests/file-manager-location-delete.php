#!/usr/bin/env php
<?php
declare(strict_types=1);

// Use the existing token-based extraction harness and exercise its resolver tests.
require __DIR__.'/file-manager-rsync-target-path.php';
$browse = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/Browse.php');
eval(extractFunction($browse, 'my_devs'));
function compress($name, $length, $mode) { return $name; }
$disks = []; $lock = 'user';
function check($condition, $message) {
  if (!$condition) throw new RuntimeException($message);
}
function locationPaths($name, $menu = 'deviceFileContextMenu') {
  $devs = ['disk1','disk2'];
  $html = my_devs($devs, $name, $menu);
  preg_match_all('/onclick="([^"]*)"/', $html, $matches);
  $paths = [];
  foreach ($matches[1] as $call) {
    $call = html_entity_decode($call, ENT_QUOTES, 'UTF-8');
    if (preg_match('/^'.$menu.'\((".*"),[0-9]+\)$/s', $call, $argument)) {
      $paths[] = json_decode($argument[1], true, 512, JSON_THROW_ON_ERROR);
    }
  }
  check(count($paths) === 2, 'Both menu arguments must decode to valid strings');
  return $paths;
}
foreach (['share/top.txt','share/sub/dup.txt','share/sub/folder','share', "share/sub/a'\"&<>\\ %2F + ü\n.txt"] as $relative) {
  foreach (['user','user0'] as $mount) {
    check(locationPaths('/mnt/'.$mount.'/'.$relative) === ['/mnt/disk1/'.$relative,'/mnt/disk2/'.$relative], 'Full row path and special characters must survive LOCATION serialization');
  }
}
foreach (['/mnt/disk1/share/sub/a.txt','/boot/config/a.txt','/mnt/disks/usb/sub/a.txt'] as $direct) {
  check(locationPaths($direct) === [$direct,$direct], 'Direct device rows must retain their exact path');
}
check(locationPaths('/mnt/user/share', 'deviceFolderContextMenu') === ['/mnt/disk1/share','/mnt/disk2/share'], 'Share-level folder operands must be preserved');
check(substr_count($browse, 'my_devs($devs,$name,') === 2, 'Both actual row branches must pass the full row path');
$page = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/Browse.page');
foreach ([1,3,4,6,8,9] as $action) check(preg_match('/doAction\('.$action.',[^;]*,dev\)/', $page), 'Device action must receive the selected row operand');

$root = realpath(sys_get_temp_dir()).'/unraid-os1053-'.bin2hex(random_bytes(6));
$deleteCode = extractFunction($fileManagerSource, 'delete_command');
// Replace only the accepted root prefix in the extracted production decision.
// Filesystem resolution, type checks, command generation and find run unchanged.
$rootPattern = '#^/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#';
$fixturePattern = '#^'.preg_quote($root, '#').'/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#';
$deleteCode = str_replace($rootPattern, $fixturePattern, $deleteCode);
eval($deleteCode);
function runDelete($paths, $action) {
  $cmd = delete_command($paths, $action);
  check($cmd !== '', 'Valid fixture must yield a delete command');
  exec($cmd.' 2>&1', $output, $status);
  check($status === 0, 'Fixture deletion must succeed: '.implode('\n', $output));
}
function exists($path) { clearstatcache(true, $path); return file_exists($path) || is_link($path); }
try {
  foreach (['disk1','disk2'] as $disk) {
    mkdir("$root/mnt/$disk/share/sub/folder", 0777, true);
    mkdir("$root/mnt/$disk/share/other", 0777, true);
    foreach (['sub/dup.txt','top.txt','sub/folder/child.txt','other/keep.txt','unique.txt'] as $file) file_put_contents("$root/mnt/$disk/share/$file", 'sentinel');
  }
  mkdir("$root/outside"); file_put_contents("$root/outside/keep.txt", 'outside sentinel');
  foreach (['sub/dup.txt','top.txt'] as $relative) {
    $paths = locationPaths('/mnt/user/share/'.$relative);
    runDelete([$root.$paths[1]], 6);
    check(!exists($root.$paths[1]) && exists($root.$paths[0]), 'Delete removes only selected disk copy');
  }
  runDelete(["$root/mnt/disk2/share/sub/folder"], 1);
  check(!exists("$root/mnt/disk2/share/sub/folder") && exists("$root/mnt/disk1/share/sub/folder/child.txt"), 'Nested folder delete must preserve other disk copy');
  mkdir("$root/mnt/disk2/share/topfolder"); file_put_contents("$root/mnt/disk2/share/topfolder/child", 'sentinel');
  runDelete(["$root/mnt/disk2/share/topfolder/"], 1);
  foreach (['disk1','disk2'] as $disk) foreach (['other/keep.txt','unique.txt'] as $file) check(exists("$root/mnt/$disk/share/$file"), 'Sibling sentinel must survive');
  $special = "a'\"&<>\\ %2F + ü\n.txt";
  file_put_contents("$root/mnt/disk2/share/$special", 'special');
  runDelete(["$root/mnt/disk2/share/$special"], 6);
  check(!exists("$root/mnt/disk2/share/$special"), 'Special filename deletes exactly');
  foreach (["$root/mnt", "$root/mnt/disk2/", "$root/boot", "$root/mnt/disk2/share/..", "$root/mnt/disk2/share/.", "$root/mnt/disk2/share/../share/unique.txt", "$root/outside/keep.txt"] as $bad) {
    check(delete_command([$bad], 1) === '', 'Reject root, traversal or outside operand');
  }
  check(delete_command(["$root/mnt/disk2/share"], 6) === '', 'File action must refuse a directory');
  check(delete_command(["$root/mnt/disk2/share/unique.txt", "$root/outside/keep.txt"], 1) === '', 'Invalid batch must fail before any deletion');
  symlink("$root/outside", "$root/mnt/disk2/share/link");
  check(delete_command(["$root/mnt/disk2/share/link/keep.txt"], 1) === '', 'Reject symlink parent');
  runDelete(["$root/mnt/disk2/share/link/"], 1);
  check(!exists("$root/mnt/disk2/share/link") && exists("$root/outside/keep.txt"), 'Trailing-slash leaf symlink must unlink only');
  symlink("$root/outside", "$root/mnt/disk2/share/file-link");
  runDelete(["$root/mnt/disk2/share/file-link"], 6);
  check(exists("$root/outside/keep.txt"), 'File-action symlink must preserve target');
  symlink("$root/outside/missing", "$root/mnt/disk2/share/broken-link");
  runDelete(["$root/mnt/disk2/share/broken-link"], 6);
  check(!exists("$root/mnt/disk2/share/broken-link"), 'Broken symlink can be removed');
  // Replace a validated file with a nonempty directory before executing its command.
  $race = "$root/mnt/disk2/share/race"; file_put_contents($race, 'file');
  $cmd = delete_command([$race], 6); unlink($race); mkdir($race); file_put_contents("$race/keep", 'sentinel');
  exec($cmd.' 2>/dev/null', $output, $status);
  check(exists("$race/keep"), 'File deletion must not recurse after a type change');
  runDelete(["$root/mnt/disk2/share/"], 1);
  check(!exists("$root/mnt/disk2/share") && exists("$root/mnt/disk1/share/unique.txt") && exists("$root/outside/keep.txt"), 'Explicit share-folder action remains recursive on selected disk only');
} finally {
  // Cleanup only our unique disposable root, and never follow symlinks.
  if (is_dir($root)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
      if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
    }
    rmdir($root);
  }
}
echo "OS-1053 LOCATION and isolated deletion regressions passed.\n";
