<?PHP

require_once __DIR__.'/../include/ParityProtectedShrinkGuard.php';

function assert_same($expected, $actual, $message) {
  if ($expected !== $actual) {
    fwrite(STDERR, "$message\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
    exit(1);
  }
}

$productionFiles = parity_protected_shrink_files();
assert_same(
  '/boot/config/unraid/storage/parity-protected-shrink.json',
  $productionFiles[0],
  'The downgrade guard must use the production Core intent path.'
);
assert_same(
  '/var/run/unraid-os-storage-interlock',
  parity_protected_shrink_interlock_path(),
  'Core and WebGUI must use the same cross-process interlock path.'
);

$testRoot = sys_get_temp_dir().'/parity-protected-shrink-guard-'.bin2hex(random_bytes(8));
$testFiles = parity_protected_shrink_files($testRoot);
mkdir(dirname($testFiles[0]), 0700, true);
mkdir(dirname($testFiles[1]), 0700, true);
$testMarker = "$testRoot/downgrade";
$testInterlock = "$testRoot/interlock";

assert_same(false, parity_protected_shrink_active($testFiles), 'No proof must report idle.');
assert_same(
  'ok',
  parity_protected_shrink_begin_downgrade($testFiles, $testMarker, $testInterlock),
  'Downgrade should publish its marker while no protected shrink exists.'
);
assert_same(true, is_file($testMarker), 'A successful downgrade decision must retain its marker.');
unlink($testMarker);

file_put_contents($testFiles[0], '{}');
assert_same(true, parity_protected_shrink_active($testFiles), 'A Core intent must block downgrade.');
assert_same(
  'protected_shrink_active',
  parity_protected_shrink_begin_downgrade($testFiles, $testMarker, $testInterlock),
  'A Core intent committed under the interlock must win over downgrade.'
);
assert_same(false, is_file($testMarker), 'A rejected downgrade must remove its transient marker.');
unlink($testFiles[0]);
file_put_contents($testFiles[2], 'stage=prepared\n');
assert_same(true, parity_protected_shrink_active($testFiles), 'A daemon proof must block downgrade.');

$firstLock = parity_protected_shrink_interlock_acquire($testInterlock);
assert_same(true, is_resource($firstLock), 'The first interlock owner must acquire the lock.');
assert_same(
  false,
  parity_protected_shrink_interlock_acquire($testInterlock, true),
  'A second publisher must not enter while the first owner is paused.'
);
parity_protected_shrink_interlock_release($firstLock);
$nextLock = parity_protected_shrink_interlock_acquire($testInterlock, true);
assert_same(true, is_resource($nextLock), 'The lock must transfer after its owner releases it.');
parity_protected_shrink_interlock_release($nextLock);

unlink($testFiles[2]);
unlink($testInterlock);
rmdir(dirname($testFiles[0]));
rmdir(dirname(dirname($testFiles[0])));
rmdir(dirname($testFiles[1]));
rmdir($testRoot);

echo "parity protected shrink downgrade guard: ok\n";

?>
