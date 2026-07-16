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

$testRoot = sys_get_temp_dir().'/parity-protected-shrink-guard-'.bin2hex(random_bytes(8));
$testFiles = parity_protected_shrink_files($testRoot);
mkdir(dirname($testFiles[0]), 0700, true);
mkdir(dirname($testFiles[1]), 0700, true);

assert_same(false, parity_protected_shrink_active($testFiles), 'No proof must report idle.');
file_put_contents($testFiles[0], '{}');
assert_same(true, parity_protected_shrink_active($testFiles), 'A Core intent must block downgrade.');
unlink($testFiles[0]);
file_put_contents($testFiles[2], 'stage=prepared\n');
assert_same(true, parity_protected_shrink_active($testFiles), 'A daemon proof must block downgrade.');

unlink($testFiles[2]);
rmdir(dirname($testFiles[0]));
rmdir(dirname(dirname($testFiles[0])));
rmdir(dirname($testFiles[1]));
rmdir($testRoot);

echo "parity protected shrink downgrade guard: ok\n";

?>
