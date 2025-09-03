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
require_once "$docroot/webGui/include/Wrappers.php";

// Invoke the plugin command with indicated method
function plugin($method, $arg = '', $dontCache = false) {
  global $docroot,$pluginCache;
  
  static $allMethods = ['dump', 'changes', 'alert', 'validate', 'check', 'checkall', 'update', 'remove', 'install', 'attributes'];

  if ( ! is_file("/tmp/plugins/pluginAttributesCache") ) {
    $pluginCache = [];
  }

  if ( ! $dontCache ) {
    if ( empty($pluginCache) && file_exists("/tmp/plugins/pluginAttributesCache") ) {
      $pluginCache = @unserialize(file_get_contents("/tmp/plugins/pluginAttributesCache"))??[];
    }

    if (!in_array($method, $allMethods)) {
      if ( $arg ) {
        if ( !isset($pluginCache[$arg]) ) {
          $pluginCache[$arg] = json_decode(plugin('attributes', $arg), true)??false;
          file_put_contents_atomic("/tmp/plugins/pluginAttributesCache", serialize($pluginCache));
        }

        // return the cached result if it exists.  If it doesn't return false;;
        if ( isset($pluginCache[$arg][$method]) ) {
          return $pluginCache[$arg][$method];
        } else {
          $pluginCache[$arg][$method] = false;
          return false;
        }
      }  
    }
  }

  exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin ".escapeshellarg($method)." ".escapeshellarg($arg), $output, $retval);
  return $retval==0 ? implode("\n", $output) : false;
}

// Invoke the language command with indicated method
function language($method, $arg = '') {
  global $docroot;
  exec("$docroot/plugins/dynamix.plugin.manager/scripts/language ".escapeshellarg($method)." ".escapeshellarg($arg), $output, $retval);
  return $retval==0 ? implode("\n", $output) : false;
}

function check_plugin($arg, &$ncsi) {
// Get network connection status indicator (NCSI)
  if ($ncsi===null) $ncsi = check_network_connectivity();
  return $ncsi ? plugin('check',$arg) : false;
}

function make_link($method, $arg, $extra='') {
  $plg = basename($arg,'.plg').':'.$method;
  $id = str_replace(['.',' ','_'],'',$plg);
  $check = $method=='remove' ? "<input type='checkbox' data='$arg' class='remove' onClick='document.getElementById(\"$id\").disabled=!this.checked;multiRemove()'>" : "";
  $disabled = $check ? ' disabled' : '';
  if ($method == 'update' && $extra) {
    $disabled = 'disabled';
    $id = $extra;
  }
  if ($method == 'delete') {
    $cmd  = "plugin_rm $arg";
    $func = "refresh";
    $plg  = "";
  } else {
    $cmd  = "plugin $method $arg".($extra?" $extra":"");
    $func = "loadlist";
  }
  if (is_file("/tmp/plugins/pluginPending/$arg") && !$check) {
    return "<span class='orange-text'><i class='fa fa-hourglass-o fa-fw'></i>&nbsp;"._('pending')."</span>";
  } else {
    return "$check<input type='button' id='$id' data='$arg' class='$method' value=\""._(ucfirst($method))."\" onclick='openInstall(\"$cmd\",\""._(ucwords($method)." Plugin")."\",\"$plg\",\"$func\");'$disabled>";
  }
}

// trying our best to find an icon
function icon($name) {
// this should be the default location and name
  $icon = "plugins/$name/images/$name.png";
  if (file_exists($icon)) return $icon;
// try alternatives if default is not present
  $icon = "plugins/$name/$name.png";
  if (file_exists($icon)) return $icon;
  $image = @preg_split('/[\._- ]/',$name)[0];
  $icon = "plugins/$name/images/$image.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/$name/$image.png";
  if (file_exists($icon)) return $icon;
// last resort - default plugin icon
  return "webGui/images/plg.png";
}
function mk_options($select,$value) {
  return "<option value='$value'".($select==$value?" selected":"").">"._(ucfirst($value))."</option>";
}

// Drop the cache for any plugins with the same basename
function dropPluginCache($name) {
  global $pluginCache;


  $pluginName = basename($name);
  $cached = @unserialize(@file_get_contents("/tmp/plugins/pluginAttributesCache"));
  if ( ! $cached ) {
    $cached = [];
  }
  $pluginCached = array_filter($cached, function($key) use ($pluginName) {
    if ( str_contains($key,$pluginName) ) {
      return false;
    }
    return true;
  },ARRAY_FILTER_USE_KEY);
  file_put_contents_atomic("/tmp/plugins/pluginAttributesCache", serialize($pluginCached));
}

?>
