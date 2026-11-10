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

$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix/include/StartCommand.php');
if ($source === false) throw new RuntimeException('Could not read StartCommand.php.');

assertContainsText("pgrep --ns \$\$ -f '.escapeshellarg(\$proc)", $source, 'Process matching must quote the process path.');
assertContainsText("ctype_digit(\$kill) && (int)\$kill > 1", $source, 'Kill requests must accept only numeric process IDs.');
assertContainsText('$commandLine = shell_command_line($name, $args);', $source, 'StartCommand must build a quoted command line.');
assertContainsText("popen(\$commandLine,'r')", $source, 'Foreground commands must use the quoted command line.');
assertContainsText("'sleep .3 && '.\$commandLine", $source, 'Background commands must use the quoted command line.');
assertNotContainsText('popen("$name $args"', $source, 'Foreground commands must not interpolate raw arguments.');
assertNotContainsText("sleep .3 && \$name \$args", $source, 'Background commands must not interpolate raw arguments.');

echo "Start command escaping regression test passed.\n";
