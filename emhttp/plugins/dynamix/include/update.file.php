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
if ($file) {
  write_log("Saving file $file");
  exec("mkdir -p ".escapeshellarg(dirname($file)));
  file_put_contents($file, str_replace(["\r\n","\r"], "\n", $_POST['text']));
}
// discard settings
$save = false;
?>
