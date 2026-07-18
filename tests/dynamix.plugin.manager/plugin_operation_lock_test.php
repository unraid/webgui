#!/usr/bin/env php
<?PHP
// Copyright 2005-2026, Lime Technology
// License: GPLv2 only

require_once dirname(__DIR__, 2).'/emhttp/plugins/dynamix.plugin.manager/include/PluginOperationLock.php';
$repo = dirname(__DIR__, 2);
$docroot = dirname(__DIR__, 2).'/emhttp';
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

const TEST_PROCESS_TIMEOUT = 6.0;

function test_fail(string $message): never {
  fwrite(STDERR, "FAIL: $message\n");
  exit(1);
}

function test_assert(bool $condition, string $message): void {
  if (!$condition) test_fail($message);
}

function test_assert_throws(callable $operation, string $expected, string $message): void {
  try {
    $operation();
  } catch (Throwable $error) {
    test_assert(str_contains($error->getMessage(), $expected), "$message: {$error->getMessage()}");
    return;
  }
  test_fail($message);
}

function test_publish_plugin_check_artifact(
  string $plugin,
  int $generation,
  string $candidate,
  string $latest
): bool {
  return test_stage_plugin_check_artifact(
    $plugin,
    $generation,
    $candidate,
    $latest
  ) && plugin_manager_finalize_plugin_check_artifact(
    $plugin,
    $generation,
    $latest
  );
}

function test_stage_plugin_check_artifact(
  string $plugin,
  int $generation,
  string $candidate,
  string $latest
): bool {
  $receipt = plugin_manager_capture_download_receipt($candidate);
  return is_array($receipt) && plugin_manager_publish_plugin_check_artifact(
    $plugin,
    $generation,
    $receipt,
    $latest
  );
}

function test_append_event(string $directory, string $event): void {
  file_put_contents("$directory/events", "$event\n", FILE_APPEND | LOCK_EX);
}

function test_enter_critical_section(string $directory, string $id, int $hold_ms, int $exit_code): never {
  $held = "$directory/held";
  if (!@mkdir($held)) test_append_event($directory, "overlap $id");

  test_append_event($directory, "enter $id");
  usleep($hold_ms * 1000);
  test_append_event($directory, "exit $id");
  @rmdir($held);
  exit($exit_code);
}

function test_command(array $arguments): array {
  return array_merge([PHP_BINARY, __FILE__], $arguments);
}

function test_start_process(array $arguments): array {
  return test_start_command(test_command($arguments));
}

function test_start_command(array $command, ?array $environment = null): array {
  $pipes = [];
  $process = proc_open(
    $command,
    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    null,
    $environment
  );

  if (!is_resource($process)) test_fail('Unable to start test process');
  stream_set_blocking($pipes[1], false);
  stream_set_blocking($pipes[2], false);

  return [$process, $pipes, microtime(true)];
}

function test_finish_process(array $running, float $timeout = TEST_PROCESS_TIMEOUT): array {
  [$process, $pipes, $started] = $running;
  $stdout = '';
  $stderr = '';
  $exit_code = null;

  do {
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    $status = proc_get_status($process);
    if (!$status['running']) {
      $exit_code = $status['exitcode'];
      break;
    }
    if (microtime(true) - $started > $timeout) {
      proc_terminate($process, 9);
      test_fail('Test process timed out: '.implode(' ', $status['command']));
    }
    usleep(10000);
  } while (true);

  $stdout .= stream_get_contents($pipes[1]);
  $stderr .= stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $closed_status = proc_close($process);
  if ($exit_code === -1) $exit_code = $closed_status;

  return [$exit_code, $stdout, $stderr, microtime(true) - $started];
}

function test_wait_for(callable $condition, float $timeout, string $failure): void {
  $started = microtime(true);
  while (!$condition()) {
    if (microtime(true) - $started > $timeout) test_fail($failure);
    usleep(10000);
  }
}

function test_events(string $directory): array {
  $path = "$directory/events";
  if (!is_file($path)) return [];
  return array_values(array_filter(explode("\n", trim(file_get_contents($path))), 'strlen'));
}

function test_process_state(int $pid): ?string {
  $stat = @file_get_contents("/proc/$pid/stat");
  if ($stat === false) return null;
  $command_end = strrpos($stat, ') ');
  if ($command_end === false) return null;
  $fields = preg_split('/\s+/', trim(substr($stat, $command_end + 2)));
  return isset($fields[0]) ? (string)$fields[0] : null;
}

function test_process_parent(int $pid): ?int {
  $stat = @file_get_contents("/proc/$pid/stat");
  if ($stat === false) return null;
  $command_end = strrpos($stat, ') ');
  if ($command_end === false) return null;
  $fields = preg_split('/\s+/', trim(substr($stat, $command_end + 2)));
  return isset($fields[1]) && ctype_digit((string)$fields[1])
    ? (int)$fields[1]
    : null;
}

function test_directory(string $root, string $name): string {
  $directory = "$root/$name";
  if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
    test_fail("Unable to create test directory: $directory");
  }
  putenv(PLUGIN_MANAGER_LOCK_PATH_ENV."=$directory/plugin-manager.lock");
  return $directory;
}

function test_activate_snapshot_scope(string $directory): array {
  $previous = [
    PLUGIN_MANAGER_LOCK_TOKEN_ENV => getenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV),
    PLUGIN_MANAGER_LOCK_SCOPE_ENV => getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV)
  ];
  $token = bin2hex(random_bytes(16));
  $scope = "$directory/plugin-manager.lock.scope.$token";
  mkdir($scope, 0700);
  mkdir("$scope.snapshots", 0700);
  putenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token");
  putenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV."=$scope");
  return $previous;
}

function test_restore_snapshot_scope(array $previous): void {
  foreach ($previous as $name => $value) {
    putenv($value === false ? $name : "$name=$value");
  }
}

function test_run_artifact_policy_update(
  string $root,
  string $name,
  string $initial_contents,
  string $refreshed_contents,
  string $policy,
  string $policy_mode
): array {
  $directory = test_directory($root, $name);
  $plugin = "$name.plg";
  $initial = "$directory/initial.plg";
  $refreshed = "$directory/refreshed.plg";
  file_put_contents($initial, $initial_contents);
  file_put_contents($refreshed, $refreshed_contents);

  $result = test_finish_process(test_start_process([
    '--artifact-policy-update',
    'update',
    $directory,
    $plugin,
    $initial,
    $refreshed,
    $policy,
    $policy_mode
  ]));
  $receipt = json_decode(
    file_get_contents("$directory/policy-receipt.json"),
    true,
    8,
    JSON_THROW_ON_ERROR
  );

  return [
    'directory' => $directory,
    'plugin' => $plugin,
    'latest' => "$directory/$plugin",
    'initial' => $initial_contents,
    'refreshed' => $refreshed_contents,
    'result' => $result,
    'receipt' => $receipt
  ];
}

function test_detach(array $arguments, ?string $output = null): void {
  $command = '';
  foreach (test_command($arguments) as $argument) {
    $command .= ($command === '' ? '' : ' ').escapeshellarg($argument);
  }
  $redirect = $output === null ? '/dev/null' : escapeshellarg($output);
  exec("$command >$redirect 2>&1 &");
}

function test_worker(array $argv): never {
  $mode = $argv[1];

  if ($mode === '--critical') {
    [, , $method, $directory, $id, $hold_ms, $exit_code] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_enter_critical_section($directory, $id, (int)$hold_ms, (int)$exit_code);
  }

  if ($mode === '--aggregate') {
    [, , $method, $leaf_method, $directory, $id] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_append_event($directory, "aggregate-start $id");
    $result = test_finish_process(test_start_process([
      '--critical', $leaf_method, $directory, "$id-child", '10', '0'
    ]));
    test_assert($result[0] === 0, "$method child failed: {$result[2]}");
    test_append_event($directory, "aggregate-exit $id");
    exit(0);
  }

  if ($mode === '--nested-parent') {
    [, , $method, $nested_method, $marker] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    $result = test_finish_process(test_start_process(['--nested-child', $nested_method, $marker]), 2.0);
    if ($result[0] !== 0) fwrite(STDERR, $result[2]);
    exit($result[0]);
  }

  if ($mode === '--nested-child') {
    [, , $method, $marker] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    file_put_contents($marker, 'nested');
    exit(0);
  }

  if ($mode === '--nested-siblings-parent') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    $first = test_start_process([
      '--critical', 'remove', $directory, 'nested-first', '180', '0'
    ]);
    $second = test_start_process([
      '--critical', 'check', $directory, 'nested-second', '20', '0'
    ]);
    $first_result = test_finish_process($first);
    $second_result = test_finish_process($second);
    if ($first_result[0] !== 0) fwrite(STDERR, $first_result[2]);
    if ($second_result[0] !== 0) fwrite(STDERR, $second_result[2]);
    exit($first_result[0] ?: $second_result[0]);
  }

  if ($mode === '--nested-chain') {
    [, , $method, $marker, $depth] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    if ((int)$depth === 0) {
      file_put_contents($marker, 'nested-chain');
      exit(0);
    }
    $child = test_finish_process(test_start_process([
      '--nested-chain', 'validate', $marker, (string)((int)$depth - 1)
    ]), 3.0);
    if ($child[0] !== 0) fwrite(STDERR, $child[2]);
    exit($child[0]);
  }

  if ($mode === '--owner-death-parent') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_detach(['--owner-death-child', 'remove', $directory]);
    test_wait_for(
      fn() => in_array('enter nested-survivor', test_events($directory), true),
      2.0,
      'Nested survivor did not enter before direct owner death'
    );
    test_append_event($directory, 'kill direct-owner');
    posix_kill(getmypid(), 9);
    exit(99);
  }

  if ($mode === '--owner-death-child') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_enter_critical_section($directory, 'nested-survivor', 650, 0);
  }

  if ($mode === '--escaped-member-parent') {
    [, , $method, $directory, $setsid] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    $escaped_command = [
      $setsid,
      PHP_BINARY,
      __FILE__,
      '--escaped-member',
      'remove',
      $directory
    ];
    $escaped = test_start_command($escaped_command);
    while (true) usleep(50000);
  }

  if ($mode === '--escaped-member') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    file_put_contents("$directory/escaped-member-pid", (string)getmypid());
    while (true) usleep(50000);
  }

  if ($mode === '--late-registration-parent') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_detach(
      ['--late-registration-child', 'remove', $directory],
      "$directory/late-child-output"
    );
    exit(0);
  }

  if ($mode === '--late-registration-child') {
    [, , $method, $directory] = $argv;
    usleep(45000);
    file_put_contents("$directory/late-attempted", 'yes');
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_enter_critical_section($directory, 'late-child', 80, 0);
  }

  if ($mode === '--spawn-zombie-member') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_detach(
      ['--zombie-unreaping-parent', $directory],
      "$directory/zombie-parent-output"
    );
    test_wait_for(
      fn() => is_file("$directory/zombie-ready"),
      2.0,
      'Nested member did not become an unreaped zombie'
    );
    file_put_contents("$directory/zombie-direct-exit", 'yes');
    exit(0);
  }

  if ($mode === '--zombie-unreaping-parent') {
    [, , $directory] = $argv;
    $child = test_start_process(['--zombie-member', 'check', $directory]);
    $child_status = proc_get_status($child[0]);
    $child_pid = $child_status['pid'];
    test_wait_for(
      fn() => is_file("$directory/zombie-registered"),
      2.0,
      'Nested member did not register before being killed'
    );
    test_wait_for(
      fn() => test_process_state($child_pid) === 'Z',
      2.0,
      'Killed nested member did not remain an unreaped zombie'
    );
    file_put_contents("$directory/zombie-ready", (string)$child_pid);
    usleep(2000000);
    file_put_contents("$directory/zombie-parent-exit", 'yes');
    exit(0);
  }

  if ($mode === '--zombie-member') {
    [, , $method, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    file_put_contents("$directory/zombie-registered", (string)getmypid());
    posix_kill(getmypid(), 9);
    exit(99);
  }

  if ($mode === '--snapshot-kill-parent') {
    [, , $method, $directory, $plugin] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    $child = test_finish_process(test_start_process([
      '--snapshot-kill-child', 'check', $directory, $plugin
    ]));
    if ($child[0] === 0) test_fail('SIGKILL snapshot child unexpectedly succeeded');
    file_put_contents("$directory/snapshot-parent-ready", 'yes');
    test_wait_for(
      fn() => is_file("$directory/snapshot-observed"),
      2.0,
      'Snapshot cleanup test did not observe the orphan before parent exit'
    );
    exit(0);
  }

  if ($mode === '--snapshot-kill-child') {
    [, , $method, $directory, $plugin] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    $latest = "$directory/$plugin";
    $candidate = "$directory/orphan-candidate";
    file_put_contents(
      $candidate,
      '<PLUGIN name="orphan-snapshot" version="2026.07.18"/>'
    );
    $generation = plugin_manager_reserve_plugin_check_generation($plugin);
    if (!test_publish_plugin_check_artifact($plugin, $generation, $candidate, $latest)) {
      test_fail('Unable to publish orphan snapshot fixture');
    }
    $receipt = plugin_manager_snapshot_plugin_check_artifact($plugin, $latest);
    if ($receipt === null) test_fail('Unable to create orphan snapshot fixture');
    file_put_contents(
      "$directory/orphan-snapshot.json",
      json_encode([
        'path' => $receipt['path'],
        'scope' => getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV)
      ])
    );
    posix_kill(getmypid(), 9);
    exit(99);
  }

  if ($mode === '--artifact-policy-update') {
    [
      ,
      ,
      $method,
      $directory,
      $plugin,
      $initial,
      $refreshed,
      $policy,
      $policy_mode
    ] = $argv;
    putenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV."=$policy");
    putenv("UNRAID_PLUGIN_MANAGER_TEST_POLICY_MODE=$policy_mode");
    putenv(
      "UNRAID_PLUGIN_MANAGER_TEST_POLICY_OBSERVED=$directory/policy-observed"
    );
    plugin_manager_serialize_operation($method, __FILE__, $argv);

    $latest = "$directory/$plugin";
    $initial_generation = plugin_manager_reserve_plugin_check_generation($plugin);
    if (
      !test_publish_plugin_check_artifact(
        $plugin,
        $initial_generation,
        $initial,
        $latest
      )
    ) {
      test_fail('Initial artifact-policy generation could not be published');
    }

    // This is the real generation flow used by the standard update pre-hook:
    // a safe previously-checked artifact can be replaced before update snapshots.
    $refreshed_generation = plugin_manager_reserve_plugin_check_generation($plugin);
    if (
      !test_publish_plugin_check_artifact(
        $plugin,
        $refreshed_generation,
        $refreshed,
        $latest
      )
    ) {
      test_fail('Refreshed artifact-policy generation could not be published');
    }

    $receipt = plugin_manager_snapshot_plugin_check_artifact($plugin, $latest);
    if ($receipt === null) {
      test_fail('Finalized post-hook artifact could not be snapshotted for policy');
    }
    file_put_contents(
      "$directory/policy-receipt.json",
      json_encode([
        'path' => $receipt['path'],
        'scope' => getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV),
        'hash' => $receipt['hash'],
        'generation' => $receipt['generation']
      ], JSON_THROW_ON_ERROR)
    );

    try {
      plugin_manager_enforce_artifact_policy($method, $plugin, $receipt);
    } catch (Throwable $error) {
      file_put_contents("$directory/policy-error", $error->getMessage());
      exit(1);
    }

    $selected = plugin_manager_read_plugin_check_snapshot($receipt);
    if ($selected === false) {
      test_fail('Approved artifact-policy snapshot became unreadable');
    }
    file_put_contents("$directory/install-observed", $selected);
    exit(0);
  }

  if ($mode === '--plugin-transaction') {
    [, , $method, $directory, $id, $kind, $hold_ms] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_append_event($directory, "host-enter $id");

    $state = "$directory/pkgtools-state";
    if ($kind === 'core') {
      $core_lock = fopen("$directory/core-transaction.lock", 'c+');
      if ($core_lock === false || !flock($core_lock, LOCK_EX)) {
        test_fail('Unable to acquire simulated Core transaction lock');
      }
      test_append_event($directory, "core-enter $id");
      $before = trim(file_get_contents($state));
      test_append_event($directory, "pkg-read $id=$before");
      usleep((int)$hold_ms * 1000);
      file_put_contents($state, 'core-committed');
      test_append_event($directory, "pkg-write $id=core-committed");
      test_append_event($directory, "core-exit $id");
      flock($core_lock, LOCK_UN);
      fclose($core_lock);
    } else {
      $before = trim(file_get_contents($state));
      test_append_event($directory, "pkg-read $id=$before");
      file_put_contents($state, 'unrelated-after-core');
      test_append_event($directory, "pkg-write $id=unrelated-after-core");
    }

    test_append_event($directory, "host-exit $id");
    exit(0);
  }

  if ($mode === '--api-artifact-writer') {
    [, , $directory, $plugin, $content, $delay_ms] = $argv;
    $generation = plugin_manager_reserve_plugin_check_generation($plugin);
    $candidate = "$directory/$content.download";
    $latest = "$directory/$plugin";
    file_put_contents($candidate, $content);
    file_put_contents("$directory/$content.reserved", (string)$generation);
    usleep((int)$delay_ms * 1000);
    $published = plugin_manager_with_operation_lock(
      fn() => test_publish_plugin_check_artifact(
        $plugin,
        $generation,
        $candidate,
        $latest
      )
    );
    @unlink($candidate);
    exit($published ? 0 : 1);
  }

  if ($mode === '--cli-artifact-writer') {
    [, , $method, $directory, $plugin, $content, $delay_ms] = $argv;
    $phase = getenv('UNRAID_PLUGIN_MANAGER_TEST_CLI_CHECK_PHASE');
    if ($phase === false) {
      $generation = plugin_manager_reserve_plugin_check_generation($plugin);
      $candidate = "$directory/$content.download";
      file_put_contents("$directory/events", "download-start $content\n", FILE_APPEND | LOCK_EX);
      usleep((int)$delay_ms * 1000);
      file_put_contents($candidate, $content);
      file_put_contents("$directory/$content.reserved", (string)$generation);
      file_put_contents(
        "$directory/events",
        "download-complete $content\n",
        FILE_APPEND | LOCK_EX
      );
      putenv(
        'UNRAID_PLUGIN_MANAGER_TEST_CLI_CHECK_PHASE='.
        base64_encode(json_encode([$generation, $candidate], JSON_THROW_ON_ERROR))
      );
      plugin_manager_serialize_operation('validate', __FILE__, $argv);
      test_fail('CLI check phase unexpectedly bypassed the host lock');
    }

    $decoded = base64_decode($phase, true);
    $receipt = is_string($decoded) ? json_decode($decoded, true) : null;
    if (
      !is_array($receipt) ||
      count($receipt) !== 2 ||
      !is_int($receipt[0]) ||
      !is_string($receipt[1])
    ) {
      test_fail('CLI check phase receipt is invalid');
    }
    [$generation, $candidate] = $receipt;
    putenv('UNRAID_PLUGIN_MANAGER_TEST_CLI_CHECK_PHASE');
    $latest = "$directory/$plugin";
    test_append_event($directory, "publish-enter $content");
    $published = test_publish_plugin_check_artifact(
      $plugin,
      $generation,
      $candidate,
      $latest
    );
    @unlink($candidate);
    exit($published ? 0 : 1);
  }

  if ($mode === '--check-artifact-gate-reader') {
    [, , $directory, $plugin] = $argv;
    $latest = "$directory/$plugin";
    $handle = plugin_manager_open_operation_lock();
    if (!@flock($handle, LOCK_EX | LOCK_NB)) {
      file_put_contents("$directory/update-blocked", 'yes');
      if (!@flock($handle, LOCK_EX)) {
        fclose($handle);
        test_fail('Concurrent update could not acquire the operation lock');
      }
    }
    try {
      file_put_contents("$directory/update-entered", 'yes');
      $accepted = plugin_manager_plugin_check_artifact_is_current(
        $plugin,
        $latest
      );
      file_put_contents(
        "$directory/update-observed",
        $accepted ? 'accepted' : 'rejected'
      );
    } finally {
      @flock($handle, LOCK_UN);
      fclose($handle);
    }
    exit(0);
  }

  if ($mode === '--spawn-background') {
    [, , $method, $delay_ms, $marker] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_detach(['--delayed-marker', $delay_ms, $marker]);
    exit(0);
  }

  if ($mode === '--delayed-marker') {
    [, , $delay_ms, $marker] = $argv;
    usleep((int)$delay_ms * 1000);
    file_put_contents($marker, 'done');
    exit(0);
  }

  if ($mode === '--spawn-delayed-worker') {
    [, , $method, $delay_ms, $directory] = $argv;
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_detach(
      ['--delayed-critical', 'check', $delay_ms, $directory, 'descendant'],
      "$directory/descendant-output"
    );
    exit(0);
  }

  if ($mode === '--delayed-critical') {
    [, , $method, $delay_ms, $directory, $id] = $argv;
    usleep((int)$delay_ms * 1000);
    file_put_contents("$directory/descendant-attempted", 'yes');
    plugin_manager_serialize_operation($method, __FILE__, $argv);
    test_enter_critical_section($directory, $id, 10, 0);
  }

  if ($mode === '--display-artifact-writer') {
    [, , $directory, $path, $contents, $hold_ms] = $argv;
    plugin_manager_with_operation_lock(
      static function() use ($directory, $path, $contents, $hold_ms): void {
        test_append_event($directory, "display-enter $contents");
        usleep((int)$hold_ms * 1000);
        if (!plugin_manager_write_shared_artifact($path, $contents)) {
          test_fail('Unable to publish display artifact');
        }
        test_append_event($directory, "display-exit $contents");
      }
    );
    exit(0);
  }

  test_fail("Unknown worker mode: $mode");
}

