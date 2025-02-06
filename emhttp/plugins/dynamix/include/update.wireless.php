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
$ssl = '/etc/rc.d/rc.ssl.input';
if (is_readable($ssl)) extract(parse_ini_file($ssl));

// encrypt username and password before saving (if existing)
if (!empty($_POST['USERNAME']) && isset($cipher,$key,$iv)) $_POST['USERNAME'] = openssl_encrypt($_POST['USERNAME'],$cipher,$key,0,$iv);
if (!empty($_POST['PASSWORD']) && isset($cipher,$key,$iv)) $_POST['PASSWORD'] = openssl_encrypt($_POST['PASSWORD'],$cipher,$key,0,$iv);

// update active wifi selection
foreach ($keys as $key => $val) if (isset($val['GROUP'])) $keys[$key]['GROUP'] = 'saved';
$keys[$section]['GROUP'] = 'active';
?>
