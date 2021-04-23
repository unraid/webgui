<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
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
session_start();
session_write_close();

require_once "$docroot/webGui/include/Markdown.php";

function _($text, $do=-1) {
  // PHP translation function _
  global $language;
  if (!$text) return '';
  switch ($do) {
  case 0: // date translation
    parse_array($language['Months_array'],$months);
    parse_array($language['Days_array'],$days);
    foreach ($months as $word => $that) if (strpos($text,$word)!==false) {$text = str_replace($word,$that,$text); break;}
    foreach ($days as $word => $that) if (strpos($text,$word)!==false) {$text = str_replace($word,$that,$text); break;}
    return $text;
  case 1: // number translation
    parse_array($language['Numbers_array'],$numbers);
    return $numbers[$text] ?? $text;
  case 2: // time translation
    $keys = ['days','hours','minutes','seconds','day','hour','minute','second'];
    foreach ($keys as $key) if (isset($language[$key])) $text = preg_replace("/\b$key\b/",$language[$key],$text);
    return $text;
  case 3: // device translation
    [$p1,$p2] = preg_split('/(?<=[a-z])(?= ?[0-9]+)/i',$text);
    return _($p1).$p2;
  default: // regular translation
    $text = $language[preg_replace(['/\&amp;|[\?\{\}\|\&\~\!\[\]\(\)\/\\:\*^\.\"\']|<.+?\/?>/','/^(null|yes|no|true|false|on|off|none)$/i','/  +/'],['','$1.',' '],$text)] ?? $text;
    return preg_replace(['/\*\*(.+?)\*\*/','/\*(.+?)\*/',"/'/"],['<b>$1</b>','<i>$1</i>','&apos;'],$text);
  }
}
function parse_lang_file($file) {
  // parser for translation files, includes some trickery to handle PHP quirks.
  return array_safe((array)parse_ini_string(preg_replace(['/^(null|yes|no|true|false|on|off|none)=/mi','/^([^>].*?)=(.*)$/m','/^:(.+_(help|plug)):$/m','/^:end$/m'],['$1.=','$1="$2"','_$1="','"'],escapeQuotes(file_get_contents($file)))));
}
function parse_help_file($file) {
  // parser for help text files, includes some trickery to handle PHP quirks.
  return array_help(array_tags((array)parse_ini_string(preg_replace(['/^$/m','/^([^:;].+)$/m','/^:(.+_help(_\d{8})?):$/m','/^:end$/m'],['>','>$1','_$1="','"'],escapeQuotes(file_get_contents($file))))));
}
function parse_text($text) {
  // inline text parser
  return preg_replace_callback('/_\((.+?)\)_/m',function($m){return _($m[1]);},preg_replace(["/^:(.+_help):$/m","/^:(.+_plug):$/m","/^:end$/m"],["<?translate(\"_$1\");?>","<?if (translate(\"_$1\")):?>","<?endif;?>"],$text));
}
function parse_file($file,$markdown=true) {
  // replacement of PHP include function
  return $markdown ? Markdown(parse_text(file_get_contents($file))) : parse_text(file_get_contents($file));
}
function parse_plugin($plugin) {
  // parse and add plugin related translations
  global $docroot,$language,$locale;
  $plugin = strtolower($plugin);
  $text = "$docroot/languages/$locale/$plugin.txt";
  if (file_exists($text)) {
    $store = "$docroot/languages/$locale/$plugin.dot";
    if (!file_exists($store)) file_put_contents($store,serialize(parse_lang_file($text)));
    $language = array_merge($language,unserialize(file_get_contents($store)));
  }
}

// internal helper functions
function parse_array($text,&$array) {
  // multi keyword parser
  parse_str(str_replace([' ',':'],['&','='],$text),$array);
}
function array_safe($array) {
  // remove potential dangerous tags
  return array_filter($array,function($v,$k){
    return strlen($v) && !preg_match('#<(script|iframe)(.*?)>(.+?)</(script|iframe)>|<(link|meta)\s(.+?)/?>#is',html_entity_decode($v));
  },ARRAY_FILTER_USE_BOTH);
}
function array_tags($array) {
  // filter outdated help tags
  return array_filter($array,function($v,$k){
    $tag = end(explode('_',$k));
    return ($tag=='help' ? true : $tag <= $_SESSION['buildDate']) && strlen($v);
  },ARRAY_FILTER_USE_BOTH);
}
function array_help(&$array) {
  // select latest applicable help
  foreach ($array as $key => $val) {
    $tag = explode('_',$key);
    if (end($tag)=='help') continue;
    $array[implode('_',array_slice($tag,0,-1))] = $array[$key];
    unset($array[$key]);
  }
  return $array;
}
function escapeQuotes($text) {
  // escape double quotes
  return str_replace(["\"\n",'"'],["\" \n",'\"'],$text);
}
function translate($key) {
  // replaces multi-line sections
  global $language;
  if ($plug = isset($language[$key])) eval('?>'.Markdown($language[$key]));
  return !$plug;
}

// main
$language = [];
$locale   = $_SESSION['locale'];
$return   = "function _(t){return t;}";
$jscript  = "$docroot/webGui/javascript/translate.en_US.js";
$root     = "$docroot/languages/en_US/helptext.txt";
$help     = "$docroot/languages/en_US/helptext.dot";

if ($locale) {
  $text = "$docroot/languages/$locale/translations.txt";
  if (file_exists($text)) {
    $store = "$docroot/languages/$locale/translations.dot";
    // global translations
    if (!file_exists($store)) file_put_contents($store,serialize(parse_lang_file($text)));
    $language = unserialize(file_get_contents($store));
  }
  if (file_exists("$docroot/languages/$locale/helptext.txt")) {
    $root = "$docroot/languages/$locale/helptext.txt";
    $help = "$docroot/languages/$locale/helptext.dot";
  }
  $jscript = "$docroot/webGui/javascript/translate.$locale.js";
  if (!file_exists($jscript)) {
    // create javascript file with translations
    $source = [];
    $files = glob("$docroot/languages/$locale/javascript*.txt",GLOB_NOSORT);
    foreach ($files as $js) $source = array_merge($source,parse_lang_file($js));
    if (count($source)) {
      $script = ['function _(t){var l=[];'];
      foreach ($source as $key => $value) $script[] = "l[\"$key\"]=\"$value\";";
      $script[] = "return l[t.replace(/\&amp;|[\?\{\}\|\&\~\!\[\]\(\)\/\\:\*^\.\"']|<.+?\/?>/g,'').replace(/  +/g,' ')]||t;}";
      file_put_contents($jscript,implode('',$script));
    } else {
      file_put_contents($jscript,$return);
    }
  }
}
// split URI into translation levels
$uri = array_filter(explode('/',strtolower(strtok($_SERVER['REQUEST_URI'],'?'))));
foreach($uri as $more) {
  $text = "$docroot/languages/$locale/$more.txt";
  if (file_exists($text)) {
    // additional translations
    $store = "$docroot/languages/$locale/$more.dot";
    if (!file_exists($store)) file_put_contents($store,serialize(parse_lang_file($text)));
    $language = array_merge($language,unserialize(file_get_contents($store)));
  }
}
// help text
if ($_SERVER['REQUEST_URI'][0]=='/') {
  if (!file_exists($help)) file_put_contents($help,serialize(parse_help_file($root)));
  $language = array_merge($language,unserialize(file_get_contents($help)));
}
// remove unused variables
unset($return,$jscript,$root,$help,$store,$uri,$more,$text);
?>
