<?PHP

function parity_protected_shrink_files($bootConfigRoot = '/boot/config') {
  $bootConfigRoot = rtrim($bootConfigRoot, '/');

  return [
    "$bootConfigRoot/unraid/storage/parity-protected-shrink.json",
    "$bootConfigRoot/unraid/storage/parity-protected-shrink.recovery.json",
  ];
}

function parity_protected_shrink_active($protectedShrinkFiles) {
  $corePresent = false;
  foreach ($protectedShrinkFiles as $protectedShrinkFile) {
    clearstatcache(true, $protectedShrinkFile);
    if (@lstat($protectedShrinkFile) !== false) $corePresent = true;
  }

  if (!$corePresent) return false;
  if (count($protectedShrinkFiles) !== 2) return true;

  $canonical = parity_protected_shrink_completed_identity($protectedShrinkFiles[0]);
  $recovery = parity_protected_shrink_completed_identity($protectedShrinkFiles[1]);

  // Core releases its mutation fence only after both durable copies contain
  // the same version-7 terminal tombstone. Missing, malformed, active, legacy,
  // or identity-divergent copies remain downgrade-blocking.
  return $canonical === false || $recovery === false ||
    !parity_protected_shrink_identity_equal($canonical, $recovery);
}

function parity_protected_shrink_completed_identity($path) {
  if (!is_file($path)) return false;

  $body = @file_get_contents($path);
  if ($body === false) return false;

  $intent = json_decode($body);
  if (!($intent instanceof stdClass)) return false;
  if (($intent->version ?? null) !== 7 || ($intent->stage ?? null) !== 'completed') return false;
  if (property_exists($intent, 'completed')) return false;

  $disk = $intent->disk ?? null;
  $deviceId = $intent->device_id ?? null;
  $bootId = $intent->boot_id ?? null;
  $topology = $intent->topology ?? null;

  if (!is_string($disk) || $disk === '') return false;
  if (!is_string($deviceId) || $deviceId === '') return false;
  if (!is_string($bootId) || $bootId === '') return false;
  if (!($topology instanceof stdClass)) return false;

  $dataSlotCount = $topology->data_slot_count ?? null;
  $arraySlots = $topology->array_slots ?? null;
  $poolMembers = $topology->pool_members ?? null;
  if (!is_int($dataSlotCount) || $dataSlotCount < 0) return false;
  if (!($arraySlots instanceof stdClass) || !($poolMembers instanceof stdClass)) return false;

  $arraySlotValues = get_object_vars($arraySlots);
  if (($arraySlotValues[$disk] ?? null) !== $deviceId) return false;
  foreach ($arraySlotValues as $slotDeviceId) {
    if (!is_null($slotDeviceId) && !is_string($slotDeviceId)) return false;
  }

  $poolMemberValues = get_object_vars($poolMembers);
  foreach ($poolMemberValues as $members) {
    if (!is_array($members)) return false;
    foreach ($members as $member) {
      if (!is_null($member) && !is_string($member)) return false;
    }
  }

  return [
    'disk' => $disk,
    'device_id' => $deviceId,
    'boot_id' => $bootId,
    'topology' => [
      'data_slot_count' => $dataSlotCount,
      'array_slots' => $arraySlotValues,
      'pool_members' => $poolMemberValues,
    ],
  ];
}

function parity_protected_shrink_identity_equal($left, $right) {
  if (gettype($left) !== gettype($right)) return false;
  if (!is_array($left)) return $left === $right;
  if (count($left) !== count($right)) return false;

  foreach ($left as $key => $value) {
    if (!array_key_exists($key, $right)) return false;
    if (!parity_protected_shrink_identity_equal($value, $right[$key])) return false;
  }

  return true;
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
