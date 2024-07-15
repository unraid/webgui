<?PHP
/* Copyright 2005-2024, Lime Technology
 * Copyright 2012-2024, Bergware International.
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
require_once "$docroot/webGui/include/Wrappers.php";

// This is a stub, does nothing but return success
my_logger("This is a stub and should not be called", "UpdateDNS");
$cli = php_sapi_name()=='cli';
if ($cli) {
  exit("success".PHP_EOL);
}
header('Content-Type: application/json');
http_response_code(204);
exit(0);
?>