if (isset($argv[1]) && str_starts_with($argv[1], '--')) test_worker($argv);

$root = sys_get_temp_dir().'/plugin-operation-lock-'.bin2hex(random_bytes(6));
if (!mkdir($root, 0777, true)) test_fail("Unable to create test root: $root");

register_shutdown_function(function () use ($root): void {
  exec('rm -rf '.escapeshellarg($root));
});

$artifact_policy = "$root/artifact-policy";
$artifact_policy_source = <<<'PHP'
#!/usr/bin/env php
<?PHP
if (count($argv) !== 4 || $argv[1] !== 'update') {
  fwrite(STDERR, "invalid artifact policy arguments\n");
  exit(64);
}
[$script, $method, $artifact, $plugin] = $argv;
$observed = getenv('UNRAID_PLUGIN_MANAGER_TEST_POLICY_OBSERVED');
if (is_string($observed) && $observed !== '') {
  file_put_contents(
    $observed,
    json_encode([
      'method' => $method,
      'plugin' => $plugin,
      'hash' => hash_file('sha256', $artifact)
    ], JSON_THROW_ON_ERROR)
  );
}
if (getenv('UNRAID_PLUGIN_MANAGER_TEST_POLICY_MODE') === 'fail') {
  fwrite(STDERR, "injected policy failure\n");
  exit(70);
}
if (getenv('UNRAID_PLUGIN_MANAGER_TEST_POLICY_MODE') === 'mutate') {
  chmod($artifact, 0600);
  file_put_contents($artifact, '<PLUGIN name="policy-mutated"/>');
  exit(0);
}
$xml = @simplexml_load_file($artifact, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
if ($xml === false) {
  fwrite(STDERR, "artifact is not readable PLG XML\n");
  exit(65);
}
$name = strtolower(trim((string)$xml['name']));
$url = trim((string)$xml['pluginURL']);
$host = parse_url($url, PHP_URL_HOST);
$path = parse_url($url, PHP_URL_PATH);
$core =
  $name === 'unraid.core' ||
  str_starts_with($name, 'unraid.core.') ||
  (
    is_string($host) &&
    is_string($path) &&
    strtolower($host) === 'preview.dl.unraid.net' &&
    str_starts_with(strtolower($path), '/unraid-core/')
  );
if ($core) {
  fwrite(STDERR, "Core artifact rejected\n");
  exit(42);
}
exit(0);
PHP;
file_put_contents($artifact_policy, $artifact_policy_source);
chmod($artifact_policy, 0755);
$artifact_policy = realpath($artifact_policy);
test_assert(is_string($artifact_policy), 'Artifact-policy fixture is not canonical');

$plugin_source = file_get_contents(
  dirname(__DIR__, 2).'/emhttp/plugins/dynamix.plugin.manager/scripts/plugin'
);
$lock_block = strpos($plugin_source, "if (\$method != 'check') {");
$lock_call = $lock_block === false
  ? false
  : strpos(
    $plugin_source,
    'plugin_manager_operation_lock_command($method, $script, $argv)',
    $lock_block
  );
test_assert($lock_call !== false, 'The plugin executable does not invoke the operation lock');
foreach (["if (\$method == 'checkall')", "if (\$method == 'updateall')", "if (\$method == 'checkos')"] as $marker) {
  $aggregate = strpos($plugin_source, $marker);
  test_assert($aggregate !== false && $aggregate < $lock_call, "$marker must run before lock acquisition");
}
foreach (
  [
    'checkall' => ["if (\$method == 'updateall')", 'check'],
    'updateall' => ["if (\$method == 'checkos')", 'update'],
    'checkos' => ['// MAIN - two or three arguments', 'check']
  ] as $aggregate_method => [$next_marker, $recursive_method]
) {
  $aggregate_start = strpos(
    $plugin_source,
    "if (\$method == '$aggregate_method')"
  );
  $aggregate_end = strpos($plugin_source, $next_marker, $aggregate_start);
  $aggregate_source =
    $aggregate_start !== false &&
    $aggregate_end !== false &&
    $aggregate_end > $aggregate_start
      ? substr(
        $plugin_source,
        $aggregate_start,
        $aggregate_end - $aggregate_start
      )
      : '';
  test_assert(
    $aggregate_source !== '' &&
      str_contains(
        $aggregate_source,
        'plugin_manager_valid_plugin_basename($plugin)'
      ) &&
      str_contains($aggregate_source, 'escapeshellarg($cmd)') &&
      str_contains($aggregate_source, "' $recursive_method '") &&
      str_contains($aggregate_source, 'escapeshellarg($plugin)'),
    "$aggregate_method does not validate and quote its recursive plugin command"
  );
}
foreach (["if (\$method == 'install')", "if (\$method == 'update')", "if (\$method == 'remove')"] as $marker) {
  $leaf = strpos($plugin_source, $marker, $lock_call);
  test_assert($leaf !== false && $leaf > $lock_call, "$marker must run after lock acquisition");
}
$check_block = strpos($plugin_source, "if (\$method == 'check')");
test_assert(
  $lock_block !== false &&
    $lock_block < $lock_call &&
    str_contains(
      substr($plugin_source, $lock_block, $check_block - $lock_block),
      "putenv(PLUGIN_MANAGER_NCHAN_CHILD_ENV.'=1')"
    ) &&
    str_contains(
      substr($plugin_source, $lock_block, $check_block - $lock_block),
      'done($status)'
  ),
  'A serialized nchan operation does not hand completion ownership back to its parent'
);
$check_download = strpos($plugin_source, 'download($installed_pluginURL, $download, $error)', $check_block);
$check_phase_lock = strpos(
  $plugin_source,
  "plugin_manager_operation_lock_command(\n        'validate'",
  $check_block
);
$check_publish = strpos(
  $plugin_source,
  'plugin_manager_publish_plugin_check_artifact(',
  $check_block
);
test_assert(
  $check_block !== false &&
    $check_download !== false &&
    $check_phase_lock !== false &&
    $check_publish !== false &&
    $check_download < $check_phase_lock &&
    $check_phase_lock < $check_publish,
  'The CLI check does not download before its phase-scoped publication lock'
);
$download_normalization = strpos($plugin_source, "if (\$method === 'download')");
$download_update = strpos($plugin_source, "\$method = 'update';", $download_normalization);
test_assert(
  $download_normalization !== false && $download_update !== false && $download_update < $lock_call,
  'download must be normalized to a serialized update before lock acquisition'
);

foreach (
  [
    'install',
    'check',
    'update',
    'download',
    'remove',
    'validate',
    'branchcheck',
    'history-delete'
  ] as $method
) {
  test_assert(plugin_manager_operation_requires_lock($method), "$method must be serialized");
}
foreach (['checkall', 'updateall', 'checkos', 'version', 'pluginURL'] as $method) {
  test_assert(!plugin_manager_operation_requires_lock($method), "$method must remain unlocked");
}
foreach (
  [
    'community.app.plg',
    'unRAIDServer-.plg',
    'plugin_name+variant.plg'
  ] as $plugin
) {
  test_assert(
    plugin_manager_valid_plugin_basename($plugin),
    "Safe plugin basename was rejected: $plugin"
  );
}
foreach (
  [
    '',
    '.plg',
    '../plugin.plg',
    '..\plugin.plg',
    '/tmp/plugin.plg',
    "plugin.plg\nEVIL=1",
    'plugin.xml',
    'plugin name.plg'
  ] as $plugin
) {
  test_assert(
    !plugin_manager_valid_plugin_basename($plugin),
    "Unsafe plugin basename was accepted: ".json_encode($plugin)
  );
}

$plugin_api_source = file_get_contents(
  dirname(__DIR__, 2).'/emhttp/plugins/dynamix.plugin.manager/scripts/PluginAPI.php'
);
test_assert(
  str_contains(
    $plugin_api_source,
    'plugin_manager_with_plugin_check_operation_lock'
  ),
  'PluginAPI update checks do not atomically revoke failures under the host-wide operation lock'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_reserve_plugin_check_generation'),
  'PluginAPI does not reserve an ordered same-plugin publication generation'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_publish_plugin_check_artifact'),
  'PluginAPI does not use the shared same-plugin artifact publisher'
);
test_assert(
  str_contains($plugin_source, 'plugin_manager_reserve_plugin_check_generation') &&
    str_contains($plugin_source, 'plugin_manager_publish_plugin_check_artifact'),
  'The plugin CLI does not use the shared same-plugin publication protocol'
);
$show_plugins_source = file_get_contents(
  dirname(__DIR__, 2).
    '/emhttp/plugins/dynamix.plugin.manager/include/ShowPlugins.php'
);
$plugin_rm_source = file_get_contents(
  dirname(__DIR__, 2).
    '/emhttp/plugins/dynamix.plugin.manager/scripts/plugin_rm'
);
$supervisor_source = file_get_contents(
  dirname(__DIR__, 2).
    '/emhttp/plugins/dynamix.plugin.manager/scripts/plugin-operation-lock'
);
test_assert(
  str_contains($show_plugins_source, 'plugin_branch_check($plugin_file, $branch)') &&
    !str_contains($show_plugins_source, '/var/tmp/') &&
    !str_contains($show_plugins_source, 'unRAIDServer-.plg') &&
    !str_contains($show_plugins_source, 'file_put_contents('),
  'ShowPlugins still exposes branch-check or display-artifact intermediate state'
);
test_assert(
  str_contains($show_plugins_source, 'plugin_manager_write_shared_artifact') &&
    str_contains($show_plugins_source, 'plugin_manager_with_operation_lock'),
  'ShowPlugins display artifacts are not atomically published under the host lock'
);
test_assert(
  str_contains($plugin_rm_source, 'history-delete') &&
    !preg_match('/(^|[;&|[:space:]])rm([[:space:]]|$)/m', $plugin_rm_source),
  'plugin_rm still mutates persistent boot history outside the serialized CLI leaf'
);
$branch_source_start = strpos($plugin_source, "if (\$method === 'branchcheck')");
$branch_source_end = strpos($plugin_source, "if (\$method === 'history-delete')");
$branch_source =
  is_int($branch_source_start) &&
  is_int($branch_source_end) &&
  $branch_source_end > $branch_source_start
    ? substr(
      $plugin_source,
      $branch_source_start,
      $branch_source_end - $branch_source_start
    )
    : '';
$branch_pre_hooks = strpos($branch_source, 'pre_hooks();');
$branch_receipt_capture = strpos(
  $branch_source,
  'plugin_manager_capture_download_receipt($download)'
);
$branch_exact_parse = strpos(
  $branch_source,
  'simplexml_load_string($checked_contents'
);
test_assert(
  $branch_source !== '' &&
    !str_contains($branch_source, 'synthetic installed link') &&
    $branch_pre_hooks !== false &&
    $branch_receipt_capture !== false &&
    $branch_exact_parse !== false &&
    $branch_pre_hooks < $branch_receipt_capture &&
    $branch_receipt_capture < $branch_exact_parse,
  'OS branch check does not select and parse the exact post-hook bytes'
);
test_assert(
  str_contains($supervisor_source, '.operation-identity') &&
    str_contains($supervisor_source, '[ "$pid" -gt 1 ]') &&
    str_contains($supervisor_source, 'bootstrap_pid="$BASHPID"') &&
    str_contains($supervisor_source, '"/proc/$bootstrap_pid/stat"') &&
    str_contains(
      $supervisor_source,
      'member_is_process_group_leader "$snapshot_dir/$identity"'
    ) &&
    !str_contains($supervisor_source, '-$finished_guardian_pid'),
  'Unexpected guardian death can target an unvalidated or stale process group'
);
test_assert(
  !str_contains($plugin_api_source, 'plugin_manager_with_plugin_check_lock'),
  'PluginAPI holds the per-plugin state lock while waiting for the global lock'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_revoke_failed_api_plugin_check') &&
    str_contains($plugin_api_source, 'plugin_manager_revoke_unserialized_plugin_check') &&
    str_contains($plugin_source, 'invalidate_failed_plugin_check') &&
    str_contains($plugin_source, 'plugin_manager_revoke_unserialized_plugin_check'),
  'API and CLI check failures do not revoke their publication generation'
);
test_assert(
  str_contains($plugin_api_source, '$response !== null || !is_int($generation) || $lock_entered'),
  'PluginAPI retries invalidation outside the host lock after its callback entered'
);
$update_gate_start = strpos($plugin_source, 'if (!$artifact_current) {');
$update_gate_end = $update_gate_start === false
  ? false
  : strpos($plugin_source, 'pre_hooks();', $update_gate_start);
$update_gate =
  $update_gate_start !== false &&
  $update_gate_end !== false &&
  $update_gate_end > $update_gate_start
    ? substr($plugin_source, $update_gate_start, $update_gate_end - $update_gate_start)
    : '';
test_assert(
  $update_gate !== '' &&
    str_contains($update_gate, 'done(1);') &&
    !str_contains($update_gate, 'exit'),
  'A rejected update can bypass the common nchan completion path'
);
test_assert(
  str_contains($show_plugins_source, "case 'remove' : return;"),
  'A remove audit can still clear alerts belonging to other plugins'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_finalize_plugin_check_artifact') &&
    str_contains($plugin_source, 'plugin_manager_finalize_plugin_check_artifact'),
  'API and CLI make staged artifacts current before semantic validation'
);
test_assert(
  str_contains($plugin_api_source, 'download_url($url,$download,$download_receipt)'),
  'PluginAPI no longer performs its existing update-artifact download'
);
test_assert(
  str_contains(
    $plugin_api_source,
    '$receipt = plugin_manager_write_complete_download($path,$out)'
  ),
  'PluginAPI does not verify its complete temporary download write'
);
test_assert(
  !str_contains($plugin_api_source, 'plugin("check",$plugin)'),
  'PluginAPI changed its check endpoint into the hook-running plugin check command'
);
test_assert(
  strpos($plugin_api_source, 'download_url($url,$download,$download_receipt)') <
    strpos($plugin_api_source, 'plugin_manager_with_plugin_check_operation_lock'),
  'PluginAPI holds the host-wide lock across its remote update-artifact download'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_attribute_uncached("pluginURL",$current)'),
  'PluginAPI revalidates a replacement plugin through the stale path-keyed attribute cache'
);

$directory = test_directory($root, 'atomic-check-state');
$plugin = 'stateful.plg';
$state_lock = "$directory/check-".hash('sha256', $plugin).'.lock';
$state_path = "$state_lock.state";
$first_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert($first_generation === 1, 'Initial check generation was not reserved');
test_assert(
  is_file($state_lock) &&
    file_get_contents($state_lock) === '' &&
    file_get_contents($state_path) === "1:0:0:-\n",
  'Check generation state was not separated from the stable flock inode'
);
$stable_lock_inode = fileinode($state_lock);
$stable_state = file_get_contents($state_path);
foreach (
  [
    'short write' => ['stage_write_after' => 3],
    'rename failure' => ['rename' => true]
  ] as $failure => $faults
) {
  test_assert_throws(
    fn() => plugin_manager_with_plugin_check_lock(
      $plugin,
      static function ($handle) use ($faults): void {
        $state = plugin_manager_read_plugin_check_state($handle);
        $state['next']++;
        plugin_manager_write_plugin_check_state($handle, $state, $faults);
      }
    ),
    $failure === 'short write' ? 'Injected Plugin Manager short write' : 'atomically commit',
    "Injected check-state $failure did not fail"
  );
  test_assert(
    file_get_contents($state_path) === $stable_state,
    "Injected check-state $failure damaged the prior generation state"
  );
  test_assert(
    fileinode($state_lock) === $stable_lock_inode,
    "Injected check-state $failure replaced the flock inode"
  );
  test_assert(
    glob("$directory/.check-*.lock.state.check-state-*") === [],
    "Injected check-state $failure left a staging file"
  );
}
$second_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  $second_generation === 2 &&
    fileinode($state_lock) === $stable_lock_inode &&
    file_get_contents($state_path) === "2:0:0:-\n",
  'Successful check-state replacement changed the flock inode or lost ordering'
);
file_put_contents($state_path, '');
chmod($state_path, 0600);
test_assert_throws(
  fn() => plugin_manager_with_plugin_check_lock(
    $plugin,
    static fn($handle) => plugin_manager_read_plugin_check_state($handle)
  ),
  'check state is invalid',
  'An existing empty check-state file was interpreted as generation zero'
);

