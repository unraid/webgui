<?PHP
// Copyright 2005-2026, Lime Technology
// License: GPLv2 only

const PLUGIN_MANAGER_LOCK_PATH_ENV = 'UNRAID_PLUGIN_MANAGER_LOCK_PATH';
const PLUGIN_MANAGER_FLOCK_PATH_ENV = 'UNRAID_PLUGIN_MANAGER_FLOCK_PATH';
const PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV = 'UNRAID_PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH';
const PLUGIN_MANAGER_LOCK_OWNER_PID_ENV = 'UNRAID_PLUGIN_MANAGER_LOCK_OWNER_PID';
const PLUGIN_MANAGER_LOCK_TOKEN_ENV = 'UNRAID_PLUGIN_MANAGER_LOCK_TOKEN';
const PLUGIN_MANAGER_LOCK_SCOPE_ENV = 'UNRAID_PLUGIN_MANAGER_LOCK_SCOPE';
const PLUGIN_MANAGER_ARTIFACT_POLICY_ENV = 'UNRAID_PLUGIN_MANAGER_ARTIFACT_POLICY';
const PLUGIN_MANAGER_DEFAULT_LOCK_PATH = '/var/run/unraid-plugin-manager/operations.lock';
const PLUGIN_MANAGER_SHARED_ARTIFACT_DIRECTORY = '/tmp/plugins';

/**
 * Aggregate operations intentionally remain unlocked. They invoke this command
 * recursively, and each stateful child operation takes the host-wide lock.
 * check acquires this lock only after its private network download completes,
 * because publication and the shared pre/post hooks are the mutating phases.
 */
function plugin_manager_operation_requires_lock(string $method): bool {
  return in_array($method, ['install', 'check', 'update', 'download', 'remove', 'validate'], true);
}

/**
 * Return true while this process belongs to a registered live lock scope.
 * Every reentrant plugin process registers a PID/start-time lease. The flock
 * supervisor retains the mutex until the direct operation and every registered
 * nested operation exit, including when the direct operation is killed.
 */
function plugin_manager_operation_has_live_owner(): bool {
  $owner_pid = getenv(PLUGIN_MANAGER_LOCK_OWNER_PID_ENV);
  $token = getenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV);
  $scope = getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV);

  if ($owner_pid === false && $token === false && $scope === false) return false;
  if (!is_string($owner_pid) || !preg_match('/^[1-9]\d*$/D', $owner_pid)) {
    throw new RuntimeException('Plugin Manager lock owner PID is invalid');
  }
  if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
    throw new RuntimeException('Plugin Manager lock owner token is invalid');
  }
  $owner_process = "/proc/$owner_pid";
  if (!is_dir($owner_process)) return false;

  $environment = @file_get_contents("$owner_process/environ");
  if ($environment === false) {
    return false;
  }

  $owner_environment = explode("\0", $environment);
  if (!in_array(PLUGIN_MANAGER_LOCK_TOKEN_ENV."=$token", $owner_environment, true)) {
    return false;
  }

  [$lock] = plugin_manager_operation_lock_path();
  $expected_scope = "$lock.scope.$token";
  if (
    !is_string($scope) ||
    $scope !== $expected_scope ||
    !in_array(PLUGIN_MANAGER_LOCK_SCOPE_ENV."=$scope", $owner_environment, true)
  ) {
    throw new RuntimeException('Plugin Manager lock scope is invalid');
  }

  return plugin_manager_register_lock_member($scope);
}

function plugin_manager_process_start_time(int $pid): ?string {
  $stat = @file_get_contents("/proc/$pid/stat");
  if ($stat === false) return null;

  $command_end = strrpos($stat, ') ');
  if ($command_end === false) return null;
  $fields = preg_split('/\s+/', trim(substr($stat, $command_end + 2)));
  $start_time = $fields[19] ?? null;
  return is_string($start_time) && preg_match('/^\d+$/D', $start_time) ? $start_time : null;
}

/**
 * Validate an inherited scope. Missing is a normal retirement signal; any
 * surviving object must still be the supervisor's private mode-0700 directory.
 */
function plugin_manager_lock_scope_is_safe(string $scope): bool {
  clearstatcache(true, $scope);
  $scope_status = @lstat($scope);
  if ($scope_status === false) return false;
  if (
    plugin_manager_lock_path_type($scope_status) !== 0040000 ||
    ($scope_status['mode'] & 07777) !== 0700
  ) {
    throw new RuntimeException('Plugin Manager lock scope is missing or unsafe');
  }
  return true;
}

/**
 * Create one member lease after the scope has been validated. A missing scope
 * means the prior supervisor retired it atomically; an unsafe replacement is
 * always an error.
 */
function plugin_manager_create_lock_member_lease(string $scope, string $lease): bool {
  $handle = @fopen($lease, 'x');
  if ($handle !== false) {
    fclose($handle);
    if (!@chmod($lease, 0600)) {
      @unlink($lease);
      throw new RuntimeException('Unable to secure Plugin Manager lock member');
    }
    try {
      $scope_is_safe = plugin_manager_lock_scope_is_safe($scope);
    } catch (Throwable $error) {
      @unlink($lease);
      throw $error;
    }
    if (!$scope_is_safe) {
      @unlink($lease);
      return false;
    }
    return true;
  }

  if (!plugin_manager_lock_scope_is_safe($scope)) return false;
  clearstatcache(true, $lease);
  $lease_status = @lstat($lease);
  if (
    $lease_status !== false &&
    plugin_manager_lock_path_type($lease_status) === 0100000
  ) {
    return true;
  }

  throw new RuntimeException('Unable to register Plugin Manager lock member');
}

/**
 * Register this process with the inherited supervisor. False means the valid
 * scope was retired before the lease could be opened, so the caller must
 * acquire a fresh global operation lock.
 */
function plugin_manager_register_lock_member(string $scope): bool {
  if (!plugin_manager_lock_scope_is_safe($scope)) return false;

  $pid = getmypid();
  $start_time = plugin_manager_process_start_time($pid);
  if ($start_time === null) {
    throw new RuntimeException('Unable to identify Plugin Manager lock member process');
  }

  $lease = "$scope/$pid.$start_time";
  if (!plugin_manager_create_lock_member_lease($scope, $lease)) return false;

  register_shutdown_function(static function () use ($lease): void {
    @unlink($lease);
  });
  return true;
}

function plugin_manager_lock_path_type(array $status): int {
  return $status['mode'] & 0170000;
}

function plugin_manager_effective_user_id(): ?int {
  return function_exists('posix_geteuid') ? posix_geteuid() : null;
}

function plugin_manager_operation_lock_path(): array {
  $lock_override = getenv(PLUGIN_MANAGER_LOCK_PATH_ENV);
  $production = $lock_override === false || $lock_override === '';
  return [
    $production ? PLUGIN_MANAGER_DEFAULT_LOCK_PATH : $lock_override,
    $production
  ];
}

/**
 * Prepare a private lock directory and regular lock file before passing the
 * path to flock. A private directory makes the lstat/open sequence safe from
 * replacement by another user.
 */
