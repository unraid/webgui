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
// This is a stub, does nothing but return success
$cli = php_sapi_name()=='cli';
if ($cli) {
  exit("success".PHP_EOL);
}
header('Content-Type: application/json');
http_response_code(204);
exit(0);
?>