$download_artifact = "$directory/plugin-api.download";
file_put_contents($download_artifact, '');
$download_contents = '<PLUGIN name="complete-download" version="2026.07.18"/>';
test_assert(
  plugin_manager_write_complete_download($download_artifact, $download_contents) &&
    file_get_contents($download_artifact) === $download_contents,
  'Complete PluginAPI download verification rejected an exact write'
);
test_assert(
  !plugin_manager_write_complete_download($download_artifact, $download_contents, 7) &&
    strlen(file_get_contents($download_artifact)) === 7,
  'Complete PluginAPI download verification accepted an injected partial write'
);

$directory = test_directory($root, 'private-download-boundary');
$private_download = plugin_manager_create_private_download_file();
$private_directory = dirname($private_download);
test_assert(
  realpath(dirname($private_directory)) === realpath($directory) &&
    (fileperms($private_directory) & 07777) === 0700 &&
    (fileperms($private_download) & 07777) === 0600,
  'Network candidate was not created under the private operation-lock boundary'
);
$private_receipt = plugin_manager_write_complete_download(
  $private_download,
  '<PLUGIN name="private-download" version="2026.07.18"/>'
);
test_assert(is_array($private_receipt), 'Private network candidate did not produce a receipt');
$replacement = "$private_directory/.plugin-check-replacement";
file_put_contents($replacement, '<PLUGIN name="attacker" version="9999.01.01"/>');
chmod($replacement, 0600);
rename($replacement, $private_download);
test_assert(
  plugin_manager_read_download_receipt($private_receipt) === false,
  'A path replacement still matched the exact network-candidate receipt'
);
$replacement_generation = plugin_manager_reserve_plugin_check_generation('replacement.plg');
test_assert(
  !plugin_manager_publish_plugin_check_artifact(
    'replacement.plg',
    $replacement_generation,
    $private_receipt,
    "$directory/replacement.plg"
  ) &&
    !file_exists("$directory/replacement.plg"),
  'A replaced private candidate was published through its stale receipt'
);

$hostile_shared = "$root/hostile-shared";
mkdir($hostile_shared, 0777);
chmod($hostile_shared, 0777);
plugin_manager_prepare_shared_artifact_directory($hostile_shared);
test_assert(
  (fileperms($hostile_shared) & 0022) === 0,
  'An owned group/other-writable shared artifact directory was not secured'
);
$hostile_target = "$root/hostile-shared-link";
symlink($hostile_shared, $hostile_target);
test_assert_throws(
  fn() => plugin_manager_prepare_shared_artifact_directory($hostile_target),
  'missing or unsafe',
  'A symlink shared artifact directory was trusted'
);
if (plugin_manager_effective_user_id() === 0 && function_exists('chown')) {
  $foreign_shared = "$root/foreign-owned-shared";
  mkdir($foreign_shared, 0700);
  chown($foreign_shared, 65534);
  test_assert_throws(
    fn() => plugin_manager_prepare_shared_artifact_directory($foreign_shared),
    'missing or unsafe',
    'A foreign-owned shared artifact directory was trusted'
  );
}

$safe_shared = "$directory/shared";
mkdir($safe_shared, 0700);
$victim = "$directory/victim";
$shared_output = "$safe_shared/changes.txt";
file_put_contents($victim, 'preserve-victim');
symlink($victim, $shared_output);
test_assert(
  plugin_manager_write_shared_artifact($shared_output, 'safe-release-notes') &&
    file_get_contents($victim) === 'preserve-victim' &&
    !is_link($shared_output) &&
    file_get_contents($shared_output) === 'safe-release-notes',
  'Atomic shared-artifact publication followed an attacker-controlled symlink'
);
unlink($shared_output);
symlink($victim, $shared_output);
test_assert(
  plugin_manager_remove_shared_artifact($shared_output) &&
    file_get_contents($victim) === 'preserve-victim' &&
    !file_exists($shared_output) &&
    !is_link($shared_output),
  'Shared-artifact removal followed an attacker-controlled symlink'
);

$attribute_fixture = "$root/attribute-revalidation.plg";
file_put_contents($attribute_fixture, '<PLUGIN pluginURL="https://old.invalid/plugin.plg"/>');
test_assert(
  plugin_attribute_uncached('pluginURL', $attribute_fixture) === 'https://old.invalid/plugin.plg',
  'Uncached plugin attribute reader did not read the original identity'
);
file_put_contents($attribute_fixture, '<PLUGIN pluginURL="https://new.invalid/plugin.plg"/>');
clearstatcache(true, $attribute_fixture);
test_assert(
  plugin_attribute_uncached('pluginURL', $attribute_fixture) === 'https://new.invalid/plugin.plg',
  'Uncached plugin attribute reader retained a replaced same-path plugin identity'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_publish_plugin_check_artifact($plugin,$generation,$download_receipt,$latest)'),
  'PluginAPI bypasses ordered atomic publication for its uniquely downloaded artifact'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_create_private_download_file()') &&
    str_contains($plugin_source, 'plugin_manager_create_private_download_file()') &&
    !str_contains($plugin_source, "tempnam(\$tmp, '.plugin-check-')"),
  'API or CLI network checks still stage candidates in the shared artifact directory'
);
test_assert(
  str_contains($plugin_api_source, 'plugin_manager_write_shared_artifact($changes_path,$changes)') &&
    str_contains($plugin_api_source, "plugin_manager_write_shared_artifact('/tmp/plugins/my_alerts.txt',\$alerts)") &&
    !str_contains($plugin_api_source, "file_put_contents('/tmp/plugins/my_alerts.txt'"),
  'PluginAPI release-note or alert output bypasses safe shared-artifact publication'
);
$update_block = strpos($plugin_source, "if (\$method == 'update')");
$update_gate = strpos(
  $plugin_source,
  'plugin_manager_plugin_check_artifact_is_current($plugin, $plugin_file)',
  $update_block
);
$update_install = strpos($plugin_source, "plugin('install', \$plugin_file, \$error)", $update_block);
$update_pre_hooks = strpos($plugin_source, 'pre_hooks();', $update_block);
$update_snapshot = strpos(
  $plugin_source,
  'plugin_manager_snapshot_plugin_check_artifact($plugin, $plugin_file)',
  $update_block
);
$snapshot_path = strpos(
  $plugin_source,
  "\$plugin_file = \$snapshot_receipt['path'];",
  $update_block
);
$snapshot_min = strpos($plugin_source, "plugin('min', \$plugin_file, \$error)", $update_block);
$snapshot_version = strpos(
  $plugin_source,
  "plugin('version', \$plugin_file, \$error)",
  $update_block
);
$snapshot_verify = strpos(
  $plugin_source,
  'plugin_manager_read_plugin_check_snapshot($snapshot_receipt)',
  $update_install
);
$snapshot_policy = strpos(
  $plugin_source,
  "plugin_manager_enforce_artifact_policy('update', \$plugin, \$snapshot_receipt)",
  $snapshot_path
);
$snapshot_persist = strpos(
  $plugin_source,
  'plugin_manager_commit_plugin_check_snapshot(',
  $update_install
);
test_assert(
  $update_block !== false &&
    $update_gate !== false &&
    $update_install !== false &&
    $update_pre_hooks !== false &&
    $update_gate < $update_pre_hooks &&
    $update_snapshot !== false &&
    $update_pre_hooks < $update_snapshot &&
    $snapshot_path !== false &&
    $update_snapshot < $snapshot_path &&
    $snapshot_min !== false &&
    $snapshot_version !== false &&
    $snapshot_policy !== false &&
    $snapshot_path < $snapshot_policy &&
    $snapshot_policy < $snapshot_min &&
    $snapshot_policy < $snapshot_version &&
    $snapshot_policy < $update_install &&
    $snapshot_path < $snapshot_min &&
    $snapshot_path < $snapshot_version &&
    $snapshot_min < $update_install &&
    $snapshot_version < $update_install &&
    $snapshot_verify !== false &&
    $update_install < $snapshot_verify &&
    $snapshot_persist !== false &&
    $snapshot_verify < $snapshot_persist,
  'Plugin update does not validate, execute, and persist one post-hook snapshot'
);
$policy_error = strpos($plugin_source, 'catch (Throwable $policy_error)', $snapshot_policy);
$policy_cleanup = strpos($plugin_source, 'post_hooks($error);', $policy_error);
$policy_exit = strpos($plugin_source, 'done(1);', $policy_cleanup);
test_assert(
  $policy_error !== false &&
    $policy_cleanup !== false &&
    $policy_exit !== false &&
    $snapshot_policy < $policy_error &&
    $policy_error < $policy_cleanup &&
    $policy_cleanup < $policy_exit &&
    $policy_exit < $update_install,
  'Artifact-policy rejection does not clean hook state before update exits'
);
$update_remove = strpos($plugin_source, "if (\$method == 'remove')", $update_block);
$update_source = $update_remove !== false
  ? substr($plugin_source, $update_block, $update_remove - $update_block)
  : '';
test_assert(
  !str_contains($update_source, 'unlink($symlink)') &&
    !str_contains($update_source, 'symlink($target, $symlink)'),
  'Plugin update still invalidates the installed link around persistence'
);

$directory = test_directory($root, 'artifact-publication-order');
$plugin = 'ordered.plg';
$latest = "$directory/$plugin";
$older = "$directory/older-download";
$newer = "$directory/newer-download";
file_put_contents($older, 'older');
file_put_contents($newer, 'newer');
$older_generation = plugin_manager_reserve_plugin_check_generation($plugin);
$newer_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  $newer_generation === $older_generation + 1,
  'Same-plugin publication generations are not monotonic'
);
test_assert(
  test_publish_plugin_check_artifact($plugin, $newer_generation, $newer, $latest),
  'Newer same-plugin writer could not publish'
);
test_assert(
  test_publish_plugin_check_artifact($plugin, $older_generation, $older, $latest),
  'Superseded same-plugin writer could not reuse the newer shared artifact'
);
test_assert(
  file_get_contents($latest) === 'newer' && is_file($older),
  'An older API/CLI writer overwrote a newer same-plugin artifact'
);

$first = "$directory/first-completion";
$second = "$directory/second-completion";
file_put_contents($first, 'first');
file_put_contents($second, 'second');
$first_generation = plugin_manager_reserve_plugin_check_generation($plugin);
$second_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact($plugin, $first_generation, $first, $latest),
  'A lock-owning check was suppressed merely because a later download had started'
);
test_assert(
  file_get_contents($latest) === 'first',
  'A current nested check did not publish a coherent artifact'
);
test_assert(
  test_publish_plugin_check_artifact($plugin, $second_generation, $second, $latest),
  'Later same-plugin writer could not replace the completed earlier artifact'
);
test_assert(
  file_get_contents($latest) === 'second',
  'Same-plugin publication order did not converge on the latest-started writer'
);

$directory = test_directory($root, 'api-before-cli-publication');
$plugin = 'shared.plg';
$api = test_start_process([
  '--api-artifact-writer', $directory, $plugin, 'api-older', '250'
]);
test_wait_for(
  fn() => is_file("$directory/api-older.reserved"),
  2.0,
  'API writer did not reserve its publication generation'
);
$cli = test_start_process([
  '--cli-artifact-writer', 'check', $directory, $plugin, 'cli-newer', '0'
]);
$cli_result = test_finish_process($cli);
$api_result = test_finish_process($api);
test_assert($cli_result[0] === 0, "CLI publication writer failed: {$cli_result[2]}");
test_assert($api_result[0] === 0, "API publication writer failed: {$api_result[2]}");
test_assert(
  file_get_contents("$directory/$plugin") === 'cli-newer',
  'An older API download overwrote a newer CLI publication'
);

$directory = test_directory($root, 'cli-before-api-publication');
$plugin = 'shared.plg';
$cli = test_start_process([
  '--cli-artifact-writer', 'check', $directory, $plugin, 'cli-current', '300'
]);
test_wait_for(
  fn() => is_file("$directory/cli-current.reserved"),
  2.0,
  'CLI writer did not reserve before entering its publication phase'
);
$api = test_start_process([
  '--api-artifact-writer', $directory, $plugin, 'api-later', '0'
]);
test_wait_for(
  fn() => is_file("$directory/api-later.reserved"),
  2.0,
  'API writer did not release its check lock before waiting for the global lock'
);
$cli_result = test_finish_process($cli);
$api_result = test_finish_process($api);
test_assert($cli_result[0] === 0, "Lock-owning CLI publication failed: {$cli_result[2]}");
test_assert($api_result[0] === 0, "Waiting API publication failed: {$api_result[2]}");
test_assert(
  file_get_contents("$directory/$plugin") === 'api-later',
  'API/CLI publication ordering deadlocked or retained the wrong generation'
);

$directory = test_directory($root, 'cli-download-before-publication-lock');
$plugin = 'phase-scoped.plg';
$holder = test_start_process([
  '--critical', 'install', $directory, 'phase-holder', '450', '0'
]);
test_wait_for(
  fn() => in_array('enter phase-holder', test_events($directory), true),
  2.0,
  'Phase-scoped check holder never acquired the host lock'
);
$cli = test_start_process([
  '--cli-artifact-writer', 'check', $directory, $plugin, 'phase-check', '50'
]);
test_wait_for(
  fn() => in_array('download-complete phase-check', test_events($directory), true),
  2.0,
  'CLI check download did not complete while the host lock was busy'
);
test_assert(
  !in_array('publish-enter phase-check', test_events($directory), true),
  'CLI check publication entered while another operation held the host lock'
);
$holder_result = test_finish_process($holder);
$cli_result = test_finish_process($cli);
test_assert($holder_result[0] === 0, "Phase-scoped holder failed: {$holder_result[2]}");
test_assert($cli_result[0] === 0, "Phase-scoped CLI check failed: {$cli_result[2]}");
test_assert(
  test_events($directory) === [
    'enter phase-holder',
    'download-start phase-check',
    'download-complete phase-check',
    'exit phase-holder',
    'publish-enter phase-check'
  ],
  'CLI check did not keep download outside and publication inside the host lock'
);

$directory = test_directory($root, 'lock-command-failure-invalidation');
$plugin = 'lock-command-failure.plg';
$latest = "$directory/$plugin";
$successful = "$directory/successful";
file_put_contents($successful, 'successful-before-lock-failure');
$successful_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact(
    $plugin,
    $successful_generation,
    $successful,
    $latest
  ),
  'Unable to publish the artifact preceding a lock-command failure'
);
$reserved_generation = plugin_manager_reserve_plugin_check_generation($plugin);
$previous_supervisor = getenv(PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV);
putenv(PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV."=$directory/not-executable");
test_assert_throws(
  fn() => plugin_manager_operation_lock_command('validate', __FILE__, $argv),
  'lock supervisor is not executable',
  'Lock-command failure injection did not fail before check publication'
);
plugin_manager_revoke_unserialized_plugin_check(
  $plugin,
  $reserved_generation,
  $latest
);
putenv(
  $previous_supervisor === false
    ? PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV
    : PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV."=$previous_supervisor"
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'A reserved generation retained an older artifact after lock-command construction failed'
);

$directory = test_directory($root, 'unsafe-global-lock-invalidation');
$plugin = 'unsafe-global-lock.plg';
$latest = "$directory/$plugin";
$successful = "$directory/successful";
file_put_contents($successful, 'successful-before-unsafe-lock');
$successful_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact(
    $plugin,
    $successful_generation,
    $successful,
    $latest
  ),
  'Unable to publish the artifact preceding an unsafe global lock'
);
$reserved_generation = plugin_manager_reserve_plugin_check_generation($plugin);
plugin_manager_prepare_lock_path("$directory/plugin-manager.lock", false);
unlink("$directory/plugin-manager.lock");
mkdir("$directory/plugin-manager.lock", 0700);
plugin_manager_revoke_unserialized_plugin_check(
  $plugin,
  $reserved_generation,
  $latest
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'A broken global lock path prevented durable reserved-generation revocation'
);

$isolated_docroot = "$root/isolated-docroot";
mkdir("$isolated_docroot/webGui/include", 0700, true);
mkdir("$isolated_docroot/plugins/dynamix/include", 0700, true);
mkdir("$isolated_docroot/plugins/dynamix.plugin.manager/include", 0700, true);
file_put_contents(
  "$isolated_docroot/webGui/include/Helpers.php",
  <<<'PHP'
<?PHP
function unscript($value) { return $value; }
function _var($values, $key) { return $values[$key] ?? ''; }
function my_explode($separator, $value) {
  return array_pad(explode($separator, $value, 2), 2, '');
}
PHP
);
file_put_contents(
  "$isolated_docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php",
  "<?PHP\n"
);
file_put_contents(
  "$isolated_docroot/plugins/dynamix/include/Secure.php",
  "<?PHP\n"
);
file_put_contents(
  "$isolated_docroot/plugins/dynamix/include/Translations.php",
  "<?PHP\n"
);
file_put_contents(
  "$isolated_docroot/webGui/include/Translations.php",
  "<?PHP\n"
);
symlink(
  "$repo/emhttp/plugins/dynamix.plugin.manager/include/PluginOperationLock.php",
  "$isolated_docroot/plugins/dynamix.plugin.manager/include/PluginOperationLock.php"
);

