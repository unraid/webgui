#!/usr/bin/env php
<?php
declare(strict_types=1);

/** Fails with $message unless $needle appears in $haystack. */
function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
}

/** Fails with $message if $needle appears in $haystack. */
function assertMissingText(string $needle, string $haystack, string $message): void
{
  if (str_contains($haystack, $needle)) throw new RuntimeException($message);
}

/** Runs $command through popen(), reads it to EOF and returns the pclose() status. */
function drainAndClose(string $command): int
{
  $proc = popen($command, 'r');
  if ($proc === false) throw new RuntimeException("Could not run: $command");
  while (fread($proc, 100) !== '' && !feof($proc)) {}
  return pclose($proc);
}

// A command backgrounded inside the popen shell hides its exit status, because
// the shell that popen waits on exits immediately and successfully.
if (drainAndClose("sh -c 'exit 3' 2>&1 &") === 3) {
  throw new RuntimeException('Backgrounded popen unexpectedly reported the real exit status.');
}
if (drainAndClose("sh -c 'exit 3' 2>&1") !== 3) {
  throw new RuntimeException('popen must report the exit status of the command it runs.');
}

$repo = dirname(__DIR__);
$source = file_get_contents("$repo/emhttp/plugins/dynamix.vm.manager/scripts/VMClone.php");
if ($source === false) throw new RuntimeException('Could not read VMClone.php.');

assertMissingText(
  '2>&1 &"',
  $source,
  'The clone copy must not be backgrounded inside the popen shell; pclose() would always return 0.'
);
assertContainsText(
  '$proc = popen("$command 2>&1",\'r\');',
  $source,
  'The clone copy must run in the foreground so pclose() returns its exit status.'
);

$functionStart = strpos($source, 'function execCommand_nchan_clone');
$conditionAt = $functionStart === false ? false : strpos($source, 'if ($refcmd) {', $functionStart);
$initAt = $functionStart === false ? false : strpos($source, '$reflinkok = false;', $functionStart);
if ($functionStart === false || $conditionAt === false || $initAt === false) {
  throw new RuntimeException('execCommand_nchan_clone() must initialise $reflinkok before it is read.');
}
if ($initAt > $conditionAt) {
  throw new RuntimeException('$reflinkok must be initialised before the $refcmd branch that may skip it.');
}

echo "VM clone copy exit status regression test passed.\n";
