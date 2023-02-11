<?PHP
/* Copyright 2005-2022, Lime Technology
 * Copyright 2012-2022, Bergware International.
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
// add translations
$_SERVER['REQUEST_URI'] = 'shares';
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

$shares  = parse_ini_file('state/shares.ini',true);
$disks   = parse_ini_file('state/disks.ini',true);
$var     = parse_ini_file('state/var.ini');
$sec     = parse_ini_file('state/sec.ini',true);
$sec_nfs = parse_ini_file('state/sec_nfs.ini',true);
$compute = unscript($_GET['compute']??'');
$path    = unscript($_GET['path']??'');
$fill    = unscript($_GET['fill']??'');

$display = [];
$display['scale'] = unscript($_GET['scale']??'');
$display['number'] = unscript($_GET['number']??'');
$display['raw'] = unscript($_GET['raw']??'');

$pools_check = pools_filter(cache_filter($disks));
$pools = implode(',', $pools_check);

if (!$shares) {
  echo "<tr><td colspan='7' style='text-align:center;padding-top:12px'><i class='fa fa-folder-open-o icon'></i>",_('There are no exportable user shares'),"</td></tr>";
  exit;
}
// Natural sorting of share names
uksort($shares,'strnatcasecmp');

// Display export settings
function user_share_settings($protocol,$share) {
  if (empty($share)) return;
  if ($protocol!='yes' || $share['export']=='-') return "-";
  return ($share['export']=='e') ? _(ucfirst($share['security'])) : '<em>'._(ucfirst($share['security'])).'</em>';
}

function globalInclude($name) {
  global $var;
  return substr($name,0,4)!='disk' || !$var['shareUserInclude'] || strpos("{$var['shareUserInclude']},","$name,")!==false;
}

function shareInclude($name) {
  global $include;
  return !$include || substr($name,0,4)!='disk' || strpos("$include,", "$name,")!==false;
}

// Compute all user shares & check encryption
$crypto = false;
foreach ($shares as $name => $share) {
  if ($compute=='yes') exec("webGui/scripts/share_size ".escapeshellarg($name)." ssz1 ".escapeshellarg($pools));
  $crypto |= $share['luksStatus']>0;
}

// global shares include/exclude
$myDisks = array_filter(array_diff(array_keys($disks), explode(',',$var['shareUserExclude'])), 'globalInclude');

// Share size per disk
$ssz1 = [];
if ($fill)
  foreach (glob("state/*.ssz1", GLOB_NOSORT) as $entry) $ssz1[basename($entry, ".ssz1")] = parse_ini_file($entry);
else
  exec("rm -f /var/local/emhttp/*.ssz1");

// Build table
$row = 0;
foreach ($shares as $name => $share) {
  $row++;
  $color = $share['color'];
  switch ($color) {
    case 'green-on' : $orb = 'circle'; $color = 'green'; $help = _('All files protected'); break;
    case 'yellow-on': $orb = 'warning'; $color = 'yellow'; $help = _('Some or all files unprotected'); break;
  }
  if ($crypto) switch ($share['luksStatus']) {
    case 0: $luks = "<i class='nolock fa fa-lock'></i>"; break;
    case 1: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt green-text'></i><span>"._('All files encrypted')."</span></a>"; break;
    case 2: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt orange-text'></i><span>"._('Some or all files unencrypted')."</span></a>"; break;
   default: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-lock red-text'></i><span>"._('Unknown encryption state')."</span></a>"; break;
  } else $luks = "";
  echo "<tr><td><a class='view' href=\"/$path/Browse?dir=/mnt/user/",urlencode($name),"\"><i class=\"icon-u-tab\" title=\"",_('Browse')," /mnt/user/".urlencode($name),"\"></i></a>";
  echo "<a class='info nohand' onclick='return false'><i class='fa fa-$orb orb $color-orb'></i><span style='left:18px'>$help</span></a>$luks<a href=\"/$path/Share?name=";
  echo urlencode($name),"\" onclick=\"$.cookie('one','tab1')\">",compress($name),"</a></td>";
  echo "<td>{$share['comment']}</td>";
  echo "<td>",user_share_settings($var['shareSMBEnabled'], $sec[$name]),"</td>";
  echo "<td>",user_share_settings($var['shareNFSEnabled'], $sec_nfs[$name]),"</td>";
  $cmd="/webGui/scripts/share_size&arg1=".urlencode($name)."&arg2=ssz1&arg3=".urlencode($pools);
  /* Check for non existent pool device. */
  if ($share['cachePool']) {
    if (! in_array($share['cachePool'], $pools_check)) {
      $share['useCache'] = "no";
    }
  }
  $cache = _(ucfirst($share['useCache'])).($share['useCache']!='no'?' : '.compress(my_disk($share['cachePool'],$display['raw'])):'');
  if (array_key_exists($name, $ssz1)) {
    echo "<td>$cache</td>";
    echo "<td>",my_scale($ssz1[$name]['disk.total'], $unit)," $unit</td>";
    echo "<td>",my_scale($share['free']*1024, $unit)," $unit</td>";
    echo "</tr>";
    foreach ($ssz1[$name] as $diskname => $disksize) {
      if ($diskname=='disk.total') continue;
      $include = $share['include'];
      $inside = in_array($diskname, array_filter(array_diff($myDisks, explode(',',$share['exclude'])), 'shareInclude'));
      echo "<tr class='",($inside ? "'>" : "warning'>");
      echo "<td><a class='view'></a><a href=\"/update.htm?cmd=$cmd&csrf_token={$var['csrf_token']}\" target=\"progressFrame\" title=\"";
      echo _('Recompute'),"...\" onclick='$.cookie(\"ssz\",\"ssz\",{path:\"/\"});$(\".share-$row-1\").html(\"",_('Please wait'),"...\");$(\".share-$row-2\").html(\"\");'><i class='fa fa-refresh icon'></i></a>&nbsp;",_(my_disk($diskname,$display['raw']),3),"</td>";
      echo "<td>",($inside ? "" : "<em>"._('Share is outside the list of designated disks')."</em>"),"</td>";
      echo "<td></td>";
      echo "<td></td>";
      echo "<td></td>";
      echo "<td class='share-$row-1'>",my_scale($disksize, $unit)," $unit</td>";
      echo "<td class='share-$row-2'>",my_scale($disks[$diskname]['fsFree']*1024, $unit)," $unit</td>";
      echo "</tr>";
    }
  } else {
    echo "<td>$cache</td>";
    echo "<td><a href=\"/update.htm?cmd=$cmd&csrf_token={$var['csrf_token']}\" target=\"progressFrame\" onclick=\"$.cookie('ssz','ssz');$(this).text('",_('Please wait')."...')\">",_('Compute'),"...</a></td>";
    echo "<td>",my_scale($share['free']*1024, $unit)," $unit</td>";
    echo "</tr>";
  }
}
if ($row==0) {
  echo "<tr><td colspan='7' style='text-align:center;padding-top:12px'><i class='fa fa-folder-open-o icon'></i>",_('There are no exportable user shares'),"</td></tr>";
}
