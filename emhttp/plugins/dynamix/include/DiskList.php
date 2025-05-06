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
require_once "$docroot/webGui/include/Helpers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'shares';
require_once "$docroot/webGui/include/Translations.php";

$compute = rawurldecode(_var($_POST,'compute'));
$path    = rawurldecode(_var($_POST,'path'));
$all     = _var($_POST,'all');

$shares  = parse_ini_file('state/shares.ini',true);
$disks   = parse_ini_file('state/disks.ini',true);
$var     = parse_ini_file('state/var.ini');
$sec     = parse_ini_file('state/sec.ini',true);
$sec_nfs = parse_ini_file('state/sec_nfs.ini',true);

/* Get the pools from the disks.ini. */
$pools_check = pools_filter(cache_filter($disks));
$pools = implode(',', $pools_check);

/* If the configuration is pools only, then no array disks are available. */
$poolsOnly	= ((int)$var['SYS_ARRAY_SLOTS'] <= 2) ? true : false;

// exit when no mountable array disks
$nodisks = "<tr><td class='empty' colspan='7'><strong>"._('There are no mounted array or pool disks - cannot add shares').".</strong></td></tr>";
if (!checkDisks($disks)) die($nodisks);

// No shared disks
$nodisks = "<tr><td class='empty' colspan='7'><i class='fa fa-folder-open-o icon'></i>"._('There are no exportable disk shares')."</td></tr>";

// GUI settings
extract(parse_plugin_cfg('dynamix',true));

/* Function to test if any Mouned volumes exist. */
function checkDisks(&$disks) {
        $rc             = false;

        foreach ($disks as $disk) {
                if ($disk['name']!=='flash' && _var($disk,'fsStatus',"")==='Mounted') {
                        $rc     = true;
                        break;
                }
        }

        return $rc;
}

// Display export settings
function disk_share_settings($protocol,$share) {
  if (empty($share)) return;
  if ($protocol!='yes' || _var($share,'export')=='-') return "-";
  return (_var($share,'export')=='e') ? _(ucfirst(_var($share,'security'))) : '<em>'._(ucfirst(_var($share,'security'))).'</em>';
}
function globalInclude($name) {
  global $var;
  return substr($name,0,4)!='disk' || !_var($var,'shareUserInclude') || strpos(_var($var,'shareUserInclude').",","$name,")!==false;
}
function shareInclude($name) {
  global $include;
  return !$include || substr($name,0,4)!='disk' || strpos("$include,", "$name,")!==false;
}
function sharesOnly($disk) {
  return in_array(_var($disk,'type'),['Data','Cache']) && _var($disk,'exportable')=='yes';
}
// filter disk shares
$disks = array_filter($disks,'sharesOnly');

// Compute disk shares & check encryption
$crypto = false;
foreach ($disks as $name => $disk) {
  if ($all!=0 && (!$compute || $compute==$name)) exec("/$docroot/webGui/scripts/disk_size ".escapeshellarg($name)." ssz2");
  $crypto |= strpos(_var($disk,'fsType'),'luks:')!==false;
}
// global shares include/exclude
$myDisks = array_filter(array_diff(array_keys($disks), explode(',',_var($var,'shareUserExclude'))), 'globalInclude');

// Share size per disk
$ssz2 = [];
if ($all==0)
  exec("rm -f /var/local/emhttp/*.ssz2");
else
  foreach (glob("state/*.ssz2",GLOB_NOSORT) as $entry) $ssz2[basename($entry,'.ssz2')] = file($entry,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);

// Build table
$row = 0;
foreach ($disks as $name => $disk) {
  $color = _var($disk,'fsColor');
  $row++;
  switch ($color) {
    case 'green-on' : $orb = 'circle'; $color = 'green'; $help = _('All files protected'); break;
    case 'yellow-on': $orb = 'warning'; $color = 'yellow'; $help = _('All files unprotected'); break;
  }
  if ($crypto) switch (_var($disk,'luksState',0)) {
    case 0: $luks = "<i class='nolock fa fa-lock'></i>"; break;
    case 1: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt green-text'></i><span>"._('All files encrypted')."</span></a>"; break;
    case 2: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt orange-text'></i><span>"._('Some or all files unencrypted')."</span></a>"; break;
   default: $luks = "<a class='info' onclick='return false'><i class='padlock fa fa-lock red-text'></i><span>"._('Unknown encryption state')."</span></a>"; break;
  } else $luks = "";
  echo "<tr><td><a class='view' href=\"/$path/Browse?dir=/mnt/$name\"><i class=\"icon-u-tab\" title=\"",_('Browse')," /mnt/$name\"></i></a>";
  echo "<a class='info nohand' onclick='return false'><i class='fa fa-$orb orb $color-orb'></i><span style='left:18px'>$help</span></a>$luks<a href=\"/$path/Disk?name=$name\" onclick=\"$.cookie('one','tab1')\">$name</a></td>";
  echo "<td>",htmlspecialchars(_var($disk,'comment')),"</td>";
  echo "<td>",disk_share_settings(_var($var,'shareSMBEnabled'), $sec[$name]),"</td>";
  echo "<td>",disk_share_settings(_var($var,'shareNFSEnabled'), $sec_nfs[$name]),"</td>";
  $cmd="/webGui/scripts/disk_size&arg1=$name&arg2=ssz2";
  $type = _var($disk,'rotational') ? _('HDD') : _('SSD');
  if (array_key_exists($name,$ssz2)) {
    echo "<td>$type</td>";
    echo "<td>",my_scale(_var($disk,'fsSize',0)*1024, $unit)," $unit</td>";
    echo "<td>",my_scale(_var($disk,'fsFree',0)*1024, $unit)," $unit</td>";
    echo "</tr>";
    foreach ($ssz2[$name] as $entry) {
      [$sharename,$sharesize] = my_explode('=',$entry);
      if ($sharename=='share.total') continue;
      $inside = in_array(_var($disk,'name'), array_filter(array_diff($myDisks, explode(',',_var($shares[$sharename],'exclude'))), 'shareInclude'));
      echo "<tr class='",($inside ? "'>" : "warning'>");
      echo "<td><a class='view'></a><a href='#' title='",_('Recompute'),"...' onclick=\"computeDisk('",rawurlencode($name),"',$(this).parent())\"><i class='fa fa-refresh icon'></i></a>&nbsp;$sharename</td>";
      echo "<td>",($inside ? "" : "<em>"._('Share is outside the list of designated disks')."</em>"),"</td>";
      echo "<td></td>";
      echo "<td></td>";
      echo "<td></td>";
      echo "<td>",my_scale($sharesize, $unit)," $unit</td>";
      echo "<td>",my_scale(_var($disk,'fsFree',0)*1024, $unit)," $unit</td>";
      echo "</tr>";
    }
  } else {
    echo "<td>$type</td>";
    echo "<td><a href='#' onclick=\"computeDisk('",rawurlencode($name),"',$(this))\">",_('Compute'),"...</a></td>";
    echo "<td>",my_scale(_var($disk,'fsFree',0)*1024, $unit)," $unit</td>";
    echo "</tr>";
  }
}
if ($row==0) echo $nodisks;
?>
