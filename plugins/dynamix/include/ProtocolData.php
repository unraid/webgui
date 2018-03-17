<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
switch ($_GET['protocol']) {
  case 'smb': $data = parse_ini_file('state/sec.ini',true); break;
  case 'afp': $data = parse_ini_file('state/sec_afp.ini',true); break;
  case 'nfs': $data = parse_ini_file('state/sec_nfs.ini',true); break;
}
echo json_encode($data[$_GET['name']]);
?>
