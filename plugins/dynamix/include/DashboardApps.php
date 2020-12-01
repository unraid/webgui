<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2014-2020, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
// add translations
$_SERVER['REQUEST_URI'] = 'dashboard';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
require_once "$docroot/webGui/include/Helpers.php";

$display = $_POST['display'];
$menu = [];

if ($_POST['docker'] && ($display=='icons' || $display=='docker')) {
  $user_prefs = $dockerManPaths['user-prefs'];
  $DockerClient = new DockerClient();
  $DockerTemplates = new DockerTemplates();
  $containers = $DockerClient->getDockerContainers();
  $allInfo = $DockerTemplates->getAllInfo();
  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($containers as $ct) $sort[] = array_search($ct['Name'],$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$containers);
  }
  echo "<tr><td></td><td colspan='4'>";
  foreach ($containers as $ct) {
    $name = $ct['Name'];
    $id = $ct['Id'];
    $info = &$allInfo[$name];
    $running = $info['running'] ? 1:0;
    $paused = $info['paused'] ? 1:0;
    $is_autostart = $info['autostart'] ? 'true':'false';
    $updateStatus = substr($ct['NetworkMode'],-4)==':???' ? 2 : ($info['updated']=='true' ? 0 : ($info['updated']=='false' ? 1 : 3));
    $template = $info['template'];
    $shell = $info['shell'];
    $webGui = html_entity_decode($info['url']);
    $support = html_entity_decode($info['Support']);
    $project = html_entity_decode($info['Project']);
    $registry = html_entity_decode($info['registry']);
    $menu[] = sprintf("addDockerContainerContext('%s','%s','%s',%s,%s,%s,%s,'%s','%s','%s','%s','%s','%s');", addslashes($name), addslashes($ct['ImageId']), addslashes($template), $running, $paused, $updateStatus, $is_autostart, addslashes($webGui), $shell, $id, addslashes($support), addslashes($project), addslashes($registry));
    $shape = $running ? ($paused ? 'pause' : 'play') : 'square';
    $status = $running ? ($paused ? 'paused' : 'started') : 'stopped';
    $color = $status=='started' ? 'green-text' : ($status=='paused' ? 'orange-text' : 'red-text');
    $update = $updateStatus==1 ? 'blue-text' : '';
    $icon = $info['icon'] ?: '/plugins/dynamix.docker.manager/images/question.png';
    $image = substr($icon,-4)=='.png' ? "<img src='$icon?".filemtime("$docroot{$info['icon']}")."' class='img' onerror=this.src='/plugins/dynamix.docker.manager/images/question.png';>" : (substr($icon,0,5)=='icon-' ? "<i class='$icon img'></i>" : "<i class='fa fa-$icon img'></i>");
    echo "<span class='outer solid apps $status'><span id='$id' class='hand'>$image</span><span class='inner'><span class='$update'>$name</span><br><i class='fa fa-$shape $status $color'></i><span class='state'>"._($status)."</span></span></span>";
  }
  $none = count($containers) ? _('No running docker containers') : _('No docker containers defined');
  echo "<span id='no_apps' style='display:none'>$none<br><br></span>";
  echo "</td><td></td></tr>";
}
echo "\0";
if ($_POST['vms'] && ($display=='icons' || $display=='vms')) {
  $user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';
  $vms = $lv->get_domains();
  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($vms as $vm) $sort[] = array_search($vm,$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$vms);
  } else {
    natcasesort($vms);
  }
  echo "<tr><td></td><td colspan='4'>";
  foreach ($vms as $vm) {
    $res = $lv->get_domain_by_name($vm);
    $uuid = libvirt_domain_get_uuid_string($res);
    $dom = $lv->domain_get_info($res);
    $id = $lv->domain_get_id($res);
    $state = $lv->domain_state_translate($dom['state']);
    $vncport = $lv->domain_get_vnc_port($res);
    $vnc = '';
    if ($vncport > 0) {
      $wsport = $lv->domain_get_ws_port($res);
      $vnc = autov('/plugins/dynamix.vm.manager/vnc.html',true).'&autoconnect=true&host='.$_SERVER['HTTP_HOST'].'&port=&path=/wsproxy/'.$wsport.'/';
    } else {
      $vncport = ($vncport < 0) ? "auto" : "";
    }
    $template = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
    if (empty($template)) $template = 'Custom';
    $log = (is_file("/var/log/libvirt/qemu/$vm.log") ? "libvirt/qemu/$vm.log" : '');
    $menu[] = sprintf("addVMContext('%s','%s','%s','%s','%s','%s');", addslashes($vm), addslashes($uuid), addslashes($template), $state, addslashes($vnc), addslashes($log));
    $icon = $lv->domain_get_icon_url($res);
    switch ($state) {
    case 'running':
      $shape = 'play';
      $status = 'started';
      $color = 'green-text';
      break;
    case 'paused':
    case 'pmsuspended':
      $shape = 'pause';
      $status = 'paused';
      $color = 'orange-text';
      break;
    default:
      $shape = 'square';
      $status = 'stopped';
      $color = 'red-text';
      break;
    }
    $image = substr($icon,-4)=='.png' ? "<img src='$icon' class='img'>" : (substr($icon,0,5)=='icon-' ? "<i class='$icon img'></i>" : "<i class='fa fa-$icon img'></i>");
    echo "<span class='outer solid vms $status'><span id='vm-$uuid' class='hand'>$image</span><span class='inner'>$vm<br><i class='fa fa-$shape $status $color'></i><span class='state'>"._($status)."</span></span></span>";
  }
  $none = count($vms) ? _('No running virtual machines') : _('No virtual machines defined');
  echo "<span id='no_vms' style='display:none'>$none<br><br></span>";
  echo "</td><td></td></tr>";
}
echo "\0";
echo implode($menu);
