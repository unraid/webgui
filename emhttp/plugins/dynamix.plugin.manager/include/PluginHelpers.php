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
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginAttributes.php";

// Invoke the plugin command with indicated method
function plugin($method, $arg = '', $dontCache = false) {
  global $docroot;
  
  static $methods = ['dump', 'changes', 'alert', 'validate', 'check', 'checkall', 'update', 'remove', 'install'];
  static $pluginAttributeCache = [];

  if ( in_array($method, $methods) || !$arg || $dontCache ) {
    $pluginAttributeCache = [];
    exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin ".escapeshellarg($method)." ".escapeshellarg($arg), $output, $retval);
    return $retval==0 ? implode("\n", $output) : false;
  }

  if ( !isset($pluginAttributeCache[$arg]) ) {
    $pluginAttributeCache = [];
    $xml = file_exists($arg) ? @simplexml_load_file($arg, NULL, LIBXML_NOCDATA) : false;
    if ( $xml ) {
      $attributes = $xml->attributes();
      $pluginAttributeCache[$arg] = (array)$attributes ?: ["error" => "no attributes present"];
    }
  }
  if ( $method == 'attributes' ) {
    return is_file($arg) ? json_encode($pluginAttributeCache[$arg]['@attributes']) : false;
  }
  return (is_file($arg) && isset($pluginAttributeCache[$arg]['@attributes'][$method]) ) ? (string)$pluginAttributeCache[$arg]['@attributes'][$method] : false;
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

function plugin_branch_check($plugin_file, $branch) {
  global $docroot;
  $command =
    escapeshellarg("$docroot/plugins/dynamix.plugin.manager/scripts/plugin").
    ' branchcheck '.escapeshellarg($plugin_file).' '.escapeshellarg($branch);
  exec("$command 2>/dev/null", $output, $status);
  if ($status !== 0 || !$output) return false;

  $receipt = null;
  foreach (array_reverse($output) as $line) {
    if (str_starts_with($line, '_PLUGIN_BRANCH_RESULT_=')) {
      $receipt = substr($line, strlen('_PLUGIN_BRANCH_RESULT_='));
      break;
    }
  }
  $encoded = is_string($receipt) ? base64_decode($receipt, true) : false;
  if (!is_string($encoded)) return false;
  try {
    $result = json_decode($encoded, true, 8, JSON_THROW_ON_ERROR);
  } catch (Throwable) {
    return false;
  }
  $path = $result['path'] ?? null;
  $past = $result['past'] ?? null;
  $next = $result['next'] ?? null;
  if (!is_string($path)) return false;
  try {
    $artifact_directory = plugin_manager_private_download_directory();
  } catch (Throwable) {
    return false;
  }
  if (
    !plugin_manager_private_artifact_is_safe(
      $path,
      $artifact_directory,
      '/^os-branch-[a-f0-9]{64}\.plg$/D',
      0600
    ) ||
    !is_string($past) ||
    !is_string($next)
  ) {
    return false;
  }
  return ['path' => $path, 'past' => $past, 'next' => $next];
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
?>
