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
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginOperationLock.php";

// add translations
$_SERVER['REQUEST_URI'] = 'plugins';
require_once "$docroot/webGui/include/Translations.php";

$system  = unscript(_var($_GET,'system'));
$branch  = unscript(_var($_GET,'branch'));
$audit   = unscript(_var($_GET,'audit'));
$check   = unscript(_var($_GET,'check'));
$cmd     = unscript(_var($_GET,'cmd'));
$init    = unscript(_var($_GET,'init'));
$empty   = true;
$install = false;
$updates = 0;
$alerts  = '/tmp/plugins/my_alerts.txt';
$alert_contents = '';
$builtin = ['unRAIDServer'];
$plugins = "/var/log/plugins/*.plg";
$ncsi    = null; // network connection status indicator
$Unraid  = parse_ini_file("/etc/unraid-version");

if ($cmd=='alert') {
  // signal alert message yer or no
  echo is_file($alerts) ? 1 : 0;
  die();
}

if ($cmd=='pending') {
  // prepare pending status for multi operations
  try {
    plugin_manager_with_operation_lock(function(): void {
      $pending = '/tmp/plugins/pluginPending';
      plugin_manager_prepare_shared_artifact_directory($pending);
      foreach (explode('*',_var($_GET,'plugin')) as $plugin) {
        $name = basename($plugin);
        if ($name === '' || $name !== $plugin) continue;
        if (!plugin_manager_write_shared_artifact("$pending/$name", 'multi', 0600)) {
          throw new RuntimeException('Unable to publish pending plugin status');
        }
      }
    });
  } catch (Throwable) {
    http_response_code(503);
  }
  die();
}

if ($audit) {
  [$plg,$action] = my_explode(':',$audit);
  switch ($action) {
    case 'return' : $check = true; break;
    case 'remove' : $plugins = '/var/log/plugins/.plugin-manager-none'; break;
    case 'install': $install = true;
    case 'update' : $plugins = "/var/log/plugins/$plg.plg"; break;
  }
}