$directory = test_directory($root, 'real-api-unsafe-global-lock');
$api_wrapper = "$root/plugin-api-failure-wrapper";
$api_wrapper_source =
  "<?PHP\n".
  '$docroot = '.var_export($isolated_docroot, true).";\n".
  '$_POST = ["action" => "test-noop"];'."\n".
  'require '.var_export(
    "$repo/emhttp/plugins/dynamix.plugin.manager/scripts/PluginAPI.php",
    true
  ).";\n".
  <<<'PHP'
[$script, $directory] = $argv;
$plugin = 'api-unsafe-global-lock.plg';
$latest = "$directory/$plugin";
$successful = "$directory/successful";
file_put_contents($successful, 'successful-before-api-lock-failure');
$successful_generation = plugin_manager_reserve_plugin_check_generation($plugin);
$receipt = plugin_manager_capture_download_receipt($successful);
if (
  !is_array($receipt) ||
  !plugin_manager_publish_plugin_check_artifact(
    $plugin,
    $successful_generation,
    $receipt,
    $latest
  ) ||
  !plugin_manager_finalize_plugin_check_artifact(
    $plugin,
    $successful_generation,
    $latest
  )
) {
  exit(2);
}
$reserved_generation = plugin_manager_reserve_plugin_check_generation($plugin);
plugin_manager_prepare_lock_path("$directory/plugin-manager.lock", false);
unlink("$directory/plugin-manager.lock");
mkdir("$directory/plugin-manager.lock", 0700);
plugin_manager_revoke_failed_api_plugin_check(
  null,
  $reserved_generation,
  false,
  $plugin,
  $latest
);
exit(
  !file_exists($latest) &&
  !plugin_manager_plugin_check_artifact_is_current($plugin, $latest)
    ? 0
    : 3
);
PHP;
file_put_contents($api_wrapper, $api_wrapper_source);
$api_environment = getenv();
$api_environment[PLUGIN_MANAGER_LOCK_PATH_ENV] = "$directory/plugin-manager.lock";
$api_result = test_finish_process(
  test_start_command([PHP_BINARY, $api_wrapper, $directory], $api_environment)
);
test_assert(
  $api_result[0] === 0,
  "The real PluginAPI fallback did not durably revoke through an unsafe global lock: ".
    "{$api_result[1]} {$api_result[2]}"
);

$directory = test_directory($root, 'show-plugins-remove-alert');
$retained_alert = "$directory/my_alerts.txt";
file_put_contents($retained_alert, 'unrelated alert');
$show_wrapper = "$root/show-plugins-remove-wrapper";
$show_wrapper_source =
  "<?PHP\n".
  'error_reporting(E_ALL);'."\n".
  '$docroot = '.var_export($isolated_docroot, true).";\n".
  '$alerts = '.var_export($retained_alert, true).";\n".
  '$_GET = ["audit" => "removed-plugin.plg:remove"];'."\n".
  'ob_start();'."\n".
  'require '.var_export(
    "$repo/emhttp/plugins/dynamix.plugin.manager/include/ShowPlugins.php",
    true
  ).";\n".
  'ob_end_clean();'."\n".
  'exit(file_get_contents($alerts) === "unrelated alert" ? 0 : 1);'."\n";
file_put_contents($show_wrapper, $show_wrapper_source);
$show_result = test_finish_process(
  test_start_command([PHP_BINARY, '-d', 'short_open_tag=1', $show_wrapper])
);
test_assert(
  $show_result[0] === 0,
  "A real ShowPlugins remove audit failed with status {$show_result[0]}: ".
    "{$show_result[1]} {$show_result[2]}"
);

$directory = test_directory($root, 'failed-generation-invalidation');
$plugin = 'revoked.plg';
$latest = "$directory/$plugin";
$first = "$directory/first-success";
file_put_contents($first, 'first-success');
$first_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_stage_plugin_check_artifact(
    $plugin,
    $first_generation,
    $first,
    $latest
  ),
  'Initial artifact could not be staged'
);
test_assert(
  !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Plugin update accepted an artifact before semantic check finalization'
);
test_assert(
  plugin_manager_finalize_plugin_check_artifact(
    $plugin,
    $first_generation,
    $latest
  ),
  'Semantically successful artifact could not be finalized'
);
test_assert(
  plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Fresh successful artifact was not accepted by the update gate'
);
file_put_contents($latest, 'tampered-after-publication');
test_assert(
  !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Plugin update accepted an artifact that no longer matched its published generation'
);
file_put_contents($latest, 'first-success');
test_assert(
  plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Restored published artifact did not match its recorded generation'
);
$failed_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  plugin_manager_invalidate_plugin_check_artifact(
    $plugin,
    $failed_generation,
    $latest
  ),
  'Failed current generation could not revoke the cached artifact'
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Plugin update could still consume a stale artifact after a failed check'
);
$obsolete = "$directory/obsolete-download";
file_put_contents($obsolete, 'obsolete');
test_assert(
  !test_stage_plugin_check_artifact(
    $plugin,
    $first_generation,
    $obsolete,
    $latest
  ),
  'An obsolete writer revived an artifact after a newer failed generation'
);

$newer = "$directory/newer-success";
file_put_contents($newer, 'newer-success');
$newer_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact(
    $plugin,
    $newer_generation,
    $newer,
    $latest
  ),
  'Newer successful generation could not recover a revoked cache'
);
test_assert(
  !plugin_manager_invalidate_plugin_check_artifact(
    $plugin,
    $failed_generation,
    $latest
  ),
  'An older failed check claimed to invalidate a newer publication'
);
test_assert(
  file_get_contents($latest) === 'newer-success' &&
    plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'An older API/CLI failure deleted or revoked a newer publication'
);
test_assert(
  plugin_manager_invalidate_plugin_check_artifact(
    $plugin,
    $newer_generation,
    $latest
  ),
  'Failure after publication could not revoke its own artifact'
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'A parse failure left its own published artifact available to update'
);

$directory = test_directory($root, 'api-rejection-atomic-invalidation');
$plugin = 'api-rejected.plg';
$latest = "$directory/$plugin";
$initial = "$directory/initial-success";
file_put_contents($initial, 'initial-success');
$initial_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact(
    $plugin,
    $initial_generation,
    $initial,
    $latest
  ),
  'Initial API rejection fixture could not be published'
);
$failed_generation = plugin_manager_reserve_plugin_check_generation($plugin);
$updater = null;
$response = plugin_manager_with_plugin_check_operation_lock(
  $plugin,
  $failed_generation,
  $latest,
  static function() use ($directory, $plugin, &$updater) {
    $updater = test_start_process([
      '--check-artifact-gate-reader',
      $directory,
      $plugin
    ]);
    test_wait_for(
      fn() => is_file("$directory/update-blocked"),
      2.0,
      'Concurrent update was not blocked after API rejection'
    );
    test_assert(
      !is_file("$directory/update-entered"),
      'Concurrent update entered before the rejected API generation was invalidated'
    );
    return null;
  }
);
test_assert($response === null, 'Rejected API callback returned a successful response');
test_assert(is_array($updater), 'Rejected API callback did not start its concurrent update');
$update_result = test_finish_process($updater);
test_assert(
  $update_result[0] === 0,
  "Concurrent update gate failed: {$update_result[2]}"
);
test_assert(
  file_get_contents("$directory/update-observed") === 'rejected',
  'Concurrent update consumed the prior artifact between API rejection and invalidation'
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Rejected API generation did not revoke the prior successful artifact'
);

$throwing_candidate = "$directory/throwing-candidate";
file_put_contents($throwing_candidate, 'throwing-candidate');
$throwing_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert_throws(
  static function() use (
    $plugin,
    $throwing_generation,
    $throwing_candidate,
    $latest
  ): void {
    plugin_manager_with_plugin_check_operation_lock(
      $plugin,
      $throwing_generation,
      $latest,
      static function() use (
        $plugin,
        $throwing_generation,
        $throwing_candidate,
        $latest
      ): never {
        if (
          !test_publish_plugin_check_artifact(
            $plugin,
            $throwing_generation,
            $throwing_candidate,
            $latest
          )
        ) {
          test_fail('Throwing API failure fixture could not be published');
        }
        throw new RuntimeException('injected API callback failure');
      }
    );
  },
  'injected API callback failure',
  'Thrown API callback failure did not escape its host-lock scope'
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Thrown API callback failure left its finalized artifact available to update'
);

foreach (
  [
    'state-short-write' => [
      ['stage_write_after' => 3],
      'Injected Plugin Manager short write'
    ],
    'state-rename-failure' => [
      ['rename' => true],
      'Unable to atomically commit Plugin Manager check state'
    ]
  ] as $failure => [$faults, $expected_diagnostic]
) {
  $directory = test_directory($root, "failed-invalidation-$failure");
  $plugin = "$failure.plg";
  $latest = "$directory/$plugin";
  $candidate = "$directory/initial-success";
  file_put_contents($candidate, "initial-success-$failure");
  $initial_generation = plugin_manager_reserve_plugin_check_generation($plugin);
  test_assert(
    test_publish_plugin_check_artifact(
      $plugin,
      $initial_generation,
      $candidate,
      $latest
    ),
    "Initial $failure invalidation fixture could not be published"
  );
  $failed_generation = plugin_manager_reserve_plugin_check_generation($plugin);
  $state_lock = "$directory/check-".hash('sha256', $plugin).'.lock';
  $state_path = "$state_lock.state";
  $state_before_failure = file_get_contents($state_path);
  $updater = null;
  $diagnostic = null;

  try {
    plugin_manager_with_operation_lock(
      static function() use (
        $directory,
        $plugin,
        $failed_generation,
        $latest,
        $failure,
        $faults,
        &$updater
      ): void {
        $updater = test_start_process([
          '--check-artifact-gate-reader',
          $directory,
          $plugin
        ]);
        test_wait_for(
          fn() => is_file("$directory/update-blocked"),
          2.0,
          "Concurrent update was not blocked before $failure invalidation"
        );
        plugin_manager_invalidate_plugin_check_artifact(
          $plugin,
          $failed_generation,
          $latest,
          $faults
        );
      }
    );
  } catch (Throwable $error) {
    $diagnostic = $error->getMessage();
  }

  test_assert(
    $diagnostic === $expected_diagnostic,
    "$failure invalidation did not preserve its state-write diagnostic: ".
      (string)$diagnostic
  );
  test_assert(
    file_get_contents($state_path) === $state_before_failure,
    "$failure invalidation damaged the prior durable generation state"
  );
  test_assert(
    is_array($updater),
    "$failure invalidation did not start its concurrent update"
  );
  $update_result = test_finish_process($updater);
  test_assert(
    $update_result[0] === 0,
    "Concurrent update after $failure invalidation failed: {$update_result[2]}"
  );
  test_assert(
    file_get_contents("$directory/update-observed") === 'rejected',
    "Concurrent update consumed old bytes after $failure invalidation"
  );
  test_assert(
    !file_exists($latest) &&
      !is_link($latest) &&
      !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
    "$failure invalidation left the prior artifact available to update"
  );
}

$directory = test_directory($root, 'failed-invalidation-quarantine');
$plugin = 'failed-quarantine.plg';
$latest = "$directory/$plugin";
$candidate = "$directory/initial-success";
file_put_contents($candidate, 'initial-success');
$initial_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact(
    $plugin,
    $initial_generation,
    $candidate,
    $latest
  ),
  'Initial failed-quarantine fixture could not be published'
);
$failed_generation = plugin_manager_reserve_plugin_check_generation($plugin);
unlink($latest);
mkdir($latest, 0700);
test_assert_throws(
  fn() => plugin_manager_with_operation_lock(
    fn() => plugin_manager_invalidate_plugin_check_artifact(
      $plugin,
      $failed_generation,
      $latest,
      ['rename' => true]
    )
  ),
  'Unable to atomically commit Plugin Manager check state; '.
    'Unable to quarantine Plugin Manager check artifact',
  'Failed generation-state commit hid an unverifiable artifact quarantine'
);
rmdir($latest);

$directory = test_directory($root, 'post-hook-incompatible-snapshot');
$snapshot_environment = test_activate_snapshot_scope($directory);
$plugin = 'hook-refresh.plg';
$latest = "$directory/$plugin";
$initial = "$directory/initial-compatible";
$refreshed = "$directory/refreshed-incompatible";
file_put_contents(
  $initial,
  '<PLUGIN name="hook-refresh" version="2026.07.18" min="7.0.0"/>'
);
file_put_contents(
  $refreshed,
  '<PLUGIN name="hook-refresh" version="2026.07.19" min="99.0.0"/>'
);
$snapshot_receipt = plugin_manager_with_operation_lock(
  function () use ($plugin, $latest, $initial, $refreshed): array {
    $initial_generation = plugin_manager_reserve_plugin_check_generation($plugin);
    test_assert(
      test_publish_plugin_check_artifact(
        $plugin,
        $initial_generation,
        $initial,
        $latest
      ),
      'Initial compatible update artifact could not be finalized'
    );

    // Simulate the standard update pre-hook publishing a refreshed generation.
    $refreshed_generation = plugin_manager_reserve_plugin_check_generation($plugin);
    test_assert(
      test_publish_plugin_check_artifact(
        $plugin,
        $refreshed_generation,
        $refreshed,
        $latest
      ),
      'Standard pre-hook could not publish its refreshed generation'
    );
    $receipt = plugin_manager_snapshot_plugin_check_artifact($plugin, $latest);
    test_assert($receipt !== null, 'Post-hook generation could not be snapshotted');
    test_assert(
      $receipt['generation'] === $refreshed_generation,
      'Update receipt did not select the generation published by the pre-hook'
    );
    return $receipt;
  }
);
test_assert(
  (fileperms($snapshot_receipt['path']) & 07777) === 0400,
  'Post-hook update snapshot is not read-only'
);
test_assert(
  plugin_attribute_uncached('min', $snapshot_receipt['path']) === '99.0.0' &&
    version_compare('7.2.0', '99.0.0', '<'),
  'Post-hook compatibility decision did not use the newer incompatible generation'
);
test_restore_snapshot_scope($snapshot_environment);

$safe_policy_plg =
  '<PLUGIN name="community.safe" version="2026.07.18" '.
  'pluginURL="https://plugins.invalid/community.safe.plg"/>';
$new_safe_policy_plg =
  '<PLUGIN name="community.safe" version="2026.07.19" '.
  'pluginURL="https://plugins.invalid/community.safe.plg"/>';
$core_policy_plg = <<<'PLG'
<?xml version="1.0"?>
<!DOCTYPE PLUGIN [
  <!ENTITY plugin_name "unraid.core.dev">
  <!ENTITY plugin_url "https://preview.dl.unraid.net/unraid-core/main/unraid.core.dev.plg">
]>
<PLUGIN name="&plugin_name;" version="2026.07.19" pluginURL="&plugin_url;"/>
PLG;

$policy_race = test_run_artifact_policy_update(
  $root,
  'artifact-policy-refresh-race',
  $safe_policy_plg,
  $core_policy_plg,
  $artifact_policy,
  'identity'
);
test_assert(
  $policy_race['result'][0] === 1 &&
    str_contains(
      file_get_contents("{$policy_race['directory']}/policy-error"),
      'Core artifact rejected'
    ),
  'Exact-snapshot policy accepted Core bytes published by the update pre-hook'
);
test_assert(
  !is_file("{$policy_race['directory']}/install-observed"),
  'Rejected post-hook Core snapshot reached the candidate install boundary'
);
test_assert(
  file_get_contents($policy_race['latest']) === $core_policy_plg &&
    plugin_manager_plugin_check_artifact_is_current(
      $policy_race['plugin'],
      $policy_race['latest']
    ),
  'Policy rejection globally revoked or changed the system-owned update artifact'
);
test_assert(
  !is_file($policy_race['receipt']['path']) &&
    !is_dir($policy_race['receipt']['scope']) &&
    !is_dir($policy_race['receipt']['scope'].'.snapshots'),
  'Policy rejection leaked its private finalized snapshot or lock scope'
);

$policy_safe = test_run_artifact_policy_update(
  $root,
  'artifact-policy-safe-refresh',
  $safe_policy_plg,
  $new_safe_policy_plg,
  $artifact_policy,
  'identity'
);
test_assert(
  $policy_safe['result'][0] === 0 &&
    file_get_contents("{$policy_safe['directory']}/install-observed") ===
      $new_safe_policy_plg,
  'Artifact policy did not allow the exact safe post-hook snapshot'
);
test_assert(
  json_decode(
    file_get_contents("{$policy_safe['directory']}/policy-observed"),
    true,
    8,
    JSON_THROW_ON_ERROR
  )['hash'] === hash('sha256', $new_safe_policy_plg),
  'Artifact policy did not observe the finalized post-hook snapshot hash'
);
test_assert(
  !is_file($policy_safe['receipt']['path']) &&
    !is_dir($policy_safe['receipt']['scope'].'.snapshots'),
  'Successful artifact policy leaked its private finalized snapshot'
);

$policy_failure = test_run_artifact_policy_update(
  $root,
  'artifact-policy-execution-failure',
  $safe_policy_plg,
  $new_safe_policy_plg,
  $artifact_policy,
  'fail'
);
test_assert(
  $policy_failure['result'][0] === 1 &&
    str_contains(
      file_get_contents("{$policy_failure['directory']}/policy-error"),
      'injected policy failure'
    ) &&
    !is_file("{$policy_failure['directory']}/install-observed"),
  'Configured artifact-policy execution failure did not fail closed'
);
test_assert(
  file_get_contents($policy_failure['latest']) === $new_safe_policy_plg &&
    plugin_manager_plugin_check_artifact_is_current(
      $policy_failure['plugin'],
      $policy_failure['latest']
    ) &&
    !is_file($policy_failure['receipt']['path']) &&
    !is_dir($policy_failure['receipt']['scope'].'.snapshots'),
  'Artifact-policy execution failure damaged shared state or leaked its snapshot'
);

$policy_mutation = test_run_artifact_policy_update(
  $root,
  'artifact-policy-mutation',
  $safe_policy_plg,
  $new_safe_policy_plg,
  $artifact_policy,
  'mutate'
);
test_assert(
  $policy_mutation['result'][0] === 1 &&
    str_contains(
      file_get_contents("{$policy_mutation['directory']}/policy-error"),
      'changed the private update snapshot'
    ) &&
    !is_file("{$policy_mutation['directory']}/install-observed") &&
    file_get_contents($policy_mutation['latest']) === $new_safe_policy_plg &&
    plugin_manager_plugin_check_artifact_is_current(
      $policy_mutation['plugin'],
      $policy_mutation['latest']
    ) &&
    !is_file($policy_mutation['receipt']['path']) &&
    !is_dir($policy_mutation['receipt']['scope'].'.snapshots'),
  'Artifact policy transformed an approved candidate or damaged shared state'
);

