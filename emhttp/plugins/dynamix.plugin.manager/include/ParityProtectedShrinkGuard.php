<?PHP

function parity_protected_shrink_files($bootConfigRoot = '/boot/config') {
  $bootConfigRoot = rtrim($bootConfigRoot, '/');

  return [
    "$bootConfigRoot/unraid/storage/parity-protected-shrink.json",
    "$bootConfigRoot/storage/parity-protected-shrink-prepared",
    "$bootConfigRoot/storage/parity-protected-shrink-prepared-complete",
    "$bootConfigRoot/storage/parity-protected-shrink-daemon",
  ];
}

function parity_protected_shrink_active($protectedShrinkFiles) {
  foreach ($protectedShrinkFiles as $protectedShrinkFile) {
    if (is_file($protectedShrinkFile)) return true;
  }

  return false;
}

function parity_protected_shrink_interlock_path() {
  return '/var/run/unraid-os-storage-interlock';
}

function parity_protected_shrink_interlock_acquire($path = null, $nonBlocking = false) {
  $path ??= parity_protected_shrink_interlock_path();
  $handle = @fopen($path, 'c');
  if ($handle === false) return false;

  @chmod($path, 0600);
  $operation = LOCK_EX | ($nonBlocking ? LOCK_NB : 0);

  if (!flock($handle, $operation)) {
    fclose($handle);
    return false;
  }

  return $handle;
}

function parity_protected_shrink_interlock_release($handle) {
  if (!is_resource($handle)) return;
  flock($handle, LOCK_UN);
  fclose($handle);
}

function parity_protected_shrink_begin_downgrade(
  $protectedShrinkFiles,
  $downgradeMarker = '/var/run/unraid-os-downgrade',
  $interlockPath = null
) {
  $interlockHandle = parity_protected_shrink_interlock_acquire($interlockPath);
  if ($interlockHandle === false) return 'interlock_unavailable';

  try {
    $downgradeMarkerHandle = @fopen($downgradeMarker, 'x');

    if ($downgradeMarkerHandle === false) return 'downgrade_pending';

    fclose($downgradeMarkerHandle);
    @chmod($downgradeMarker, 0600);

    if (parity_protected_shrink_active($protectedShrinkFiles)) {
      @unlink($downgradeMarker);
      return 'protected_shrink_active';
    }

    return 'ok';
  } finally {
    parity_protected_shrink_interlock_release($interlockHandle);
  }
}

?>
