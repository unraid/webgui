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

?>
