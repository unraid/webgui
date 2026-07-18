#!/usr/bin/env php
<?PHP
// Copyright 2005-2026, Lime Technology
// License: GPLv2 only

$repo = dirname(__DIR__, 2);
require_once "$repo/emhttp/plugins/dynamix.plugin.manager/include/PluginOperationLock.php";

function production_alias_fail(string $message): never {
  fwrite(STDERR, "FAIL: $message\n");
  exit(1);
}

function production_alias_assert(bool $condition, string $message): void {
  if (!$condition) production_alias_fail($message);
}

function production_alias_run(array $command, array $environment): array {
  $pipes = [];
  $process = proc_open(
    $command,
    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    null,
    $environment
  );
  if (!is_resource($process)) production_alias_fail('Unable to start plugin CLI');
  stream_set_blocking($pipes[1], false);
  stream_set_blocking($pipes[2], false);
  $stdout = '';
  $stderr = '';
  $started = microtime(true);
  $status = null;
  do {
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    $process_status = proc_get_status($process);
    if (!$process_status['running']) {
      $status = $process_status['exitcode'];
      break;
    }
    if (microtime(true) - $started > 10.0) {
      proc_terminate($process, 9);
      production_alias_fail('Plugin CLI timed out');
    }
    usleep(10000);
  } while (true);
  $stdout .= stream_get_contents($pipes[1]);
  $stderr .= stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $closed_status = proc_close($process);
  if ($status === -1) $status = $closed_status;
  return [$status, $stdout, $stderr];
}

production_alias_assert(
  PHP_OS_FAMILY === 'Linux' &&
    is_link('/var/run') &&
    realpath('/var/run') === '/run',
  'Container does not expose the production /var/run to /run alias'
);

foreach (
  [
    PLUGIN_MANAGER_LOCK_PATH_ENV,
    PLUGIN_MANAGER_LOCK_OWNER_PID_ENV,
    PLUGIN_MANAGER_LOCK_TOKEN_ENV,
    PLUGIN_MANAGER_LOCK_SCOPE_ENV,
    PLUGIN_MANAGER_LOCK_PROTOCOL_ENV,
    PLUGIN_MANAGER_LOCK_ENTRY_ENV,
    PLUGIN_MANAGER_LOCK_MEMBER_ENV
  ] as $name
) {
  putenv($name);
}

$root = '/tmp/plugin-production-run-alias';
$plugin = 'production-run-alias.plg';
$installed = "/boot/config/plugins/$plugin";
$latest_source = "$root/$plugin";
$latest = "/tmp/plugins/$plugin";
$wrapper = "$root/plugin";
$fake_bin = "$root/bin";

@mkdir($root, 0700, true);
@mkdir($fake_bin, 0700, true);
@mkdir('/boot/config/plugins', 0770, true);
@mkdir('/var/log/plugins', 0755, true);
@mkdir('/tmp/plugins', 0755, true);
file_put_contents('/etc/unraid-version', "version=\"7.3.0\"\n");
file_put_contents(
  $installed,
  '<PLUGIN name="production-run-alias" version="1.0.0" '.
    'pluginURL="file:///tmp/plugin-production-run-alias/'.
    $plugin.'"></PLUGIN>'
);
file_put_contents(
  $latest_source,
  '<PLUGIN name="production-run-alias" version="2.0.0" '.
    'pluginURL="file:///tmp/plugin-production-run-alias/'.
    $plugin.'"></PLUGIN>'
);
@unlink("/var/log/plugins/$plugin");
symlink($installed, "/var/log/plugins/$plugin");

$wget = "$fake_bin/wget";
file_put_contents(
  $wget,
  <<<'SH'
#!/bin/sh
set -eu
output=""
source=""
while [ "$#" -gt 0 ]; do
  case "$1" in
    -O)
      shift
      output="${1:-}"
      ;;
    file://*)
      source="${1#file://}"
      ;;
  esac
  shift
done
[ -n "$output" ] && [ -n "$source" ]
cp "$source" "$output"
SH
);
chmod($wget, 0755);
file_put_contents(
  $wrapper,
  "#!/usr/bin/env php\n<?PHP\n".
    '$docroot = '.var_export("$repo/emhttp", true).";\n".
    'require '.var_export(
      "$repo/emhttp/plugins/dynamix.plugin.manager/scripts/plugin",
      true
    ).";\n"
);
chmod($wrapper, 0755);

$environment = getenv();
foreach (
  [
    PLUGIN_MANAGER_LOCK_PATH_ENV,
    PLUGIN_MANAGER_LOCK_OWNER_PID_ENV,
    PLUGIN_MANAGER_LOCK_TOKEN_ENV,
    PLUGIN_MANAGER_LOCK_SCOPE_ENV,
    PLUGIN_MANAGER_LOCK_PROTOCOL_ENV,
    PLUGIN_MANAGER_LOCK_ENTRY_ENV,
    PLUGIN_MANAGER_LOCK_MEMBER_ENV
  ] as $name
) {
  unset($environment[$name]);
}
$environment['PATH'] = "$fake_bin:/usr/local/bin:/usr/bin:/bin";
$environment[PLUGIN_MANAGER_FLOCK_PATH_ENV] = '/usr/bin/flock';
$environment[PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV] =
  "$repo/emhttp/plugins/dynamix.plugin.manager/scripts/plugin-operation-lock";

$check = production_alias_run([$wrapper, 'check', $plugin], $environment);
production_alias_assert(
  $check[0] === 0 &&
    is_file($latest) &&
    str_contains((string)file_get_contents($latest), 'version="2.0.0"'),
  "Production-default CLI check failed: {$check[1]} {$check[2]}"
);

$private_directory = plugin_manager_private_download_directory();
production_alias_assert(
  $private_directory === '/var/run/unraid-plugin-manager/downloads' &&
    realpath($private_directory) === '/run/unraid-plugin-manager/downloads',
  'Production private directory did not retain its expected aliased spelling'
);
$probe = plugin_manager_create_private_download_file();
production_alias_assert(
  dirname($probe) === '/run/unraid-plugin-manager/downloads' &&
    plugin_manager_private_artifact_is_safe(
      $probe,
      $private_directory,
      '/^\.plugin-check-[A-Za-z0-9]+$/D',
      0600
    ),
  'Production tempnam path did not cross the /var/run alias safely'
);
@unlink($probe);

$update = production_alias_run([$wrapper, 'update', $plugin], $environment);
production_alias_assert(
  $update[0] === 0 &&
    str_contains((string)file_get_contents($installed), 'version="2.0.0"') &&
    is_link("/var/log/plugins/$plugin") &&
    readlink("/var/log/plugins/$plugin") === $installed &&
    glob('/run/unraid-plugin-manager/downloads/.plugin-check-*') === [] &&
    glob('/run/unraid-plugin-manager/operations.lock.scope.*') === [],
  "Production-default CLI update snapshot failed: {$update[1]} {$update[2]}"
);

echo "Production /var/run alias integration test passed.\n";