$policy_validation_directory = test_directory($root, 'artifact-policy-validation');
putenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV);
plugin_manager_enforce_artifact_policy('update', 'opt-out.plg', []);
$relative_policy = basename($artifact_policy);
putenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV."=$relative_policy");
test_assert_throws(
  fn() => plugin_manager_artifact_policy_executable(),
  'must be absolute',
  'Artifact policy accepted a relative executable path'
);
$policy_symlink = "$policy_validation_directory/policy-link";
symlink($artifact_policy, $policy_symlink);
putenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV."=$policy_symlink");
test_assert_throws(
  fn() => plugin_manager_artifact_policy_executable(),
  'canonical regular executable',
  'Artifact policy accepted a symlink executable'
);
chmod($artifact_policy, 0775);
putenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV."=$artifact_policy");
test_assert_throws(
  fn() => plugin_manager_artifact_policy_executable(),
  'must not be writable',
  'Artifact policy accepted a group-writable executable'
);
chmod($artifact_policy, 0755);
putenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV);

$directory = test_directory($root, 'nested-check-snapshot-consistency');
$snapshot_environment = test_activate_snapshot_scope($directory);
$plugin = 'nested-replacement.plg';
$latest = "$directory/$plugin";
$selected = "$directory/selected-generation";
$nested = "$directory/nested-generation";
$persisted = "$directory/persisted.plg";
file_put_contents(
  $selected,
  '<PLUGIN name="nested-replacement" version="2026.07.18"><FILE Run="/bin/true"/></PLUGIN>'
);
file_put_contents(
  $nested,
  '<PLUGIN name="nested-replacement" version="2026.07.19"><FILE Run="/bin/false"/></PLUGIN>'
);
$consistency = plugin_manager_with_operation_lock(
  function () use ($plugin, $latest, $selected, $nested, $persisted): array {
    $selected_generation = plugin_manager_reserve_plugin_check_generation($plugin);
    test_assert(
      test_publish_plugin_check_artifact(
        $plugin,
        $selected_generation,
        $selected,
        $latest
      ),
      'Executable update generation could not be finalized'
    );
    $receipt = plugin_manager_snapshot_plugin_check_artifact($plugin, $latest);
    test_assert($receipt !== null, 'Executable generation could not be snapshotted');
    $executed = plugin_manager_read_plugin_check_snapshot($receipt);
    test_assert($executed !== false, 'Executable snapshot did not match its receipt');

    // Simulate a nested same-plugin check from a FILE Run replacing the cache.
    $nested_generation = plugin_manager_reserve_plugin_check_generation($plugin);
    test_assert(
      test_publish_plugin_check_artifact(
        $plugin,
        $nested_generation,
        $nested,
        $latest
      ),
      'Nested same-plugin check could not publish its replacement'
    );
    $persisted_contents = plugin_manager_read_plugin_check_snapshot($receipt);
    test_assert($persisted_contents !== false, 'Selected snapshot changed during nested check');
    file_put_contents($persisted, $persisted_contents);
    return [
      'executed' => $executed,
      'persisted' => file_get_contents($persisted),
      'shared' => file_get_contents($latest),
      'selected_generation' => $selected_generation,
      'nested_generation' => $nested_generation
    ];
  }
);
test_assert(
  $consistency['executed'] === $consistency['persisted'],
  'Plugin execution and persistence used different generations'
);
test_assert(
  $consistency['shared'] !== $consistency['persisted'] &&
    $consistency['selected_generation'] < $consistency['nested_generation'],
  'Nested same-plugin check did not replace only the mutable shared cache'
);
test_restore_snapshot_scope($snapshot_environment);

$directory = test_directory($root, 'atomic-snapshot-persistence');
$target = "$directory/boot-plugin.plg";
$prior_target = "$directory/prior-plugin.plg";
$installed_link = "$directory/installed-plugin.plg";
$old_target_contents = '<PLUGIN name="atomic" version="old"/>';
file_put_contents($target, $old_target_contents);
file_put_contents($prior_target, '<PLUGIN name="atomic" version="prior"/>');
symlink($prior_target, $installed_link);
$persistence_failures = [
  'stage-open' => ['stage_open' => true],
  'short-write' => ['stage_write_after' => 7],
  'target-rename' => ['target_rename' => true],
  'symlink-swap' => ['symlink_swap' => true]
];
foreach ($persistence_failures as $failure => $faults) {
  $failed = false;
  try {
    plugin_manager_commit_plugin_check_snapshot(
      $snapshot_receipt,
      $target,
      $installed_link,
      $faults
    );
  } catch (Throwable) {
    $failed = true;
  }
  test_assert($failed, "Injected $failure persistence fault unexpectedly succeeded");
  test_assert(
    file_get_contents($target) === $old_target_contents,
    "Injected $failure persistence fault damaged the prior boot PLG"
  );
  test_assert(
    is_link($installed_link) && readlink($installed_link) === $prior_target,
    "Injected $failure persistence fault damaged the installed symlink"
  );
  test_assert(
    glob("$directory/.boot-plugin.plg.plugin-*") === [],
    "Injected $failure persistence fault left a sibling staging file"
  );
}
$executed_snapshot = plugin_manager_read_plugin_check_snapshot($snapshot_receipt);
test_assert($executed_snapshot !== false, 'Atomic persistence receipt became unreadable');
$rollback_failure = null;
try {
  plugin_manager_commit_plugin_check_snapshot(
    $snapshot_receipt,
    $target,
    $installed_link,
    ['symlink_swap' => true, 'rollback_target_rename' => true]
  );
} catch (Throwable $error) {
  $rollback_failure = $error->getMessage();
}
test_assert(
  is_string($rollback_failure) &&
    preg_match(
      '/rollback copy preserved at ([^;]+)$/D',
      $rollback_failure,
      $rollback_match
    ) === 1,
  'Failed target restoration did not report its preserved rollback copy'
);
$preserved_backup = $rollback_match[1];
test_assert(
  is_file($preserved_backup) &&
    !is_link($preserved_backup) &&
    file_get_contents($preserved_backup) === $old_target_contents,
  'Failed target restoration deleted or damaged its only rollback copy'
);
test_assert(
  file_get_contents($target) === $executed_snapshot &&
    is_link($installed_link) &&
    readlink($installed_link) === $prior_target,
  'Failed target restoration left an unreported persistence state'
);
@unlink($preserved_backup);
file_put_contents($target, $old_target_contents);
plugin_manager_commit_plugin_check_snapshot(
  $snapshot_receipt,
  $target,
  $installed_link
);
test_assert(
  file_get_contents($target) === $executed_snapshot &&
    hash_file('sha256', $target) === $snapshot_receipt['hash'],
  'Successful persistence did not commit the exact executed snapshot'
);
test_assert(
  is_link($installed_link) && readlink($installed_link) === $target,
  'Successful persistence did not atomically update the installed symlink'
);

$plugins_page_source = file_get_contents(
  dirname(__DIR__, 2).'/emhttp/plugins/dynamix.plugin.manager/Plugins.page'
);
test_assert(
  str_contains($plugins_page_source, 'plugin_manager_with_nonblocking_operation_lock'),
  'Plugins.page stale cleanup does not use the nonblocking host lock'
);
test_assert(
  !str_contains($plugins_page_source, 'pgrep --ns'),
  'Plugins.page still uses process-name inspection instead of the host lock'
);

$directory = "$root/path-create";
putenv(PLUGIN_MANAGER_LOCK_PATH_ENV."=$directory/plugin-manager.lock");
$command = plugin_manager_operation_lock_command('install', __FILE__, $argv);
test_assert($command !== null, 'A stateful operation did not prepare a lock command');
test_assert(is_dir($directory), 'Plugin Manager did not create its private lock directory');
test_assert(is_file("$directory/plugin-manager.lock"), 'Plugin Manager did not create its lock file');
test_assert(
  (fileperms($directory) & 07777) === 0700,
  'New Plugin Manager lock directory does not have mode 0700'
);
test_assert(
  (fileperms("$directory/plugin-manager.lock") & 07777) === 0600,
  'New Plugin Manager lock file does not have mode 0600'
);

$directory = test_directory($root, 'path-mode');
$command = plugin_manager_operation_lock_command('install', __FILE__, $argv);
test_assert($command !== null, 'A stateful operation did not prepare a lock command');
test_assert(
  (fileperms($directory) & 07777) === 0700,
  'Plugin Manager lock directory does not have mode 0700'
);
test_assert(
  (fileperms("$directory/plugin-manager.lock") & 07777) === 0600,
  'Plugin Manager lock file does not have mode 0600'
);

$directory = test_directory($root, 'retired-member-scope');
$scope = "$directory/member.scope";
mkdir($scope, 0700);
test_assert(
  plugin_manager_lock_scope_is_safe($scope),
  'Valid member scope was rejected before the retirement regression'
);
$lease = "$scope/".getmypid().'.'.plugin_manager_process_start_time(getmypid());
rmdir($scope);
test_assert(
  !plugin_manager_create_lock_member_lease($scope, $lease),
  'A scope retired between validation and lease fopen did not request fresh lock acquisition'
);

$directory = test_directory($root, 'retired-scope-reacquire');
$token = str_repeat('b', 32);
$scope = "$directory/plugin-manager.lock.scope.$token";
$owner_environment = getenv();
$owner_environment[PLUGIN_MANAGER_LOCK_TOKEN_ENV] = $token;
$owner_environment[PLUGIN_MANAGER_LOCK_SCOPE_ENV] = $scope;
$owner = test_start_command(['/bin/sleep', '2'], $owner_environment);
$owner_status = proc_get_status($owner[0]);
$owner_pid = $owner_status['pid'];
test_wait_for(
  function () use ($owner_pid, $token): bool {
    $environment = @file_get_contents("/proc/$owner_pid/environ");
    return $environment !== false &&
      str_contains($environment, PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token");
  },
  1.0,
  'Synthetic inherited lock owner did not expose its identity'
);
$inherited_environment = [
  PLUGIN_MANAGER_LOCK_OWNER_PID_ENV => getenv(PLUGIN_MANAGER_LOCK_OWNER_PID_ENV),
  PLUGIN_MANAGER_LOCK_TOKEN_ENV => getenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV),
  PLUGIN_MANAGER_LOCK_SCOPE_ENV => getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV)
];
putenv(PLUGIN_MANAGER_LOCK_OWNER_PID_ENV."=$owner_pid");
putenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token");
putenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV."=$scope");
$command = plugin_manager_operation_lock_command('check', __FILE__, $argv);
foreach ($inherited_environment as $name => $value) {
  putenv($value === false ? $name : "$name=$value");
}
proc_terminate($owner[0]);
test_finish_process($owner);
test_assert(
  $command !== null,
  'A retired inherited scope did not fall back to a fresh global lock acquisition'
);

$directory = test_directory($root, 'version-one-scope-drain');
$token = str_repeat('c', 32);
$scope = "$directory/plugin-manager.lock.scope.$token";
mkdir($scope, 0700);
$owner_environment = getenv();
$owner_environment[PLUGIN_MANAGER_LOCK_TOKEN_ENV] = $token;
$owner_environment[PLUGIN_MANAGER_LOCK_SCOPE_ENV] = $scope;
unset($owner_environment[PLUGIN_MANAGER_LOCK_PROTOCOL_ENV]);
$owner = test_start_command(['/bin/sleep', '2'], $owner_environment);
$owner_status = proc_get_status($owner[0]);
$owner_pid = $owner_status['pid'];
test_wait_for(
  function () use ($owner_pid, $token): bool {
    $environment = @file_get_contents("/proc/$owner_pid/environ");
    return $environment !== false &&
      str_contains($environment, PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token");
  },
  1.0,
  'Version-1 lock owner did not expose its identity'
);
$inherited_environment = [
  PLUGIN_MANAGER_LOCK_OWNER_PID_ENV => getenv(PLUGIN_MANAGER_LOCK_OWNER_PID_ENV),
  PLUGIN_MANAGER_LOCK_TOKEN_ENV => getenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV),
  PLUGIN_MANAGER_LOCK_SCOPE_ENV => getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV)
];
putenv(PLUGIN_MANAGER_LOCK_OWNER_PID_ENV."=$owner_pid");
putenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token");
putenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV."=$scope");
$command = plugin_manager_operation_lock_command('remove', __FILE__, $argv);
foreach ($inherited_environment as $name => $value) {
  putenv($value === false ? $name : "$name=$value");
}
proc_terminate($owner[0]);
test_finish_process($owner);
test_assert(
  $command === null,
  'New Plugin Manager code did not preserve an in-flight version-1 reentrant scope'
);

$directory = test_directory($root, 'replaced-member-scope');
$scope = "$directory/member.scope";
mkdir($scope, 0700);
test_assert(
  plugin_manager_lock_scope_is_safe($scope),
  'Valid member scope was rejected before the replacement regression'
);
$lease = "$scope/".getmypid().'.'.plugin_manager_process_start_time(getmypid());
rmdir($scope);
mkdir($scope, 0755);
test_assert_throws(
  fn() => plugin_manager_create_lock_member_lease($scope, $lease),
  'missing or unsafe',
  'A scope replaced with an unsafe mode during lease creation did not fail closed'
);

$directory = test_directory($root, 'unsafe-member-scope');
$scope = "$directory/member.scope";
mkdir($scope, 0755);
test_assert_throws(
  fn() => plugin_manager_register_lock_member($scope),
  'missing or unsafe',
  'Plugin Manager accepted an inherited scope with an unsafe mode'
);

$directory = test_directory($root, 'path-mode-repair');
file_put_contents("$directory/plugin-manager.lock", '');
chmod("$directory/plugin-manager.lock", 0644);
plugin_manager_operation_lock_command('install', __FILE__, $argv);
clearstatcache(true, "$directory/plugin-manager.lock");
test_assert(
  (fileperms("$directory/plugin-manager.lock") & 07777) === 0600,
  'Plugin Manager did not repair the mode of an owned regular lock file'
);

$directory = test_directory($root, 'symlink-leaf');
file_put_contents("$directory/target", '');
symlink("$directory/target", "$directory/plugin-manager.lock");
test_assert_throws(
  fn() => plugin_manager_operation_lock_command('install', __FILE__, $argv),
  'not a regular file',
  'Plugin Manager accepted a symlink lock file'
);

$directory = test_directory($root, 'nonregular-leaf');
mkdir("$directory/plugin-manager.lock", 0700);
test_assert_throws(
  fn() => plugin_manager_operation_lock_command('install', __FILE__, $argv),
  'not a regular file',
  'Plugin Manager accepted a non-regular lock path'
);

$target_parent = test_directory($root, 'symlink-parent-target');
$symlink_parent = "$root/symlink-parent";
symlink($target_parent, $symlink_parent);
putenv(PLUGIN_MANAGER_LOCK_PATH_ENV."=$symlink_parent/plugin-manager.lock");
test_assert_throws(
  fn() => plugin_manager_operation_lock_command('install', __FILE__, $argv),
  'not a directory',
  'Plugin Manager accepted a symlink lock parent'
);

$directory = "$root/insecure-parent";
mkdir($directory, 0755);
putenv(PLUGIN_MANAGER_LOCK_PATH_ENV."=$directory/plugin-manager.lock");
test_assert_throws(
  fn() => plugin_manager_operation_lock_command('install', __FILE__, $argv),
  'mode 0700',
  'Plugin Manager accepted an insecure lock directory mode'
);

if (plugin_manager_effective_user_id() === 0 && function_exists('chown')) {
  $directory = test_directory($root, 'production-owner');
  chown($directory, 65534);
  test_assert_throws(
    fn() => plugin_manager_prepare_lock_path("$directory/plugin-manager.lock", true),
    'owned by root',
    'Plugin Manager accepted a non-root-owned production lock directory'
  );

  $directory = test_directory($root, 'production-file-owner');
  file_put_contents("$directory/plugin-manager.lock", '');
  chmod("$directory/plugin-manager.lock", 0600);
  chown("$directory/plugin-manager.lock", 65534);
  test_assert_throws(
    fn() => plugin_manager_prepare_lock_path("$directory/plugin-manager.lock", true),
    'owned by root',
    'Plugin Manager accepted a non-root-owned production lock file'
  );
}

$directory = "$root/cold-start";
putenv(PLUGIN_MANAGER_LOCK_PATH_ENV."=$directory/plugin-manager.lock");
$cold_start = [];
foreach (range(1, 8) as $worker) {
  $cold_start[] = test_start_process([
    '--critical', $worker % 2 ? 'install' : 'check', $directory, "cold-$worker", '10', '0'
  ]);
}
foreach ($cold_start as $worker => $process) {
  $result = test_finish_process($process);
  test_assert($result[0] === 0, "Cold-start worker ".($worker + 1)." failed: {$result[2]}");
}
$events = test_events($directory);
test_assert(count($events) === 16, 'Cold-start operations did not all execute');
test_assert(
  count(array_filter($events, fn($event) => str_starts_with($event, 'overlap '))) === 0,
  'Cold-start operations overlapped'
);
for ($event = 0; $event < count($events); $event += 2) {
  test_assert(str_starts_with($events[$event], 'enter '), 'Cold-start event pair did not begin with enter');
  test_assert(
    $events[$event + 1] === 'exit '.substr($events[$event], strlen('enter ')),
    'Cold-start operation event pair was not serialized'
  );
}

$directory = test_directory($root, 'concurrency');
$first = test_start_process(['--critical', 'install', $directory, 'first', '300', '0']);
test_wait_for(
  fn() => in_array('enter first', test_events($directory), true),
  2.0,
  'First operation never entered its critical section'
);
$second = test_start_process(['--critical', 'remove', $directory, 'second', '10', '0']);
$first_result = test_finish_process($first);
$second_result = test_finish_process($second);
test_assert($first_result[0] === 0, "First operation failed: {$first_result[2]}");
test_assert($second_result[0] === 0, "Second operation failed: {$second_result[2]}");
test_assert(
  test_events($directory) === ['enter first', 'exit first', 'enter second', 'exit second'],
  'Concurrent stateful operations were not serialized'
);