function plugin_manager_prepare_lock_path(string $lock, bool $production): void {
  if ($lock === '' || $lock[0] !== '/') {
    throw new RuntimeException("Plugin Manager lock path must be absolute: $lock");
  }

  $owner = plugin_manager_effective_user_id();
  if ($production && $owner !== null && $owner !== 0) {
    throw new RuntimeException('The production Plugin Manager lock must be created by root');
  }

  $parent = dirname($lock);
  $parent_status = @lstat($parent);
  if ($parent_status === false) {
    @mkdir($parent, 0700, true);
    clearstatcache(true, $parent);
    $parent_status = @lstat($parent);
  }

  if ($parent_status === false) {
    throw new RuntimeException("Unable to create Plugin Manager lock directory: $parent");
  }
  if (plugin_manager_lock_path_type($parent_status) !== 0040000) {
    throw new RuntimeException("Plugin Manager lock parent is not a directory: $parent");
  }
  if (($parent_status['mode'] & 07777) !== 0700) {
    throw new RuntimeException("Plugin Manager lock directory must have mode 0700: $parent");
  }
  if ($production && $parent_status['uid'] !== 0) {
    throw new RuntimeException("Plugin Manager lock directory must be owned by root: $parent");
  }
  if (!$production && $owner !== null && $parent_status['uid'] !== $owner) {
    throw new RuntimeException("Plugin Manager lock directory must be owned by the current user: $parent");
  }

  $lock_status = @lstat($lock);
  if ($lock_status === false) {
    $handle = @fopen($lock, 'x');
    if ($handle !== false) {
      fclose($handle);
      if (!@chmod($lock, 0600)) {
        @unlink($lock);
        throw new RuntimeException("Unable to secure Plugin Manager lock file: $lock");
      }
    }
    clearstatcache(true, $lock);
    $lock_status = @lstat($lock);
  }

  if ($lock_status === false) {
    throw new RuntimeException("Unable to create Plugin Manager lock file: $lock");
  }
  if (plugin_manager_lock_path_type($lock_status) !== 0100000) {
    throw new RuntimeException("Plugin Manager lock path is not a regular file: $lock");
  }
  if ($production && $lock_status['uid'] !== 0) {
    throw new RuntimeException("Plugin Manager lock file must be owned by root: $lock");
  }
  if (!$production && $owner !== null && $lock_status['uid'] !== $owner) {
    throw new RuntimeException("Plugin Manager lock file must be owned by the current user: $lock");
  }
  if (($lock_status['mode'] & 07777) !== 0600) {
    if (!@chmod($lock, 0600)) {
      throw new RuntimeException("Unable to set Plugin Manager lock file mode to 0600: $lock");
    }
    clearstatcache(true, $lock);
    $lock_status = @lstat($lock);
    if ($lock_status === false || ($lock_status['mode'] & 07777) !== 0600) {
      throw new RuntimeException("Plugin Manager lock file must have mode 0600: $lock");
    }
  }
}

function plugin_manager_expected_path_owner(bool $production): ?int {
  return $production ? 0 : plugin_manager_effective_user_id();
}

/**
 * Return the root/current-user-owned mode-0700 directory used for network
 * candidates. It shares the already validated operation-lock parent rather
 * than relying on the ownership of /tmp/plugins.
 */
function plugin_manager_private_download_directory(): string {
  [$lock, $production] = plugin_manager_operation_lock_path();
  plugin_manager_prepare_lock_path($lock, $production);

  $directory = dirname($lock).'/downloads';
  clearstatcache(true, $directory);
  $status = @lstat($directory);
  if ($status === false) {
    @mkdir($directory, 0700);
    clearstatcache(true, $directory);
    $status = @lstat($directory);
  }

  $expected_owner = plugin_manager_expected_path_owner($production);
  if (
    $status === false ||
    plugin_manager_lock_path_type($status) !== 0040000 ||
    ($status['mode'] & 07777) !== 0700 ||
    ($expected_owner !== null && $status['uid'] !== $expected_owner)
  ) {
    throw new RuntimeException(
      'Plugin Manager private download directory is missing or unsafe'
    );
  }
  return $directory;
}

function plugin_manager_create_private_download_file(): string {
  $directory = plugin_manager_private_download_directory();
  $path = tempnam($directory, '.plugin-check-');
  if ($path === false || dirname($path) !== $directory || !@chmod($path, 0600)) {
    if (is_string($path)) @unlink($path);
    throw new RuntimeException('Unable to create private Plugin Manager download file');
  }

  clearstatcache(true, $path);
  $status = @lstat($path);
  [$lock, $production] = plugin_manager_operation_lock_path();
  unset($lock);
  $expected_owner = plugin_manager_expected_path_owner($production);
  if (
    $status === false ||
    plugin_manager_lock_path_type($status) !== 0100000 ||
    ($status['mode'] & 07777) !== 0600 ||
    ($expected_owner !== null && $status['uid'] !== $expected_owner)
  ) {
    @unlink($path);
    throw new RuntimeException('Plugin Manager private download file is unsafe');
  }
  return $path;
}

/**
 * Validate the shared artifact directory without following a leaf symlink.
 * A root-owned, non-group/other-writable child of sticky /tmp cannot be
 * replaced by another local principal.
 */
function plugin_manager_prepare_shared_artifact_directory(
  string $directory = PLUGIN_MANAGER_SHARED_ARTIFACT_DIRECTORY
): void {
  if ($directory === '' || $directory[0] !== '/') {
    throw new RuntimeException('Plugin Manager shared artifact directory must be absolute');
  }

  clearstatcache(true, $directory);
  $status = @lstat($directory);
  if ($status === false) {
    @mkdir($directory, 0755);
    clearstatcache(true, $directory);
    $status = @lstat($directory);
  }

  [$lock, $production] = plugin_manager_operation_lock_path();
  unset($lock);
  $expected_owner = plugin_manager_expected_path_owner($production);
  if (
    $status !== false &&
    plugin_manager_lock_path_type($status) === 0040000 &&
    ($expected_owner === null || $status['uid'] === $expected_owner) &&
    ($status['mode'] & 0022) !== 0
  ) {
    @chmod($directory, 0755);
    clearstatcache(true, $directory);
    $status = @lstat($directory);
  }
  if (
    $status === false ||
    plugin_manager_lock_path_type($status) !== 0040000 ||
    ($status['mode'] & 0022) !== 0 ||
    ($expected_owner !== null && $status['uid'] !== $expected_owner)
  ) {
    throw new RuntimeException(
      "Plugin Manager shared artifact directory is missing or unsafe: $directory"
    );
  }
}

/**
 * Build the command which re-executes the requested operation under the
 * host-wide mutex. The signal-aware supervisor owns the validated lock
 * descriptor, closes it explicitly in the operation child, and retains the
 * mutex for registered nested plugin operations.
 */
function plugin_manager_operation_lock_command(string $method, string $script, array $argv): ?string {
  if (!plugin_manager_operation_requires_lock($method) || plugin_manager_operation_has_live_owner()) {
    return null;
  }

  $flock = getenv(PLUGIN_MANAGER_FLOCK_PATH_ENV) ?: '/usr/bin/flock';
  $supervisor =
    getenv(PLUGIN_MANAGER_LOCK_SUPERVISOR_PATH_ENV) ?:
    dirname(__DIR__).'/scripts/plugin-operation-lock';
  [$lock, $production_lock] = plugin_manager_operation_lock_path();

  if (!is_executable($flock)) {
    throw new RuntimeException("Plugin Manager lock utility is not executable: $flock");
  }
  if (!is_executable($supervisor)) {
    throw new RuntimeException("Plugin Manager lock supervisor is not executable: $supervisor");
  }

  plugin_manager_prepare_lock_path($lock, $production_lock);

  $resolved_script = realpath($script) ?: $script;
  $arguments = array_values($argv);
  $arguments[0] = $resolved_script;
  $token = bin2hex(random_bytes(16));
  $scope = "$lock.scope.$token";

  $command = PLUGIN_MANAGER_LOCK_TOKEN_ENV.'='.escapeshellarg($token);
  $command .= ' '.PLUGIN_MANAGER_LOCK_SCOPE_ENV.'='.escapeshellarg($scope);
  $command .= ' '.escapeshellarg($supervisor);
  $command .= ' '.escapeshellarg($flock);
  $command .= ' '.escapeshellarg($lock);
  $command .= ' --';

  foreach ($arguments as $argument) {
    $command .= ' '.escapeshellarg((string)$argument);
  }

  return $command;
}

/**
 * Open the prepared mutex and verify that the opened descriptor still names
 * the regular file which was validated.
 *
 * @return resource
 */
