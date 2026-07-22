<?PHP

require_once __DIR__.'/../include/ParityProtectedShrinkGuard.php';

function endpoint_assert_same($expected, $actual, $message) {
  if ($expected !== $actual) {
    fwrite(STDERR, "$message\nExpected: ".var_export($expected, true)."\nActual: ".var_export($actual, true)."\n");
    exit(1);
  }
}

$testRoot = sys_get_temp_dir().'/downgrade-endpoint-'.bin2hex(random_bytes(8));
mkdir($testRoot, 0700, true);
$bootArtifact = "$testRoot/bzimage";
file_put_contents($bootArtifact, 'original boot image');

$cases = [
  'interlock_unavailable' => [503, 'The Unraid downgrade safety lock is unavailable. Nothing was changed.'],
  'completion_durability_unavailable' => [503, 'The protected disk-removal safety record could not be synchronized. Nothing was changed.'],
  'downgrade_pending' => [409, 'An Unraid downgrade is already pending.'],
  'protected_shrink_active' => [409, 'Finish or recover the active parity-protected disk removal before downgrading Unraid.'],
];

foreach ($cases as $decision => [$expectedStatus, $expectedMessage]) {
  $mutationCount = 0;
  $response = parity_protected_shrink_handle_downgrade_decision(
    $decision,
    function() use (&$mutationCount, $bootArtifact) {
      $mutationCount++;
      unlink($bootArtifact);
    }
  );

  endpoint_assert_same($expectedStatus, $response['status'], "$decision must map to its fail-closed HTTP status.");
  endpoint_assert_same($expectedMessage, $response['message'], "$decision must preserve its operator guidance.");
  endpoint_assert_same(0, $mutationCount, "$decision must exit before the boot mutation callback.");
  endpoint_assert_same('original boot image', file_get_contents($bootArtifact), "$decision must leave bz artifacts untouched.");
}

$mutationCount = 0;
$response = parity_protected_shrink_handle_downgrade_decision(
  'unexpected_decision',
  function() use (&$mutationCount) {
    $mutationCount++;
  }
);
endpoint_assert_same(503, $response['status'], 'Unknown decisions must fail closed.');
endpoint_assert_same(0, $mutationCount, 'Unknown decisions must not reach boot mutation.');

$response = parity_protected_shrink_handle_downgrade_decision(
  'ok',
  function() use (&$mutationCount) {
    $mutationCount++;
  }
);
endpoint_assert_same(null, $response, 'An accepted decision must continue without an HTTP rejection.');
endpoint_assert_same(1, $mutationCount, 'Only an accepted decision may invoke boot mutation.');

unlink($bootArtifact);
rmdir($testRoot);

echo "downgrade endpoint decision: ok\n";

?>
