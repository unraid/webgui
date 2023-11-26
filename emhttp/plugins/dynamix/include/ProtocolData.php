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
require_once "$docroot/webGui/include/Secure.php";
require_once "$docroot/webGui/include/Wrappers.php";

switch (_var($_GET,'protocol')) {
  case 'smb': $data = (array)@parse_ini_file('state/sec.ini',true); break;
  case 'nfs': $data = (array)@parse_ini_file('state/sec_nfs.ini',true); break;
}
$name = unscript(_var($_GET,'name'));
echo json_encode(_var($data,$name));
?>