$directory = test_directory($root, 'failure-release');
$failing = test_start_process(['--critical', 'update', $directory, 'failing', '100', '17']);
test_wait_for(
  fn() => in_array('enter failing', test_events($directory), true),
  2.0,
  'Failing operation never acquired the lock'
);
$following = test_start_process(['--critical', 'check', $directory, 'following', '10', '0']);
$failing_result = test_finish_process($failing);
$following_result = test_finish_process($following);
test_assert($failing_result[0] === 17, 'The serialized operation exit status was not preserved');
test_assert($following_result[0] === 0, "Lock was not released after failure: {$following_result[2]}");
test_assert(
  test_events($directory) === ['enter failing', 'exit failing', 'enter following', 'exit following'],
  'The lock was not released cleanly after a failed operation'
);

foreach (['checkall' => 'check', 'updateall' => 'update', 'checkos' => 'check'] as $aggregate => $leaf) {
  $directory = test_directory($root, "aggregate-$aggregate");
  $result = test_finish_process(test_start_process([
    '--aggregate', $aggregate, $leaf, $directory, $aggregate
  ]));
  test_assert($result[0] === 0, "$aggregate recursion failed: {$result[2]}");
  test_assert(
    test_events($directory) === [
      "aggregate-start $aggregate",
      "enter $aggregate-child",
      "exit $aggregate-child",
      "aggregate-exit $aggregate"
    ],
    "$aggregate did not delegate locking to its recursive leaf operation"
  );
}

$directory = test_directory($root, 'nested-descendant');
$marker = "$directory/nested";
$nested = test_finish_process(test_start_process([
  '--nested-parent', 'install', 'remove', $marker
]), 2.0);
test_assert($nested[0] === 0, "A synchronous descendant could not share its parent's lock scope: {$nested[2]}");
test_assert(
  is_file($marker),
  'A synchronous descendant stateful operation did not execute inside the parent lock scope'
);

$directory = test_directory($root, 'nested-siblings');
$siblings = test_finish_process(test_start_process([
  '--nested-siblings-parent', 'install', $directory
]), 3.0);
test_assert($siblings[0] === 0, "Nested sibling operations failed: {$siblings[2]}");
$sibling_events = test_events($directory);
test_assert(
  count($sibling_events) === 4 &&
    count(array_filter($sibling_events, fn($event) => str_starts_with($event, 'overlap '))) === 0,
  'Parallel nested sibling operations were admitted together'
);
for ($event = 0; $event < count($sibling_events); $event += 2) {
  test_assert(
    str_starts_with($sibling_events[$event], 'enter ') &&
      $sibling_events[$event + 1] ===
        'exit '.substr($sibling_events[$event], strlen('enter ')),
    'Nested sibling operation event pairs were not serialized'
  );
}

$directory = test_directory($root, 'nested-chain');
$marker = "$directory/complete";
$chain = test_finish_process(test_start_process([
  '--nested-chain', 'install', $marker, '3'
]), 4.0);
test_assert($chain[0] === 0, "Three-level nested operation chain deadlocked: {$chain[2]}");
test_assert(is_file($marker), 'Three-level nested operation chain did not reach its leaf');

$directory = test_directory($root, 'owner-death');
$owner = test_start_process(['--owner-death-parent', 'install', $directory]);
test_wait_for(
  fn() => in_array('kill direct-owner', test_events($directory), true),
  2.0,
  'Direct owner did not reach its forced-death point'
);
usleep(100000);
$contender = test_start_process([
  '--critical', 'check', $directory, 'owner-death-contender', '10', '0'
]);
$owner_result = test_finish_process($owner);
$contender_result = test_finish_process($contender);
test_assert($owner_result[0] !== 0, 'Killed direct owner unexpectedly succeeded');
test_assert($contender_result[0] === 0, "Owner-death contender failed: {$contender_result[2]}");
test_assert(
  test_events($directory) === [
    'enter nested-survivor',
    'kill direct-owner',
    'exit nested-survivor',
    'enter owner-death-contender',
    'exit owner-death-contender'
  ],
  'The host lock was released before a registered nested operation survived direct-owner death'
);

$flock_path = getenv(PLUGIN_MANAGER_FLOCK_PATH_ENV) ?: '/usr/bin/flock';
$setsid_path = dirname($flock_path).'/setsid';
test_assert(is_executable($setsid_path), 'Process-group signal tests require setsid');

$directory = test_directory($root, 'killed-coordinator');
$coordinator_child = <<<'SH'
directory="$1"
printf "%s\n" "enter original" >> "$directory/events"
sleep 0.55
printf "%s\n" "exit original" >> "$directory/events"
SH;
$coordinator_command = plugin_manager_operation_lock_command(
  'install',
  '/bin/bash',
  ['/bin/bash', '-c', $coordinator_child, 'coordinator-child', $directory]
);
test_assert($coordinator_command !== null, 'Unable to build coordinator-death command');
$killed_coordinator = test_start_command([
  $setsid_path,
  '/bin/bash',
  '-c',
  $coordinator_command
]);
test_wait_for(
  fn() => in_array('enter original', test_events($directory), true),
  2.0,
  'Coordinator-death mutation did not start'
);
$coordinator_status = proc_get_status($killed_coordinator[0]);
$coordinator_pid = (int)$coordinator_status['pid'];
test_assert(
  $coordinator_status['running'] &&
    $coordinator_pid > 1 &&
    posix_kill($coordinator_pid, 9),
  'Unable to kill only the lock coordinator'
);
$contender = test_start_process([
  '--critical', 'remove', $directory, 'coordinator-contender', '10', '0'
]);
usleep(120000);
test_assert(
  !in_array('enter coordinator-contender', test_events($directory), true),
  'Coordinator SIGKILL released the host lock while its mutation survived'
);
$killed_result = test_finish_process($killed_coordinator);
$contender_result = test_finish_process($contender);
test_assert($killed_result[0] !== 0, 'SIGKILLed lock coordinator unexpectedly succeeded');
test_assert(
  $contender_result[0] === 0,
  "Coordinator-death contender failed: {$contender_result[2]}"
);
test_assert(
  test_events($directory) === [
    'enter original',
    'exit original',
    'enter coordinator-contender',
    'exit coordinator-contender'
  ],
  'The guardian did not retain and release the host lock around coordinator death'
);

$directory = test_directory($root, 'killed-guardian');
$guardian_child = <<<'SH'
directory="$1"
printf "%s\n" "$PPID" > "$directory/guardian-pid"
printf "%s\n" "$$" > "$directory/mutation-pid"
printf "%s\n" "guardian mutation entered" >> "$directory/events"
while :; do sleep 0.05; done
SH;
$guardian_command = plugin_manager_operation_lock_command(
  'install',
  '/bin/bash',
  ['/bin/bash', '-c', $guardian_child, 'guardian-child', $directory]
);
test_assert($guardian_command !== null, 'Unable to build guardian-death command');
$killed_guardian = test_start_command([
  $setsid_path,
  '/bin/bash',
  '-c',
  $guardian_command
]);
test_wait_for(
  fn() => is_file("$directory/guardian-pid") &&
    is_file("$directory/mutation-pid") &&
    in_array('guardian mutation entered', test_events($directory), true),
  2.0,
  'Guardian-death mutation did not start'
);
$guardian_pid = (int)trim(file_get_contents("$directory/guardian-pid"));
$mutation_pid = (int)trim(file_get_contents("$directory/mutation-pid"));
$mutation_start = plugin_manager_process_start_time($mutation_pid);
test_assert(
  is_string($mutation_start),
  'Unable to capture guardian-death mutation identity'
);
test_assert(
  $guardian_pid > 1 && posix_kill($guardian_pid, 9),
  'Unable to kill only the lock guardian'
);
$contender = test_start_process([
  '--critical', 'remove', $directory, 'guardian-contender', '10', '0'
]);
$guardian_result = test_finish_process($killed_guardian);
$contender_result = test_finish_process($contender);
test_assert($guardian_result[0] !== 0, 'SIGKILLed lock guardian unexpectedly succeeded');
test_assert(
  !plugin_manager_lock_member_is_live("$mutation_pid.$mutation_start"),
  'The coordinator left the same mutation identity running after guardian death: '.
    json_encode([
      'guardian' => $guardian_pid,
      'mutation' => "$mutation_pid.$mutation_start",
      'mutation_parent' => test_process_parent($mutation_pid),
      'mutation_group' => posix_getpgid($mutation_pid),
      'mutation_state' => test_process_state($mutation_pid)
    ])
);
test_assert(
  $contender_result[0] === 0,
  "Guardian-death contender failed: {$contender_result[2]}"
);
test_assert(
  test_events($directory) === [
    'guardian mutation entered',
    'enter guardian-contender',
    'exit guardian-contender'
  ],
  'The coordinator did not terminate guardian-less mutation before releasing the host lock'
);

$directory = test_directory($root, 'escaped-member-guardian-death');
$escaped_owner = test_start_process([
  '--escaped-member-parent',
  'install',
  $directory,
  $setsid_path
]);
$escaped_fixture_started = microtime(true);
$guardian_pid = null;
$escaped_pid = null;
while (true) {
  $lane_records = [];
  foreach (glob("$directory/*.lane.members/*") ?: [] as $record) {
    $lane_records[basename($record)] = @file_get_contents($record);
  }
  $roots = array_keys(array_filter(
    $lane_records,
    static fn($parent) => $parent === '-'
  ));
  if (count($roots) === 1) {
    $root_identity = $roots[0];
    $children = array_keys(array_filter(
      $lane_records,
      static fn($parent) => $parent === $root_identity
    ));
    if (count($children) === 1) {
      $root_pid = (int)strstr($root_identity, '.', true);
      $escaped_pid = (int)strstr($children[0], '.', true);
      $guardian_pid = test_process_parent($root_pid);
      if (
        is_int($guardian_pid) &&
        $guardian_pid > 1 &&
        plugin_manager_lock_member_is_live($children[0])
      ) {
        break;
      }
    }
  }
  $escaped_owner_status = proc_get_status($escaped_owner[0]);
  if (!$escaped_owner_status['running']) {
    $escaped_owner_result = test_finish_process($escaped_owner);
    test_fail(
      "Escaped-member guardian-death fixture exited early: ".
        "{$escaped_owner_result[1]} {$escaped_owner_result[2]}"
    );
  }
  if (microtime(true) - $escaped_fixture_started > 3.0) {
    test_fail(
      'Escaped-member guardian-death fixture did not start: lane='.
        json_encode($lane_records)
    );
  }
  usleep(10000);
}
$identity_markers = glob(
  "$directory/plugin-manager.lock.scope.*.snapshots/.operation-identity"
) ?: [];
test_assert(
  count($identity_markers) === 1,
  'Escaped-member fixture did not publish one operation identity marker'
);
$stale_process = test_start_command(['/bin/sleep', '0.05']);
$stale_status = proc_get_status($stale_process[0]);
$stale_pid = (int)$stale_status['pid'];
$stale_start = plugin_manager_process_start_time($stale_pid);
test_assert(
  is_string($stale_start),
  'Unable to capture stale operation-marker identity'
);
test_finish_process($stale_process);
chmod($identity_markers[0], 0600);
file_put_contents($identity_markers[0], "$stale_pid.$stale_start\n");
chmod($identity_markers[0], 0400);
test_assert(
  $guardian_pid > 1 && posix_kill($guardian_pid, 9),
  'Unable to kill the guardian with an escaped registered member'
);
$contender = test_start_process([
  '--critical', 'check', $directory, 'escaped-member-contender', '10', '0'
]);
$escaped_owner_result = test_finish_process($escaped_owner);
$contender_result = test_finish_process($contender);
test_assert(
  $escaped_owner_result[0] !== 0,
  'Guardian death with an escaped member unexpectedly succeeded'
);
test_assert(
  in_array(test_process_state($escaped_pid), [null, 'Z', 'X', 'x'], true),
  'Unexpected guardian death did not fail-stop an escaped registered member'
);
test_assert(
  $contender_result[0] === 0,
  "Escaped-member contender deadlocked after guardian death: {$contender_result[2]}"
);
test_assert(
  test_events($directory) === [
    'enter escaped-member-contender',
    'exit escaped-member-contender'
  ],
  'Escaped-member cleanup released the lock before fail-stop completed'
);

foreach (
  [
    'TERM' => ['number' => 15, 'status' => 42],
    'INT' => ['number' => 2, 'status' => 43],
    'HUP' => ['number' => 1, 'status' => 44]
  ] as $signal => $expectation
) {
  $signal_name = strtolower($signal);
  $directory = test_directory($root, "signalled-supervisor-$signal_name");
  $signal_child = <<<'SH'
signal="$1"
status="$2"
directory="$3"
on_signal() {
  printf "%s\n" "child-signal-$signal" >> "$directory/events"
  sleep 0.35
  printf "%s\n" "child-exit-$signal" >> "$directory/events"
  exit "$status"
}
trap on_signal "$signal"
printf "%s\n" "$PPID" > "$directory/supervisor-pid"
printf "%s\n" "child-ready-$signal" >> "$directory/events"
while :; do sleep 0.05; done
SH;
  $signal_command = plugin_manager_operation_lock_command(
    'install',
    '/bin/bash',
    [
      '/bin/bash',
      '-c',
      $signal_child,
      'signal-child',
      $signal,
      (string)$expectation['status'],
      $directory
    ]
  );
  test_assert($signal_command !== null, "Unable to build $signal supervisor command");
  $signalled = test_start_command([$setsid_path, '/bin/bash', '-c', $signal_command]);
  usleep(100000);
  $signalled_status = proc_get_status($signalled[0]);
  if (!$signalled_status['running']) {
    $signalled_result = test_finish_process($signalled);
    test_fail(
      "$signal supervisor exited before its child started: ".
      "{$signalled_result[1]} {$signalled_result[2]}"
    );
  }
  test_wait_for(
    fn() => is_file("$directory/supervisor-pid") &&
      in_array("child-ready-$signal", test_events($directory), true),
    2.0,
    "$signal supervisor child did not start"
  );
  $operation_markers = glob(
    "$directory/plugin-manager.lock.scope.*.snapshots/.operation-identity"
  ) ?: [];
  $operation_identity = count($operation_markers) === 1
    ? trim((string)file_get_contents($operation_markers[0]))
    : '';
  $operation_pid = (int)strstr($operation_identity, '.', true);
  test_assert(
    count($operation_markers) === 1 &&
      plugin_manager_lock_member_is_live($operation_identity) &&
      posix_getpgid($operation_pid) === $operation_pid,
    "$signal supervisor published an invalid operation identity: ".
      json_encode([
        'identity' => $operation_identity,
        'live' => plugin_manager_lock_member_is_live($operation_identity),
        'group' => posix_getpgid($operation_pid),
        'parent' => test_process_parent($operation_pid),
        'state' => test_process_state($operation_pid)
      ])
  );
  $supervisor_pid = (int)trim(file_get_contents("$directory/supervisor-pid"));
  $supervisor_group = $supervisor_pid > 1 ? posix_getpgid($supervisor_pid) : false;
  test_assert(
    is_int($supervisor_group) &&
      $supervisor_group > 1 &&
      $supervisor_group !== posix_getpgrp() &&
      posix_kill(-$supervisor_group, $expectation['number']),
    "Unable to signal the complete $signal supervisor process group"
  );
  test_wait_for(
    fn() => in_array("child-signal-$signal", test_events($directory), true),
    2.0,
    "Lock supervisor did not forward $signal to its child"
  );
  $contender = test_start_process([
    '--critical', 'remove', $directory, "$signal_name-contender", '10', '0'
  ]);
  usleep(100000);
  test_assert(
    !in_array("enter $signal_name-contender", test_events($directory), true),
    "$signal process group released the host lock while its child was still running"
  );
  $signalled_result = test_finish_process($signalled);
  $contender_result = test_finish_process($contender);
  test_assert(
    $signalled_result[0] === $expectation['status'],
    "$signal supervisor did not preserve its child status: {$signalled_result[2]}"
  );
  test_assert(
    $contender_result[0] === 0,
    "Contender failed after $signal child cleanup: {$contender_result[2]}"
  );
  test_assert(
    test_events($directory) === [
      "child-ready-$signal",
      "child-signal-$signal",
      "child-exit-$signal",
      "enter $signal_name-contender",
      "exit $signal_name-contender"
    ],
    "$signal supervisor did not wait, clean up, and release in order"
  );
  test_assert(
    count(array_filter(
      test_events($directory),
      static fn($event) => $event === "child-signal-$signal"
    )) === 1,
    "Coordinator-forwarded $signal reached the mutation more than once"
  );
}

foreach (
  [
    'TERM' => ['number' => 15, 'status' => 52],
    'INT' => ['number' => 2, 'status' => 53],
    'HUP' => ['number' => 1, 'status' => 54]
  ] as $signal => $expectation
) {
  $signal_name = strtolower($signal);
  $directory = test_directory($root, "direct-guardian-signal-$signal_name");
  $signal_child = <<<'SH'
signal="$1"
status="$2"
directory="$3"
on_signal() {
  printf "%s\n" "direct-child-signal-$signal" >> "$directory/events"
  sleep 0.2
  printf "%s\n" "direct-child-exit-$signal" >> "$directory/events"
  exit "$status"
}
trap on_signal "$signal"
printf "%s\n" "$PPID" > "$directory/guardian-pid"
printf "%s\n" "direct-child-ready-$signal" >> "$directory/events"
while :; do sleep 0.05; done
SH;
  $signal_command = plugin_manager_operation_lock_command(
    'install',
    '/bin/bash',
    [
      '/bin/bash',
      '-c',
      $signal_child,
      'direct-signal-child',
      $signal,
      (string)$expectation['status'],
      $directory
    ]
  );
  test_assert($signal_command !== null, "Unable to build direct $signal guardian command");
  $signalled = test_start_command([$setsid_path, '/bin/bash', '-c', $signal_command]);
  test_wait_for(
    fn() => is_file("$directory/guardian-pid") &&
      in_array("direct-child-ready-$signal", test_events($directory), true),
    2.0,
    "Direct $signal guardian child did not start"
  );
  $guardian_pid = (int)trim(file_get_contents("$directory/guardian-pid"));
  $operation_markers = glob(
    "$directory/plugin-manager.lock.scope.*.snapshots/.operation-identity"
  ) ?: [];
  $operation_identity = count($operation_markers) === 1
    ? trim((string)file_get_contents($operation_markers[0]))
    : '';
  $operation_pid = (int)strstr($operation_identity, '.', true);
  test_assert(
    count($operation_markers) === 1 &&
      plugin_manager_lock_member_is_live($operation_identity) &&
      posix_getpgid($operation_pid) === $operation_pid &&
      test_process_parent($operation_pid) === $guardian_pid,
    "Direct $signal guardian does not directly supervise the operation: ".
      json_encode([
        'guardian' => $guardian_pid,
        'operation' => $operation_identity,
        'operation_parent' => test_process_parent($operation_pid),
        'operation_group' => posix_getpgid($operation_pid),
        'operation_live' => plugin_manager_lock_member_is_live($operation_identity),
        'operation_start' => plugin_manager_process_start_time($operation_pid),
        'operation_state' => test_process_state($operation_pid)
      ])
  );
  test_assert(
    $guardian_pid > 1 && posix_kill($guardian_pid, $expectation['number']),
    "Unable to signal only the $signal guardian"
  );
  test_wait_for(
    fn() => in_array("direct-child-signal-$signal", test_events($directory), true),
    2.0,
    "Guardian PID-only $signal was not forwarded to its child"
  );
  $contender = test_start_process([
    '--critical', 'remove', $directory, "direct-$signal_name-contender", '10', '0'
  ]);
  usleep(80000);
  test_assert(
    !in_array("enter direct-$signal_name-contender", test_events($directory), true),
    "Guardian PID-only $signal released the lock before child cleanup"
  );
  $signalled_result = test_finish_process($signalled);
  $contender_result = test_finish_process($contender);
  test_assert(
    $signalled_result[0] === $expectation['status'],
    "Guardian PID-only $signal did not preserve child status: {$signalled_result[2]}"
  );
  test_assert(
    $contender_result[0] === 0,
    "Guardian PID-only $signal contender failed: {$contender_result[2]}"
  );
  test_assert(
    test_events($directory) === [
      "direct-child-ready-$signal",
      "direct-child-signal-$signal",
      "direct-child-exit-$signal",
      "enter direct-$signal_name-contender",
      "exit direct-$signal_name-contender"
    ],
    "Guardian PID-only $signal did not wait, clean up, and release in order"
  );
  test_assert(
    count(array_filter(
      test_events($directory),
      static fn($event) => $event === "direct-child-signal-$signal"
    )) === 1,
    "Guardian PID-only $signal reached the mutation more than once"
  );
}

