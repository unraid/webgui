<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

$user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';

$act = $_POST['action'];
$vms = $lv->get_domains();

if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($vms as $vm) $sort[] = array_search($vm,$prefs) ?? 999;
  array_multisort($sort, ($act=='start'?SORT_ASC:SORT_DESC), SORT_NUMERIC, $vms);
} else {
  array_multisort($vms, ($act=='start'?SORT_ASC:SORT_DESC), SORT_NATURAL|SORT_FLAG_CASE);
}

foreach ($vms as $vm) {
  $res = $lv->get_domain_by_name($vm);
  $uuid = $lv->domain_get_uuid($res);
  $domName = $lv->domain_get_name_by_uuid($uuid);
  $dom = $lv->domain_get_info($res);
  $state = $lv->domain_state_translate($dom['state']);
  switch ($act) {
  case 'stop':
    if ($state!='running') continue;
    $result = $lv->domain_shutdown($domName) ? ['success'=>true, 'state'=>$lv->domain_get_state($domName)] : ['error'=>$lv->get_last_error()];
    $n = 20; // wait for VM to die
    while ($result['success'] && $lv->domain_get_state($domName)=='running') {sleep(1); if(!--$n) break;}
    break;
  case 'start':
    if ($state=='running') continue;
    $result = $lv->domain_start($domName) ? ['success'=>true, 'state'=>$lv->domain_get_state($domName)] : ['error'=>$lv->get_last_error()];
    break;
  }
}
?>
