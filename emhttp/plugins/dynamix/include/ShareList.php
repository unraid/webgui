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

/* If the configuration is pools only, then no array disks are available. */
$poolsOnly	= ((int)$var['SYS_ARRAY_SLOTS'] <= 2) ? true : false;

/* Check for any files in the share. */
if (isset($_POST['scan'])) {
	$directory = "/mnt/user/{$_POST['scan']}";
	$hasFiles = false;

	/* Check if the directory exists */
	if (is_dir($directory)) {
		/* Create a new RecursiveDirectoryIterator instance with SKIP_DOTS to skip . and .. entries */
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		/* Iterate over each item in the directory and its subdirectories */
		foreach ($iterator as $fileinfo) {
			/* Check if the current item is a file and not a .DS_Store file */
			if ($fileinfo->isFile() && $fileinfo->getFilename() !== '.DS_Store') {
				$hasFiles = true;
				break;
			}
		}
		/* Output 0 if files are found, 1 if no files are found */
		die($hasFiles ? '0' : '1');
	} else {
		/* Output 1 if the directory does not exist */
		die('1');
	}
}

/* Remove all '.DS_Store' files from a directory recursively and delete empty directories. */
if (isset($_POST['delete'])) {
	$nameToDelete = $_POST['delete'];
	$dirPath = "/mnt/user/{$nameToDelete}";

	if (is_dir($dirPath)) {
		removeDSStoreFilesAndEmptyDirs($dirPath);
	}

	die("success");
}

/* Function to remove '.DS_Store' files and empty directories from a share. */
function removeDSStoreFilesAndEmptyDirs($dir) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iterator as $file) {
		if ($file->isFile() && $file->getFilename() === '.DS_Store') {
			unlink($file->getRealPath());
		}
	}

	/* Second pass to remove empty directories */
	foreach ($iterator as $file) {
		if ($file->isDir() && !(new \FilesystemIterator($file->getRealPath()))->valid()) {
			rmdir($file->getRealPath());
		}
	}
}

if (isset($_POST['cleanup'])) {
  $n = 0;
  // active shares
  $shares = array_map('strtolower',array_keys(parse_ini_file('state/shares.ini',true)));

  // stored shares
  foreach (glob("/boot/config/shares/*.cfg",GLOB_NOSORT) as $name) {
    if (!in_array(strtolower(basename($name,'.cfg')),$shares)) {
      $n++;
      if ($_POST['cleanup']==1) unlink($name);
    }
  }
  // return number of deleted files
  die((string)$n);
}

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

// exit when no mountable array disks
$nodisks = "<tr><td class='empty' colspan='7'><strong>"._('There are no mounted array or pool disks - cannot add shares').".</strong></td></tr>";
if (!checkDisks($disks)) die($nodisks);

// exit when no shares
$noshares = "<tr><td class='empty' colspan='7'><i class='fa fa-folder-open-o icon'></i>"._('There are no exportable user shares')."</td></tr>";
if (!$shares) die($noshares);

// GUI settings
extract(parse_plugin_cfg('dynamix',true));

// Natural sorting of share names
uksort($shares,'strnatcasecmp');

/* Function to test if any Mouned volumes exist. */
function checkDisks(&$disks) {
	$rc		= false;

	foreach ($disks as $disk) {
		if ($disk['name']!=='flash' && _var($disk,'fsStatus',"")==='Mounted') {
			$rc	= true;
			break;
		}
	}

	return $rc;
}

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
// Compute user shares & check encryption
$crypto = false;
foreach ($shares as $name => $share) {
	if ($all!=0 && (!$compute || $compute==$name)) exec("$docroot/webGui/scripts/share_size ".escapeshellarg($name)." ssz1 ".escapeshellarg($pools));
	$crypto |= _var($share,'luksStatus',0)>0;
}
// global shares include/exclude
$myDisks = array_filter(array_diff(array_keys($disks), explode(',',$var['shareUserExclude'])), 'globalInclude');

// Share size per disk
$ssz1 = [];
if ($all==0) {
	exec("rm -f /var/local/emhttp/*.ssz1");
} else {
	foreach (glob("state/*.ssz1",GLOB_NOSORT) as $entry) $ssz1[basename($entry,'.ssz1')] = parse_ini_file($entry);
}