$directory = test_directory($root, 'zombie-member');
$zombie_owner = test_start_process([
  '--spawn-zombie-member', 'install', $directory
]);
test_wait_for(
  fn() => is_file("$directory/zombie-direct-exit"),
  2.0,
  'Direct owner did not exit after creating the nested zombie'
);
$contender = test_start_process([
  '--critical', 'check', $directory, 'zombie-contender', '10', '0'
]);
$contender_result = test_finish_process($contender);
$zombie_owner_result = test_finish_process($zombie_owner);
test_assert(
  $zombie_owner_result[0] === 0,
  "Zombie-member owner failed: {$zombie_owner_result[2]}"
);
test_assert(
  $contender_result[0] === 0,
  "Contender did not pass the dead zombie lease: {$contender_result[2]}"
);
test_assert(
  !is_file("$directory/zombie-parent-exit"),
  'The supervisor retained the global lock until the zombie was finally reaped'
);
test_assert(
  test_events($directory) === ['enter zombie-contender', 'exit zombie-contender'],
  'The contender did not execute after the dead zombie lease was discarded'
);
test_wait_for(
  fn() => is_file("$directory/zombie-parent-exit"),
  3.0,
  'Unreaping zombie parent did not finish'
);

$directory = test_directory($root, 'killed-nested-snapshot-cleanup');
$snapshot_owner = test_start_process([
  '--snapshot-kill-parent', 'install', $directory, 'orphan.plg'
]);
test_wait_for(
  fn() => is_file("$directory/snapshot-parent-ready"),
  2.0,
  'Nested SIGKILL snapshot parent did not reach the observation boundary'
);
$orphan = json_decode(file_get_contents("$directory/orphan-snapshot.json"), true);
test_assert(
  is_array($orphan) &&
    isset($orphan['path'], $orphan['scope']) &&
    str_starts_with($orphan['path'], $orphan['scope'].'.snapshots/') &&
    is_file($orphan['path']) &&
    !is_link($orphan['path']) &&
    (fileperms($orphan['path']) & 07777) === 0400 &&
    (fileperms(dirname($orphan['path'])) & 07777) === 0700 &&
    (
      plugin_manager_effective_user_id() === null ||
      fileowner($orphan['path']) === plugin_manager_effective_user_id()
    ),
  'Nested SIGKILL snapshot was not private to its lock scope'
);
$foreign_snapshot_directory =
  "$directory/plugin-manager.lock.scope.".str_repeat('f', 32).'.snapshots';
mkdir($foreign_snapshot_directory, 0700);
file_put_contents("$foreign_snapshot_directory/foreign", 'preserve');
file_put_contents("$directory/snapshot-observed", 'yes');
$snapshot_owner_result = test_finish_process($snapshot_owner);
test_assert(
  $snapshot_owner_result[0] === 0,
  "Nested SIGKILL snapshot owner failed: {$snapshot_owner_result[2]}"
);
test_assert(
  !file_exists($orphan['path']) &&
    !file_exists($orphan['scope'].'.snapshots') &&
    !file_exists($orphan['scope']),
  'Supervisor did not remove the retired scope and its orphan snapshot'
);
test_assert(
  file_get_contents("$foreign_snapshot_directory/foreign") === 'preserve',
  'Supervisor snapshot cleanup crossed into a different lock scope'
);
$contender = test_finish_process(test_start_process([
  '--critical', 'check', $directory, 'post-snapshot-cleanup', '10', '0'
]));
test_assert(
  $contender[0] === 0,
  "Global lock was not released after orphan snapshot cleanup: {$contender[2]}"
);

foreach (range(1, 10) as $attempt) {
  $directory = test_directory($root, "late-registration-$attempt");
  $parent = test_start_process(['--late-registration-parent', 'install', $directory]);
  test_wait_for(
    fn() => is_file("$directory/late-attempted"),
    2.0,
    "Late registration attempt $attempt never reached the lock boundary"
  );
  $contender = test_start_process([
    '--critical', 'check', $directory, 'late-contender', '10', '0'
  ]);
  $parent_result = test_finish_process($parent);
  $contender_result = test_finish_process($contender);
  test_assert($parent_result[0] === 0, "Late-registration parent $attempt failed: {$parent_result[2]}");
  test_assert($contender_result[0] === 0, "Late-registration contender $attempt failed: {$contender_result[2]}");
  test_wait_for(
    fn() => in_array('exit late-child', test_events($directory), true),
    10.0,
    "Late-registration child $attempt did not finish: ".
      (@file_get_contents("$directory/late-child-output") ?: 'no output')
  );
  test_assert(
    count(array_filter(test_events($directory), fn($event) => str_starts_with($event, 'overlap '))) === 0,
    "Late registration raced the supervisor's atomic scope release on attempt $attempt"
  );
}

$directory = test_directory($root, 'descriptor');
$marker = "$directory/background-finished";
$background = test_finish_process(test_start_process([
  '--spawn-background', 'install', '1200', $marker
]));
test_assert($background[0] === 0, "Unable to spawn background descendant: {$background[2]}");
$flock = getenv(PLUGIN_MANAGER_FLOCK_PATH_ENV) ?: '/usr/bin/flock';
$probe_output = [];
exec(
  escapeshellarg($flock).' --nonblock '.
  escapeshellarg("$directory/plugin-manager.lock").' /bin/true',
  $probe_output,
  $probe_status
);
test_assert($probe_status === 0, 'A background descendant inherited and retained the lock descriptor');
$contender = test_finish_process(test_start_process([
  '--critical', 'check', $directory, 'contender', '10', '0'
]));
test_assert($contender[0] === 0, "Contender failed after owner exit: {$contender[2]}");
test_wait_for(fn() => is_file($marker), 2.0, 'Background descendant did not remain alive for descriptor test');

$directory = test_directory($root, 'stale-inherited-owner');
$spawner = test_finish_process(test_start_process([
  '--spawn-delayed-worker', 'install', '250', $directory
]));
test_assert($spawner[0] === 0, "Unable to spawn delayed descendant: {$spawner[2]}");
$holder = test_start_process(['--critical', 'remove', $directory, 'holder', '650', '0']);
test_wait_for(
  fn() => in_array('enter holder', test_events($directory), true),
  2.0,
  'New lock owner never entered its critical section'
);
$holder_result = test_finish_process($holder);
test_assert($holder_result[0] === 0, "New lock owner failed: {$holder_result[2]}");
test_wait_for(
  fn() => is_file("$directory/descendant-attempted"),
  2.0,
  'Delayed descendant never attempted its inherited operation'
);
test_wait_for(
  fn() => in_array('exit descendant', test_events($directory), true),
  2.0,
  'Delayed descendant did not finish after the replacement owner released the lock: '.
    (@file_get_contents("$directory/descendant-output") ?: 'no descendant output')
);
test_assert(
  test_events($directory) === [
    'enter holder',
    'exit holder',
    'enter descendant',
    'exit descendant'
  ],
  'A stale descendant did not reacquire the lock after its original owner exited: '.
    (@file_get_contents("$directory/descendant-output") ?: 'no descendant output')
);

$directory = test_directory($root, 'core-vs-unrelated');
file_put_contents("$directory/pkgtools-state", 'initial');
$core = test_start_process([
  '--plugin-transaction', 'install', $directory, 'core', 'core', '300'
]);
test_wait_for(
  fn() => in_array('core-enter core', test_events($directory), true),
  2.0,
  'Core install never acquired its nested transaction lock'
);
$unrelated = test_start_process([
  '--plugin-transaction', 'remove', $directory, 'unrelated', 'unrelated', '0'
]);
$core_result = test_finish_process($core);
$unrelated_result = test_finish_process($unrelated);
test_assert($core_result[0] === 0, "Simulated Core transaction failed: {$core_result[2]}");
test_assert($unrelated_result[0] === 0, "Simulated unrelated plugin transaction failed: {$unrelated_result[2]}");
test_assert(
  test_events($directory) === [
    'host-enter core',
    'core-enter core',
    'pkg-read core=initial',
    'pkg-write core=core-committed',
    'core-exit core',
    'host-exit core',
    'host-enter unrelated',
    'pkg-read unrelated=core-committed',
    'pkg-write unrelated=unrelated-after-core',
    'host-exit unrelated'
  ],
  'Core and unrelated plugin operations did not preserve global-lock then Core-lock ordering'
);
test_assert(
  trim(file_get_contents("$directory/pkgtools-state")) === 'unrelated-after-core',
  'Serialized Core and unrelated plugin operations left unexpected simulated pkgtools state'
);

$directory = test_directory($root, 'display-artifact-concurrency');
$display_artifact = "$directory/display.txt";
$first_display = test_start_process([
  '--display-artifact-writer',
  $directory,
  $display_artifact,
  'first-complete-document',
  '220'
]);
test_wait_for(
  fn() => in_array(
    'display-enter first-complete-document',
    test_events($directory),
    true
  ),
  2.0,
  'First display-artifact publisher never acquired the host lock'
);
$second_display = test_start_process([
  '--display-artifact-writer',
  $directory,
  $display_artifact,
  'second-complete-document',
  '0'
]);
$cleanup_ran = false;
test_assert(
  !plugin_manager_with_nonblocking_operation_lock(
    function() use (&$cleanup_ran, $display_artifact): void {
      $cleanup_ran = true;
      plugin_manager_remove_shared_artifact($display_artifact);
    }
  ),
  'Display-artifact cleanup raced an active publisher'
);
test_assert(!$cleanup_ran, 'Busy display-artifact cleanup callback ran');
$first_display_result = test_finish_process($first_display);
$second_display_result = test_finish_process($second_display);
test_assert(
  $first_display_result[0] === 0 && $second_display_result[0] === 0,
  "Serialized display-artifact publication failed: ".
    "{$first_display_result[2]} {$second_display_result[2]}"
);
test_assert(
  file_get_contents($display_artifact) === 'second-complete-document' &&
    test_events($directory) === [
      'display-enter first-complete-document',
      'display-exit first-complete-document',
      'display-enter second-complete-document',
      'display-exit second-complete-document'
    ],
  'Display artifacts were partially published or reordered around cleanup'
);

$directory = test_directory($root, 'nonblocking-free');
$maintenance_ran = false;
test_assert(
  plugin_manager_with_nonblocking_operation_lock(function () use (&$maintenance_ran): void {
    $maintenance_ran = true;
  }),
  'Nonblocking maintenance did not acquire a free host lock'
);
test_assert($maintenance_ran, 'Nonblocking maintenance callback did not run');

$directory = test_directory($root, 'nonblocking-busy');
$holder = test_start_process(['--critical', 'install', $directory, 'holder', '300', '0']);
test_wait_for(
  fn() => in_array('enter holder', test_events($directory), true),
  2.0,
  'Nonblocking test holder never acquired the lock'
);
$maintenance_ran = false;
$started = microtime(true);
test_assert(
  !plugin_manager_with_nonblocking_operation_lock(function () use (&$maintenance_ran): void {
    $maintenance_ran = true;
  }),
  'Nonblocking maintenance entered while a plugin operation held the lock'
);
test_assert(!$maintenance_ran, 'Busy nonblocking maintenance callback ran');
test_assert(microtime(true) - $started < 0.2, 'Busy nonblocking maintenance waited for the lock');
$holder_result = test_finish_process($holder);
test_assert($holder_result[0] === 0, "Nonblocking test holder failed: {$holder_result[2]}");

$wrapper = "$root/plugin-executable";
$wrapper_source = <<<'PHP'
#!/usr/bin/env php
<?PHP
$docroot = __DOCROOT__;
require __PLUGIN_SCRIPT__;
PHP;
$wrapper_source = str_replace(
  ['__DOCROOT__', '__PLUGIN_SCRIPT__'],
  [var_export("$repo/emhttp", true), var_export("$repo/emhttp/plugins/dynamix.plugin.manager/scripts/plugin", true)],
  $wrapper_source
);
file_put_contents($wrapper, $wrapper_source);
chmod($wrapper, 0755);

$directory = test_directory($root, 'executable-contention');
$holder = test_start_process(['--critical', 'install', $directory, 'holder', '350', '0']);
test_wait_for(
  fn() => in_array('enter holder', test_events($directory), true),
  2.0,
  'Executable contention holder never acquired the lock'
);
$actual = test_finish_process(test_start_command([$wrapper, 'check', 'missing.plg']));
$holder_result = test_finish_process($holder);
test_assert($holder_result[0] === 0, "Executable contention holder failed: {$holder_result[2]}");
test_assert($actual[0] === 1, "Contending plugin executable returned an unexpected status: {$actual[2]}");
test_assert(
  $actual[3] >= 0.25,
  'The real plugin executable did not wait behind an active plugin operation'
);
test_assert(
  str_contains($actual[1], 'plugin: checking: missing.plg'),
  "Contending plugin executable did not resume after lock release: {$actual[1]} {$actual[2]}"
);

$directory = test_directory($root, 'executable-owner');
$actual = test_finish_process(test_start_command([$wrapper, 'check', 'missing.plg']));
test_assert($actual[0] === 1, "Plugin executable returned an unexpected status: {$actual[2]}");
test_assert(
  str_contains($actual[1], 'plugin: checking: missing.plg'),
  "Plugin executable did not reach its serialized check operation: {$actual[1]} {$actual[2]}"
);
test_assert(
  !str_contains($actual[2], 'unable to acquire operation lock'),
  "Plugin executable could not recognize its owner-only re-entry: {$actual[2]}"
);

$nchan_root = "$root/nchan-docroot";
mkdir("$nchan_root/webGui/include", 0700, true);
mkdir("$nchan_root/plugins/dynamix.plugin.manager/include", 0700, true);
file_put_contents(
  "$nchan_root/webGui/include/Wrappers.php",
  "<?PHP\nfunction my_logger(...\$arguments): void {}\n"
);
file_put_contents(
  "$nchan_root/webGui/include/publish.php",
  <<<'PHP'
<?PHP
function publish($endpoint, $message, $len = 1, $abort = false) {
  $log = getenv('PLUGIN_MANAGER_TEST_NCHAN_LOG');
  if (is_string($log)) {
    file_put_contents($log, base64_encode((string)$message)."\n", FILE_APPEND | LOCK_EX);
  }
  return true;
}
PHP
);
symlink(
  "$repo/emhttp/plugins/dynamix.plugin.manager/include/PluginOperationLock.php",
  "$nchan_root/plugins/dynamix.plugin.manager/include/PluginOperationLock.php"
);
symlink(
  "$repo/emhttp/plugins/dynamix.plugin.manager/include/PluginAttributes.php",
  "$nchan_root/plugins/dynamix.plugin.manager/include/PluginAttributes.php"
);
$nchan_wrapper = "$root/plugin-nchan-executable";
$nchan_wrapper_source =
  "#!/usr/bin/env php\n<?PHP\n".
  '$docroot = '.var_export($nchan_root, true).";\n".
  'require '.var_export(
    "$repo/emhttp/plugins/dynamix.plugin.manager/scripts/plugin",
    true
  ).";\n";
