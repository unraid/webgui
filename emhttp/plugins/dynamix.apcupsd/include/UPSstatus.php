<?PHP
/* Copyright 2005-2024, Lime Technology
 * Copyright 2012-2024, Bergware International.
 * Copyright 2015, Dan Landon.
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

// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";
$cfg = parse_plugin_cfg('dynamix.apcupsd');
$overrideUpsCapacity = (int) htmlspecialchars($cfg['OVERRIDE_UPS_CAPACITY'] ?: 0);

$state = [
  'ONLINE'   => _('Online'),
  'SLAVE'    => '('._('slave').')',
  'TRIM'     => '('._('trim').')',
  'BOOST'    => '('._('boost').')',
  'COMMLOST' => _('Lost communication'),
  'ONBATT'   => _('On battery'),
  'NOBATT'   => _('No battery detected'),
  'LOWBATT'  => _('Low on battery'),
  'OVERLOAD' => _('UPS overloaded'),
  'SHUTTING DOWN' => _('System goes down')
];

$red     = "class='red-text'";
$green   = "class='green-text'";
$orange  = "class='orange-text'";
$defaultCell = "<td>-</td>";
$status  = array_fill(0,7,$defaultCell);
$result  = [];
$level   = $_POST['level'] ?: 10;
$runtime = $_POST['runtime'] ?: 5;

if (file_exists("/var/run/apcupsd.pid")) {
  exec("/sbin/apcaccess 2>/dev/null", $rows);
  for ($i=0; $i<count($rows); $i++) {
    [$key,$val] = array_map('trim',array_pad(explode(':',$rows[$i],2),2,''));
    switch ($key) {
    case 'MODEL':
      $status[0] = "<td $green>$val</td>";
      break;
    case 'STATUS':
      $text = strtr($val, $state);
      $status[1] = $val ? (strpos($val,'ONLINE')!==false ? "<td $green>$text</td>" : "<td $red>$text</td>") : "<td $orange>"._('Refreshing')."...</td>";
      break;
    case 'BCHARGE':
      $charge = round(strtok($val,' '));
      $status[2] = $charge>$level ? "<td $green>$charge %</td>" : "<td $red>$charge %</td>";
      break;
    case 'TIMELEFT':
      $time = round(strtok($val,' '));
      $unit = _('minutes');
      $status[3] = $time>$runtime ? "<td $green>$time $unit</td>" : "<td $red>$time $unit</td>";
      break;
    case 'NOMPOWER':
      $power = strtok($val,' ');
      $status[4] = $power>0 ? "<td $green>$power W</td>" : "<td $red>$power W</td>";
      break;
    case 'LOADPCT':
      $load = strtok($val,' ');
      $status[5] = round($load)." %";
      break;
    case 'OUTPUTV':
      $output = round(strtok($val,' '));
      $status[6] = "$output V";
      break;
    case 'NOMINV':
      $volt = strtok($val,' ');
      $minv = floor($volt / 1.1); // +/- 10% tolerance
      $maxv = ceil($volt * 1.1);
      break;
    case 'LINEFREQ':
      $freq = round(strtok($val,' '));
      break;
    }
    if ($i%2==0) $result[] = "<tr>";
    $result[]= "<td><strong>$key</strong></td><td>$val</td>";
    if ($i%2==1) $result[] = "</tr>";
  }
  if (count($rows)%2==1) $result[] = "<td></td><td></td></tr>";

  // If the override is defined, override the power value, using the same implementation as above.
  // This is a better implementation, as it allows the existing Unraid code to work with the override.
  if ($overrideUpsCapacity > 0) {
    $power = $overrideUpsCapacity;
    $status[4] = $power>0 ? "<td $green>$power W</td>" : "<td $red>$power W</td>";
  }

  if ( ($power??false) && isset($load)) $status[5] = ($load<90 ? "<td $green>" : "<td $red>").round($power*$load/100)." W (".$status[5].")</td>";
  elseif (isset($load)) $status[5] = ($load<90 ? "<td $green>" : "<td $red>").$status[5]."</td>";
  $status[6] = isset($output) ? ((!$volt || ($minv<$output && $output<$maxv) ? "<td $green>" : "<td $red>").$status[6].(isset($freq) ? " ~ $freq Hz" : "")."</td>") : $status[6];
}
if (empty($rows)) $result[] = "<tr><td colspan='4' style='text-align:center'>"._('No information available')."</td></tr>";

echo "<tr class='ups'>",implode($status),"</tr>\n",implode($result);
?>
