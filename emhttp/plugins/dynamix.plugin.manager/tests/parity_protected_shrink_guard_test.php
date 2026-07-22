<?PHP

require_once __DIR__.'/../include/ParityProtectedShrinkGuard.php';

function assert_same($expected, $actual, $message) {
  if ($expected !== $actual) {
    fwrite(STDERR, "$message\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
    exit(1);
  }
}

function protected_shrink_intent($stage, $overrides = []) {
  return array_replace_recursive([
    'version' => 6,
    'disk' => 'disk2',
    'device_id' => 'QA-DISK-2',
    'boot_id' => 'qa-boot',
    'stage' => $stage,
    'topology' => [
      'data_slot_count' => 2,
      'array_slots' => [
        'parity' => 'QA-PARITY',
        'disk1' => 'QA-DISK-1',
        'disk2' => 'QA-DISK-2',
      ],
      'pool_members' => ['cache' => ['QA-CACHE']],
    ],
  ], $overrides);
}

function write_intent($path, $intent) {
  file_put_contents($path, json_encode($intent, JSON_THROW_ON_ERROR));
}

$productionFiles = parity_protected_shrink_files();
assert_same(
  '/boot/config/unraid/storage/parity-protected-shrink.json',
  $productionFiles[0],
  'The downgrade guard must use the production Core intent path.'
);
assert_same(
  '/boot/config/unraid/storage/parity-protected-shrink.recovery.json',
  $productionFiles[1],
  'The downgrade guard must include the production Core recovery path.'
);
assert_same(
  '/var/run/unraid-os-storage-interlock',
  parity_protected_shrink_interlock_path(),
  'Core and WebGUI must use the same cross-process interlock path.'
);

$testRoot = sys_get_temp_dir().'/parity-protected-shrink-guard-'.bin2hex(random_bytes(8));
$testFiles = parity_protected_shrink_files($testRoot);
mkdir(dirname($testFiles[0]), 0700, true);
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

write_intent($testFiles[0], protected_shrink_intent('zeroing'));
write_intent($testFiles[1], protected_shrink_intent('zeroing'));
assert_same(true, parity_protected_shrink_active($testFiles), 'An active Core intent must block downgrade.');
assert_same(
  'protected_shrink_active',
  parity_protected_shrink_begin_downgrade($testFiles, $testMarker, $testInterlock),
  'A Core intent committed under the interlock must win over downgrade.'
);
assert_same(false, is_file($testMarker), 'A rejected downgrade must remove its transient marker.');
unlink($testFiles[0]);
unlink($testFiles[1]);

write_intent($testFiles[1], protected_shrink_intent('zeroing'));
assert_same(
  true,
  parity_protected_shrink_active($testFiles),
  'A recovery-only Core intent must block downgrade.'
);
assert_same(
  'protected_shrink_active',
  parity_protected_shrink_begin_downgrade($testFiles, $testMarker, $testInterlock),
  'A recovery-only checkpoint must retain the rollback fence.'
);
assert_same(false, is_file($testMarker), 'A recovery-only rejection must remove its marker.');
unlink($testFiles[1]);

$completed = protected_shrink_intent('completed', ['version' => 7]);
write_intent($testFiles[0], $completed);
write_intent($testFiles[1], $completed);
assert_same(
  false,
  parity_protected_shrink_active($testFiles),
  'An identity-equivalent version-7 completion pair must permit downgrade.'
);
assert_same(
  'ok',
  parity_protected_shrink_begin_downgrade($testFiles, $testMarker, $testInterlock),
  'The terminal tombstone must agree with Core and release the downgrade path.'
);
assert_same(true, is_file($testMarker), 'An allowed completed downgrade must retain its marker.');
unlink($testMarker);

write_intent(
  $testFiles[1],
  protected_shrink_intent('completed', ['version' => 7, 'boot_id' => 'different-boot'])
);
assert_same(
  true,
  parity_protected_shrink_active($testFiles),
  'Identity-divergent completed copies must block downgrade.'
);

write_intent(
  $testFiles[0],
  protected_shrink_intent('completed', [
    'version' => 7,
    'device_id' => '0123',
    'topology' => ['array_slots' => ['disk2' => '0123']],
  ])
);
write_intent(
  $testFiles[1],
  protected_shrink_intent('completed', [
    'version' => 7,
    'device_id' => '123',
    'topology' => ['array_slots' => ['disk2' => '123']],
  ])
);
assert_same(
  true,
  parity_protected_shrink_active($testFiles),
  'Numeric-looking identities must compare as exact strings.'
);

file_put_contents($testFiles[1], '{');
assert_same(
  true,
  parity_protected_shrink_active($testFiles),
  'A torn completed recovery copy must block downgrade.'
);

write_intent($testFiles[0], protected_shrink_intent('signing', ['completed' => true]));
write_intent($testFiles[1], protected_shrink_intent('signing', ['completed' => true]));
assert_same(
  true,
  parity_protected_shrink_active($testFiles),
  'Legacy additive completion must stay fenced until Core migrates both copies.'
);
unlink($testFiles[0]);
unlink($testFiles[1]);

mkdir($testFiles[1]);
assert_same(
  true,
  parity_protected_shrink_active($testFiles),
  'A present non-regular recovery path must fail closed.'
);
rmdir($testFiles[1]);

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

unlink($testInterlock);
rmdir(dirname($testFiles[0]));
rmdir(dirname(dirname($testFiles[0])));
rmdir($testRoot);

echo "parity protected shrink downgrade guard: ok\n";

?>
