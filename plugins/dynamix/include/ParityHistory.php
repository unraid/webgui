<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2012-2018, Bergware International.
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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";

$month = [' Jan '=>'-01-',' Feb '=>'-02-',' Mar '=>'-03-',' Apr '=>'-04-',' May '=>'-05-',' Jun '=>'-06-',' Jul '=>'-07-',' Aug '=>'-08-',' Sep '=>'-09-',' Oct '=>'-10-',' Nov '=>'-11-',' Dec '=>'-12-'];

function his_plus($val, $word, $last) {
  return $val>0 ? (($val||$last)?($val.' '.$word.($last?'':', ')):'') : '';
}
function his_duration($time) {
  if (!$time) return 'Unavailable';
  $days = floor($time/86400);
  $hmss = $time-$days*86400;
  $hour = floor($hmss/3600);
  $mins = $hmss/60%60;
  $secs = $hmss%60;
  return his_plus($days,'day',($hour|$mins|$secs)==0).his_plus($hour,'hr',($mins|$secs)==0).his_plus($mins,'min',$secs==0).his_plus($secs,'sec',true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="robots" content="noindex, nofollow">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
</head>
<body>
<table class='share_status'><thead><tr><td>Date</td><td>Duration<td>Elapsed</td><td>Speed</td><td>Status</td><td>Errors</td>
<td>Increments</td></tr></thead><tbody>
<?
$log = '/boot/config/parity-checks.log'; $list = [];
if (file_exists($log)) {
  $handle = fopen($log, 'r');
  while (($line = fgets($handle)) !== false) {
    list($date,$duration,$speed,$status,$error,$elapsed,$increments) = explode('|',$line);
    if ($speed==0) $speed = 'Unavailable';
    $date = str_replace(' ',', ',strtr(str_replace('  ',' 0',$date),$month));
    if ($duration>0||$status<>0) $list[] = "<tr><td>$date</td><td>".his_duration($duration)."</td><td>".($elapsed==0?'Unknown':his_duration($elapsed))."</td><td>$speed</td><td>".($status==0?'OK':($status==-4?'Canceled':$status))."</td><td>$error</td><td>".($increments==0?'Unknown':$increments)."<td></td></tr>";
  }
  fclose($handle);
}
if ($list)
  for ($i=count($list); $i>=0; --$i) echo $list[$i];
else
  echo "<tr><td colspan='5' style='text-align:center;padding-top:12px'>No parity check history present!</td></tr>";
?>
</tbody></table>
<div style="text-align:center;margin-top:12px"><input type="button" value="Done" onclick="top.Shadowbox.close()"></div>
</body>
</html>
