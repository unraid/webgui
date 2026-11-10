<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
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
$conf  = "/etc/apcupsd/apcupsd.conf";
$new   = array_replace_recursive($_POST, $default);
$cable = $new['UPSCABLE']=='custom' ? $new['CUSTOMUPSCABLE'] : $new['UPSCABLE'];

function apcupsd_set($key, $value, $conf) {
  // Keep the replacement a single sed command even when a submitted value
  // contains line breaks, then quote the complete expression for the shell.
  $value = preg_replace('/[\r\n]/', ' ', (string)$value);
  $expression = '/^'.preg_quote($key, '/').'/c\\'.$key.' '.$value;
  exec('sed -i -e '.escapeshellarg($expression).' '.escapeshellarg($conf));
}

exec("/etc/rc.d/rc.apcupsd stop");
apcupsd_set('NISIP', '0.0.0.0', $conf);
apcupsd_set('UPSTYPE', $new['UPSTYPE'], $conf);
apcupsd_set('DEVICE', $new['DEVICE'], $conf);
apcupsd_set('BATTERYLEVEL', intval($new['BATTERYLEVEL']), $conf);
apcupsd_set('MINUTES', intval($new['MINUTES']), $conf);
apcupsd_set('TIMEOUT', intval($new['TIMEOUT']), $conf);
apcupsd_set('UPSCABLE', $cable, $conf);

if ($new['KILLUPS']=='yes' && $new['SERVICE']=='enable')
  exec("! grep -q apccontrol /etc/rc.d/rc.6 && sed -i -e 's:/sbin/poweroff:/etc/apcupsd/apccontrol killpower; /sbin/poweroff:' /etc/rc.d/rc.6");
else
  exec("grep -q apccontrol /etc/rc.d/rc.6 && sed -i -e 's:/etc/apcupsd/apccontrol killpower; /sbin/poweroff:/sbin/poweroff:' /etc/rc.d/rc.6");

if ($new['SERVICE']=='enable') exec("/etc/rc.d/rc.apcupsd start");
?>