foreach (glob($plugins,GLOB_NOSORT) as $plugin_link) {
  //only consider symlinks
  $plugin_file = @readlink($plugin_link);
  if ($plugin_file === false) continue;
  //plugin name
  $name = plugin('name',$plugin_file) ?: basename($plugin_file,".plg");
  $user = in_array($name,$builtin);
  //switch between system and user plugins
  if (($system && !$user) || (!$system && $user)) continue;
  //OS update?
  $os = $system && $name==$builtin[0];
  if ($init || $install) {
    //icon + link
    $launch = plugin('launch',$plugin_file);
    if ($icon = plugin('icon',$plugin_file)) {
      if (substr($icon,-4)=='.png') {
        if (file_exists("plugins/$name/images/$icon")) {
          $icon = "plugins/$name/images/$icon";
        } elseif (file_exists("plugins/$name/$icon")) {
          $icon = "plugins/$name/$icon";
        } else {
          $icon = "plugins/dynamix.plugin.manager/images/dynamix.plugin.manager.png";
        }
        $icon = "<img src='/$icon' class='list'>";
      } elseif (substr($icon,0,5)=='icon-') {
        $icon = "<i class='$icon list'></i>";
      } else {
        if (substr($icon,0,3)!='fa-') $icon = "fa-$icon";
        $icon = "<i class='fa $icon list'></i>";
      }
      $link = $launch ? "<a href='/$launch' class='list'>$icon</a>" : $icon;
    } else {
      $icon = icon($name);
      $link = $launch ? "<a href='/$launch' class='list'><img src='/$icon' class='list'></a>" : "<img src='/$icon' class='list'>";
    }
    //description
    $readme = "plugins/{$name}/README.md";
    $desc = file_exists($readme) ? Markdown(file_get_contents($readme)) : Markdown("**{$name}**");
    //support
    $support = plugin('support',$plugin_file) ?: "";
    $support = $support ? "<a href='$support' target='_blank'>"._('Support Thread')."</a>" : "";
    //author
    $author = plugin('author',$plugin_file) ?: _('anonymous');
    //version
    $version = plugin('version',$plugin_file) ?: _('unknown');
    $date = str_replace('.','',$version);
    //category
    $category = plugin('category',$plugin_file) ?: (strpos($version,'-')!==false ? 'next' : 'stable');
    //status
    $status = $check ? _('unknown') : _('checking').'...';
    $id = str_replace('.','-',$name);
    $empty = false;
    echo "<tr id=\"".str_replace(['.',' ','_'],'',basename($plugin_file,'.plg'))."\">";
    echo "<td>$link</td>";
    echo "<td><span class='desc_readmore' style='display:block'>$desc</span> $support</td>";
    echo "<td>$author</td>";
    echo "<td id='vid-$id' data='$date'>$version&nbsp;<span class='fa fa-info-circle fa-fw big blue-text'></span></td>";
    echo "<td id='sid-$id' data='0'><span style='color:#267CA8'><i class='fa fa-refresh fa-spin fa-fw'></i>&nbsp;$status</span></td>";
    echo "<td>";
    if ($os) {
      $regular = ['stable','next'];
      echo "<select id='change_branch' class='auto' onchange='update_table(this.value)'>";
      foreach ($regular as $choice) echo mk_options($category,$choice);
      if (!in_array($category,$regular)) echo mk_options($category,$category);
      echo "</select>";
    } else {
      echo make_link('remove',basename($plugin_file));
    }
    echo "</td>";
    echo "</tr>";
  } else {
    //forced plugin check?
    $checked = (!$audit && !$check) ? check_plugin(basename($plugin_file),$ncsi) : true;
    $past = false;
    //toggle stable/next release?
    if ($os && $branch) {
      $branch_result = plugin_branch_check($plugin_file, $branch);
      if (is_array($branch_result)) {
        $past = $branch_result['past'];
        $plugin_file = $branch_result['path'];
        $checked = true;
      } else {
        $checked = false;
      }
    }
    //version
    $version = plugin('version',$plugin_file) ?: _('unknown');
    $date = str_replace('.','',$version);
    //status
    $status = "<span class='orange-text'><i class='fa fa-unlink fa-fw'></i>&nbsp;"._('not available')."</span>";
    //compare
    $changes_file = $plugin_file;
    $url = plugin('pluginURL',$plugin_file);
    if ($url !== false) {
      $filename = ($os && $branch)
        ? $plugin_file
        : "/tmp/plugins/".basename($url);
      if ($checked && file_exists($filename)) {
        if ($past && $past != $version) {
          $status = make_link('install',$plugin_file,'forced');
        } else {
          $latest = plugin('version',$filename);
          if ($os ? version_compare($latest,$version,'>') : strcmp($latest,$version) > 0) {
            if ($os) {
              $version = "<small>"._('I have read the release notes')."</small><input type='checkbox' onclick=\"$('#cmdUpdate').prop('disabled',!this.checked)\"><br><span class='red-text'>$latest</span>";
            } else {
              $version .= "<br><span class='red-text'>$latest</span>";
            }
            $error = null;
            if (!$os && (version_compare(plugin("min",$filename,$error) ?: "1.0",$Unraid['version'],">") || version_compare(plugin("max",$filename,$error) ?: "999.9.9",$Unraid['version'],"<"))) {
              $status = "<span class='warning'><i class='fa fa-exclamation-triangle' aria-hidden='true'></i> "._("Update Incompatible")."</span>";
            } else {
              $status = make_link("update",basename($plugin_file),$os?'cmdUpdate':'');
            }
            $changes_file = $filename;
            if (!$os) $updates++;
          } else {
            //status is considered outdated when older than 1 day
            if (file_exists($filename)) {
              $status = filectime($filename) > (time()-86400) ? "<span class='green-text'><i class='fa fa-check fa-fw'></i>&nbsp;"._('up-to-date')."</span>" : "<span class='orange-text'><i class='fa fa-flash fa-fw'></i>&nbsp;"._('need check')."</span>";
            } else {
              $status = "<span class='red-text'><i class='fa fa-exclamation-triangle fa-fw'></i>&nbsp;"._('cannot check')."</span>";
            }
          }
        }
      }
    }
    if (strpos($status,'update')!==false) $rank = '0';
    elseif (strpos($status,'install')!==false) $rank = '1';
    elseif ($status=='need check') $rank = '2';
    elseif ($status=='up-to-date') $rank = '3';
    else $rank = '4';
    if (($changes = plugin('changes',$changes_file)) !== false) {
      try {
        $txtfile =
          plugin_manager_private_download_directory().
          '/release-notes-'.hash('sha256', $changes).'.txt';
        $notes_published = plugin_manager_with_operation_lock(
          static fn() => plugin_manager_write_shared_artifact(
            $txtfile,
            $changes
          )
        );
      } catch (Throwable) {
        $notes_published = false;
      }
      if ($notes_published) {
        $version .= "&nbsp;<span class='fa fa-info-circle fa-fw big blue-text' title='"._('View Release Notes')."' onclick=\"openChanges('showchanges $txtfile','"._('Release Notes')."')\"></span>";
      }
    }
    if ($rank < 2 && ($alert = plugin('alert',$changes_file)) !== false) {
      // generate alert message (if existing) when newer version is available
      $alert_contents .= ($os ? "" : "## $name\n\n").$alert."\n\n";
    }
    //write plugin information
    $empty = false;
    $id = str_replace('.','-',$name);
    echo "vid-$id::$date::$version\rsid-$id::$rank::$status\n";
  }
}
try {
  plugin_manager_with_operation_lock(
    static function() use ($alerts, $alert_contents): void {
      $published = $alert_contents === ''
        ? plugin_manager_remove_shared_artifact($alerts)
        : plugin_manager_write_shared_artifact($alerts, $alert_contents);
      if (!$published) {
        throw new RuntimeException('Unable to publish plugin alerts');
      }
    }
  );
} catch (Throwable) {
  // A stale alert is safer than a partially-written alert document.
}
if ($empty) echo "<tr><td colspan='6' style='text-align:center;padding-top:12px'><i class='fa fa-check-square-o icon'></i> "._('No plugins installed')."</td><tr>";
if (!$init && !($os??false)) echo "\0".$updates;
?>
