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
?>
<?
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Encryption.php";

$_POST['AuthPass'] = base64_encrypt($_POST['AuthPass']);
// The display name goes into the From header: replace control characters, a CR/LF would start a new header.
// update.php writes it inside INI double quotes unescaped, and the config loader (my_parse_ini_string) decodes
// HTML entities and strips tags; the INI parser also expands ${...}. So " is saved as ', < > { } as ( ) ( ), \ is
// removed (the page sends a typed " as \"), and an & that starts an entity is saved as &amp; so the name loads back as typed.
$name = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $_POST['SenderName'] ?? '');
$name = strtr($name, ['\\"' => "'", '"' => "'", '\\' => '', '<' => '(', '>' => ')', '{' => '(', '}' => ')']);
$_POST['SenderName'] = trim(preg_replace('/&(?=#?[A-Za-z0-9]+;)/', '&amp;', $name));
?>
