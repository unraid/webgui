#!/usr/bin/env php
<?php
declare(strict_types=1);

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (strpos($haystack, $needle) === false) throw new RuntimeException($message);
}

function assertNotContainsText(string $needle, string $haystack, string $message): void
{
  if (strpos($haystack, $needle) !== false) throw new RuntimeException($message);
}

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php');
if ($source === false) throw new RuntimeException('Could not read CreateDocker.php.');

assertContainsText(
  'docker create --name " . escapeshellarg($Name) . " " . escapeshellarg($Repository)',
  $source,
  'Docker create arguments must not add a second layer of literal quotes.'
);
assertContainsText(
  'docker rm " . escapeshellarg($Name)',
  $source,
  'Docker remove arguments must not add a second layer of literal quotes.'
);
assertNotContainsText(
  <<<'SOURCE'
docker create --name '" . escapeshellarg($Name) . "' '" . escapeshellarg($Repository) . "'
SOURCE,
  $source,
  'The old double-quoted Docker argument construction is still present.'
);
assertNotContainsText(
  <<<'SOURCE'
docker rm '" . escapeshellarg($Name) . "'
SOURCE,
  $source,
  'The old double-quoted Docker remove construction is still present.'
);

echo "Docker argument escaping regression test passed.\n";
