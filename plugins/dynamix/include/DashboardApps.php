<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2014-2018, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2018, Bergware International.
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
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

$display = $_POST['display'];

if (pgrep('dockerd')!==false && ($display=='icons' || $display=='docker')) {
  $DockerClient    = new DockerClient();
  $DockerTemplates = new DockerTemplates();
  $containers      = $DockerClient->getDockerContainers() ?: [];
  ksort($containers,SORT_NATURAL);
  $Allinfo = $DockerTemplates->getAllInfo();
  $menu = [];
  foreach ($containers as $ct) {
    $name = $ct['Name'];
    $info = &$Allinfo[$name];
    $id = $ct['Id'];
    $imageID = $ct['ImageId'];
    $is_autostart = $info['autostart'] ? 'true':'false';
    $updateStatus = $info['updated']=='true'||$info[$name]['updated']=='undef' ? 'true':'false';
    $running = $ct['Running'] ? 'true':'false';
    $template = $info[$name]['template'];
    $webGui = html_entity_decode($info['url']);
    $support = html_entity_decode($info['Support']);
    $project = html_entity_decode($info['Project']);
    $menu[] = sprintf("addDockerContainerContext('%s','%s','%s',%s,%s,%s,'%s','%s','%s','%s');",addslashes($name),addslashes($imageID),addslashes($template),$running,$updateStatus,$is_autostart,addslashes($webGui),$id,addslashes($support),addslashes($project));
    $shape = $ct['Running'] ? 'play':'square';
    $status = $ct['Running'] ? 'started':'stopped';
    $icon = $info['icon'] ?: '/plugins/dynamix.docker.manager/images/question.png';
    echo "<div class='Panel $status'>";
    echo "<div id='$id' style='display:block; cursor:pointer'>";
    echo "<div style='position:relative;width:48px;height:48px;margin:0px auto;'>";
    echo "<img src='$icon' class='$status' style='position:absolute;top:0;bottom:0;left:0;right:0;width:48px;height:48px;'><i class='fa iconstatus fa-$shape $status' title='$status'></i></div></div>";
    echo "<div class='PanelText'><span class='PanelText ".($updateStatus=='false'?'update':$status)."'>$name</span></div></div>";
  }
}

if (pgrep('libvirtd')!==false && ($display=='icons' || $display=='vms')) {
  $txt = '/boot/config/plugins/dynamix.vm.manager/userprefs.txt';
  $vms = $lv->get_domains();
  if (file_exists($txt)) {
    $prefs = parse_ini_file($txt); $sort = [];
    foreach ($vms as $vm) $sort[] = $prefs[$vm] ?? 999;
    array_multisort($sort,SORT_NUMERIC,$vms);
  } else {
    natsort($vms);
  }
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
      $vnc = '/plugins/dynamix.vm.manager/vnc.html?autoconnect=true&host='.$_SERVER['HTTP_HOST'].'&port=&path=/wsproxy/'.$wsport.'/';
    } else {
      $vncport = ($vncport < 0) ? "auto" : "";
    }
    $template = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
    if (empty($template)) $template = 'Custom';
    $log = (is_file("/var/log/libvirt/qemu/$vm.log") ? "libvirt/qemu/$vm.log" : '');
    $menu[] = sprintf("addVMContext('%s','%s','%s','%s','%s','%s');",addslashes($vm),addslashes($uuid),addslashes($template),$state,addslashes($vnc),addslashes($log));
    $vmicon = $lv->domain_get_icon_url($res);
    echo renderVMContentIcon($uuid, $vm, $vmicon, $state);
  }
}
echo "\0".implode($menu);
