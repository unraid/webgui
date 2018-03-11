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

// Add the Docker JSON client
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

$DockerClient = new DockerClient();
$DockerTemplates = new DockerTemplates();

$all_containers = $DockerClient->getDockerContainers();
if (!$all_containers) {
  echo "<tr><td colspan='8' style='text-align:center;padding-top:12px'>No Docker containers installed</td></tr>";
  return;
}

// Read network settings
extract(parse_ini_file('state/network.ini',true));

// Read container info
$all = $DockerTemplates->getAllInfo();
$menu = [];
$docker = ['var docker=[];'];

foreach ($all_containers as $ct) {
  $name = $ct['Name'];
  $info = &$all[$name];
  $mode = $ct['NetworkMode'];
  $id = $ct['Id'];
  $imageID = $ct['ImageId'];
  $is_autostart = $info['autostart'] ? 'true':'false';
  $updateStatus = $info['updated']=='true'||$info['updated']=='undef' ? 'true':'false';
  $running = $ct['Running'] ? 'true':'false';
  $template = $info['template'];
  $webGui = html_entity_decode($info['url']);
  $support = html_entity_decode($info['Support']);
  $project = html_entity_decode($info['Project']);
  $menu[] = sprintf("addDockerContainerContext('%s','%s','%s',%s,%s,%s,'%s','%s','%s','%s');",addslashes($name),addslashes($imageID),addslashes($template),$running,$updateStatus,$is_autostart,addslashes($webGui),$id,addslashes($support),addslashes($project));
  $docker[] = "docker.push({name:'$name',id:'$id',state:'$running',update:'$updateStatus'});";
  $shape = $ct['Running'] ? 'play':'square';
  $status = $ct['Running'] ? 'started':'stopped';
  $icon = $info['icon'] ?: '/plugins/dynamix.docker.manager/images/question.png';
  $ports = [];
  if ($mode=='bridge') {
    $binds = explode('|',exec("docker inspect --format='{{range \$p,\$c := .HostConfig.PortBindings}}{{\$p}}:{{(index \$c 0).HostPort}}|{{end}}' $name 2>/dev/null"));
    $ip = exec("docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $name 2>/dev/null");
    foreach ($binds as $bind) {
      if (!$bind) continue;
      list($container_port,$host_port) = explode(':',$bind);
      $ports[] = sprintf('%s:%s<i class="fa fa-arrows-h" style="margin:0 6px"></i>%s:%s',$ip, $container_port, $eth0['IPADDR:0'], $host_port);
    }
  } else {
    $binds = explode('|',exec("docker inspect --format='{{range \$p,\$c := .Config.ExposedPorts}}{{\$p}}|{{end}}' $name 2>/dev/null"));
    $ip = exec("docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $name 2>/dev/null") ?: $eth0['IPADDR:0'];
    foreach ($binds as $bind) {
      if (!$bind) continue;
      $ports[] = sprintf('%s:%s<i class="fa fa-arrows-h" style="margin:0 6px"></i>%s:%s',$ip, $bind, $ip, str_replace(['/tcp','/udp'],'',$bind));
    }
  }
  $paths = [];
  $mounts = explode('|',exec("docker inspect --format='{{range \$c := .HostConfig.Binds}}{{\$c}}|{{end}}' $name 2>/dev/null"));
  foreach ($mounts as $mount) {
    if (!$mount) continue;
    list($host_path,$container_path,$access_mode) = explode(':',$mount);
    $paths[] = sprintf('%s<i class="fa fa-%s" style="margin:0 6px"></i>%s', htmlspecialchars($container_path), $access_mode=='ro'?'long-arrow-left':'arrows-h', htmlspecialchars($host_path));
  }
  echo "<tr><td style='width:48px;padding:4px'>";
  echo "<div id='$id' style='display:block; cursor:pointer'><div style='position:relative;width:48px;height:48px;margin:0px auto'>";
  echo "<img src='".htmlspecialchars($icon)."' class='".htmlspecialchars($status)."' style='position:absolute;top:0;bottom:0;left:0;right:0;width:48px;height:48px'>";
  echo "<i class='fa iconstatus fa-$shape $status' title='".htmlspecialchars($status)."'></i></div></div>";
  echo "</td><td>";
  if ($template) {
    echo "<a class='exec' onclick=\"editContainer('".addslashes(htmlspecialchars($name))."','".addslashes(htmlspecialchars($template))."')\">".htmlspecialchars($name)."</a>";
  } else {
    echo htmlspecialchars($name);
  }
  echo "<div class='advanced' style='width:160px'>Container ID: ".htmlspecialchars($id)."</div>";
  if ($ct['BaseImage']) echo "<div class='advanced' style='width:160px;'><i class='fa fa-cubes' style='margin-right:5px'></i>".htmlspecialchars(${ct['BaseImage']})."</div>"; 
  echo "<div class='advanced' style='width:160px'>By:";
  $registry = $info['registry'];
  if ($registry) {
    echo "<a href='".htmlspecialchars($registry)."' target='_blank'>".htmlspecialchars($ct['Image'])."</a>";
  } else {
    echo htmlspecialchars($ct['Image']);
  }
  echo "</div></td><td class='updatecolumn'>";
  if ($updateStatus=='false') {
    echo "<a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download'></i> update ready</span></a>";
  } elseif ($updateStatus=='true') {
    echo "<span style='color:#44B012;white-space:nowrap;'><i class='fa fa-check'></i> up-to-date</span>";
    echo "<div class='advanced'><a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download'></i> force update</span></a></div>";
  } else {
    echo "<span style='color:#FF2400;white-space:nowrap;'><i class='fa fa-exclamation-triangle'></i> not available</span>";
    echo "<div class='advanced'><a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download'></i> force update</span></a></div>";
  }
  echo "</td><td>$mode</td>";
  echo "<td style='white-space:nowrap'><span class='docker_readmore'>".implode('<br>',$ports)."</span></td>";
  echo "<td style='word-break:break-all'><span class='docker_readmore'>".implode('<br>',$paths)."</span></td>";
  echo "<td><input type='checkbox' class='autostart' container='".htmlspecialchars($name)."'".($info['autostart'] ? ' checked':'')."></td>";
  echo "<td><a class='log' onclick=\"containerLogs('".addslashes(htmlspecialchars($name))."','$id',false,false)\"><img class='basic' src='/plugins/dynamix/icons/log.png'><div class='advanced' style='width:124px;'>".htmlspecialchars(str_replace('Up','Uptime',$ct['Status']))."</div><div class='advanced'>Created ".htmlspecialchars($ct['Created'])."</div></a></td></tr>";
}
foreach ($DockerClient->getDockerImages() as $image) {
  if (count($image['usedBy'])) continue;
  $menu[] = sprintf("addDockerImageContext('%s','%s');",$image['Id'],implode(', ',$image['Tags']));
  echo "<tr class='advanced'><td style='width:48px;padding:4px'>";
  echo "<div id='context-{$image['Id']}' style='display:block;cursor:pointer'>";
  echo "<div style='position:relative;width:48px;height:48px;margin:0 auto'>";
  echo "<img src='/webGui/images/disk.png' style='position:absolute;opacity:0.3;top:0;bottom:0;left:0;right:0;width:48px;height:48px'>";
  echo "</div></div></td>";
  echo "<td data-sort-value='ZZZZZZZZZZZ'><i>(orphan image)</i><div style='width:160px;'>Image ID: ".htmlspecialchars($image['Id'])."</div>";
  if (strpos(implode($image['Tags']),"&lt;none&gt;:&lt;none&gt;")===false) echo "<div style='width:'160px'>".implode('<br>',array_map('htmlspecialchars',$image['Tags']))."</div>";
  echo "</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
  echo "<td><div class='advanced' style='width:124px'>Created ".htmlspecialchars($image['Created'])."</div></td></tr>";
}
echo "\0".implode($menu).implode($docker);
?>
