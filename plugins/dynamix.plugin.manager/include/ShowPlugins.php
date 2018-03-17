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
require_once "$docroot/webGui/include/Markdown.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

$system  = $_GET['system'] ?? false;
$branch  = $_GET['branch'] ?? false;
$audit   = $_GET['audit'] ?? false;
$check   = $_GET['check'] ?? false;
$empty   = true;
$builtin = ['unRAIDServer'];
$plugins = "/var/log/plugins/*.plg";

if ($audit) {
  list($plg,$action) = explode(':',$audit);
  switch ($action) {
    case 'remove' : return;
    case 'install':
    case 'update' : $plugins = "/var/log/plugins/$plg.plg"; break;
  }
}

foreach (glob($plugins,GLOB_NOSORT) as $plugin_link) {
//only consider symlinks
  $plugin_file = @readlink($plugin_link);
  if ($plugin_file === false) continue;
//plugin name
  $name = plugin('name',$plugin_file) ?: basename($plugin_file,".plg");
  $custom = in_array($name,$builtin);
//switch between system and custom plugins
  if (($system && !$custom) || (!$system && $custom)) continue;
//forced plugin check?
  $checked = (!$audit && !$check) ? check_plugin(basename($plugin_file)) : true;
//OS update?
  $os = $system && $name==$builtin[0];
  $toggle = false;
//toggle stable/next release?
  if ($os && $branch) {
    $toggle = plugin('version',$plugin_file);
    $tmp_plg = "$name-.plg";
    $tmp_file = "/var/tmp/$name.plg";
    copy($plugin_file,$tmp_file);
    exec("sed -ri 's|^(<!ENTITY category).*|\\1 \"{$branch}\">|' $tmp_file");
    symlink($tmp_file,"/var/log/plugins/$tmp_plg");
    if (check_plugin($tmp_plg)) {
      copy("/tmp/plugins/$tmp_plg",$tmp_file);
      $plugin_file = $tmp_file;
    }
  }
//link/icon
  $icon = icon($name);
  if ($launch = plugin('launch',$plugin_file))
    $link = "<a href='/$launch'><img src='/$icon' class='list'></a>";
  else
    $link = "<img src='/$icon' class='list'>";
//description
  $readme = "plugins/{$name}/README.md";
  if (file_exists($readme))
    $desc = Markdown(file_get_contents($readme));
  else
    $desc = Markdown("**{$name}**");
//author
  $author = plugin('author',$plugin_file) ?: "anonymous";
//version
  $version = plugin('version',$plugin_file) ?: "unknown";
  $date = str_replace('.','',$version);
//category
  $category = plugin('category',$plugin_file) ?: (strpos($version,'-')!==false ? 'next' : 'stable');
//status
  $status = 'unknown';
  $changes_file = $plugin_file;
  $url = plugin('pluginURL',$plugin_file);
  if ($url !== false) {
    $filename = "/tmp/plugins/".(($os && $branch) ? $tmp_plg : basename($url));
    if ($checked && file_exists($filename)) {
      if ($toggle && $toggle != $version) {
        $status = make_link('install',$plugin_file,'forced');
      } else {
        $latest = plugin('version',$filename);
        if ($os ? version_compare($latest,$version,'>') : strcmp($latest,$version) > 0) {
          $version .= "<br><span class='red-text'>$latest</span>";
          $status = make_link("update",basename($plugin_file));
          $changes_file = $filename;
        } else {
          //status is considered outdated when older than 1 day
          $status = filectime($filename) > (time()-86400) ? 'up-to-date' : 'need check';
        }
      }
    }
  }
  if (strpos($status,'update')!==false) $rank = '0';
  elseif (strpos($status,'install')!==false) $rank = '1';
  elseif ($status=='need check') $rank = '2';
  elseif ($status=='up-to-date') $rank = '3';
  else $rank = '4';
  $changes = plugin('changes',$changes_file);
  if ($changes !== false) {
    $txtfile = "/tmp/plugins/".basename($plugin_file,'.plg').".txt";
    file_put_contents($txtfile,$changes);
    $version .= "&nbsp;<a href='#' title='View Release Notes' onclick=\"openBox('/plugins/dynamix.plugin.manager/include/ShowChanges.php?file=".urlencode($txtfile)."','Release Notes',600,900); return false\"><span class='fa fa-info-circle big blue-text'></span></a>";
  }
//write plugin information
  $empty = false;
  echo "<tr id=\"".str_replace(['.',' ','_'],'',basename($plugin_file,'.plg'))."\">";
  echo "<td style='vertical-align:top;width:64px'><p style='text-align:center'>$link</p></td>";
  echo "<td><span class='desc_readmore' style='display:block'>$desc</span></td>";
  echo "<td>$author</td>";
  echo "<td data='$date'>$version</td>";
  echo "<td data='$rank'>$status</td>";
  echo "<td>";
  if ($system) {
    if ($os) {
      echo "<select id='change_branch' class='auto' onchange='update_table(this.value)'>";
      echo mk_options($category,'stable');
      echo mk_options($category,'next');
      echo "</select>";
    }
  } else {
    echo make_link('remove',basename($plugin_file));
  }
  echo "</td>";
  echo "</tr>";
//remove temporary symlink
  @unlink("/var/log/plugins/$tmp_plg");
}
if ($empty) echo "<tr><td colspan='6' style='text-align:center;padding-top:12px'><i class='fa fa-check-square-o icon'></i> No plugins installed</td><tr>";
?>
