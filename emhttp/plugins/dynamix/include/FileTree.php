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
  global $root;
  return mb_strlen($dir) > mb_strlen($root);
}

function no_dots($name) {
  return !in_array($name, ['.','..']);
}

function my_dir($name) {
  global $rootdir, $userdir, $topdir, $UDincluded;
  return ($rootdir === $userdir && in_array($name, $UDincluded)) ? $topdir : $rootdir;
}

$root = path(realpath($_POST['root']));
if (!$root) exit("ERROR: Root filesystem directory not set in jqueryFileTree.php");

$docroot = '/usr/local/emhttp';
require_once "$docroot/webGui/include/Secure.php";

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

echo "<ul class='jqueryFileTree'>";
if ($_POST['show_parent'] == 'true' && is_top($rootdir)) {
  echo "<li class='directory collapsed'>$checkbox<a href='#' rel=\"".htmlspecialchars(dirname($rootdir))."\">..</a></li>";
}

if (is_dir($rootdir)) {
  $dirs  = $files = [];
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
}
echo "</ul>";
?>
