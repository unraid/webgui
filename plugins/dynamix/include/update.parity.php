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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Wrappers.php";

$memory = '/tmp/memory.tmp';
if (isset($_POST['#apply'])) {
  $cron = "";
  if ($_POST['mode']>0) {
    $time  = $_POST['hour'] ?? '* *';
    $dotm  = $_POST['dotm'] ?? '*';
    $month = $_POST['month'] ?? '*';
    $day   = $_POST['day'] ?? '*';
    $write = $_POST['write'] ?? '';
    $term  = '';
    switch ($dotm) {
      case '28-31': 
        $term = '[[ $(date +%e -d +1day) -eq 1 ]] && ';
        break;
      case 'W1'   :
        $dotm = '*';
        $term = '[[ $(date +%e) -le 7 ]] && ';
        break;
      case 'W2'   :
        $dotm = '*';
        $term = '[[ $(date +%e -d -7days) -le 7 ]] && ';
        break;
      case 'W3'   :
        $dotm = '*';
        $term = '[[ $(date +%e -d -14days) -le 7 ]] && ';
        break;
      case 'W4'   : 
        $dotm = '*';
        $term = '[[ $(date +%e -d -21days) -le 7 ]] && ';
        break;
      case 'WL'   : 
        $dotm = '*';
        $term = '[[ $(date +%e -d +7days) -le 7 ]] && ';
        break;
    }
    $cron = "# Generated parity check schedule:\n$time $dotm $month $day $term/usr/local/sbin/mdcmd check $write &> /dev/null || :\n\n";
  }
  parse_cron_cfg("dynamix", "parity-check", $cron);
  @unlink($memory);
} else {
  file_safeput_contents($memory, http_build_query($_POST));
  $save = false;
}
?>