file_put_contents($nchan_wrapper, $nchan_wrapper_source);
chmod($nchan_wrapper, 0755);
$directory = test_directory($root, 'nchan-completion');
$nchan_log = "$directory/messages";
$nchan_environment = getenv();
$nchan_environment['PLUGIN_MANAGER_TEST_NCHAN_LOG'] = $nchan_log;
$nchan_result = test_finish_process(
  test_start_command([$nchan_wrapper, 'check', 'missing.plg', 'nchan'], $nchan_environment)
);
test_assert($nchan_result[0] === 1, "Nchan check returned an unexpected status: {$nchan_result[2]}");
$nchan_messages = array_map(
  static fn($message) => base64_decode($message, true),
  file($nchan_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
);
test_assert(
  count(array_filter($nchan_messages, static fn($message) => $message === '_DONE_')) === 1,
  'A supervised nchan check emitted more than one completion marker'
);
file_put_contents($nchan_log, '');
$nchan_environment[PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV] = '/bin/false';
$nchan_result = test_finish_process(
  test_start_command([$nchan_wrapper, 'check', 'missing.plg', 'nchan'], $nchan_environment)
);
test_assert(
  $nchan_result[0] === 1,
  "Nchan supervisor-startup failure returned an unexpected status: {$nchan_result[2]}"
);
$nchan_messages = array_map(
  static fn($message) => base64_decode($message, true),
  file($nchan_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
);
test_assert(
  count(array_filter($nchan_messages, static fn($message) => $message === '_DONE_')) === 1,
  'An nchan supervisor-startup failure did not emit exactly one completion marker'
);
foreach (['check', 'update', 'download', 'remove'] as $invalid_method) {
  file_put_contents($nchan_log, '');
  $invalid_name_result = test_finish_process(
    test_start_command(
      [$nchan_wrapper, $invalid_method, '../../etc/arbitrary.plg', 'nchan'],
      $nchan_environment
    )
  );
  test_assert(
    $invalid_name_result[0] === 1,
    "$invalid_method accepted a path-like plugin name: ".
      "{$invalid_name_result[1]} {$invalid_name_result[2]}"
  );
  $invalid_name_messages = array_map(
    static fn($message) => base64_decode($message, true),
    file($nchan_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
  );
  test_assert(
    in_array("plugin: invalid plugin basename\n", $invalid_name_messages, true) &&
      count(
        array_filter(
          $invalid_name_messages,
          static fn($message) => $message === '_DONE_'
        )
      ) === 1,
    "$invalid_method path rejection did not emit one nchan completion marker"
  );
}

$directory = test_directory($root, 'real-cli-lock-command-failure');
$cli_plugins = "$root/real-cli-installed";
$cli_tmp = "$root/real-cli-tmp";
mkdir($cli_plugins, 0700);
mkdir($cli_tmp, 0700);
$plugin = 'real-cli-revoke.plg';
$installed = "$root/real-cli-installed.plg";
file_put_contents(
  $installed,
  '<PLUGIN name="real-cli-revoke" version="2026.07.18" '.
    'pluginURL="http://127.0.0.1:9/real-cli-revoke.plg"></PLUGIN>'
);
symlink($installed, "$cli_plugins/$plugin");
$latest = "$cli_tmp/$plugin";
$successful = "$root/real-cli-successful.plg";
file_put_contents(
  $successful,
  '<PLUGIN name="real-cli-revoke" version="2026.07.17"></PLUGIN>'
);
$successful_generation = plugin_manager_reserve_plugin_check_generation($plugin);
test_assert(
  test_publish_plugin_check_artifact(
    $plugin,
    $successful_generation,
    $successful,
    $latest
  ),
  'Unable to publish the artifact preceding a real CLI lock-command failure'
);
$cli_plugin_source = file_get_contents(
  "$repo/emhttp/plugins/dynamix.plugin.manager/scripts/plugin"
);
$cli_plugin_source = str_replace(
  [
    "\$plugins       = '/var/log/plugins';",
    "\$tmp           = '/tmp/plugins';"
  ],
  [
    '$plugins       = '.var_export($cli_plugins, true).';',
    '$tmp           = '.var_export($cli_tmp, true).';'
  ],
  $cli_plugin_source,
  $cli_replacements
);
test_assert(
  $cli_replacements === 2,
  'Unable to isolate real CLI plugin paths for lock-command failure testing'
);
$cli_plugin = "$root/plugin-real-cli-copy";
file_put_contents($cli_plugin, $cli_plugin_source);
$cli_wrapper = "$root/plugin-real-cli-executable";
$cli_wrapper_source =
  "#!/usr/bin/env php\n<?PHP\n".
  '$docroot = '.var_export($nchan_root, true).";\n".
  'require '.var_export($cli_plugin, true).";\n";
file_put_contents($cli_wrapper, $cli_wrapper_source);
chmod($cli_wrapper, 0755);
$invalid_plugin = 'invalid-update.plg';
$invalid_installed = "$root/invalid-update-installed.plg";
file_put_contents(
  $invalid_installed,
  '<PLUGIN name="invalid-update" version="2026.07.17"></PLUGIN>'
);
symlink($invalid_installed, "$cli_plugins/$invalid_plugin");
file_put_contents($nchan_log, '');
$invalid_update_environment = $nchan_environment;
$invalid_update_environment[PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV] =
  "$repo/emhttp/plugins/dynamix.plugin.manager/scripts/plugin-operation-lock";
$invalid_update_result = test_finish_process(
  test_start_command(
    [$cli_wrapper, 'update', $invalid_plugin, 'nchan'],
    $invalid_update_environment
  )
);
test_assert(
  $invalid_update_result[0] === 1,
  "Nchan invalid-artifact update returned an unexpected status: ".
    "{$invalid_update_result[2]}"
);
$invalid_update_messages = array_map(
  static fn($message) => base64_decode($message, true),
  file($nchan_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
);
test_assert(
  count(
    array_filter(
      $invalid_update_messages,
      static fn($message) => $message === '_DONE_'
    )
  ) === 1,
  'An nchan invalid-artifact update did not emit exactly one completion marker: '.
    json_encode([
      'messages' => $invalid_update_messages,
      'stdout' => $invalid_update_result[1],
      'stderr' => $invalid_update_result[2]
    ])
);
file_put_contents($nchan_log, '');
$invalid_update_startup_environment = $invalid_update_environment;
$invalid_update_startup_environment[PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV] =
  '/bin/false';
$invalid_update_startup_result = test_finish_process(
  test_start_command(
    [$cli_wrapper, 'update', $invalid_plugin, 'nchan'],
    $invalid_update_startup_environment
  )
);
test_assert(
  $invalid_update_startup_result[0] === 1,
  "Nchan update lock-startup failure returned an unexpected status: ".
    "{$invalid_update_startup_result[2]}"
);
$invalid_update_startup_messages = array_map(
  static fn($message) => base64_decode($message, true),
  file($nchan_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
);
test_assert(
  count(
    array_filter(
      $invalid_update_startup_messages,
      static fn($message) => $message === '_DONE_'
    )
  ) === 1,
  'An nchan update lock-startup failure did not emit exactly one completion marker'
);
$cli_environment = getenv();
$cli_environment[PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV] =
  "$directory/not-executable";
$cli_result = test_finish_process(
  test_start_command([$cli_wrapper, 'check', $plugin], $cli_environment)
);
test_assert(
  $cli_result[0] === 1 &&
    str_contains($cli_result[1], 'plugin: downloading: real-cli-revoke.plg') &&
    str_contains($cli_result[2], 'lock supervisor is not executable'),
  "Real CLI did not parse, download, and enter its lock-command failure catch: ".
    "{$cli_result[1]} {$cli_result[2]}"
);
test_assert(
  !file_exists($latest) &&
    !plugin_manager_plugin_check_artifact_is_current($plugin, $latest),
  'Real CLI lock-command failure did not durably revoke its reserved generation'
);

$directory = test_directory($root, 'aggregate-derived-name-boundary');
foreach (glob("$cli_plugins/*.plg", GLOB_NOSORT) ?: [] as $installed_link) {
  @unlink($installed_link);
}
$aggregate_marker = "$directory/recursive-mutation";
$aggregate_nchan_log = "$directory/nchan-messages";
$aggregate_unsafe_basename =
  'odd plugin;touch${IFS}${PLUGIN_MANAGER_TEST_AGGREGATE_MARKER};#.plg';
$aggregate_unsafe_target = "$root/$aggregate_unsafe_basename";
$aggregate_odd_basename = 'space separated plugin.plg';
$aggregate_odd_target = "$root/$aggregate_odd_basename";
file_put_contents(
  $aggregate_unsafe_target,
  '<PLUGIN name="aggregate-unsafe" version="2026.07.18" '.
    'pluginURL="http://127.0.0.1:9/aggregate-unsafe.plg"></PLUGIN>'
);
file_put_contents(
  $aggregate_odd_target,
  '<PLUGIN name="aggregate-odd" version="2026.07.18" '.
    'pluginURL="http://127.0.0.1:9/aggregate-odd.plg"></PLUGIN>'
);
symlink(
  $aggregate_unsafe_target,
  "$cli_plugins/aggregate-derived-name.plg"
);
symlink(
  $aggregate_odd_target,
  "$cli_plugins/unRAIDServer.plg"
);
$aggregate_environment = getenv();
$aggregate_environment['PLUGIN_MANAGER_TEST_AGGREGATE_MARKER'] =
  $aggregate_marker;
$aggregate_environment['PLUGIN_MANAGER_TEST_NCHAN_LOG'] =
  $aggregate_nchan_log;
foreach (['checkall', 'updateall', 'checkos'] as $aggregate_method) {
  @unlink($aggregate_marker);
  file_put_contents($aggregate_nchan_log, '');
  $aggregate_result = test_finish_process(
    test_start_command(
      [$cli_wrapper, $aggregate_method, 'nchan'],
      $aggregate_environment
    )
  );
  $aggregate_messages = array_map(
    static fn($message) => base64_decode($message, true),
    file(
      $aggregate_nchan_log,
      FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    ) ?: []
  );
  test_assert(
    $aggregate_result[0] === 0,
    "$aggregate_method rejected its aggregate request: ".
      "{$aggregate_result[1]} {$aggregate_result[2]}"
  );
  test_assert(
    !file_exists($aggregate_marker),
    "$aggregate_method executed a command embedded in a derived plugin basename"
  );
  test_assert(
    count(
      array_filter(
        $aggregate_messages,
        static fn($message) => $message === '_DONE_'
      )
    ) === 1,
    "$aggregate_method did not retain exactly one nchan completion owner"
  );
  test_assert(
    !array_filter(
      $aggregate_messages,
      static fn($message) =>
        is_string($message) &&
        (
          str_contains($message, 'odd plugin') ||
          str_contains($message, 'space separated plugin')
        )
    ),
    "$aggregate_method recursively processed an unsafe derived plugin basename"
  );
}
@unlink("$cli_plugins/aggregate-derived-name.plg");
@unlink("$cli_plugins/unRAIDServer.plg");

$directory = test_directory($root, 'real-cli-branch-check');
$branch_source = "$root/unRAIDServer-branch-source.plg";
$branch_contents = <<<'PLG'
<!DOCTYPE PLUGIN [
<!ENTITY category "stable">
]>
<PLUGIN name="unRAIDServer" version="2026.07.18"
  pluginURL="http://127.0.0.1:9/unRAIDServer-&category;.plg"
  category="&category;"></PLUGIN>
PLG;
file_put_contents($branch_source, $branch_contents);
$holder = test_start_process([
  '--critical', 'install', $directory, 'branch-holder', '300', '0'
]);
test_wait_for(
  fn() => in_array('enter branch-holder', test_events($directory), true),
  2.0,
  'Branch-check holder never acquired the host lock'
);
$branch_result = test_finish_process(
  test_start_command([$cli_wrapper, 'branchcheck', $branch_source, 'next'])
);
$holder_result = test_finish_process($holder);
test_assert($holder_result[0] === 0, "Branch-check holder failed: {$holder_result[2]}");
test_assert(
  $branch_result[0] === 0 && $branch_result[3] >= 0.2,
  "Real branch check did not serialize behind the host lock: {$branch_result[2]}"
);
$branch_receipt_match = [];
preg_match(
  '/^_PLUGIN_BRANCH_RESULT_=(.+)$/m',
  $branch_result[1],
  $branch_receipt_match
);
$branch_json = isset($branch_receipt_match[1])
  ? base64_decode(trim($branch_receipt_match[1]), true)
  : false;
$branch_receipt = is_string($branch_json)
  ? json_decode($branch_json, true)
  : null;
$branch_path = is_array($branch_receipt) ? ($branch_receipt['path'] ?? null) : null;
test_assert(
  is_string($branch_path) &&
    is_file($branch_path) &&
    str_contains(
      (string)file_get_contents($branch_path),
      '<!ENTITY category "next">'
    ),
  'Real branch check did not atomically publish its private branch definition: '.
    "{$branch_result[1]} {$branch_result[2]} path=".
    var_export($branch_path, true)
);
test_assert(
  !file_exists("$cli_plugins/unRAIDServer-.plg") &&
    !is_link("$cli_plugins/unRAIDServer-.plg") &&
    !file_exists("$cli_tmp/unRAIDServer-.plg"),
  'Branch check exposed synthetic installed or shared update state'
);

$directory = test_directory($root, 'killed-real-cli-branch-check');
$kill_branch_source = "$root/unRAIDServer-killed-branch.plg";
file_put_contents($kill_branch_source, $branch_contents);
$wget_directory = "$root/wget-fixture";
mkdir($wget_directory, 0700);
$wget = "$wget_directory/wget";
file_put_contents(
  $wget,
  <<<'SH'
#!/bin/sh
printf '%s\n' "$PPID" > "$PLUGIN_MANAGER_TEST_WGET_READY"
sleep 10
exit 1
SH
);
chmod($wget, 0755);
$kill_environment = getenv();
$kill_environment['PATH'] =
  $wget_directory.':'.($kill_environment['PATH'] ?? '/usr/bin:/bin');
$kill_environment['PLUGIN_MANAGER_TEST_WGET_READY'] = "$directory/wget-ready";
$killed_branch = test_start_command(
  [$cli_wrapper, 'branchcheck', $kill_branch_source, 'next'],
  $kill_environment
);
test_wait_for(
  fn() => is_file("$directory/wget-ready"),
  2.0,
  'Killed branch check did not reach its private download'
);
$download_parent = (int)trim(file_get_contents("$directory/wget-ready"));
$mutation_group = $download_parent > 1 ? posix_getpgid($download_parent) : false;
test_assert(
  is_int($mutation_group) &&
    $mutation_group > 1 &&
    $mutation_group !== posix_getpgrp() &&
    posix_kill(-$mutation_group, 9),
  'Unable to SIGKILL the branch-check mutation group'
);
$killed_branch_result = test_finish_process($killed_branch);
test_assert(
  $killed_branch_result[0] !== 0,
  'SIGKILLed branch check unexpectedly succeeded'
);
$killed_target =
  plugin_manager_private_download_directory().
  '/os-branch-'.hash(
    'sha256',
    str_replace(
      '<!ENTITY category "stable">',
      '<!ENTITY category "next">',
      $branch_contents
    )
  ).'.plg';
test_assert(
  !file_exists("$cli_plugins/unRAIDServer-.plg") &&
    !is_link("$cli_plugins/unRAIDServer-.plg") &&
    !file_exists("$cli_tmp/unRAIDServer-.plg") &&
    !file_exists($killed_target),
  'SIGKILLed branch check left synthetic or partially-published state'
);
$branch_private_directory = plugin_manager_private_download_directory();
$first_branch_debris =
  glob("$branch_private_directory/.plugin-branch-*", GLOB_NOSORT) ?: [];
test_assert(
  count($first_branch_debris) <= 2,
  'One SIGKILLed branch check left unbounded private temporary files'
);
$kill_environment['PLUGIN_MANAGER_TEST_WGET_READY'] =
  "$directory/wget-ready-second";
$killed_branch_again = test_start_command(
  [$cli_wrapper, 'branchcheck', $kill_branch_source, 'next'],
  $kill_environment
);
test_wait_for(
  fn() => is_file("$directory/wget-ready-second"),
  2.0,
  'Repeated killed branch check did not reach its private download'
);
$second_branch_debris =
  glob("$branch_private_directory/.plugin-branch-*", GLOB_NOSORT) ?: [];
test_assert(
  count($second_branch_debris) <= 2 &&
    array_intersect($first_branch_debris, $second_branch_debris) === [],
  'A later branch check did not retire prior private SIGKILL debris'
);
$download_parent = (int)trim(
  file_get_contents("$directory/wget-ready-second")
);
$mutation_group = $download_parent > 1
  ? posix_getpgid($download_parent)
  : false;
test_assert(
  is_int($mutation_group) &&
    $mutation_group > 1 &&
    $mutation_group !== posix_getpgrp() &&
    posix_kill(-$mutation_group, 9),
  'Unable to SIGKILL the repeated branch-check mutation group'
);
$killed_branch_again_result = test_finish_process($killed_branch_again);
$remaining_branch_debris =
  glob("$branch_private_directory/.plugin-branch-*", GLOB_NOSORT) ?: [];
test_assert(
  $killed_branch_again_result[0] !== 0 &&
    count($remaining_branch_debris) <= 2 &&
    !file_exists($killed_target),
  'Repeated SIGKILL accumulated private debris or published partial state'
);

$directory = test_directory($root, 'real-cli-history-delete');
$history_boot = "$root/history-boot/config/plugins";
mkdir("{$history_boot}-error", 0700, true);
mkdir("{$history_boot}-stale", 0700, true);
$history_source = str_replace(
  "\$boot          = '/boot/config/plugins';",
  '$boot          = '.var_export($history_boot, true).';',
  $cli_plugin_source,
  $history_replacements
);
test_assert(
  $history_replacements === 1,
  'Unable to isolate boot history paths for CLI deletion testing'
);
$history_plugin = "$root/plugin-history-copy";
file_put_contents($history_plugin, $history_source);
$history_wrapper = "$root/plugin-history-executable";
file_put_contents(
  $history_wrapper,
  "#!/usr/bin/env php\n<?PHP\n".
    '$docroot = '.var_export($nchan_root, true).";\n".
    'require '.var_export($history_plugin, true).";\n"
);
chmod($history_wrapper, 0755);
$history_file = "{$history_boot}-stale/old-plugin.plg";
file_put_contents($history_file, '<PLUGIN name="old-plugin" version="1"></PLUGIN>');
$holder = test_start_process([
  '--critical', 'install', $directory, 'history-holder', '300', '0'
]);
test_wait_for(
  fn() => in_array('enter history-holder', test_events($directory), true),
  2.0,
  'History-delete holder never acquired the host lock'
);
$history_result = test_finish_process(
  test_start_command([$history_wrapper, 'history-delete', $history_file])
);
$holder_result = test_finish_process($holder);
test_assert($holder_result[0] === 0, "History-delete holder failed: {$holder_result[2]}");
test_assert(
  $history_result[0] === 0 &&
    $history_result[3] >= 0.2 &&
    !file_exists($history_file),
  "Boot-history deletion did not serialize its persistent mutation: {$history_result[2]}"
);

$directory = test_directory($root, 'executable-stale-owner');
$token = str_repeat('a', 32);
$actual = test_finish_process(test_start_command([
  '/usr/bin/env',
  PLUGIN_MANAGER_LOCK_OWNER_PID_ENV.'=1',
  PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token",
  $wrapper,
  'check',
  'missing.plg'
]));
test_assert($actual[0] === 1, 'Plugin executable returned an unexpected stale-owner status');
test_assert(
  str_contains($actual[1], 'plugin: checking: missing.plg'),
  "Plugin executable did not recover from stale inherited ownership: {$actual[1]} {$actual[2]}"
);
test_assert(
  !str_contains($actual[2], 'unable to acquire operation lock'),
  "Plugin executable treated stale inherited ownership as live: {$actual[2]}"
);

fwrite(STDOUT, "PASS: Plugin Manager host-wide operation lock\n");