/* Define constants for magic strings */
define('STATUS_GREEN_ON', 'green-on');
define('STATUS_YELLOW_ON', 'yellow-on');
define('LUKS_STATUS_UNKNOWN', 0);
define('LUKS_STATUS_ENCRYPTED', 1);
define('LUKS_STATUS_UNENCRYPTED', 2);

// Build table
$row = 0;

/* Get the first pool if needed. */
$firstPool = $pools_check[0] ?? "";
foreach ($shares as $name => $share) {
	/* Correct a situation in previous Unraid versions where an array only share has a useCache defined. */
	if ((!$poolsOnly) && ($share['useCache'] == "no")) {
		$share['cachePool'] = "";
	} else if (($poolsOnly) && (!$share['cachePool'])) {
		$share['cachePool']	= $firstPool;
	}

	/* Is cachePool2 defined? If it is we need to show the cache pool 2 device name instead of 'Array'. */
	if ($share['cachePool2']) {
		$array		= compress(my_disk($share['cachePool2'],$display['raw']));
		$indicator	= "<i class='fa fa-bullseye fa-fw'></i>";
	} else {
		$array		= _('Array');
		$indicator	= "<i class='fa fa-database fa-fw'></i>";
	}

	/* Check that the share storage assignments are valid. */
	if (($share['cachePool']) && (! in_array($share['cachePool'], $pools_check))) {
		$array			= compress(my_disk($share['cachePool'],$display['raw']));
		$indicator	= "<i class='fa fa-bullseye fa-fw'></i>";
		$share_valid	= false;
	} else if (($share['cachePool2']) && (! in_array($share['cachePool2'], $pools_check))) {
		$array			= compress(my_disk($share['cachePool2'],$display['raw']));
		$indicator	= "<i class='fa fa-bullseye fa-fw'></i>";
		$share_valid	= false;
	} else if (($poolsOnly) && (! $share['cachePool']) && (! $share['cachePool2'])) {
		$share_valid	= false;
	} else {
		/* Is the share exclusive? */
	    $exclusive = _var($share, 'exclusive') == 'yes' ? "<i class='fa fa-caret-right '></i> " : "";
		$share_valid	= true;
	}

	/* When there is no array, all pools are treated as 'only' cache. */
	if (($poolsOnly) && (! $share['cachePool2'])) {
		$share['useCache'] = 'only';
	}

	$row++;
	$color = $share['color'] ?? '';
	$orb = '';
	$help = '';

	switch ($color) {
		case STATUS_GREEN_ON:
			$orb = 'circle';
			$color = 'green';
			$help = _('All files protected');
			break;
		case STATUS_YELLOW_ON:
			$orb = 'warning';
			$color = 'yellow';
			$help = _('Some or all files unprotected');
			break;
		default:
			/* Handle unexpected color values */
			$orb = 'question';
			$color = 'grey';
			$help = _('Unknown protection status');
			break;
	}

	$luks = '';
	if ($crypto) {
		switch ($share['luksStatus'] ?? LUKS_STATUS_UNKNOWN) {
			case LUKS_STATUS_UNKNOWN:
				$luks = "<i class='nolock fa fa-lock'></i>";
				break;
			case LUKS_STATUS_ENCRYPTED:
				$luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt green-text'></i><span>"._('All files encrypted')."</span></a>";
				break;
			case LUKS_STATUS_UNENCRYPTED:
				$luks = "<a class='info' onclick='return false'><i class='padlock fa fa-unlock-alt orange-text'></i><span>"._('Some or all files unencrypted')."</span></a>";
				break;
			default:
				$luks = "<a class='info' onclick='return false'><i class='padlock fa fa-lock red-text'></i><span>"._('Unknown encryption state')."</span></a>";
				break;
		}
	}

	echo "<tr><td><a class='view' href=\"/$path/Browse?dir=/mnt/user/", htmlspecialchars($name), "\"><i class=\"icon-u-tab\" title=\"", _('Browse'), " /mnt/user/" . htmlspecialchars($name), "\"></i></a>";
	echo "<a class='info nohand' onclick='return false'><i class='fa fa-$orb orb $color-orb'></i><span style='left:18px'>$help</span></a>$luks<a href=\"/$path/Share?name=";
	echo rawurlencode($name), "\" onclick=\"$.cookie('one','tab1')\">$name</a></td>";
        echo "<td>", htmlspecialchars(_var($share,'comment')), "</td>";
	echo "<td>", user_share_settings($var['shareSMBEnabled'], $sec[$name]), "</td>";
	echo "<td>", user_share_settings($var['shareNFSEnabled'], $sec_nfs[$name]), "</td>";

	/* If the share pool or array is not valid, indicate that to the user. */
	if (!$share_valid) {
		$cache = "<a class='hand info none' onclick='return false'>".$indicator." <i class='fa fa-warning red-orb'></i> ".$array."<span>"._('This share is invalid').'; '. _('It references storage that does not exist')."</span></a>";
	} else {
		switch ($share['useCache']) {
			case 'no':
				$cache = "<a class='hand info none' onclick='return false'>".$indicator.$exclusive.$array."<span>".sprintf(_('Primary storage %s'), $array)."</span></a>";
				break;
			case 'yes':
				$cache = "<a class='hand info none' onclick='return false'><i class='fa fa-bullseye fa-fw'></i>".compress(my_disk($share['cachePool'], $display['raw']))." <i class='fa fa-arrow-right fa-fw'></i>".$indicator.$array."<span>"._('Primary storage to Secondary storage')."</span></a>";
				break;
			case 'prefer':
				$cache = "<a class='hand info none' onclick='return false'><i class='fa fa-bullseye fa-fw'></i>".compress(my_disk($share['cachePool'], $display['raw']))." <i class='fa fa-arrow-left fa-fw'></i>".$indicator.$array."<span>"._('Secondary storage to Primary storage')."</span></a>";
				break;
			case 'only':
				$cache = "<a class='hand info none' onclick='return false'><i class='fa fa-bullseye fa-fw'></i>$exclusive".my_disk($share['cachePool'], $display['raw'])."<span>".sprintf(_('Primary storage %s'), $share['cachePool']).($exclusive ? ", "._('Exclusive access') : "")."</span></a>";
				break;
			default:
				/* Handle unexpected useCache values */
				$cache = "<a class='hand info none' onclick='return false'>". $indicator . $array . "<span>"._('Unknown cache usage')."</span></a>";
				break;
		}
	}

	if (array_key_exists($name, $ssz1)) {
		echo "<td>$cache</td>";
		echo "<td>", my_scale($ssz1[$name]['disk.total'], $unit), " $unit</td>";
		echo "<td>", my_scale($share['free'] * 1024, $unit), " $unit</td>";
		echo "</tr>";
		foreach ($ssz1[$name] as $diskname => $disksize) {
			if ($diskname == 'disk.total') continue;
			$include = $share['include'];
			$inside = in_array($diskname, array_filter(array_diff($myDisks, explode(',', $share['exclude'])), 'shareInclude'));
			echo "<tr class='", ($inside ? "'>" : "warning'>");
			echo "<td><a class='view'></a><a href='#' title='", _('Recompute'), "...' onclick=\"computeShare('", rawurlencode($name), "',$(this).parent())\"><i class='fa fa-refresh icon'></i></a>&nbsp;", _(my_disk($diskname, $display['raw']), 3), "</td>";
			echo "<td>", ($inside ? "" : "<em>"._('Share is outside the list of designated disks')."</em>"), "</td>";
			echo "<td></td>";
			echo "<td></td>";
			echo "<td></td>";
			echo "<td>", my_scale($disksize, $unit), " $unit</td>";
			echo "<td>", my_scale($disks[$diskname]['fsFree'] * 1024, $unit), " $unit</td>";
			echo "</tr>";
		}
	} else {
		echo "<td>$cache</td>";
		echo "<td><a href='#' onclick=\"computeShare('", rawurlencode($name), "',$(this))\">", _('Compute'), "...</a></td>";
		echo "<td>", my_scale($share['free'] * 1024, $unit), " $unit</td>";
		echo "</tr>";
	}
}
if ($row==0) echo $noshares;
?>
