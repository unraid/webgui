<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 * Copyright 2012-2025, Simon Fairweather.
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
require_once "$docroot/webGui/include/Helpers.php";


function process_action($pciaddr,$action)
{
  global $saved,$current;
  switch ($action) {
    case 'removed':
      unset($saved[$pciaddr]);
      break;
    case 'changed':
    case 'added':
      $saved[$pciaddr] = $current[$pciaddr];
      break;
    }
}


$savedfile = "/boot/config/savedpcidata.json";
$saved = loadSavedData($savedfile);
if (!$saved) {echo "ERROR"; return;};
$current = loadCurrentPCIData();
$pciaddr = $_POST['pciid'];
$action = $_POST['action']??'';

if ($action == 'all') {
  $pciaddrs = explode(";", $pciaddr);
  foreach ($pciaddrs as $pciaddraction){
    $values = explode(',',$pciaddraction);
    process_action($values[0],$values[1]);
  }
} else {
  process_action($pciaddr,$action);
}
file_put_contents($savedfile,json_encode($saved,JSON_PRETTY_PRINT));
echo "OK";
?>
