#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace HelpersRmdirTest;

// Run the production function with intercepted commands; never invoke ZFS.
$helpers = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/Helpers.php');
$start = strpos($helpers, 'function my_rmdir(');
if ($start === false) throw new \RuntimeException('Cannot locate my_rmdir.');
$end = strpos($helpers, "\nfunction ", $start + 1);
if ($end === false) throw new \RuntimeException('Cannot locate the end of my_rmdir.');
eval('namespace '.__NAMESPACE__.'; '.substr($helpers, $start, $end - $start));

$commands = [];
$filesystem = 'zfs';
$lookupOutput = [];
$lookupStatus = 0;
$destroyStatus = 0;
$unresolvable = '';

function shell_exec($command) {
  global $commands, $filesystem, $lookupOutput;
  $commands[] = $command;
  if (strpos($command, 'stat -f') !== false) return $filesystem;
  // Also recognize the old lookup so the regression fails on the base code.
  if (strpos($command, 'zfs list -H -o name ') === 0) return explode("\t", $lookupOutput[0])[0];
  throw new \RuntimeException('Unexpected command: '.$command);
}

function exec($command, &$output, &$status) {
  global $commands, $lookupOutput, $lookupStatus, $destroyStatus;
  $commands[] = $command;
  if (strpos($command, 'zfs list ') === 0) {
    $output = $lookupOutput;
    $status = $lookupStatus;
  } elseif (strpos($command, 'zfs destroy ') === 0) {
    $output = $destroyStatus ? ['dataset is busy'] : [];
    $status = $destroyStatus;
  } else {
    throw new \RuntimeException('Unexpected command: '.$command);
  }
  return $output ? end($output) : '';
}

function realpath($path) {
  global $unresolvable;
  return $path === $unresolvable ? false : \realpath($path);
}

function expect($condition, $message) {
  if (!$condition) throw new \RuntimeException($message);
}

function destroyCommands() {
  global $commands;
  return array_values(array_filter($commands, function ($command) {
    return strpos($command, 'zfs destroy ') === 0;
  }));
}

$root = sys_get_temp_dir().'/helpers-rmdir-'.bin2hex(random_bytes(6));
mkdir($root);
$target = $root."/VM's directory";
$sibling = $root.'/other-vm.img';
file_put_contents($sibling, 'another VM disk');
try {
  mkdir($target);
  $lookupOutput = ["cache/domains\t".$root];
  $result = my_rmdir($target);
  expect(destroyCommands() === [], 'An ordinary directory must never destroy its containing dataset.');
  expect(!is_dir($target) && file_get_contents($sibling) === 'another VM disk', 'Remove only the empty VM directory.');
  expect($result['rtncode'] === 0, 'Ordinary-directory removal must report success.');

  mkdir($target);
  $commands = [];
  $lookupOutput = ["cache/domains/vm\t".$target];
  $result = my_rmdir($target);
  expect(destroyCommands() === ["zfs destroy 'cache/domains/vm'  2>&1 "], 'A dataset mountpoint must destroy only its dataset.');
  expect($result['rtncode'] === 0 && $result['dataset'] === 'cache/domains/vm', 'Preserve dataset result metadata.');
  expect(in_array('zfs list -H -o name,mountpoint '.escapeshellarg($target).' 2>&1', $commands, true), 'Quote the lookup path.');

  $commands = [];
  $destroyStatus = 1;
  $result = my_rmdir($target);
  expect(count(destroyCommands()) === 1 && is_dir($target), 'A failed destroy must not remove the directory by another path.');
  expect($result['rtncode'] === 1 && $result['output'] === ['dataset is busy'], 'Preserve ZFS destroy errors.');
  $destroyStatus = 0;

  $commands = [];
  $lookupStatus = 1;
  $lookupOutput = ['cannot open dataset'];
  $result = my_rmdir($target);
  expect(destroyCommands() === [] && is_dir($target), 'Failed lookup must stop before deletion.');
  expect($result['rtncode'] === 1 && $result['output'] === $lookupOutput, 'Preserve lookup errors.');
  $lookupStatus = 0;

  // Unusable mountpoint metadata cannot authorize a dataset destroy.
  foreach (['', '-', 'none', 'legacy', $root.'/missing'] as $mountpoint) {
    $commands = [];
    $lookupOutput = ["cache/domains\t".$mountpoint];
    $result = my_rmdir($target);
    expect(destroyCommands() === [] && !is_dir($target), 'Unusable mountpoint must never authorize destroy: '.$mountpoint);
    mkdir($target);
  }

  $commands = [];
  $lookupOutput = [];
  $result = my_rmdir($target);
  expect(destroyCommands() === [] && !is_dir($target), 'Empty lookup output cannot authorize destroy.');
  mkdir($target);

  $commands = [];
  $lookupOutput = ["cache/domains\t".$root.'/missing'];
  $unresolvable = $target;
  $result = my_rmdir($target);
  expect(destroyCommands() === [], 'Two failed realpath calls must not compare as a mountpoint match.');
  $unresolvable = '';
  mkdir($target);

  $commands = [];
  $lookupOutput = ["cache/domains/vm\t".$target];
  $alias = $root.'/alias';
  symlink($target, $alias);
  $result = my_rmdir($alias.'/');
  expect(count(destroyCommands()) === 1, 'Canonical aliases and trailing slashes must identify the same mountpoint.');
  unlink($alias);

  $commands = [];
  $lookupOutput = ["cache/domains\t".$root];
  file_put_contents($target.'/keep.img', 'keep');
  $result = @my_rmdir($target);
  expect(destroyCommands() === [] && is_file($target.'/keep.img'), 'A nonempty ordinary directory must remain intact.');
  expect($result['rtncode'] !== 0, 'Nonempty-directory removal must report failure.');
  unlink($target.'/keep.img');

  $commands = [];
  $filesystem = 'xfs';
  $result = my_rmdir($target);
  expect(!is_dir($target) && $result['rtncode'] === true && $result['type'] === 'xfs', 'Preserve non-ZFS removal behavior.');
  expect(destroyCommands() === [], 'Non-ZFS removal must not invoke ZFS.');

  $commands = [];
  $result = my_rmdir($target);
  expect($result['type'] === 'NoDir' && $commands === [], 'A missing directory must not execute commands.');
  echo "Helpers rmdir tests passed.\n";
} finally {
  if (is_link($root.'/alias')) unlink($root.'/alias');
  if (is_file($target.'/keep.img')) unlink($target.'/keep.img');
  if (is_dir($target)) rmdir($target);
  unlink($sibling);
  rmdir($root);
}
