<?PHP
/* Copyright 2005-2022, Lime Technology
 * Copyright 2012-2022, Bergware International.
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
require_once "$docroot/webGui/include/ColorCoding.php";

array_multisort(array_map('filemtime',($logs = glob($_POST['log'].'*',GLOB_NOSORT))),SORT_ASC,$logs);
$sum = array_sum(array_map(function($log){return count(file($log));},$logs));
$max = $_POST['max'];
$row = 0;

foreach ($logs as $log) {
  $fh = fopen($log,'r');
  while (($line = fgets($fh)) !== false) {
    if ($max > 0 && $max < $sum - $row++) continue;
    $span = '<span class="text">';
    foreach ($match as $type) foreach ($type['text'] as $text) if (preg_match("/$text/i",$line)) {
      $span = '<span class="'.$type['class'].'">';
      break 2;
    }
    echo $span,htmlspecialchars($line),"</span>";
  }
  fclose($fh);
}
?>
