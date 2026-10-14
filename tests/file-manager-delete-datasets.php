#!/usr/bin/env php
<?php
declare(strict_types=1);
require __DIR__.'/file-manager-rsync-target-path.php';
function ensure(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
$root = realpath(sys_get_temp_dir()).'/unraid-os1053-zfs-'.bin2hex(random_bytes(6));
$docroot = "$root/emhttp";
mkdir("$docroot/state", 0777, true);
mkdir("$root/bin", 0777, true);
mkdir("$root/mnt/user", 0777, true);
$originalPath = getenv('PATH');
$realFind = trim((string)shell_exec('command -v find'));
putenv("OS1053_REAL_FIND=$realFind");
// Adapt only the production mount prefix and function names to our temporary
// root. Keep the real exclusive-share metadata, resolver and delete decisions.
$names = ['rsync_share_is_exclusive', 'rsync_exclusive_share_target', 'resolve_rsync_path', 'delete_command'];
foreach ($names as $function) {
  $code = extractFunction($fileManagerSource, $function);
  foreach ($names as $name) $code = str_replace($name.'(', 'fixture_'.$name.'(', $code);
  $code = str_replace('#^/mnt/', '#^'.preg_quote($root, '#').'/mnt/', $code);
  $code = str_replace("'/mnt/'", "'$root/mnt/'", $code);
  $pattern = '#^/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#';
  $code = str_replace($pattern, '#^'.preg_quote($root, '#').'/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#', $code);
  eval($code);
}
file_put_contents("$docroot/state/shares.ini", "[exclusive]\nexclusive=yes\n[file-link]\nexclusive=yes\n");
file_put_contents("$docroot/state/disks.ini", "[cache]\ntype=Cache\nuuid=test\n");
file_put_contents("$root/bin/getfattr", "#!/bin/sh\nprintf '%s\\n' cache\n");
chmod("$root/bin/getfattr", 0700);
// No host ZFS or mount operations: inventory and destroy use only this fixture.
file_put_contents("$root/bin/zfs", <<<'SCRIPT'
#!/usr/bin/env php
<?php
$r = getenv('OS1053_ZFS_FIXTURE');
$s = json_decode(file_get_contents($r.'/zfs.json'), true);
if (($argv[1] ?? '') === 'list') {
  foreach ($s['datasets'] as $name => $mount) echo "$name\t$mount\n";
  exit;
}
if (($argv[1] ?? '') !== 'destroy') exit(2);
$name = $argv[2] ?? '';
file_put_contents($r.'/destroy.log', $name."\n", FILE_APPEND);
if (!isset($s['datasets'][$name]) || in_array($name, $s['fail'] ?? [], true)) {
  fwrite(STDERR, "cannot destroy '$name': fixture busy or snapshot\n"); exit(1);
}
$mount = $s['datasets'][$name];
if (isset($s['delay_ms'])) usleep($s['delay_ms'] * 1000);
if (!str_starts_with($mount, $r.'/mnt/') || !rmdir($mount)) exit(1);
SCRIPT
);
chmod("$root/bin/zfs", 0700);
// Simulate the kernel refusing to remove mounted dataset directories. Real
// find still traverses and deletes fixture contents; the wrapper prevents only
// mountpoint rmdir and emits EBUSY when the command did not exclude that root.
file_put_contents("$root/bin/find", <<<'SCRIPT'
#!/usr/bin/env php
<?php
$r = getenv('OS1053_ZFS_FIXTURE');
$args = array_slice($argv, 1);
if (($args[1] ?? '') === '-mindepth' && getenv('OS1053_PROBE_MODE')) {
  if (getenv('OS1053_PROBE_MODE') === 'fail') { fwrite(STDERR,"fixture probe failed\n"); exit(1); }
  echo ".hidden-new-entry\n"; exit;
}
$errors = [];
if (in_array('-delete', $args, true)) {
  $state = json_decode(file_get_contents($r.'/zfs.json'), true);
  $sources = [];
  foreach ($args as $arg) { if (str_starts_with($arg,'-')) break; $sources[] = $arg; }
  foreach ($state['datasets'] as $mount) {
    $selected = false;
    foreach ($sources as $source) if ($mount === $source || str_starts_with($mount,$source.'/')) $selected = true;
    if (!$selected) continue;
    $excluded = false;
    foreach ($args as $i=>$arg) if ($arg === '-path' && fnmatch($args[$i+1],$mount)) $excluded = true;
    if (!$excluded) {
      $pattern = strtr($mount, [chr(92)=>chr(92).chr(92), '*'=>chr(92).'*', '?'=>chr(92).'?', '['=>chr(92).'[']);
      array_splice($args,array_search('-delete',$args,true),0,['!','-path',$pattern]);
      $errors[] = "find: cannot delete '$mount': Device or resource busy";
    }
  }
}
passthru(escapeshellarg(getenv('OS1053_REAL_FIND')).' '.implode(' ',array_map('escapeshellarg',$args)), $result);
foreach ($errors as $error) fwrite(STDERR,$error."\n");
exit($errors ? 1 : $result);
SCRIPT
);
chmod("$root/bin/find", 0700);
putenv("PATH=$root/bin:$originalPath"); putenv("OS1053_ZFS_FIXTURE=$root");
function inventory(array $datasets, array $fail = []): void {
  global $root;
  file_put_contents("$root/zfs.json", json_encode(['datasets'=>$datasets,'fail'=>$fail]));
  file_put_contents("$root/destroy.log", '');
}
function populated(string $dir): void {
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  file_put_contents($dir.'/content', 'selected');
  file_put_contents($dir.'/.hidden', 'hidden selected');
}
function executeFixture(array $sources, int $action): array {
  global $root;
  $command = fixture_delete_command($sources, $action);
  ensure($command !== '', 'Valid fixture must produce a command');
  exec($command.' 2>'.escapeshellarg("$root/stderr"), $output, $status);
  clearstatcache();
  return [$status, file_get_contents("$root/stderr")];
}
try {
  // The reviewed baseline command reproduces both causal failures locally.
  $baseline = shell_exec('git show 3b8086ee19cc94f1ad507e17af58026d14645161:emhttp/plugins/dynamix/nchan/file_manager');
  $code = extractFunction($baseline,'delete_command');
  $code = str_replace('delete_command(', 'baseline_delete_command(', $code);
  foreach (array_slice($names,0,3) as $name) $code = str_replace($name.'(', 'fixture_'.$name.'(', $code);
  $pattern = '#^/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#';
  $code = str_replace($pattern,'#^'.preg_quote($root,'#').'/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#',$code);
  eval($code);
  populated("$root/mnt/cache/baseline");
  inventory(['cache/baseline'=>"$root/mnt/cache/baseline"]);
  exec(baseline_delete_command(["$root/mnt/cache/baseline/"],1).' 2>'.escapeshellarg("$root/stderr"),$out,$rc);
  ensure($rc !== 0 && str_contains(file_get_contents("$root/stderr"),'Device or resource busy'), 'Baseline reports mountpoint EBUSY: rc='.$rc.' stderr='.file_get_contents("$root/stderr"));
  [$rc,$err] = executeFixture(["$root/mnt/cache/baseline/"],1);
  ensure($rc === 0 && $err === '', 'Patched dataset cleanup completes the same simulated mount without EBUSY');
  populated("$root/mnt/cache/exclusive");
  populated("$root/mnt/cache/exclusive-other");
  populated("$root/outside");
  symlink("$root/mnt/cache/exclusive", "$root/mnt/user/exclusive");
  inventory(['cache/exclusive'=>"$root/mnt/cache/exclusive", 'cache/exclusive-other'=>"$root/mnt/cache/exclusive-other"]);
  exec(baseline_delete_command(["$root/mnt/user/exclusive/"],1).' 2>'.escapeshellarg("$root/stderr"),$out,$rc);
  ensure(file_exists("$root/mnt/cache/exclusive/content") && file_get_contents("$root/destroy.log") === '', 'Baseline stops at the exclusive link instead of deleting contents');
  if (!is_link("$root/mnt/user/exclusive")) symlink("$root/mnt/cache/exclusive", "$root/mnt/user/exclusive");
  [$status,$error] = executeFixture(["$root/mnt/user/exclusive/"], 1);
  ensure($status === 0 && $error === '' && !is_dir("$root/mnt/cache/exclusive"), 'Exclusive share folder action must delete its physical dataset without false errors');
  ensure(file_exists("$root/mnt/cache/exclusive-other/content") && file_exists("$root/outside/content"), 'Prefix sibling and outside sentinels survive');
  ensure(trim(file_get_contents("$root/destroy.log")) === 'cache/exclusive', 'Only selected dataset is destroyed');

  populated("$root/mnt/cache/pool-share");
  inventory(['cache/pool-share'=>"$root/mnt/cache/pool-share"]);
  [$status,$error] = executeFixture(["$root/mnt/cache/pool-share/"], 1);
  ensure($status === 0 && $error === '' && !is_dir("$root/mnt/cache/pool-share"), 'Pool dataset completes with empty stderr');

  // Selected ordinary ancestor, nested datasets, and literal find glob syntax.
  $parent = "$root/mnt/cache/parent[*]?";
  populated($parent.'/child'); populated($parent.'/child/grandchild');
  inventory(['cache/parent[*]?/child'=>$parent.'/child','cache/parent[*]?/child/grandchild'=>$parent.'/child/grandchild']);
  [$status,$error] = executeFixture([$parent], 1);
  ensure($status === 0 && $error === '' && !is_dir($parent), 'Dataset descendants and deferred ordinary ancestors complete');
  ensure(explode("\n",trim(file_get_contents("$root/destroy.log"))) === ['cache/parent[*]?/child/grandchild','cache/parent[*]?/child'], 'Destroy deepest dataset first; mount inventory is simulated');

  populated("$root/mnt/cache/file-link");
  symlink("$root/mnt/cache/file-link", "$root/mnt/user/file-link");
  inventory(['cache/file-link'=>"$root/mnt/cache/file-link"]);
  [$status,$error] = executeFixture(["$root/mnt/user/file-link/"], 6);
  ensure(!is_link("$root/mnt/user/file-link") && file_exists("$root/mnt/cache/file-link/content"), 'File action unlinks even a trusted mapping without following it');
  ensure(file_get_contents("$root/destroy.log") === '', 'File action performs no dataset cleanup');
  symlink("$root/outside", "$root/mnt/cache/arbitrary");
  [$status,$error] = executeFixture(["$root/mnt/cache/arbitrary/"], 1);
  ensure(!is_link("$root/mnt/cache/arbitrary") && file_exists("$root/outside/content"), 'Untrusted final folder link remains unlink-only');

  populated("$root/mnt/cache/invalid-batch");
  inventory(['cache/invalid-batch'=>"$root/mnt/cache/invalid-batch"]);
  ensure(fixture_delete_command(["$root/mnt/cache/invalid-batch", "$root/outside"], 1) === '', 'Invalid batch is rejected before deletion');
  ensure(file_exists("$root/mnt/cache/invalid-batch/.hidden") && file_get_contents("$root/destroy.log") === '', 'Invalid batch leaves contents and cleanup untouched');

  populated("$root/mnt/cache/fail");
  inventory(['cache/fail'=>"$root/mnt/cache/fail"], ['cache/fail']);
  [$status,$error] = executeFixture(["$root/mnt/cache/fail"], 1);
  ensure($status !== 0 && str_contains($error,'fixture busy or snapshot') && is_dir("$root/mnt/cache/fail"), 'Real dataset destroy failures remain errors and preserve the mountpoint');

  // A failed or nonempty post-delete probe must not authorize destroy.
  foreach (['fail', 'nonempty'] as $mode) {
    populated("$root/mnt/cache/probe-$mode");
    inventory(["cache/probe-$mode"=>"$root/mnt/cache/probe-$mode"]);
    putenv("OS1053_PROBE_MODE=$mode");
    [$status,$error] = executeFixture(["$root/mnt/cache/probe-$mode"], 1);
    ensure(file_get_contents("$root/destroy.log") === '' && is_dir("$root/mnt/cache/probe-$mode"), 'Failed and nonempty probes preserve dataset roots');
    if ($mode === 'fail') ensure(str_contains($error, 'fixture probe failed'), 'Probe failure reaches the existing error channel');
  }
  putenv('OS1053_PROBE_MODE');

  // Launch the exact production branch with delayed fake destroy, then read
  // the production completion reply. The shell PID must cover dataset cleanup.
  populated("$root/mnt/cache/async");
  inventory(['cache/async'=>"$root/mnt/cache/async"]);
  $state = json_decode(file_get_contents("$root/zfs.json"),true); $state['delay_ms']=350;
  file_put_contents("$root/zfs.json",json_encode($state));
  $source=["$root/mnt/cache/async/"]; $action=1; $zfs='cache/'; $reply=[]; unset($pid);
  $status="$root/status"; $error="$root/error";
  $active="$root/active"; $pid_file="$root/pid";
  $start=strpos($fileManagerSource,'        $cmd = delete_command($source, $action);');
  $end=strpos($fileManagerSource,"      }\n      break;\n    case 2:",$start);
  eval(str_replace('delete_command(', 'fixture_delete_command(',substr($fileManagerSource,$start,$end-$start)));
  ensure(isset($pid[0]) && ctype_digit((string)$pid[0]), 'Production launch reports a background PID');
  usleep(100000);
  ensure(posix_kill((int)$pid[0],0) && is_dir("$root/mnt/cache/async"), 'Monitored process remains alive during delayed destroy');
  $deadline=microtime(true)+3;
  do { usleep(20000); clearstatcache(); } while (is_dir("$root/mnt/cache/async") && microtime(true)<$deadline);
  ensure(!is_dir("$root/mnt/cache/async"), 'Delayed cleanup finishes within fixture deadline');
  function delete_file(...$files) { foreach ($files as $file) if (file_exists($file)) unlink($file); }
  $start=strrpos($fileManagerSource,'        if ($action != 15) {');
  $end=strpos($fileManagerSource,'        unset($pid);',$start)+strlen('        unset($pid);');
  eval(substr($fileManagerSource,$start,$end-$start));
  ensure($reply['done'] === 1 && ($reply['error'] ?? '') === '', 'Production completion publishes done with empty error, allowing UI refresh/queue progression');
  ensure(trim(file_get_contents("$root/destroy.log")) === 'cache/async', 'Completion does not repeat dataset cleanup');

  // In-process cleanup replaces the old completion sweep only for deletes.
  ensure(str_contains($fileManagerSource, 'if ($zfs && !in_array($action, [1, 6]))'), 'Deletion must not repeat the old broad ZFS completion sweep');
} finally {
  putenv("PATH=$originalPath"); putenv('OS1053_ZFS_FIXTURE'); putenv('OS1053_PROBE_MODE'); putenv('OS1053_REAL_FIND');
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($it as $entry) {
    if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname());
  }
  rmdir($root);
}
echo "OS-1053 exclusive-share and simulated dataset regressions passed.\n";