function plugin_manager_open_validated_lock(string $lock, bool $production_lock) {
  plugin_manager_prepare_lock_path($lock, $production_lock);
  $handle = @fopen($lock, 'r+');
  if ($handle === false) {
    throw new RuntimeException("Unable to open Plugin Manager lock file: $lock");
  }

  $path_status = @lstat($lock);
  $handle_status = @fstat($handle);
  if (
    $path_status === false ||
    $handle_status === false ||
    plugin_manager_lock_path_type($path_status) !== 0100000 ||
    plugin_manager_lock_path_type($handle_status) !== 0100000 ||
    $path_status['dev'] !== $handle_status['dev'] ||
    $path_status['ino'] !== $handle_status['ino']
  ) {
    fclose($handle);
    throw new RuntimeException("Plugin Manager lock path changed while opening: $lock");
  }

  return $handle;
}

/**
 * @return resource
 */
function plugin_manager_open_operation_lock() {
  [$lock, $production_lock] = plugin_manager_operation_lock_path();
  return plugin_manager_open_validated_lock($lock, $production_lock);
}

/**
 * Run an in-process operation while holding the same host-wide mutex used by
 * the plugin executable.
 */
function plugin_manager_with_operation_lock(callable $operation): mixed {
  $handle = plugin_manager_open_operation_lock();
  if (!@flock($handle, LOCK_EX)) {
    fclose($handle);
    throw new RuntimeException('Unable to acquire Plugin Manager operation lock');
  }

  try {
    return $operation();
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

/**
 * Run an API check publication while holding the host-wide mutex. A null result
 * or exception means the reserved generation failed and must be revoked before
 * another operation can acquire the mutex and consume an older artifact.
 */
function plugin_manager_with_plugin_check_operation_lock(
  string $plugin,
  int $generation,
  string $latest,
  callable $operation
): mixed {
  return plugin_manager_with_operation_lock(
    static function() use ($plugin, $generation, $latest, $operation): mixed {
      $successful = false;
      try {
        $result = $operation();
        $successful = $result !== null;
        return $result;
      } finally {
        if (!$successful) {
          plugin_manager_invalidate_plugin_check_artifact(
            $plugin,
            $generation,
            $latest
          );
        }
      }
    }
  );
}

/**
 * Run a short per-plugin check-state transaction. Callers must never hold this
 * lock while waiting for the global operation lock: global-lock holders also
 * use it briefly when publishing an artifact.
 */
function plugin_manager_with_plugin_check_lock(string $plugin, callable $operation): mixed {
  [$global_lock, $production_lock] = plugin_manager_operation_lock_path();
  $check_lock = dirname($global_lock).'/check-'.hash('sha256', $plugin).'.lock';
  $handle = plugin_manager_open_validated_lock($check_lock, $production_lock);
  if (!@flock($handle, LOCK_EX)) {
    fclose($handle);
    throw new RuntimeException('Unable to acquire Plugin Manager check lock');
  }

  try {
    return $operation($handle);
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

/**
 * Keep generation state separate from the stable inode used for flock. An
 * atomic rename of the flocked path would leave waiters split across inodes.
 *
 * @param resource $handle
 */
function plugin_manager_plugin_check_state_path($handle): string {
  $metadata = stream_get_meta_data($handle);
  $lock_path = $metadata['uri'] ?? null;
  if (!is_string($lock_path) || $lock_path === '' || $lock_path[0] !== '/') {
    throw new RuntimeException('Plugin Manager check lock path is invalid');
  }
  return "$lock_path.state";
}

/**
 * @param resource $handle
 * @return array{next: int, published: int, valid: bool, hash: ?string}
 */
function plugin_manager_read_plugin_check_state($handle): array {
  $state_path = plugin_manager_plugin_check_state_path($handle);
  clearstatcache(true, $state_path);
  $path_status = @lstat($state_path);
  if ($path_status === false) {
    return ['next' => 0, 'published' => 0, 'valid' => false, 'hash' => null];
  }
  $owner = plugin_manager_effective_user_id();
  if (
    plugin_manager_lock_path_type($path_status) !== 0100000 ||
    ($path_status['mode'] & 07777) !== 0600 ||
    ($owner !== null && $path_status['uid'] !== $owner)
  ) {
    throw new RuntimeException('Plugin Manager check state path is unsafe');
  }

  $state_handle = @fopen($state_path, 'rb');
  if ($state_handle === false) {
    throw new RuntimeException('Unable to open Plugin Manager check state');
  }
  try {
    clearstatcache(true, $state_path);
    $opened_path_status = @lstat($state_path);
    $handle_status = @fstat($state_handle);
    if (
      $opened_path_status === false ||
      $handle_status === false ||
      plugin_manager_lock_path_type($opened_path_status) !== 0100000 ||
      plugin_manager_lock_path_type($handle_status) !== 0100000 ||
      $opened_path_status['dev'] !== $handle_status['dev'] ||
      $opened_path_status['ino'] !== $handle_status['ino'] ||
      ($opened_path_status['mode'] & 07777) !== 0600 ||
      ($owner !== null && $opened_path_status['uid'] !== $owner)
    ) {
      throw new RuntimeException('Plugin Manager check state changed while opening');
    }
    $contents = trim((string)stream_get_contents($state_handle));
  } finally {
    fclose($state_handle);
  }

  if ($contents === '') {
    throw new RuntimeException('Plugin Manager check state is invalid');
  }
  if (
    !preg_match(
      '/^([0-9]+):([0-9]+)(?::([01])(?::([a-f0-9]{64}|-))?)?$/D',
      $contents,
      $matches
    )
  ) {
    throw new RuntimeException('Plugin Manager check state is invalid');
  }

  $next = filter_var($matches[1], FILTER_VALIDATE_INT);
  $published = filter_var($matches[2], FILTER_VALIDATE_INT);
  if ($next === false || $published === false || $published > $next) {
    throw new RuntimeException('Plugin Manager check state is invalid');
  }
  $valid = isset($matches[3]) ? $matches[3] === '1' : $published > 0;
  $hash = isset($matches[4]) && $matches[4] !== '-' ? $matches[4] : null;
  return ['next' => $next, 'published' => $published, 'valid' => $valid, 'hash' => $hash];
}

/**
 * @param resource $handle
 * @param array{next: int, published: int, valid: bool, hash: ?string} $state
 * @param array{stage_open?: bool, stage_write_after?: int, rename?: bool} $faults
 */
function plugin_manager_write_plugin_check_state(
  $handle,
  array $state,
  array $faults = []
): void {
  if (
    $state['next'] < 0 ||
    $state['published'] < 0 ||
    $state['published'] > $state['next'] ||
    ($state['hash'] !== null && !preg_match('/^[a-f0-9]{64}$/D', $state['hash']))
  ) {
    throw new RuntimeException('Plugin Manager check state is invalid');
  }

  $valid = $state['valid'] ? 1 : 0;
  $hash = $state['hash'] ?? '-';
  $contents = "{$state['next']}:{$state['published']}:$valid:$hash\n";
  $state_path = plugin_manager_plugin_check_state_path($handle);
  clearstatcache(true, $state_path);
  $state_status = @lstat($state_path);
  $owner = plugin_manager_effective_user_id();
  if (
    $state_status !== false &&
    (
      plugin_manager_lock_path_type($state_status) !== 0100000 ||
      ($state_status['mode'] & 07777) !== 0600 ||
      ($owner !== null && $state_status['uid'] !== $owner)
    )
  ) {
    throw new RuntimeException('Plugin Manager check state path is unsafe');
  }

  $stage_path = null;
  try {
    $stage = plugin_manager_stage_sibling_contents(
      $state_path,
      'check-state',
      $contents,
      0600,
      ($faults['stage_open'] ?? false) === true,
      isset($faults['stage_write_after']) ? (int)$faults['stage_write_after'] : null
    );
    $stage_path = $stage['path'];
    if (($faults['rename'] ?? false) === true || !@rename($stage_path, $state_path)) {
      throw new RuntimeException('Unable to atomically commit Plugin Manager check state');
    }
    $stage_path = null;
    $persisted = plugin_manager_read_plugin_check_state($handle);
    if ($persisted !== $state) {
      throw new RuntimeException('Committed Plugin Manager check state does not match');
    }
  } catch (Throwable $error) {
    if ($stage_path !== null) @unlink($stage_path);
    throw $error;
  }
}

/**
 * Capture the exact regular inode and bytes produced by a completed network
 * download. The receipt is safe to carry across the global-lock wait.
 *
 * @return array{path: string, hash: string, dev: int, ino: int, size: int}|false
 */
function plugin_manager_capture_download_receipt(
  string $path,
  ?string $expected_hash = null
): array|false {
  if (
    $path === '' ||
    $path[0] !== '/' ||
    (
      $expected_hash !== null &&
      !preg_match('/^[a-f0-9]{64}$/D', $expected_hash)
    )
  ) {
    return false;
  }

  clearstatcache(true, $path);
  $path_status = @lstat($path);
  if (
    $path_status === false ||
    plugin_manager_lock_path_type($path_status) !== 0100000 ||
    ($path_status['mode'] & 0022) !== 0
  ) {
    return false;
  }

  [$lock, $production] = plugin_manager_operation_lock_path();
  unset($lock);
  $expected_owner = plugin_manager_expected_path_owner($production);
  if ($expected_owner !== null && $path_status['uid'] !== $expected_owner) {
    return false;
  }

  $handle = @fopen($path, 'rb');
  if ($handle === false) return false;
  try {
    if (!@flock($handle, LOCK_SH)) return false;
    clearstatcache(true, $path);
    $opened_path_status = @lstat($path);
    $handle_status = @fstat($handle);
    if (
      $opened_path_status === false ||
      $handle_status === false ||
      plugin_manager_lock_path_type($opened_path_status) !== 0100000 ||
      plugin_manager_lock_path_type($handle_status) !== 0100000 ||
      $opened_path_status['dev'] !== $handle_status['dev'] ||
      $opened_path_status['ino'] !== $handle_status['ino'] ||
      ($opened_path_status['mode'] & 0022) !== 0 ||
      ($expected_owner !== null && $opened_path_status['uid'] !== $expected_owner)
    ) {
      return false;
    }

    $contents = stream_get_contents($handle);
    if (!is_string($contents) || $contents === '') return false;
    $hash = hash('sha256', $contents);
    if ($expected_hash !== null && !hash_equals($expected_hash, $hash)) {
      return false;
    }

    clearstatcache(true, $path);
    $final_path_status = @lstat($path);
    $final_handle_status = @fstat($handle);
    if (
      $final_path_status === false ||
      $final_handle_status === false ||
      $final_path_status['dev'] !== $final_handle_status['dev'] ||
      $final_path_status['ino'] !== $final_handle_status['ino'] ||
      $final_handle_status['size'] !== strlen($contents)
    ) {
      return false;
    }

    return [
      'path' => $path,
      'hash' => $hash,
      'dev' => $final_handle_status['dev'],
      'ino' => $final_handle_status['ino'],
      'size' => $final_handle_status['size']
    ];
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

/**
 * Read only the inode and bytes named by a previously captured receipt.
 *
 * @param array{path: string, hash: string, dev: int, ino: int, size: int} $receipt
 */
function plugin_manager_read_download_receipt(array $receipt): string|false {
  $path = $receipt['path'] ?? null;
  $hash = $receipt['hash'] ?? null;
  $dev = $receipt['dev'] ?? null;
  $ino = $receipt['ino'] ?? null;
  $size = $receipt['size'] ?? null;
  if (
    !is_string($path) ||
    $path === '' ||
    $path[0] !== '/' ||
    !is_string($hash) ||
    !preg_match('/^[a-f0-9]{64}$/D', $hash) ||
    !is_int($dev) ||
    !is_int($ino) ||
    !is_int($size) ||
    $size < 1
  ) {
    return false;
  }

  clearstatcache(true, $path);
  $path_status = @lstat($path);
  [$lock, $production] = plugin_manager_operation_lock_path();
  unset($lock);
  $expected_owner = plugin_manager_expected_path_owner($production);
  if (
    $path_status === false ||
    plugin_manager_lock_path_type($path_status) !== 0100000 ||
    $path_status['dev'] !== $dev ||
    $path_status['ino'] !== $ino ||
    $path_status['size'] !== $size ||
    ($path_status['mode'] & 0022) !== 0 ||
    ($expected_owner !== null && $path_status['uid'] !== $expected_owner)
  ) {
    return false;
  }

  $handle = @fopen($path, 'rb');
  if ($handle === false) return false;
  try {
    if (!@flock($handle, LOCK_SH)) return false;
    clearstatcache(true, $path);
    $opened_path_status = @lstat($path);
    $handle_status = @fstat($handle);
    if (
      $opened_path_status === false ||
      $handle_status === false ||
      $opened_path_status['dev'] !== $dev ||
      $opened_path_status['ino'] !== $ino ||
      $handle_status['dev'] !== $dev ||
      $handle_status['ino'] !== $ino ||
      $handle_status['size'] !== $size
    ) {
      return false;
    }

    $contents = stream_get_contents($handle);
    if (
      !is_string($contents) ||
      strlen($contents) !== $size ||
      !hash_equals($hash, hash('sha256', $contents))
    ) {
      return false;
    }

    clearstatcache(true, $path);
    $final_path_status = @lstat($path);
    $final_handle_status = @fstat($handle);
    if (
      $final_path_status === false ||
      $final_handle_status === false ||
      $final_path_status['dev'] !== $dev ||
      $final_path_status['ino'] !== $ino ||
      $final_handle_status['dev'] !== $dev ||
      $final_handle_status['ino'] !== $ino ||
      $final_handle_status['size'] !== $size
    ) {
      return false;
    }
    return $contents;
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

/**
 * @param array{valid: bool, hash: ?string} $state
 */
function plugin_manager_plugin_check_artifact_matches_state(
  array $state,
  string $latest
): bool {
  if (
    !$state['valid'] ||
    $state['hash'] === null ||
    !is_file($latest) ||
    is_link($latest)
  ) {
    return false;
  }
  $actual_hash = @hash_file('sha256', $latest);
  return is_string($actual_hash) && hash_equals($state['hash'], $actual_hash);
}

/**
 * Reserve a monotonically increasing generation before starting a download.
 * The per-plugin lock is released before any network or global-lock wait.
 */
function plugin_manager_reserve_plugin_check_generation(string $plugin): int {
  return plugin_manager_with_plugin_check_lock($plugin, static function ($handle): int {
    $state = plugin_manager_read_plugin_check_state($handle);
    if ($state['next'] === PHP_INT_MAX) {
      throw new RuntimeException('Plugin Manager check generation is exhausted');
    }
    $state['next']++;
    plugin_manager_write_plugin_check_state($handle, $state);
    return $state['next'];
  });
}

/**
 * Stage a privately downloaded artifact in request-start order while holding
 * the global operation lock. A candidate older than an already-finalized
 * generation reuses that shared artifact. A newly staged candidate remains
 * unusable by update until semantic validation finalizes it.
 */
function plugin_manager_publish_plugin_check_artifact(
  string $plugin,
  int $generation,
  array $candidate_receipt,
  string $latest
): bool {
  if ($generation < 1) {
    throw new RuntimeException('Plugin Manager check generation is invalid');
  }

  return plugin_manager_with_plugin_check_lock(
    $plugin,
    static function ($handle) use ($generation, $candidate_receipt, $latest): bool {
      $state = plugin_manager_read_plugin_check_state($handle);
      if ($generation < $state['published']) {
        return plugin_manager_plugin_check_artifact_matches_state($state, $latest);
      }

      $contents = plugin_manager_read_download_receipt($candidate_receipt);
      if ($contents === false) return false;
      $candidate_hash = $candidate_receipt['hash'];
      plugin_manager_prepare_shared_artifact_directory(dirname($latest));
      $stage = plugin_manager_stage_sibling_contents(
        $latest,
        'check-artifact',
        $contents,
        0600
      );
      if (!hash_equals($candidate_hash, $stage['hash'])) {
        @unlink($stage['path']);
        return false;
      }

      $previous_state = $state;
      $state['next'] = max($state['next'], $generation);
      $state['published'] = $generation;
      $state['valid'] = false;
      $state['hash'] = $candidate_hash;
      try {
        plugin_manager_write_plugin_check_state($handle, $state);
        if (!@rename($stage['path'], $latest)) {
          $previous_state['next'] = max($previous_state['next'], $generation);
          plugin_manager_write_plugin_check_state($handle, $previous_state);
          @unlink($stage['path']);
          return false;
        }
        $committed_hash = @hash_file('sha256', $latest);
        return is_string($committed_hash) &&
          hash_equals($candidate_hash, $committed_hash);
      } catch (Throwable $error) {
        @unlink($stage['path']);
        throw $error;
      }
    }
  );
}

/**
 * Mark a semantically validated publication usable by update. Until this
 * succeeds, a crash or parse failure leaves the staged artifact fail-closed.
 */
function plugin_manager_finalize_plugin_check_artifact(
  string $plugin,
  int $generation,
  string $latest
): bool {
  if ($generation < 1) {
    throw new RuntimeException('Plugin Manager check generation is invalid');
  }

  return plugin_manager_with_plugin_check_lock(
    $plugin,
    static function ($handle) use ($generation, $latest): bool {
      $state = plugin_manager_read_plugin_check_state($handle);
      if ($generation < $state['published']) {
        return plugin_manager_plugin_check_artifact_matches_state($state, $latest);
      }
      if (
        $generation !== $state['published'] ||
        $state['hash'] === null ||
        !is_file($latest) ||
        is_link($latest)
      ) {
        return false;
      }

      $actual_hash = @hash_file('sha256', $latest);
      if (!is_string($actual_hash) || !hash_equals($state['hash'], $actual_hash)) {
        return false;
      }
      $state['valid'] = true;
      plugin_manager_write_plugin_check_state($handle, $state);
      return true;
    }
  );
}

/**
 * Remove a shared check artifact and verify that its pathname no longer names
 * any object. This is the fail-closed fallback when generation-state revocation
 * could not be committed durably.
 */
function plugin_manager_quarantine_plugin_check_artifact(string $latest): void {
  if ($latest === '' || $latest[0] !== '/') {
    throw new RuntimeException('Plugin Manager check artifact path is invalid');
  }

  clearstatcache(true, $latest);
  $status = @lstat($latest);
  if ($status === false) return;
  if (
    plugin_manager_lock_path_type($status) === 0040000 ||
    !@unlink($latest)
  ) {
    throw new RuntimeException('Unable to quarantine Plugin Manager check artifact');
  }

  clearstatcache(true, $latest);
  if (@lstat($latest) !== false) {
    throw new RuntimeException('Unable to verify Plugin Manager check artifact quarantine');
  }
}

/**
 * Revoke a failed generation while holding the global operation lock. A newer
 * successful publication wins and is never removed by an older failure.
 *
 * @param array{stage_open?: bool, stage_write_after?: int, rename?: bool} $faults
 */
function plugin_manager_invalidate_plugin_check_artifact(
  string $plugin,
  int $generation,
  string $latest,
  array $faults = []
): bool {
  if ($generation < 1) {
    throw new RuntimeException('Plugin Manager check generation is invalid');
  }

  return plugin_manager_with_plugin_check_lock(
    $plugin,
    static function ($handle) use ($generation, $latest, $faults): bool {
      $state = plugin_manager_read_plugin_check_state($handle);
      if ($generation < $state['published']) return false;

      $state['next'] = max($state['next'], $generation);
      $state['published'] = $generation;
      $state['valid'] = false;
      $state['hash'] = null;
      try {
        plugin_manager_write_plugin_check_state($handle, $state, $faults);
      } catch (Throwable $state_error) {
        try {
          plugin_manager_quarantine_plugin_check_artifact($latest);
        } catch (Throwable $quarantine_error) {
          throw new RuntimeException(
            "{$state_error->getMessage()}; {$quarantine_error->getMessage()}",
            0,
            $state_error
          );
        }
        throw $state_error;
      }
      plugin_manager_quarantine_plugin_check_artifact($latest);
      return true;
    }
  );
}

/**
 * Verify that update is consuming the artifact from the latest successful
 * generation. This must be called while holding the global operation lock.
 */
function plugin_manager_plugin_check_artifact_is_current(
  string $plugin,
  string $latest
): bool {
  return plugin_manager_with_plugin_check_lock(
    $plugin,
    static function ($handle) use ($latest): bool {
      $state = plugin_manager_read_plugin_check_state($handle);
      return $state['published'] > 0 &&
        plugin_manager_plugin_check_artifact_matches_state($state, $latest);
    }
  );
}

/**
 * Return the private snapshot directory owned by the active lock scope.
 */
function plugin_manager_operation_snapshot_directory(): string {
  $token = getenv(PLUGIN_MANAGER_LOCK_TOKEN_ENV);
  $scope = getenv(PLUGIN_MANAGER_LOCK_SCOPE_ENV);
  [$global_lock] = plugin_manager_operation_lock_path();
  if (
    !is_string($token) ||
    !preg_match('/^[a-f0-9]{32}$/D', $token) ||
    !is_string($scope) ||
    $scope !== "$global_lock.scope.$token" ||
    !plugin_manager_lock_scope_is_safe($scope)
  ) {
    throw new RuntimeException('Plugin Manager update snapshot scope is invalid');
  }

  $snapshot_directory = "$scope.snapshots";
  clearstatcache(true, $snapshot_directory);
  $status = @lstat($snapshot_directory);
  if ($status === false) {
    @mkdir($snapshot_directory, 0700);
    clearstatcache(true, $snapshot_directory);
    $status = @lstat($snapshot_directory);
  }
  $owner = plugin_manager_effective_user_id();
  if (
    $status === false ||
    plugin_manager_lock_path_type($status) !== 0040000 ||
    ($status['mode'] & 07777) !== 0700 ||
    ($owner !== null && $status['uid'] !== $owner)
  ) {
    throw new RuntimeException('Plugin Manager update snapshot directory is unsafe');
  }
  return $snapshot_directory;
}

/**
 * Capture the latest finalized generation as a private, read-only update
 * snapshot. The caller must already hold the global operation lock.
 *
 * @return array{generation: int, hash: string, path: string}|null
 */
function plugin_manager_snapshot_plugin_check_artifact(
  string $plugin,
  string $latest
): ?array {
  $snapshot_parent = plugin_manager_operation_snapshot_directory();

  return plugin_manager_with_plugin_check_lock(
    $plugin,
    static function ($handle) use ($latest, $snapshot_parent): ?array {
      $state = plugin_manager_read_plugin_check_state($handle);
      if (
        $state['published'] < 1 ||
        !$state['valid'] ||
        $state['hash'] === null ||
        !is_file($latest) ||
        is_link($latest)
      ) {
        return null;
      }

      $contents = @file_get_contents($latest);
      if (
        !is_string($contents) ||
        !hash_equals($state['hash'], hash('sha256', $contents))
      ) {
        return null;
      }

      $snapshot = tempnam($snapshot_parent, '.plugin-update-');
      if ($snapshot === false) {
        throw new RuntimeException('Unable to create private Plugin Manager update snapshot');
      }
      if (
        dirname($snapshot) !== $snapshot_parent ||
        file_put_contents($snapshot, $contents, LOCK_EX) !== strlen($contents) ||
        !@chmod($snapshot, 0400)
      ) {
        @unlink($snapshot);
        throw new RuntimeException('Unable to secure Plugin Manager update snapshot');
      }
      clearstatcache(true, $snapshot);
      $snapshot_status = @lstat($snapshot);
      $owner = plugin_manager_effective_user_id();
      if (
        $snapshot_status === false ||
        plugin_manager_lock_path_type($snapshot_status) !== 0100000 ||
        ($snapshot_status['mode'] & 07777) !== 0400 ||
        ($owner !== null && $snapshot_status['uid'] !== $owner)
      ) {
        @unlink($snapshot);
        throw new RuntimeException('Unable to secure Plugin Manager update snapshot');
      }

      $snapshot_hash = @hash_file('sha256', $snapshot);
      if (!is_string($snapshot_hash) || !hash_equals($state['hash'], $snapshot_hash)) {
        @unlink($snapshot);
        throw new RuntimeException('Plugin Manager update snapshot changed while capturing it');
      }

      register_shutdown_function(static function () use ($snapshot): void {
        @chmod($snapshot, 0600);
        @unlink($snapshot);
      });
      return [
        'generation' => $state['published'],
        'hash' => $state['hash'],
        'path' => $snapshot
      ];
    }
  );
}

/**
 * Read an update snapshot only when it still matches its generation receipt.
 *
 * @param array{generation: int, hash: string, path: string} $receipt
 */
function plugin_manager_read_plugin_check_snapshot(array $receipt): string|false {
  $generation = $receipt['generation'] ?? null;
  $hash = $receipt['hash'] ?? null;
  $path = $receipt['path'] ?? null;
  if (
    !is_int($generation) ||
    $generation < 1 ||
    !is_string($hash) ||
    !preg_match('/^[a-f0-9]{64}$/D', $hash) ||
    !is_string($path) ||
    !is_file($path) ||
    is_link($path)
  ) {
    return false;
  }

  $contents = @file_get_contents($path);
  if (
    !is_string($contents) ||
    !hash_equals($hash, hash('sha256', $contents))
  ) {
    return false;
  }
  return $contents;
}

/**
 * Return an optional root-controlled executable which may only veto use of a
 * finalized private artifact. Production policies and their path ancestors
 * must be root-owned and not writable by group/other; test lock overrides use
 * the current effective user so the same checks are executable in CI.
 */
function plugin_manager_artifact_policy_executable(): ?string {
  $configured = getenv(PLUGIN_MANAGER_ARTIFACT_POLICY_ENV);
  if ($configured === false || $configured === '') return null;
  if ($configured[0] !== '/') {
    throw new RuntimeException('Plugin Manager artifact policy path must be absolute');
  }

  clearstatcache(true, $configured);
  $status = @lstat($configured);
  $resolved = @realpath($configured);
  [$lock, $production] = plugin_manager_operation_lock_path();
  unset($lock);
  $owner = plugin_manager_effective_user_id();
  $expected_owner = $production ? 0 : $owner;

  if (
    $status === false ||
    plugin_manager_lock_path_type($status) !== 0100000 ||
    !is_string($resolved) ||
    $resolved !== $configured
  ) {
    throw new RuntimeException(
      'Plugin Manager artifact policy must be a canonical regular executable'
    );
  }
  if ($expected_owner !== null && $status['uid'] !== $expected_owner) {
    throw new RuntimeException(
      $production
        ? 'Plugin Manager artifact policy must be owned by root'
        : 'Plugin Manager artifact policy must be owned by the current user'
    );
  }
  if (($status['mode'] & 0022) !== 0) {
    throw new RuntimeException(
      'Plugin Manager artifact policy must not be writable by group or other'
    );
  }
  if (($status['mode'] & 0111) === 0 || !is_executable($configured)) {
    throw new RuntimeException('Plugin Manager artifact policy is not executable');
  }

  if ($production) {
    $ancestor = dirname($configured);
    while (true) {
      clearstatcache(true, $ancestor);
      $ancestor_status = @lstat($ancestor);
      if (
        $ancestor_status === false ||
        plugin_manager_lock_path_type($ancestor_status) !== 0040000 ||
        $ancestor_status['uid'] !== 0 ||
        ($ancestor_status['mode'] & 0022) !== 0
      ) {
        throw new RuntimeException(
          'Plugin Manager artifact policy path must remain under root-owned directories'
        );
      }
      if ($ancestor === '/') break;
      $ancestor = dirname($ancestor);
    }
  }

  return $configured;
}

/**
 * Apply an opt-in restrictive policy to the exact finalized update snapshot.
 * Exit zero allows the existing update flow to continue; every configuration,
 * execution, or nonzero result fails closed without changing shared check
 * state. The callback receives: METHOD, SNAPSHOT-PATH, PLUGIN-BASENAME.
 */
function plugin_manager_enforce_artifact_policy(
  string $method,
  string $plugin,
  array $snapshot_receipt
): void {
  $policy = plugin_manager_artifact_policy_executable();
  if ($policy === null) return;

  if (plugin_manager_read_plugin_check_snapshot($snapshot_receipt) === false) {
    throw new RuntimeException(
      'Plugin Manager artifact policy received an invalid private snapshot'
    );
  }

  $snapshot = $snapshot_receipt['path'] ?? null;
  if (!is_string($snapshot)) {
    throw new RuntimeException(
      'Plugin Manager artifact policy received an invalid private snapshot'
    );
  }

  $process = @proc_open(
    [$policy, $method, $snapshot, $plugin],
    [
      0 => ['file', '/dev/null', 'r'],
      1 => ['pipe', 'w'],
      2 => ['redirect', 1]
    ],
    $pipes,
    null,
    null,
    ['bypass_shell' => true]
  );
  if (!is_resource($process)) {
    throw new RuntimeException('Unable to start Plugin Manager artifact policy');
  }

  $output = '';
  while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], 8192);
    if ($chunk === false) break;
    if (strlen($output) < 4096) {
      $output .= substr($chunk, 0, 4096 - strlen($output));
    }
  }
  fclose($pipes[1]);
  $status = proc_close($process);

  if ($status !== 0) {
    $message = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $output));
    throw new RuntimeException(
      $message !== ''
        ? "Plugin Manager artifact policy rejected update: $message"
        : "Plugin Manager artifact policy rejected update with status $status"
    );
  }

  // A policy is a veto only. It may not transform the candidate it approves.
  if (plugin_manager_read_plugin_check_snapshot($snapshot_receipt) === false) {
    throw new RuntimeException(
      'Plugin Manager artifact policy changed the private update snapshot'
    );
  }
}

/**
 * @return array{path: string, handle: resource}
 */
function plugin_manager_create_exclusive_sibling_file(
  string $target,
  string $purpose,
  bool $inject_failure = false
): array {
  if ($inject_failure) {
    throw new RuntimeException("Injected Plugin Manager $purpose staging failure");
  }

  $parent = dirname($target);
  if (!is_dir($parent)) {
    throw new RuntimeException("Plugin Manager target directory does not exist: $parent");
  }
  $prefix = '.'.basename($target).".$purpose-";
  foreach (range(1, 16) as $_attempt) {
    $path = "$parent/$prefix".bin2hex(random_bytes(8));
    $handle = @fopen($path, 'x+b');
    if ($handle === false) continue;
    $path_status = @lstat($path);
    $handle_status = @fstat($handle);
    if (
      $path_status === false ||
      $handle_status === false ||
      plugin_manager_lock_path_type($path_status) !== 0100000 ||
      plugin_manager_lock_path_type($handle_status) !== 0100000 ||
      $path_status['dev'] !== $handle_status['dev'] ||
      $path_status['ino'] !== $handle_status['ino'] ||
      !@chmod($path, 0600)
    ) {
      fclose($handle);
      @unlink($path);
      throw new RuntimeException("Unable to secure Plugin Manager $purpose staging file");
    }
    return ['path' => $path, 'handle' => $handle];
  }
  throw new RuntimeException("Unable to create Plugin Manager $purpose staging file");
}

/**
 * Write every byte and durably flush a regular staging file. PHP has no
 * portable directory-fsync API, so the strongest supported sequence is a full
 * file write, fflush, file fsync, close, hash verification, then atomic rename.
 *
 * @param resource $handle
 */
function plugin_manager_write_and_sync_file(
  $handle,
  string $contents,
  ?int $inject_failure_after = null
): void {
  $offset = 0;
  $length = strlen($contents);
  while ($offset < $length) {
    if ($inject_failure_after !== null && $offset >= $inject_failure_after) {
      throw new RuntimeException('Injected Plugin Manager short write');
    }
    $write_length = $length - $offset;
    if ($inject_failure_after !== null) {
      $write_length = min($write_length, $inject_failure_after - $offset);
    }
    $written = @fwrite($handle, substr($contents, $offset, $write_length));
    if ($written === false || $written < 1) {
      throw new RuntimeException('Unable to complete Plugin Manager staged write');
    }
    $offset += $written;
  }
  if (!@fflush($handle)) {
    throw new RuntimeException('Unable to flush Plugin Manager staging file');
  }
  if (!function_exists('fsync') || !@fsync($handle)) {
    throw new RuntimeException('Unable to fsync Plugin Manager staging file');
  }
}

/**
 * Publish a downloaded temporary artifact only after every byte can be read
 * back from the destination. The caller removes the temporary file on false.
 */
function plugin_manager_write_complete_download(
  string $path,
  string $contents,
  ?int $inject_failure_after = null
): array|false {
  if ($contents === '' || is_link($path)) return false;
  clearstatcache(true, $path);
  $path_status = @lstat($path);
  if (
    $path_status === false ||
    plugin_manager_lock_path_type($path_status) !== 0100000
  ) {
    return false;
  }

  $handle = @fopen($path, 'r+b');
  if ($handle === false) return false;
  try {
    clearstatcache(true, $path);
    $opened_path_status = @lstat($path);
    $handle_status = @fstat($handle);
    if (
      $opened_path_status === false ||
      $handle_status === false ||
      plugin_manager_lock_path_type($opened_path_status) !== 0100000 ||
      plugin_manager_lock_path_type($handle_status) !== 0100000 ||
      $opened_path_status['dev'] !== $handle_status['dev'] ||
      $opened_path_status['ino'] !== $handle_status['ino'] ||
      !@flock($handle, LOCK_EX) ||
      !@ftruncate($handle, 0)
    ) {
      return false;
    }

    $offset = 0;
    $length = strlen($contents);
    while ($offset < $length) {
      if ($inject_failure_after !== null && $offset >= $inject_failure_after) {
        return false;
      }
      $write_length = $length - $offset;
      if ($inject_failure_after !== null) {
        $write_length = min($write_length, $inject_failure_after - $offset);
      }
      $written = @fwrite($handle, substr($contents, $offset, $write_length));
      if ($written === false || $written < 1) return false;
      $offset += $written;
    }
    if (!@fflush($handle) || !@rewind($handle)) return false;
    $persisted = stream_get_contents($handle);
    if (
      !is_string($persisted) ||
      strlen($persisted) !== $length ||
      !hash_equals(hash('sha256', $contents), hash('sha256', $persisted))
    ) {
      return false;
    }

    clearstatcache(true, $path);
    $status = @lstat($path);
    $final_handle_status = @fstat($handle);
    [$lock, $production] = plugin_manager_operation_lock_path();
    unset($lock);
    $expected_owner = plugin_manager_expected_path_owner($production);
    if (
      $status === false ||
      $final_handle_status === false ||
      plugin_manager_lock_path_type($status) !== 0100000 ||
      plugin_manager_lock_path_type($final_handle_status) !== 0100000 ||
      $status['dev'] !== $final_handle_status['dev'] ||
      $status['ino'] !== $final_handle_status['ino'] ||
      $status['size'] !== $length ||
      ($status['mode'] & 0022) !== 0 ||
      ($expected_owner !== null && $status['uid'] !== $expected_owner)
    ) {
      return false;
    }
    return [
      'path' => $path,
      'hash' => hash('sha256', $contents),
      'dev' => $final_handle_status['dev'],
      'ino' => $final_handle_status['ino'],
      'size' => $length
    ];
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
}

/**
 * @return array{path: string, hash: string}
 */
function plugin_manager_stage_sibling_contents(
  string $target,
  string $purpose,
  string $contents,
  int $mode,
  bool $inject_open_failure = false,
  ?int $inject_write_failure_after = null
): array {
  $stage = plugin_manager_create_exclusive_sibling_file(
    $target,
    $purpose,
    $inject_open_failure
  );
  try {
    plugin_manager_write_and_sync_file(
      $stage['handle'],
      $contents,
      $inject_write_failure_after
    );
    if (!@chmod($stage['path'], $mode)) {
      throw new RuntimeException("Unable to set Plugin Manager $purpose staging mode");
    }
    if (!@fflush($stage['handle'])) {
      throw new RuntimeException("Unable to flush Plugin Manager $purpose staging metadata");
    }
    if (!function_exists('fsync') || !@fsync($stage['handle'])) {
      throw new RuntimeException("Unable to fsync Plugin Manager $purpose staging metadata");
    }
    fclose($stage['handle']);
    $stage['handle'] = null;
    $hash = @hash_file('sha256', $stage['path']);
    if (!is_string($hash) || !hash_equals(hash('sha256', $contents), $hash)) {
      throw new RuntimeException("Plugin Manager $purpose staging hash mismatch");
    }
    return ['path' => $stage['path'], 'hash' => $hash];
  } catch (Throwable $error) {
    if (is_resource($stage['handle'])) fclose($stage['handle']);
    @unlink($stage['path']);
    throw $error;
  }
}

function plugin_manager_write_shared_artifact(
  string $path,
  string $contents,
  int $mode = 0644
): bool {
  if ($contents === '' || $path === '' || $path[0] !== '/') return false;
  plugin_manager_prepare_shared_artifact_directory(dirname($path));
  $stage = plugin_manager_stage_sibling_contents(
    $path,
    'shared-artifact',
    $contents,
    $mode
  );
  try {
    if (!@rename($stage['path'], $path)) return false;
    clearstatcache(true, $path);
    $status = @lstat($path);
    $hash = @hash_file('sha256', $path);
    return
      $status !== false &&
      plugin_manager_lock_path_type($status) === 0100000 &&
      is_string($hash) &&
      hash_equals($stage['hash'], $hash);
  } finally {
    @unlink($stage['path']);
  }
}

function plugin_manager_remove_shared_artifact(string $path): bool {
  if ($path === '' || $path[0] !== '/') return false;
  plugin_manager_prepare_shared_artifact_directory(dirname($path));
  clearstatcache(true, $path);
  $status = @lstat($path);
  if ($status === false) return true;
  if (plugin_manager_lock_path_type($status) === 0040000) return false;
  return @unlink($path);
}

function plugin_manager_create_sibling_symlink(string $link, string $target): string {
  $parent = dirname($link);
  $prefix = '.'.basename($link).'.plugin-link-';
  foreach (range(1, 16) as $_attempt) {
    $temporary = "$parent/$prefix".bin2hex(random_bytes(8));
    if (!@symlink($target, $temporary)) continue;
    if (!is_link($temporary) || readlink($temporary) !== $target) {
      @unlink($temporary);
      throw new RuntimeException('Unable to verify Plugin Manager staged symlink');
    }
    return $temporary;
  }
  throw new RuntimeException('Unable to create Plugin Manager staged symlink');
}

/**
 * Atomically persist a verified snapshot while preserving the prior target and
 * installed symlink on every pre-commit or symlink-swap failure.
 *
 * Fault keys are used only by deterministic tests:
 * stage_open, stage_write_after, target_rename, symlink_create, symlink_swap,
 * rollback_target_rename.
 *
 * @param array{generation: int, hash: string, path: string} $receipt
 * @param array<string, bool|int> $faults
 */
function plugin_manager_commit_plugin_check_snapshot(
  array $receipt,
  string $target,
  ?string $installed_link = null,
  array $faults = []
): void {
  $contents = plugin_manager_read_plugin_check_snapshot($receipt);
  if ($contents === false) {
    throw new RuntimeException('Plugin Manager update snapshot receipt is invalid');
  }
  $expected_hash = $receipt['hash'];
  $target_status = @lstat($target);
  $target_existed = $target_status !== false;
  if (
    $target_existed &&
    plugin_manager_lock_path_type($target_status) !== 0100000
  ) {
    throw new RuntimeException('Plugin Manager update target is not a regular file');
  }
  $previous_contents = $target_existed ? @file_get_contents($target) : null;
  if ($target_existed && !is_string($previous_contents)) {
    throw new RuntimeException('Unable to read prior Plugin Manager update target');
  }
  $target_mode = $target_existed ? ($target_status['mode'] & 0777) : 0644;
  $previous_hash = $target_existed ? hash('sha256', $previous_contents) : null;

  $previous_link_target = null;
  $link_must_change = false;
  if ($installed_link !== null) {
    $link_status = @lstat($installed_link);
    if (
      $link_status === false ||
      plugin_manager_lock_path_type($link_status) !== 0120000
    ) {
      throw new RuntimeException('Plugin Manager installed link is missing or unsafe');
    }
    $previous_link_target = readlink($installed_link);
    if (!is_string($previous_link_target)) {
      throw new RuntimeException('Unable to read Plugin Manager installed link');
    }
    $link_must_change = $previous_link_target !== $target;
  }

  $stage_path = null;
  $backup_path = null;
  $temporary_link = null;
  $target_committed = false;
  $link_committed = false;
  try {
    $stage = plugin_manager_stage_sibling_contents(
      $target,
      'plugin-stage',
      $contents,
      $target_mode,
      ($faults['stage_open'] ?? false) === true,
      isset($faults['stage_write_after']) ? (int)$faults['stage_write_after'] : null
    );
    $stage_path = $stage['path'];
    if (!hash_equals($expected_hash, $stage['hash'])) {
      throw new RuntimeException('Plugin Manager staged target does not match update receipt');
    }

    if ($target_existed) {
      $backup = plugin_manager_stage_sibling_contents(
        $target,
        'plugin-backup',
        $previous_contents,
        $target_mode
      );
      $backup_path = $backup['path'];
      if (!hash_equals($previous_hash, $backup['hash'])) {
        throw new RuntimeException('Plugin Manager rollback copy does not match prior target');
      }
    }

    if (($faults['target_rename'] ?? false) === true || !@rename($stage_path, $target)) {
      throw new RuntimeException('Unable to atomically commit Plugin Manager update target');
    }
    $stage_path = null;
    $target_committed = true;
    $committed_hash = @hash_file('sha256', $target);
    if (!is_string($committed_hash) || !hash_equals($expected_hash, $committed_hash)) {
      throw new RuntimeException('Committed Plugin Manager update target hash mismatch');
    }

    if ($link_must_change) {
      if (($faults['symlink_create'] ?? false) === true) {
        throw new RuntimeException('Injected Plugin Manager symlink staging failure');
      }
      $temporary_link = plugin_manager_create_sibling_symlink($installed_link, $target);
      if (($faults['symlink_swap'] ?? false) === true ||
        !@rename($temporary_link, $installed_link)) {
        throw new RuntimeException('Unable to atomically swap Plugin Manager installed link');
      }
      $temporary_link = null;
      $link_committed = true;
      if (!is_link($installed_link) || readlink($installed_link) !== $target) {
        throw new RuntimeException('Committed Plugin Manager installed link is invalid');
      }
    }

    if ($backup_path !== null) {
      @unlink($backup_path);
      $backup_path = null;
    }
  } catch (Throwable $error) {
    $rollback_error = null;
    $preserve_backup = false;
    if ($target_committed) {
      if ($backup_path !== null) {
        $restore_path = null;
        try {
          $backup_contents = @file_get_contents($backup_path);
          if (
            !is_string($backup_contents) ||
            !hash_equals((string)$previous_hash, hash('sha256', $backup_contents))
          ) {
            throw new RuntimeException('rollback copy changed before restoration');
          }
          $restore = plugin_manager_stage_sibling_contents(
            $target,
            'plugin-restore',
            $backup_contents,
            $target_mode
          );
          $restore_path = $restore['path'];
          if (
            ($faults['rollback_target_rename'] ?? false) === true ||
            !@rename($restore_path, $target)
          ) {
            throw new RuntimeException('unable to restore prior update target');
          }
          $restore_path = null;
          $restored_hash = @hash_file('sha256', $target);
          if (
            !is_string($restored_hash) ||
            !hash_equals((string)$previous_hash, $restored_hash)
          ) {
            throw new RuntimeException('restored update target hash mismatch');
          }
          if (!@unlink($backup_path) && file_exists($backup_path)) {
            throw new RuntimeException('unable to remove verified rollback copy');
          }
          $backup_path = null;
        } catch (Throwable $restore_error) {
          if ($restore_path !== null) @unlink($restore_path);
          $preserve_backup = is_file($backup_path) && !is_link($backup_path);
          $rollback_error = $restore_error->getMessage();
          if ($preserve_backup) {
            $rollback_error .= "; rollback copy preserved at $backup_path";
          }
        }
      } elseif (!@unlink($target) && file_exists($target)) {
        $rollback_error = 'unable to remove newly created update target';
      }
    }

    if ($link_committed && $previous_link_target !== null) {
      try {
        $restore_link = plugin_manager_create_sibling_symlink(
          $installed_link,
          $previous_link_target
        );
        if (!@rename($restore_link, $installed_link)) {
          @unlink($restore_link);
          throw new RuntimeException('unable to restore prior installed link');
        }
      } catch (Throwable $link_error) {
        $rollback_error = $rollback_error === null
          ? $link_error->getMessage()
          : "$rollback_error; {$link_error->getMessage()}";
      }
    }

    if ($stage_path !== null) @unlink($stage_path);
    if ($backup_path !== null && !$preserve_backup) @unlink($backup_path);
    if ($temporary_link !== null) @unlink($temporary_link);
    if ($rollback_error !== null) {
      throw new RuntimeException(
        "{$error->getMessage()}; rollback failed: $rollback_error",
        0,
        $error
      );
    }
    throw $error;
  }
}

/**
 * Run maintenance only when the host-wide mutex can be acquired immediately.
 * Page rendering must never wait behind a plugin install, and an unsafe lock
 * path must result in skipped maintenance rather than an unlocked mutation.
 */
function plugin_manager_with_nonblocking_operation_lock(callable $operation): bool {
  try {
    $handle = plugin_manager_open_operation_lock();
  } catch (Throwable) {
    return false;
  }

  if (!@flock($handle, LOCK_EX | LOCK_NB)) {
    fclose($handle);
    return false;
  }

  try {
    $operation();
  } finally {
    @flock($handle, LOCK_UN);
    fclose($handle);
  }
  return true;
}

/**
 * Re-execute a stateful operation under the host-wide mutex, preserving output
 * and exit status. The re-executed process recognizes the live owner and
 * continues without trying to acquire the lock again.
 */
function plugin_manager_serialize_operation(string $method, string $script, array $argv): void {
  try {
    $command = plugin_manager_operation_lock_command($method, $script, $argv);
  } catch (Throwable $error) {
    fwrite(STDERR, "plugin: unable to acquire operation lock: {$error->getMessage()}\n");
    exit(1);
  }

  if ($command === null) return;

  passthru($command, $status);
  exit($status);
}
