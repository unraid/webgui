<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
// Parse a command argument string without evaluating shell syntax. The caller
// must escape every returned argument again when building a shell command.
function shell_args($text) {
  $args = [];
  $arg = '';
  $quote = null;
  $escaped = false;
  $started = false;
  $length = strlen((string)$text);

  for ($i = 0; $i < $length; $i++) {
    $char = $text[$i];
    if ($escaped) {
      $arg .= $char;
      $escaped = false;
      $started = true;
      continue;
    }
    if ($quote === "'") {
      if ($char === "'") $quote = null; else $arg .= $char;
      continue;
    }
    if ($quote === '"') {
      if ($char === '"') {
        $quote = null;
      } elseif ($char === '\\') {
        $escaped = true;
      } else {
        $arg .= $char;
      }
      continue;
    }
    if ($char === '\\') {
      $escaped = true;
      $started = true;
    } elseif ($char === "'" || $char === '"') {
      $quote = $char;
      $started = true;
    } elseif (ctype_space($char)) {
      if ($started) {
        $args[] = $arg;
        $arg = '';
        $started = false;
      }
    } else {
      $arg .= $char;
      $started = true;
    }
  }

  if ($escaped || $quote !== null) return null;
  if ($started) $args[] = $arg;
  return $args;
}

// Convert a command plus its legacy argument string into a safely quoted
// command line. Shell metacharacters remain data and are never re-evaluated.
function shell_command_line($command, $args = '', $suffix = []) {
  $parsed = shell_args($args);
  if ($parsed === null) return null;
  $parts = array_merge([(string)$command], $parsed, $suffix);
  return implode(' ', array_map('escapeshellarg', $parts));
}
?>
<?
// remove malicious code appended after variable assignment
function unscript($text) {
  return trim(preg_split('/[;|&\?=]/',untangle($text))[0]);
}
// remove malicious HTML elements
function untangle($text) {
  return strip_tags(html_entity_decode($text));
}
// remove malicious code appended after string variable
function unbundle($text) {
  return trim(preg_split('/[;|\?=]/',preg_replace(["#['\"](.*?)['\"];?.+$#","#[()\[\]/\\&`]#"],'',html_entity_decode($text)))[0]);
}
?>
