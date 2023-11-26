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
if ($_POST['#arg'][1] != 'none') {
  $ethX = $_POST['#section'];
  if ($_POST['BONDING']=='yes') {
    $nics = explode(',',str_replace($ethX,'',$_POST['BONDNICS']));
    // prepare 'other' interfaces which are part of the bond
    foreach ($nics as $nic) if ($nic) unset($keys[$nic]);
  }
  if ($_POST['BRIDGING']=='yes') {
    $nics = explode(',',str_replace($ethX,'',$_POST['BRNICS']));
    // prepare 'other' interfaces which are part of the bridge
    foreach ($nics as $nic) if ($nic) unset($keys[$nic]);
  }
  // prepare interface to be changed
  unset($keys[$ethX]);
}
?>