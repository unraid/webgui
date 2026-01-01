<?php
/**
 * jQuery File Tree PHP Connector
 *
 * Version 1.2.0
 *
 * @author - Cory S.N. LaViska A Beautiful Site (http://abeautifulsite.net/)
 * @author - Dave Rogers - https://github.com/daverogers/jQueryFileTree
 *
 * History:
 *
 * 1.2.2 - allow user shares to UD shares
 * 1.2.1 - exclude folders from the /mnt/ root folder
 * 1.2.0 - adapted by Bergware for use in Unraid - support UTF-8 encoding & hardening
 * 1.1.1 - SECURITY: forcing root to prevent users from determining system's file structure (per DaveBrad)
 * 1.1.0 - adding multiSelect (checkbox) support (08/22/2014)
 * 1.0.2 - fixes undefined 'dir' error - by itsyash (06/09/2014)
 * 1.0.1 - updated to work with foreign characters in directory/file names (12 April 2008)
 * 1.0.0 - released (24 March 2008)
 *
 * Output a list of files for jQuery File Tree
 */

/**
 * filesystem root - USER needs to set this!
 * -> prevents debug users from exploring system's directory structure
 * ex: $root = $_SERVER['DOCUMENT_ROOT'];
 */

function path($dir) {
  return mb_substr($dir,-1) == '/' ? $dir : $dir.'/';
}

function is_top($dir) {
  global $fileTreeRoot;
  return mb_strlen($dir) > mb_strlen($fileTreeRoot);
}

function no_dots($name) {
  return !in_array($name, ['.','..']);
}

function my_dir($name) {
  global $rootdir, $userdir, $topdir, $UDincluded;
  return ($rootdir === $userdir && in_array($name, $UDincluded)) ? $topdir : $rootdir;
}

$fileTreeRoot = path(realpath($_POST['root']));
if (!$fileTreeRoot) exit("ERROR: Root filesystem directory not set in jqueryFileTree.php");

$docroot = '/usr/local/emhttp';
require_once "$docroot/webGui/include/Secure.php";
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/plugins/dynamix/include/PopularDestinations.php";

$mntdir   = '/mnt/';
$userdir  = '/mnt/user/';
$rootdir  = path(realpath($_POST['dir']));
$topdir   = str_replace($userdir, $mntdir, $rootdir);
$filters  = (array)$_POST['filter'];
$match    = $_POST['match'];
$checkbox = $_POST['multiSelect'] == 'true' ? "<input type='checkbox'>" : "";

// Excluded UD shares to hide under '/mnt'
$UDexcluded = ['RecycleBin', 'addons', 'rootshare'];
// Included UD shares to show under '/mnt/user'
$UDincluded = ['disks','remotes'];

$showPopular = in_array('SHOW_POPULAR', $filters);

echo "<ul class='jqueryFileTree'>";

// Show popular destinations at the top (only at root level when SHOW_POPULAR filter is set)
if ($rootdir === $fileTreeRoot && $showPopular) {
  $popularPaths = getPopularDestinations(5);
  
  // Filter popular paths to prevent FUSE conflicts between /mnt/user and /mnt/diskX
  if (!empty($popularPaths)) {
    $isUserContext = (strpos($fileTreeRoot, '/mnt/user') === 0 || strpos($fileTreeRoot, '/mnt/rootshare') === 0);
    
    if ($isUserContext) {
      // In /mnt/user context: only show /mnt/user paths OR non-/mnt paths (external mounts)
      $popularPaths = array_values(array_filter($popularPaths, function($path) {
        return (strpos($path, '/mnt/user') === 0 || strpos($path, '/mnt/rootshare') === 0 || strpos($path, '/mnt/') !== 0);
      }));
    } else if (strpos($fileTreeRoot, '/mnt/') === 0) {
      // In /mnt/diskX or /mnt/cache context: exclude /mnt/user and /mnt/rootshare paths
      $popularPaths = array_values(array_filter($popularPaths, function($path) {
        return (strpos($path, '/mnt/user') !== 0 && strpos($path, '/mnt/rootshare') !== 0);
      }));
    }
    // If root is not under /mnt/, no filtering needed
  }
  
  if (!empty($popularPaths)) {
    echo "<li class='popular-header small-caps-label' style='list-style:none;padding:5px 0 5px 20px;'>"._('Popular')."</li>";
    
    foreach ($popularPaths as $path) {
      $htmlPath = htmlspecialchars($path);
      $displayPath = htmlspecialchars($path);  // Show full path instead of basename
      // Use data-path instead of rel to prevent jQueryFileTree from handling these links
      // Use 'directory' class so jQueryFileTree CSS handles the icon
      echo "<li class='directory popular-destination' style='list-style:none;'>$checkbox<a href='#' data-path='$htmlPath'>$displayPath</a></li>";
    }
    
    // Separator line
    echo "<li class='popular-separator' style='list-style:none;border-top:1px solid var(--inverse-border-color);margin:5px 0 5px 20px;'></li>";
  }
}

// Read directory contents
$dirs = $files = [];
if (is_dir($rootdir)) {
  $names = array_filter(scandir($rootdir, SCANDIR_SORT_NONE), 'no_dots');
  // add UD shares under /mnt/user
  foreach ($UDincluded as $name) {
    if (!is_dir($topdir.$name)) continue;
    if ($rootdir === $userdir) {
      if (!in_array($name, $names)) $names[] = $name;
    } else {
      if (explode('/', $topdir)[2] === $name) $names = array_merge($names, array_filter(scandir($topdir, SCANDIR_SORT_NONE), 'no_dots'));
    }
  }
  natcasesort($names);
  foreach ($names as $name) {
    if (is_dir(my_dir($name).$name)) {
      $dirs[] = $name;
    } else {
      $files[] = $name;
    }
  }
}

// Normal mode: show directory tree
if ($_POST['show_parent'] == 'true' && is_top($rootdir)) {
  echo "<li class='directory collapsed'>$checkbox<a href='#' rel=\"".htmlspecialchars(dirname($rootdir))."\">..</a></li>";
}

// Display directories and files (arrays already populated above)
foreach ($dirs as $name) {
  // Exclude '.Recycle.Bin' from all shares and UD folders from '/mnt'
  if ($name === '.Recycle.Bin' || ($rootdir === $mntdir && in_array($name, $UDexcluded))) continue;
  $htmlRel  = htmlspecialchars(my_dir($name).$name);
  $htmlName = htmlspecialchars(mb_strlen($name) <= 33 ? $name : mb_substr($name, 0, 30).'...');
  if (empty($match) || preg_match("/$match/", $rootdir.$name.'/')) {
    echo "<li class='directory collapsed'>$checkbox<a href='#' rel=\"$htmlRel/\">$htmlName</a></li>";
  }
}
foreach ($files as $name) {
  $htmlRel  = htmlspecialchars(my_dir($name).$name);
  $htmlName = htmlspecialchars($name);
  $ext      = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
  foreach ($filters as $filter) if (empty($filter) || $ext === $filter) {
    if (empty($match) || preg_match("/$match/", $name)) {
      echo "<li class='file ext_$ext'>$checkbox<a href='#' rel=\"$htmlRel\">$htmlName</a></li>";
    }
  }
}

echo "</ul>";
?>
