#!/usr/bin/env php
<?php
declare(strict_types=1);

function extractFunction(string $source, string $name): string
{
  $source = preg_replace('/<\?(?!php|=|xml)/i', '<?php', $source) ?? $source;
  $tokens = token_get_all($source);
  $capturing = false;
  $braces = 0;
  $inDoubleQuote = false;
  $code = '';
  $tokenCount = count($tokens);

  for ($i = 0; $i < $tokenCount; $i++) {
    $token = $tokens[$i];
    if (!$capturing && is_array($token) && $token[0] === T_FUNCTION) {
      $j = $i + 1;
      while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
      if ($j < $tokenCount && $tokens[$j] === '&') $j++;
      while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
      if ($j >= $tokenCount || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) continue;
      $capturing = true;
    }
    if (!$capturing) continue;
    $code .= is_array($token) ? $token[1] : $token;
    if (is_string($token)) {
      if ($token === '"') {
        $inDoubleQuote = !$inDoubleQuote;
      } elseif (!$inDoubleQuote && $token === '{') {
        $braces++;
      } elseif (!$inDoubleQuote && $token === '}' && --$braces === 0) {
        break;
      }
    }
  }
  if ($code === '') throw new RuntimeException("Could not extract $name");
  return $code;
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
  if ($expected !== $actual) throw new RuntimeException($message);
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
  if (strpos($haystack, $needle) === false) throw new RuntimeException($message);
}

$repo = dirname(__DIR__);
$secure = file_get_contents("$repo/emhttp/plugins/dynamix/include/Secure.php");
$queue = file_get_contents("$repo/emhttp/plugins/dynamix/include/TaskQueue.php");
if ($secure === false || $queue === false) throw new RuntimeException('Could not read task queue sources.');
eval(extractFunction($secure, 'shell_args'));
eval(extractFunction($secure, 'shell_command_line'));

assertSameValue(
  ['safe', 'two words', '$(not-a-command)', '`literal`'],
  shell_args('safe "two words" $(not-a-command) `literal`'),
  'Shell argument parsing must keep metacharacters as literal data.'
);
assertSameValue(null, shell_args("unterminated '"), 'Unterminated shell quoting must fail closed.');
assertSameValue(
  "'/usr/local/bin/example' '$(not-a-command)' 'two words'",
  shell_command_line('/usr/local/bin/example', '$(not-a-command) "two words"'),
  'Each parsed argument must be quoted before it enters bash -c.'
);
assertContainsText('$command = shell_command_line($name, $args, $suffix);', $queue, 'TaskQueue must build commands from individually escaped arguments.');
assertContainsText('$payload = $gate.\'sleep .3 && \'.$command.$stamp;', $queue, 'TaskQueue must not append the raw argument string to its payload.');

echo "Task queue argument escaping regression test passed.\n";
