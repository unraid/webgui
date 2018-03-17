<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
require_once "$docroot/webGui/include/Wrappers.php";

$dynamix = parse_plugin_cfg('dynamix',true);
$filter = $_GET['filter'];
$files = glob("{$dynamix['notify']['path']}/archive/*.notify", GLOB_NOSORT);
usort($files, create_function('$a,$b', 'return filemtime($b)-filemtime($a);'));

$row = 1; $empty = true;
foreach ($files as $file) {
  $fields = explode(PHP_EOL, file_get_contents($file));
  if ($filter && $filter != substr($fields[4],11)) continue;
  $empty = false;
  $archive = basename($file);
  if ($extra = count($fields)>6) {
    $td_ = "<td data='*' rowspan='3'><a href='#' onclick='openClose($row)'>"; $_td = "</a></td>";
  } else {
    $td_ = "<td data='*' style='white-space:nowrap'>"; $_td = "</td>";
  }
  $c = 0;
  foreach ($fields as $field) {
    if ($c==5) break;
    $text = $field ? explode('=',$field,2)[1] : "-";
    $tag = ($c<4) ? "" : " data='".str_replace(['alert','warning','normal'],['0','1','2'],$text)."'";
    echo (!$c++) ? "<tr>".str_replace('*',$text,$td_).date($dynamix['notify']['date'].' '.$dynamix['notify']['time'],$text)."$_td" : "<td$tag>$text</td>";
  }
  echo "<td><a href='#' onclick='$.post(\"/webGui/include/DeleteLogFile.php\",{log:\"$archive\"},function(){archiveList();});return false' title='Delete notification'><i class='fa fa-trash-o'></i></a></td></tr>";
  if ($extra) {
    $text = explode('=',$field,2)[1];
    echo "<tr class='tablesorter-childRow row$row'><td colspan='4'>$text</td><td></td></tr><tr class='tablesorter-childRow row$row'><td colspan='5'></td></tr>";
    $row++;
  }
}
if ($empty) echo "<tr><td></td><td colspan='4' style='text-align:center;padding-top:12px'><em>No notifications present</em></td><td></td></tr>";
?>
