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
require_once "$docroot/plugins/dynamix/include/TaskQueue.php";

// true when the backend task queue has an active/queued task targeting this .plg
// (the task queue is the source of truth for an in-flight install/update)
function plugin_task_busy($arg) {
  if (!$arg) return false;
  foreach (task_list() as $t) {
    if ($t['type']==='plugins' && in_array($t['status'],['running','aborting','queued'],true)
        && in_array($arg, preg_split('/\s+/', trim($t['cmd'])), true)) return true;
  }
  return false;
}

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

function plugin_delete_roots() {
  return [
    'error' => '/boot/config/plugins-error',
    'stale' => '/boot/config/plugins-stale'
  ];
}

function plugin_delete_secret() {
  static $secret;
  if ($secret !== null) return $secret;
  $path = '/var/local/emhttp/plugins/dynamix.plugin.manager/delete.key';
  $directory = dirname($path);
  if (!is_dir($directory)) @mkdir($directory,0700,true);
  if (!is_file($path)) {
    $candidate = random_bytes(32);
    if (@file_put_contents($path,$candidate,LOCK_EX) !== false) @chmod($path,0600);
  }
  $secret = @file_get_contents($path);
  return is_string($secret) && strlen($secret) >= 32 ? $secret : false;
}

function plugin_delete_token($arg) {
  $real = realpath((string)$arg);
  $base = basename((string)$arg);
  if (!$real || !is_file($real) || is_link((string)$arg) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.plg\z/D',$base)) return '';
  $secret = plugin_delete_secret();
  if ($secret === false) return '';
  foreach (plugin_delete_roots() as $label => $directory) {
    $root = realpath($directory);
    if ($root && dirname($real) === $root) return hash_hmac('sha256',"$label/$base",$secret);
  }
  return '';
}

function plugin_js_string($value) {
  return json_encode((string)$value,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES);
}

function make_link($method, $arg, $extra='') {
  $arg = (string)$arg;
  $plg = basename($arg,'.plg').':'.$method;
  $id = substr(hash('sha256',$plg),0,16);
  $argHtml = htmlspecialchars($arg,ENT_QUOTES,'UTF-8');
  $check = $method=='remove' ? "<input type='checkbox' data='$argHtml' class='remove' onClick='document.getElementById(\"$id\").disabled=!this.checked;multiRemove()'>" : "";
  $disabled = $check ? ' disabled' : '';
  if ($method == 'update' && $extra) {
    $disabled = 'disabled';
    $id = htmlspecialchars($extra,ENT_QUOTES,'UTF-8');
  }
  if ($method == 'delete') {
    $token = plugin_delete_token($arg);
    $onclick = $token
      ? "if(!confirm(".plugin_js_string(_('Delete this plugin?'))."))return false;$.post('/plugins/dynamix.plugin.manager/include/DeletePlugin.php',{id:".plugin_js_string($token).",csrf_token:csrf_token},function(){location.reload();});"
      : 'return false;';
  } else {
    $cmd = 'plugin '.escapeshellarg($method).' '.escapeshellarg($arg).($extra?' '.escapeshellarg($extra):'');
    $onclick = 'openInstall('.plugin_js_string($cmd).','.plugin_js_string(ucwords($method).' Plugin').','.plugin_js_string($plg).',"loadlist")';
  }
  if (in_array($method,['update','install']) && plugin_task_busy($arg)) {
    $label = $method=='install' ? _('Installing') : _('Upgrading');
    return "<span class='orange-text'><i class='fa fa-hourglass-o fa-fw'></i>&nbsp;$label</span>";
  } elseif (is_file("/tmp/plugins/pluginPending/".basename($arg)) && !$check) {
    return "<span class='orange-text'><i class='fa fa-hourglass-o fa-fw'></i>&nbsp;"._('pending')."</span>";
  } else {
    $label = htmlspecialchars(_(ucfirst($method)),ENT_QUOTES,'UTF-8');
    return "$check<input type='button' id='$id' data='$argHtml' class='".htmlspecialchars($method,ENT_QUOTES,'UTF-8')."' value=\"$label\" onclick=\"".htmlspecialchars($onclick,ENT_QUOTES,'UTF-8')."\"$disabled>";
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
