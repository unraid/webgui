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
require_once "$docroot/webGui/include/MarkdownExtra.inc.php";
require_once "$docroot/webGui/include/Wrappers.php";

function get_ini_key($key,$default) {
  $x = strpos($key, '[');
  $var = $x>0 ? substr($key,1,$x-1) : substr($key,1);
  global $$var;
  eval("\$var=$key;");
  return $var ?: $default;
}

function get_file_key($file,$default) {
  [$key, $default] = my_explode('=',$default);
  $var = @parse_ini_file($file);
  return $var[$key] ?? $default;
}

function build_pages($pattern) {
  global $site;
  foreach (glob($pattern,GLOB_NOSORT) as $entry) {
    [$header, $content] = my_explode("\n---\n",file_get_contents($entry));
    $page = @parse_ini_string($header);
    if (!$page) {my_logger("Invalid .page format: $entry"); continue;}
    $page['file'] = $entry;
    $page['root'] = dirname($entry);
    $page['name'] = basename($entry, '.page');
    $page['text'] = $content;
    $site[$page['name']] = $page;
  }
}

function find_pages($item) {
  global $docroot,$site,$var,$disks,$devs,$users,$shares,$sec,$sec_nfs,$name,$display,$pool_devices;
  $pages = [];
  foreach ($site as $page) {
    if (empty($page['Menu'])) continue;
    $menu = strtok($page['Menu'], ' ');
    switch ($menu[0]) {
      case '$': $menu = get_ini_key($menu,strtok(' ')); break;
      case '/': $menu = get_file_key($menu,strtok(' ')); break;
    }
    while ($menu !== false) {
      [$menu,$rank] = my_explode(':',$menu);
      if ($menu == $item) {
        $enabled = true;
        if (isset($page['Cond'])) eval("\$enabled={$page['Cond']};");
        if ($enabled) $pages["$rank{$page['name']}"] = $page;
        break;
      }
      $menu = strtok(' ');
    }
  }
  ksort($pages,SORT_NATURAL);
  return $pages;
}

function tab_title($title,$path,$tag) {
  global $docroot,$pools;
  $names = implode('|',array_merge(['disk','parity'],$pools));
  if (preg_match("/^($names)/",$title)) {
    $device = strtok($title,' ');
    $title = str_replace($device,_(my_disk($device),3),$title);
  }
  $title = _(parse_text($title));
  if (!$tag || substr($tag,-4)=='.png') {
    $file = "$path/icons/".($tag ?: strtolower(str_replace(' ','',$title)).".png");
    if (file_exists("$docroot/$file")) {
      return "<img src='/$file' class='icon' style='max-width: 18px; max-height: 18px; width: auto; height: auto; object-fit: contain;'>$title";
    } else {
      return "<i class='fa fa-th title'></i>$title";
    }
  } elseif (substr($tag,0,5)=='icon-') {
    return "<i class='$tag title'></i>$title";
  } else {
    if (substr($tag,0,3)!='fa-') $tag = "fa-$tag";
    return "<i class='fa $tag title'></i>$title";
  }
}

// hack to embed function output in a quoted string (e.g., in a page Title)
// see: http://stackoverflow.com/questions/6219972/why-embedding-functions-inside-of-strings-is-different-than-variables
function _func($x) {return $x;}
$func = '_func';
?>
