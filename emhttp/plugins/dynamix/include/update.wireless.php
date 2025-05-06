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

// encrypt username and password before saving (if existing)
if (!empty($_POST['USERNAME'])) $_POST['USERNAME'] = exec("$open_ssl encrypt ".escapeshellarg($_POST['USERNAME']));
if (!empty($_POST['PASSWORD'])) $_POST['PASSWORD'] = exec("$open_ssl encrypt ".escapeshellarg($_POST['PASSWORD']));

// update active wifi selection
foreach ($keys as $key => $val) if (isset($val['GROUP'])) $keys[$key]['GROUP'] = 'saved';
$keys[$section]['GROUP'] = 'active';
?>
