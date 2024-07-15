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
require_once "$docroot/webGui/include/Secure.php";
require_once "$docroot/webGui/include/Wrappers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'tools';
require_once "$docroot/webGui/include/Translations.php";

$dynamix = parse_plugin_cfg('dynamix',true);
$filter  = unscript($_GET['filter']??false);
$files   = glob("{$dynamix['notify']['path']}/archive/*.notify",GLOB_NOSORT);

usort($files, function($a,$b){return filemtime($b)-filemtime($a);});

$row = 1; $rows = 0;
foreach ($files as $file) {
  $fields = file($file,FILE_IGNORE_NEW_LINES);
  if ($filter && $filter != substr($fields[4],11)) continue;
  $rows++;
  $archive = basename($file);
  if ($extra = count($fields)>5) {
    $td_ = "<td data='*' rowspan='3'><a href='#' onclick='openClose($row)'>"; $_td = "</a></td>";
  } else {
    $td_ = "<td data='*' style='white-space:nowrap'>"; $_td = "</td>";
  }
  $c = 0;
  foreach ($fields as $field) {
    if ($c==5) break;
    $text = $field ? (explode('=',$field,2)[1]??"") : "-";
    $tag = ($c<4) ? "" : " data='".str_replace(['alert','warning','normal'],['0','1','2'],$text)."'";
    echo (!$c++) ? "<tr>".str_replace('*',$text,$td_).date($dynamix['notify']['date'].' '.$dynamix['notify']['time'],$text)."$_td" : "<td$tag>"._($text)."</td>";
  }
  echo "<td><a href='#' onclick='$(this).hide();$.post(\"/webGui/include/DeleteLogFile.php\",{log:\"$archive\"},function(){archiveList();});return false' title=\""._('Delete notification')."\"><i class='fa fa-trash-o'></i></a></td></tr>";
  if ($extra) {
    $text = explode('=',$field,2)[1]??"";
    echo "<tr class='tablesorter-childRow row$row'><td colspan='4'>$text</td><td></td></tr><tr class='tablesorter-childRow row$row'><td colspan='5'></td></tr>";
    $row++;
  }
}
if ($rows==0) echo "<tr><td colspan='6' style='padding-top:12px'><center><em>"._("No notifications present")."</em></center></td></tr>";
echo "\0$rows";
?>
