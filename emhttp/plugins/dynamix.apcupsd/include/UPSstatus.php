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

$red     = 'red-text';
$green   = 'green-text';
$orange  = 'orange-text';
$status  = array_fill(0,7,['text' => '-', 'class' => '']);
$result  = [];
$rows    = [];
$level   = (float)($_POST['level'] ?? 10);
$runtime = (float)($_POST['runtime'] ?? 5);

if (file_exists("/var/run/apcupsd.pid")) {
  exec("/sbin/apcaccess 2>/dev/null", $rows);
  for ($i=0; $i<count($rows); $i++) {
    [$key,$val] = array_map('trim',array_pad(explode(':',$rows[$i],2),2,''));
    switch ($key) {
    case 'MODEL':
      $status[0] = ['text' => $val, 'class' => $green];
      break;
    case 'STATUS':
      $text = strtr($val, $state);
      $status[1] = $val ? ['text' => $text, 'class' => strpos($val,'ONLINE')!==false ? $green : $red] : ['text' => _('Refreshing').'...', 'class' => $orange];
      break;
    case 'BCHARGE':
      $charge = round(strtok($val,' '));
      $status[2] = ['text' => "$charge %", 'class' => $charge>$level ? $green : $red];
      break;
    case 'TIMELEFT':
      $time = round(strtok($val,' '));
      $unit = _('minutes');
      $status[3] = ['text' => "$time $unit", 'class' => $time>$runtime ? $green : $red];
      break;
    case 'NOMPOWER':
      $power = (float)strtok($val,' ');
      $status[4] = ['text' => "$power W", 'class' => $power>0 ? $green : $red];
      break;
    case 'LOADPCT':
      $load = (float)strtok($val,' ');
      $status[5] = ['text' => round($load)." %", 'class' => ''];
      break;
    case 'OUTPUTV':
      $output = round((float)strtok($val,' '));
      $status[6] = ['text' => "$output V", 'class' => ''];
      break;
    case 'NOMINV':
      $volt = (float)strtok($val,' ');
      $minv = floor($volt / 1.1); // +/- 10% tolerance
      $maxv = ceil($volt * 1.1);
      break;
    case 'LINEFREQ':
      $freq = round((float)strtok($val,' '));
      break;
    }
    $result[] = ['key' => $key, 'value' => $val];
  }

  // If the override is defined, override the power value, using the same implementation as above.
  // This is a better implementation, as it allows the existing Unraid code to work with the override.
  if ($overrideUpsCapacity > 0) {
    $power = $overrideUpsCapacity;
    $status[4] = ['text' => "$power W", 'class' => $power>0 ? $green : $red];
  }

  if (($power??false) && isset($load)) {
    $status[5] = ['text' => round($power*$load/100)." W (".round($load)." %)", 'class' => $load<90 ? $green : $red];
  } elseif (isset($load)) {
    $status[5]['class'] = $load<90 ? $green : $red;
  }
  if (isset($output)) {
    $withinRange = !($volt??0) || ($minv<$output && $output<$maxv);
    $status[6]['text'] .= isset($freq) ? " ~ $freq Hz" : '';
    $status[6]['class'] = $withinRange ? $green : $red;
  }
}
if (empty($rows)) $result[] = ['message' => _('No information available')];

header('Content-Type: application/json');
echo json_encode(['summary' => $status, 'details' => $result]);
?>
