<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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
$open_ssl = "/usr/local/emhttp/webGui/scripts/open_ssl";
$open_ssl_command = escapeshellarg($open_ssl);
$encrypt = function($value) use ($open_ssl_command) {
  $output = [];
  $status = 1;
  $result = exec("$open_ssl_command encrypt ".escapeshellarg($value),$output,$status);
  return $status === 0 && is_string($result) && $result !== '' ? $result : false;
};

// encrypt username and password before saving (if existing)
if (!empty($_POST['USERNAME'])) {
  $encrypted = $encrypt($_POST['USERNAME']);
  if ($encrypted === false) $save = false;
  else $_POST['USERNAME'] = $encrypted;
}
if (!empty($_POST['PASSWORD'])) {
  $encrypted = $encrypt($_POST['PASSWORD']);
  if ($encrypted === false) $save = false;
  else $_POST['PASSWORD'] = $encrypted;
}

// update active wifi selection
foreach ($keys as $key => $val) if (isset($val['GROUP'])) $keys[$key]['GROUP'] = 'saved';
$keys[$section]['GROUP'] = 'active';
?>
