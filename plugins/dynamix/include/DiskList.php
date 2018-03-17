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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";

$shares  = parse_ini_file('state/shares.ini',true);
$disks   = parse_ini_file('state/disks.ini',true);
$var     = parse_ini_file('state/var.ini');
$sec     = parse_ini_file('state/sec.ini',true);
$sec_nfs = parse_ini_file('state/sec_nfs.ini',true);
$sec_afp = parse_ini_file('state/sec_afp.ini',true);
$compute = $_GET['compute'];
$path    = $_GET['path'];
$fill    = $_GET['fill'];

$display = [];
$display['scale'] = $_GET['scale'];
$display['number'] = $_GET['number'];

// Display export settings
function disk_share_settings($protocol,$share) {
  if (empty($share)) return;
  if ($protocol!='yes' || $share['export']=='-') return "-";
  if ($share['export']=='e') return ucfirst($share['security']);
  return '<em>'.ucfirst($share['security']).'</em>';
}

function globalInclude($name) {
  global $var;
  return substr($name,0,4)!='disk' || !$var['shareUserInclude'] || strpos("{$var['shareUserInclude']},","$name,")!==false;
}

function shareInclude($name) {
  global $include;
  return !$include || substr($name,0,4)!='disk' || strpos("$include,", "$name,")!==false;
}

function sharesOnly($disk) {
  return strpos('Data,Cache',$disk['type'])!==false && $disk['exportable']=='yes';
}
// filter disk shares
$disks = array_filter($disks,'sharesOnly');

// Compute all disk shares & check encryption
$crypto = false;
foreach ($disks as $name => $disk) {
  if ($compute=='yes') exec("webGui/scripts/disk_size ".escapeshellarg($name)." ssz2");
  $crypto |= strpos($disk['fsType'],'luks:')!==false;
}

// global shares include/exclude
$myDisks = array_filter(array_diff(array_keys($disks), explode(',',$var['shareUserExclude'])), 'globalInclude');

// Share size per disk
$ssz2 = [];
if ($fill)
  foreach (glob("state/*.ssz2", GLOB_NOSORT) as $entry) $ssz2[basename($entry, ".ssz2")] = parse_ini_file($entry);
else
  exec("rm -f /var/local/emhttp/*.ssz2");

// Build table
$row = 0;
foreach ($disks as $name => $disk) {
  $color = $disk['fsColor'];
  $row++;
  $ball = "/webGui/images/$color.png";
  switch ($color) {
    case 'green-on':  $help = 'All files protected'; break;
    case 'yellow-on': $help = 'All files unprotected'; break;
  }
  if ($crypto) switch ($disk['luksState']) {
    case 0: $luks = "<i class='nolock fa fa-lock'></i>"; break;
    case 1: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt green-text'></i><span>All files encrypted</span></a>"; break;
    case 2: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt orange-text'></i><span>Some or all files unencrypted</span></a>"; break;
   default: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-lock red-text'></i><span>Unknown encryption state'</span></a>"; break;
  } else $luks = "";
  echo "<tr>";
  echo "<td><a class='info nohand' onclick='return false'><img src='$ball' class='icon'><span style='left:18px'>$help</span></a>$luks<a href='$path/Disk?name=$name' onclick=\"$.cookie('one','tab1',{path:'/'})\">$name</a></td>";
  echo "<td>{$disk['comment']}</td>";
  echo "<td>".disk_share_settings($var['shareSMBEnabled'], $sec[$name])."</td>";
  echo "<td>".disk_share_settings($var['shareNFSEnabled'], $sec_nfs[$name])."</td>";
  echo "<td>".disk_share_settings($var['shareAFPEnabled'], $sec_afp[$name])."</td>";
  $cmd="/webGui/scripts/disk_size"."&arg1=".urlencode($name)."&arg2=ssz2";
  if (array_key_exists($name, $ssz2)) {
    echo "<td>".my_scale(($disk['fsSize'])*1024, $unit)." $unit</td>";
    echo "<td>".my_scale($disk['fsFree']*1024, $unit)." $unit</td>";
    echo "<td><a href='$path/Browse?dir=/mnt/$name'><img src='/webGui/images/explore.png' title='Browse /mnt/$name'></a></td>";
    echo "</tr>";
    foreach ($ssz2[$name] as $sharename => $sharesize) {
      if ($sharename=='share.total') continue;
      $include = $shares[$sharename]['include'];
      $inside = in_array($disk['name'], array_filter(array_diff($myDisks, explode(',',$shares[$sharename]['exclude'])), 'shareInclude'));
      echo "<tr class='share_status_size".($inside ? "'>" : " warning'>");
      echo "<td>$sharename:</td>";
      echo "<td>".($inside ? "" : "<em>Share is outside the list of designated disks</em>")."</td>";
      echo "<td></td>";
      echo "<td></td>";
      echo "<td></td>";
      echo "<td class='disk-$row-1'>".my_scale($sharesize*1024, $unit)." $unit</td>";
      echo "<td class='disk-$row-2'>".my_scale($disk['fsFree']*1024, $unit)." $unit</td>";
      echo "<td><a href='/update.htm?cmd=$cmd&csrf_token={$var['csrf_token']}' target='progressFrame' title='Recompute...' onclick='$.cookie(\"ssz\",\"ssz\",{path:\"/\"});$(\".disk-$row-1\").html(\"Please wait...\");$(\".disk-$row-2\").html(\"\");'><i class='fa fa-refresh icon'></i></a></td>";
      echo "</tr>";
    }
  } else {
    echo "<td><a href='/update.htm?cmd=$cmd&csrf_token={$var['csrf_token']}' target='progressFrame' onclick=\"$.cookie('ssz','ssz',{path:'/'});$(this).text('Please wait...')\">Compute...</a></td>";
    echo "<td>".my_scale($disk['fsFree']*1024, $unit)." $unit</td>";
    echo "<td><a href='$path/Browse?dir=/mnt/$name'><img src='/webGui/images/explore.png' title='Browse /mnt/$name'></a></td>";
    echo "</tr>";
  }
}
if ($row==0) {
  echo "<tr><td colspan='8' style='text-align:center;padding-top:12px'><i class='fa fa-folder-open-o icon'></i>There are no exportable disk shares</td></tr>";
}
?>
