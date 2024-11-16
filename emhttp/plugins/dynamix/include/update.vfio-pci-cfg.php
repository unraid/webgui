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

$vfio = '/boot/config/vfio-pci.cfg';
$old  = is_file($vfio) ? rtrim(file_get_contents($vfio)) : '';
$new  = _var($_POST,'cfg');

$reply = 0;
if ($new != $old) {
  if ($old) copy($vfio,"$vfio.bak");
  if ($new) file_put_contents($vfio,$new); else @unlink($vfio);
  $reply = 1;
}
echo $reply;
?>
