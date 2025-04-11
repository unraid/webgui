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
function is_low($dir) {
  global $root;
  return mb_substr($dir,0,mb_strlen($root)) == $root;
}

$root = path(realpath($_POST['root']));
if (!$root) exit("ERROR: Root filesystem directory not set in jqueryFileTree.php");

$docroot = '/usr/local/emhttp';
require_once "$docroot/webGui/include/Secure.php";

$rootdir  = path(realpath($_POST['dir']));
$topdir   = '/mnt/';
$userdir  = '/mnt/user/';
$filters  = (array)$_POST['filter'];
$match    = $_POST['match'];
$checkbox = $_POST['multiSelect'] == 'true' ? "<input type='checkbox'>" : "";

/* Excluded folders to not show in the dropdown in the '/mnt/' directory only. */
$excludedFolders = ['RecycleBin', 'addons', 'rootshare'];
$udShares = ['disks','remotes'];

echo "<ul class='jqueryFileTree'>";
if ($_POST['show_parent'] == 'true' && is_top($rootdir)) {
  echo "<li class='directory collapsed'>$checkbox<a href='#' rel=\"".htmlspecialchars(dirname($rootdir))."\">..</a></li>";
}

if (is_low($rootdir) && is_dir($rootdir)) {
  $dirs = $files = [];
  $names = array_filter(scandir($rootdir, SCANDIR_SORT_NONE), function($n){return $n != '.' && $n != '..';});
  natcasesort($names);
  foreach ($names as $name) {
    if (is_dir($rootdir.$name)) {
      $dirs[] = $name;
    } else {
      $files[] = $name;
    }
  }
  foreach ($dirs as $name) {
    $htmlRel  = htmlspecialchars($rootdir.$name);
    $htmlName = htmlspecialchars(mb_strlen($name) <= 33 ? $name : mb_substr($name, 0, 30).'...');

    /* Exclude '.Recycle.Bin' from all directories */
    if ($name === '.Recycle.Bin') continue;

    /* Exclude folders only when directory is '/mnt/' */
    if (in_array($name, $excludedFolders) && $rootdir === $topdir) continue;

    if (empty($match) || preg_match("/$match/", $rootdir.$name.'/')) {
      echo "<li class='directory collapsed'>$checkbox<a href='#' rel=\"$htmlRel/\">$htmlName</a></li>";
    }
  }
  if ($rootdir === $userdir) {
    // add unassigned devices top level shares
    foreach ($udShares as $name) if (is_dir($topdir.$name)) {
      echo "<li class='directory collapsed'>$checkbox<a href='#' rel=\"{$topdir}{$name}/\">$name</a></li>";
    }
  }
  foreach ($files as $name) {
    $htmlRel  = htmlspecialchars($rootdir . $name);
    $htmlName = htmlspecialchars($name);
    $ext      = mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
    foreach ($filters as $filter) {
      if (empty($filter) || $ext == $filter) {
        if (empty($match) || preg_match("/$match/", $name)) {
          echo "<li class='file ext_$ext'>$checkbox<a href='#' rel=\"$htmlRel\">$htmlName</a></li>";
        }
      }
    }
  }
}
echo "</ul>";
?>
