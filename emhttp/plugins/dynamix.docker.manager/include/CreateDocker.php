<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2014-2021, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

$var = parse_ini_file('state/var.ini');
ignore_user_abort(true);

$DockerClient = new DockerClient();
$DockerUpdate = new DockerUpdate();
$DockerTemplates = new DockerTemplates();

#   ███████╗██╗   ██╗███╗   ██╗ ██████╗████████╗██╗ ██████╗ ███╗   ██╗███████╗
#   ██╔════╝██║   ██║████╗  ██║██╔════╝╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
#   █████╗  ██║   ██║██╔██╗ ██║██║        ██║   ██║██║   ██║██╔██╗ ██║███████╗
#   ██╔══╝  ██║   ██║██║╚██╗██║██║        ██║   ██║██║   ██║██║╚██╗██║╚════██║
#   ██║     ╚██████╔╝██║ ╚████║╚██████╗   ██║   ██║╚██████╔╝██║ ╚████║███████║
#   ╚═╝      ╚═════╝ ╚═╝  ╚═══╝ ╚═════╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

$custom = DockerUtil::custom();
$subnet = DockerUtil::network($custom);
$cpus   = DockerUtil::cpus();

function cpu_pinning() {
  global $xml,$cpus;
  $vcpu = explode(',',_var($xml,'CPUset'));
  $total = count($cpus);
  $loop = floor(($total-1)/16)+1;
  for ($c = 0; $c < $loop; $c++) {
    $row1 = $row2 = [];
    $max = ($c == $loop-1 ? ($total%16?:16) : 16);
    for ($n = 0; $n < $max; $n++) {
      unset($cpu1,$cpu2);
      [$cpu1, $cpu2] = my_preg_split('/[,-]/',$cpus[$c*16+$n]);
      $check1 = in_array($cpu1, $vcpu) ? ' checked':'';
      $check2 = $cpu2 ? (in_array($cpu2, $vcpu) ? ' checked':''):'';
      $row1[] = "<label id='cpu$cpu1' class='checkbox'>$cpu1<input type='checkbox' id='box$cpu1'$check1><span class='checkmark'></span></label>";
      if ($cpu2) $row2[] = "<label id='cpu$cpu2' class='checkbox'>$cpu2<input type='checkbox' id='box$cpu2'$check2><span class='checkmark'></span></label>";
    }
    if ($c) echo '<hr>';
    echo "<span class='cpu'>"._('CPU').":</span>".implode($row1);
    if ($row2) echo "<br><span class='cpu'>"._('HT').":</span>".implode($row2);
  }
}

#    ██████╗ ██████╗ ██████╗ ███████╗
#   ██╔════╝██╔═══██╗██╔══██╗██╔════╝
#   ██║     ██║   ██║██║  ██║█████╗
#   ██║     ██║   ██║██║  ██║██╔══╝
#   ╚██████╗╚██████╔╝██████╔╝███████╗
#    ╚═════╝ ╚═════╝ ╚═════╝ ╚══════╝

##########################
##   CREATE CONTAINER   ##
##########################

if (isset($_POST['contName'])) {
  $postXML = postToXML($_POST, true);
  $dry_run = isset($_POST['dryRun']) && $_POST['dryRun']=='true';
  $existing = _var($_POST,'existingContainer',false);
  $create_paths = $dry_run ? false : true;
  // Get the command line
  [$cmd, $Name, $Repository] = xmlToCommand($postXML, $create_paths);
  readfile("$docroot/plugins/dynamix.docker.manager/log.htm");
  @flush();
  // Saving the generated configuration file.
  $userTmplDir = $dockerManPaths['templates-user'];
  if (!is_dir($userTmplDir)) mkdir($userTmplDir, 0777, true);
  if ($Name) {
    $filename = sprintf('%s/my-%s.xml', $userTmplDir, $Name);
    if (is_file($filename)) {
      $oldXML = simplexml_load_file($filename);
      if ($oldXML->Icon != $_POST['contIcon']) {
        if (!strpos($Repository,":")) $Repository .= ":latest";
        $iconPath = $DockerTemplates->getIcon($Repository,$Name);
        @unlink("$docroot/$iconPath");
        @unlink("{$dockerManPaths['images']}/".basename($iconPath));
      }
    }
    file_put_contents($filename, $postXML);
  }
  // Run dry
  if ($dry_run) {
    echo "<h2>XML</h2>";
    echo "<pre>".htmlspecialchars($postXML)."</pre>";
    echo "<h2>COMMAND:</h2>";
    echo "<pre>".htmlspecialchars($cmd)."</pre>";
    echo "<div style='text-align:center'><button type='button' onclick='window.location=window.location.pathname+window.location.hash+\"?xmlTemplate=edit:$filename\"'>"._('Back')."</button>";
    echo "<button type='button' onclick='done()'>"._('Done')."</button></div><br>";
    goto END;
  }
  // Will only pull image if it's absent
  if (!$DockerClient->doesImageExist($Repository)) {
    // Pull image
    if (!pullImage($Name, $Repository)) {
      echo '<div style="text-align:center"><button type="button" onclick="done()">'._('Done').'</button></div><br>';
      goto END;
    }
  }
  $startContainer = true;
  // Remove existing container
  if ($DockerClient->doesContainerExist($Name)) {
    // attempt graceful stop of container first
    $oldContainerInfo = $DockerClient->getContainerDetails($Name);
    if (!empty($oldContainerInfo) && !empty($oldContainerInfo['State']) && !empty($oldContainerInfo['State']['Running'])) {
      // attempt graceful stop of container first
      stopContainer($Name);
    }
    // force kill container if still running after 10 seconds
    removeContainer($Name);
  }
  // Remove old container if renamed
  if ($existing && $DockerClient->doesContainerExist($existing)) {
    // determine if the container is still running
    $oldContainerInfo = $DockerClient->getContainerDetails($existing);
    if (!empty($oldContainerInfo) && !empty($oldContainerInfo['State']) && !empty($oldContainerInfo['State']['Running'])) {
      // attempt graceful stop of container first
      stopContainer($existing);
    } else {
      // old container was stopped already, ensure newly created container doesn't start up automatically
      $startContainer = false;
    }
    // force kill container if still running after 10 seconds
    removeContainer($existing,1);
    // remove old template
    if (strtolower($filename) != strtolower("$userTmplDir/my-$existing.xml")) {
      @unlink("$userTmplDir/my-$existing.xml");
    }
  }
  // Extract real Entrypoint and Cmd from container for Tailscale
  if (isset($_POST['contTailscale']) && $_POST['contTailscale'] == 'on') {
    // Create preliminary base container but don't run it
    exec("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/docker create --name '" . escapeshellarg($Name) . "' '" . escapeshellarg($Repository) . "'");
    // Get Entrypoint and Cmd from docker inspect
    $containerInfo = $DockerClient->getContainerDetails($Name);
    $ts_env  = isset($containerInfo['Config']['Entrypoint']) ? '-e ORG_ENTRYPOINT="' . implode(' ', $containerInfo['Config']['Entrypoint']) . '" ' : '';
    $ts_env .= isset($containerInfo['Config']['Cmd']) ? '-e ORG_CMD="' . implode(' ', $containerInfo['Config']['Cmd']) . '" ' : '';
    // Insert Entrypoint and Cmd to docker command
    $cmd = str_replace('-l net.unraid.docker.managed=dockerman', $ts_env . '-l net.unraid.docker.managed=dockerman' , $cmd);
    // Remove preliminary container
    exec("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/docker rm '" . escapeshellarg($Name) . "'");
  }
  if ($startContainer) $cmd = str_replace('/docker create ', '/docker run -d ', $cmd);
  execCommand($cmd);
  if ($startContainer) addRoute($Name); // add route for remote WireGuard access

  echo '<div style="text-align:center"><button type="button" onclick="openTerminal(\'docker\',\''.addslashes($Name).'\',\'.log\')">'._('View Container Log').'</button> <button type="button" onclick="done()">'._('Done').'</button></div><br>';
  goto END;
}

##########################
##   UPDATE CONTAINER   ##
##########################

if (isset($_GET['updateContainer'])){
  $echo = empty($_GET['mute']);
  if ($echo) {
    readfile("$docroot/plugins/dynamix.docker.manager/log.htm");
    @flush();
  }
  foreach ($_GET['ct'] as $value) {
    $tmpl = $DockerTemplates->getUserTemplate(unscript(urldecode($value)));
    if ($echo && !$tmpl) {
      echo "<script>addLog('<p>"._('Configuration not found').". "._('Was this container created using this plugin')."?</p>');</script>";
      @flush();
      continue;
    }
    $xml = file_get_contents($tmpl);
    [$cmd, $Name, $Repository] = xmlToCommand($tmpl);
    $Registry = getXmlVal($xml, "Registry");
    $ExtraParams = getXmlVal($xml, "ExtraParams");
    $Network = getXmlVal($xml, "Network");
    $TS_Enabled = getXmlVal($xml, "TailscaleEnabled");
    $oldImageID = $DockerClient->getImageID($Repository);
    // pull image
    if ($echo && !pullImage($Name, $Repository)) continue;
    $oldContainerInfo = $DockerClient->getContainerDetails($Name);
    // determine if the container is still running
    $startContainer = false;
    if (!empty($oldContainerInfo) && !empty($oldContainerInfo['State']) && !empty($oldContainerInfo['State']['Running'])) {
      // since container was already running, put it back it to a running state after update
      $cmd = str_replace('/docker create ', '/docker run -d ', $cmd);
      $startContainer = true;
      // attempt graceful stop of container first
      stopContainer($Name, false, $echo);
    }
    // check if network from another container is specified in xml (Network & ExtraParams)
    if (preg_match('/^container:(.*)/', $Network)) {
      $Net_Container = str_replace("container:", "", $Network);
    } else {
      preg_match("/--(net|network)=container:[^\s]+/", $ExtraParams, $NetworkParam);
      if (!empty($NetworkParam[0])) {
        $Net_Container = explode(':', $NetworkParam[0])[1];
        $Net_Container = str_replace(['"', "'"], '', $Net_Container);
      }
    }
    // check if the container still exists from which the network should be used, if it doesn't exist any more recreate container with network none and don't start it
    if (!empty($Net_Container)) {
      $Net_Container_ID = $DockerClient->getContainerID($Net_Container);
      if (empty($Net_Container_ID)) {
        $cmd = str_replace('/docker run -d ', '/docker create ', $cmd);
        $cmd = preg_replace("/--(net|network)=(['\"]?)container:[^'\"]+\\2/", "--network=none ", $cmd);
      }
    }
    // force kill container if still running after time-out
    if (empty($_GET['communityApplications'])) removeContainer($Name, $echo);
    // Extract real Entrypoint and Cmd from container for Tailscale
    if ($TS_Enabled == 'true') {
      // Create preliminary base container but don't run it
      exec("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/docker create --name '" . escapeshellarg($Name) . "' '" . escapeshellarg($Repository) . "'");
      // Get Entrypoint and Cmd from docker inspect
      $containerInfo = $DockerClient->getContainerDetails($Name);
      $ts_env  = isset($containerInfo['Config']['Entrypoint']) ? '-e ORG_ENTRYPOINT="' . implode(' ', $containerInfo['Config']['Entrypoint']) . '" ' : '';
      $ts_env .= isset($containerInfo['Config']['Cmd']) ? '-e ORG_CMD="' . implode(' ', $containerInfo['Config']['Cmd']) . '" ' : '';
      // Insert Entrypoint and Cmd to docker command
      $cmd = str_replace('-l net.unraid.docker.managed=dockerman', $ts_env . '-l net.unraid.docker.managed=dockerman' , $cmd);
      // Remove preliminary container
      exec("/usr/local/emhttp/plugins/dynamix.docker.manager/scripts/docker rm '" . escapeshellarg($Name) . "'");
    }
    execCommand($cmd, $echo);
    if ($startContainer) addRoute($Name); // add route for remote WireGuard access
    $DockerClient->flushCaches();
    $newImageID = $DockerClient->getImageID($Repository);
    // remove old orphan image since it's no longer used by this container
    if ($oldImageID && $oldImageID != $newImageID) removeImage($oldImageID, $echo);
  }
  echo '<div style="text-align:center"><button type="button" onclick="window.parent.jQuery(\'#iframe-popup\').dialog(\'close\')">'._('Done').'</button></div><br>';
  goto END;
}

#########################
##   REMOVE TEMPLATE   ##
#########################

if (isset($_POST['rmTemplate'])) {
  if (file_exists($_POST['rmTemplate']) && dirname($_POST['rmTemplate'])==$dockerManPaths['templates-user']) unlink($_POST['rmTemplate']);
}

#########################
##    LOAD TEMPLATE    ##
#########################

$xmlType = $xmlTemplate = '';
if (isset($_GET['xmlTemplate'])) {
  [$xmlType, $xmlTemplate] = my_explode(':', unscript(urldecode($_GET['xmlTemplate'])));
  if (is_file($xmlTemplate)) {
    $xml = xmlToVar($xmlTemplate);
    $templateName = $xml['Name'];
    if (preg_match('/^container:(.*)/', $xml['Network'])) {
      $xml['Network'] = explode(':', $xml['Network'], 2);
    }
    if ($xmlType == 'default') {
      if (!empty($dockercfg['DOCKER_APP_CONFIG_PATH']) && file_exists($dockercfg['DOCKER_APP_CONFIG_PATH'])) {
        // override /config
        foreach ($xml['Config'] as &$arrConfig) {
          if ($arrConfig['Type'] == 'Path' && strtolower($arrConfig['Target']) == '/config') {
            $arrConfig['Default'] = $arrConfig['Value'] = realpath($dockercfg['DOCKER_APP_CONFIG_PATH']).'/'.$xml['Name'];
            if (empty($arrConfig['Display']) || preg_match("/^Host Path\s\d/", $arrConfig['Name'])) {
              $arrConfig['Display'] = 'advanced-hide';
            }
            if (empty($arrConfig['Name']) || preg_match("/^Host Path\s\d/", $arrConfig['Name'])) {
              $arrConfig['Name'] = 'AppData Config Path';
            }
          }
          $arrConfig['Name'] = strip_tags(_var($arrConfig,'Name'));
          $arrConfig['Description'] = strip_tags(_var($arrConfig,'Description'));
          $arrConfig['Requires'] = strip_tags(_var($arrConfig,'Requires'));
        }
      }
      if (!empty($dockercfg['DOCKER_APP_UNRAID_PATH']) && file_exists($dockercfg['DOCKER_APP_UNRAID_PATH'])) {
        // override /unraid
        $boolFound = false;
        foreach ($xml['Config'] as &$arrConfig) {
          if ($arrConfig['Type'] == 'Path' && strtolower($arrConfig['Target']) == '/unraid') {
            $arrConfig['Default'] = $arrConfig['Value'] = realpath($dockercfg['DOCKER_APP_UNRAID_PATH']);
            $arrConfig['Display'] = 'hidden';
            $arrConfig['Name'] = 'Unraid Share Path';
            $boolFound = true;
          }
        }
        if (!$boolFound) {
          $xml['Config'][] = [
            'Name'        => 'Unraid Share Path',
            'Target'      => '/unraid',
            'Default'     => realpath($dockercfg['DOCKER_APP_UNRAID_PATH']),
            'Value'       => realpath($dockercfg['DOCKER_APP_UNRAID_PATH']),
            'Mode'        => 'rw',
            'Description' => '',
            'Type'        => 'Path',
            'Display'     => 'hidden',
            'Required'    => 'false',
            'Mask'        => 'false'
          ];
        }
      }
    }
    $xml['Overview'] = str_replace(['[', ']'], ['<', '>'], $xml['Overview']);
    $xml['Description'] = $xml['Overview'] = strip_tags(str_replace("<br>","\n", $xml['Overview']));
    echo "<script>var Settings=".json_encode($xml).";</script>";
  }
}
echo "<script>var Allocations=".json_encode(getAllocations()).";</script>";
$authoringMode = $dockercfg['DOCKER_AUTHORING_MODE'] == "yes" ? true : false;
$authoring     = $authoringMode ? 'advanced' : 'noshow';
$disableEdit   = $authoringMode ? 'false' : 'true';
$showAdditionalInfo = '';

$bgcolor = $themeHelper->isLightTheme() ? '#f2f2f2' : '#1c1c1c'; // $themeHelper set in DefaultPageLayout.php

# Search for existing TAILSCALE_ entries in the Docker template
$TS_existing_vars = false;
if (isset($xml["Config"]) && is_array($xml["Config"])) {
  foreach ($xml["Config"] as $config) {
    if (isset($config["Target"]) && strpos($config["Target"], "TAILSCALE_") === 0) {
      $TS_existing_vars = true;
      break;
    }
  }
}

# Try to detect port from WebUI and set webui_url
$TSwebuiport = '';
$webui_url = '';
if (empty($xml['TailscalePort'])) {
  if (!empty($xml['WebUI'])) {
    $webui_url = parse_url($xml['WebUI']);
    preg_match('/:(\d+)\]/', $webui_url['host'], $matches);
    $TSwebuiport = $matches[1];
  }
}

$TS_raw = [];
$TS_container_raw = [];
$TS_HostNameWarning = "";
$TS_HTTPSDisabledWarning = "";
$TS_ExitNodeNeedsApproval = false;
$TS_MachinesLink = "https://login.tailscale.com/admin/machines/";
$TS_DirectMachineLink = $TS_MachinesLink;
$TS_HostNameActual = "";
$TS_not_approved = "";
$TS_https_enabled = false;
$ts_exit_nodes = [];
$ts_en_check = false;
// Get Tailscale information and create arrays/variables
!empty($xml) && exec("docker exec -i " . escapeshellarg($xml['Name']) . " /bin/sh -c \"tailscale status --peers=false --json\"", $TS_raw);
$TS_no_peers = json_decode(implode('', $TS_raw),true);
$TS_container = json_decode(implode('', $TS_raw),true);
$TS_container = $TS_container['Self']??'';

# Look for Exit Nodes through Tailscale plugin (if installed) when container is not running
if (empty($TS_container) && file_exists('/usr/local/sbin/tailscale') && exec('pgrep --ns $$ -f "/usr/local/sbin/tailscaled"')) {
  exec('tailscale exit-node list', $ts_exit_node_list, $retval);
  if ($retval === 0) {
    foreach ($ts_exit_node_list as $line) {
      if (!empty(trim($line))) {
        if (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s+(.+)$/', trim($line), $matches)) {
          $parts = preg_split('/\s+/', $matches[2]);
          $ts_exit_nodes[] = [
            'ip' => $matches[1],
            'hostname' => $parts[0],
            'country' => $parts[1],
            'city' => $parts[2],
            'status' => $parts[3]
          ];
          $ts_en_check = true;
        }
      }
    }
  }
}

if (!empty($TS_no_peers) && !empty($TS_container)) {
  // define the direct link to this machine on the Tailscale website
  if (!empty($TS_container['TailscaleIPs']) && !empty($TS_container['TailscaleIPs'][0])) {
    $TS_DirectMachineLink = $TS_MachinesLink.$TS_container['TailscaleIPs'][0];
  }
  // warn if MagicDNS or HTTPS is disabled
  if (isset($TS_no_peers['Self']['Capabilities']) && is_array($TS_no_peers['Self']['Capabilities'])) {
    $TS_https_enabled = in_array("https", $TS_no_peers['Self']['Capabilities'], true) ? true : false;
  }
  if (empty($TS_no_peers['CurrentTailnet']['MagicDNSEnabled']) || !$TS_no_peers['CurrentTailnet']['MagicDNSEnabled'] || $TS_https_enabled !== true) {
    $TS_HTTPSDisabledWarning = "<span><b><a href='https://tailscale.com/kb/1153/enabling-https' target='_blank'>Enable HTTPS</a> on your Tailscale account to use Tailscale Serve/Funnel.</b></span>";
  }
  // In $TS_container, 'HostName' is what the user requested, need to parse 'DNSName' to find the actual HostName in use
  $TS_DNSName = _var($TS_container,'DNSName','');
  $TS_HostNameActual = substr($TS_DNSName, 0, strpos($TS_DNSName, '.'));
  // compare the actual HostName in use to the one in the XML file
  if (strcasecmp($TS_HostNameActual, _var($xml, 'TailscaleHostname')) !== 0 && !empty($TS_DNSName)) {
    // they are different, show a warning
    $TS_HostNameWarning = "<span><b>Warning: the actual Tailscale hostname is '".$TS_HostNameActual."'</b></span>";
  }
  // If this is an Exit Node, show warning if it still needs approval
  if (_var($xml,'TailscaleIsExitNode') == 'true' && _var($TS_container, 'ExitNodeOption') === false) {
    $TS_ExitNodeNeedsApproval = true;
  }
  //Check for key expiry
  if(!empty($TS_container['KeyExpiry'])) {
    $TS_expiry = new DateTime($TS_container['KeyExpiry']);
    $current_Date = new DateTime();
    $TS_expiry_diff = $current_Date->diff($TS_expiry);
  }
  // Check for non approved routes
  if(!empty($xml['TailscaleRoutes'])) {
    $TS_advertise_routes = str_replace(' ', '', $xml['TailscaleRoutes']);
    if (empty($TS_container['PrimaryRoutes'])) {
      $TS_container['PrimaryRoutes'] = [];
    }
    $routes = explode(',', $TS_advertise_routes);
    foreach ($routes as $route) {
      if (!in_array($route, $TS_container['PrimaryRoutes'])) {
        $TS_not_approved .= " " . $route;
      }
    }
  }
  // Check for exit nodes if ts_en_check was not already done
  if (!$ts_en_check) {
    exec("docker exec -i ".$xml['Name']." /bin/sh -c \"tailscale exit-node list\"", $ts_exit_node_list, $retval);
    if ($retval === 0) {
      foreach ($ts_exit_node_list as $line) {
        if (!empty(trim($line))) {
          if (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s+(.+)$/', trim($line), $matches)) {
            $parts = preg_split('/\s+/', $matches[2]);
            $ts_exit_nodes[] = [
              'ip' => $matches[1],
              'hostname' => $parts[0],
              'country' => $parts[1],
              'city' => $parts[2],
              'status' => $parts[3]
            ];
          }
        }
      }
    }
  }
  // Construct WebUI URL on container template page
  // Check if webui_url, Tailscale WebUI and MagicDNS are not empty and make sure that MagicDNS is enabled
  if ( !empty($webui_url) && !empty($xml['TailscaleWebUI']) && (!empty($TS_no_peers['CurrentTailnet']['MagicDNSEnabled']) || ($TS_no_peers['CurrentTailnet']['MagicDNSEnabled']??false))) {
    // Check if serve or funnel are enabled by checking for [hostname] and replace string with TS_DNSName
    if (!empty($xml['TailscaleWebUI']) && strpos($xml['TailscaleWebUI'], '[hostname]') !== false && isset($TS_DNSName)) {
      $TS_webui_url = str_replace("[hostname][magicdns]", rtrim($TS_DNSName, '.'), $xml['TailscaleWebUI']);
      $TS_webui_url = preg_replace('/\[IP\]/', rtrim($TS_DNSName, '.'), $TS_webui_url);
      $TS_webui_url = preg_replace('/\[PORT:(\d{1,5})\]/', '443', $TS_webui_url);
    // Check if serve is disabled, construct url with port, path and query if present and replace [noserve] with url
    } elseif (strpos($xml['TailscaleWebUI'], '[noserve]') !== false && isset($TS_container['TailscaleIPs'])) {
      $ipv4 = '';
      foreach ($TS_container['TailscaleIPs'] as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
          $ipv4 = $ip;
          break;
        }
      }
      if (!empty($ipv4)) {
        $webui_url = isset($xml['WebUI']) ? parse_url($xml['WebUI']) : '';
        $webui_port = (preg_match('/\[PORT:(\d+)\]/', $xml['WebUI'], $matches)) ? ':' . $matches[1] : '';
        $webui_path = $webui_url['path'] ?? '';
        $webui_query = isset($webui_url['query']) ? '?' . $webui_url['query'] : '';
        $webui_query = preg_replace('/\[IP\]/', $ipv4, $webui_query);
        $webui_query = preg_replace('/\[PORT:(\d{1,5})\]/', ltrim($webui_port, ':'), $webui_query);
        $TS_webui_url = 'http://' . $ipv4 . $webui_port . $webui_path . $webui_query;
      }
    // Check if TailscaleWebUI in the xml is custom and display instead
    } elseif (strpos($xml['TailscaleWebUI'], '[hostname]') === false && strpos($xml['TailscaleWebUI'], '[noserve]') === false) {
      $TS_webui_url = $xml['TailscaleWebUI'];
    }
  }
}
?>
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/jquery.switchbutton.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/jquery.filetree.css")?>">

<script src="<?autov('/webGui/javascript/jquery.switchbutton.js')?>"></script>
<script src="<?autov('/webGui/javascript/jquery.filetree.js')?>" charset="utf-8"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/javascript/dynamix.vm.manager.js')?>"></script>
<script src="<?autov('/plugins/dynamix.docker.manager/javascript/markdown.js')?>"></script>
<script>
var confNum = 0;
var drivers = {};
<?foreach ($driver as $d => $v) echo "drivers['$d']='$v';\n";?>

if (!Array.prototype.forEach) {
  Array.prototype.forEach = function(fn, scope) {
    for (var i = 0, len = this.length; i < len; ++i) fn.call(scope, this[i], i, this);
  };
}
if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) {
      return typeof args[number] != 'undefined' ? args[number] : match;
    });
  };
}
if (!String.prototype.replaceAll) {
  String.prototype.replaceAll = function(str1, str2, ignore) {
    return this.replace(new RegExp(str1.replace(/([\/\,\!\\\^\$\{\}\[\]\(\)\.\*\+\?\|\<\>\-\&])/g,"\\$&"),(ignore?"gi":"g")),(typeof(str2)=="string")?str2.replace(/\$/g,"$$$$"):str2);
  };
}
// Create config nodes using templateDisplayConfig
function makeConfig(opts) {
  confNum += 1;
  var icons = {'Path':'folder-o', 'Port':'minus-square-o', 'Variable':'file-text-o', 'Label':'tags', 'Device':'play-circle-o'};
  var newConfig = $("#templateDisplayConfig").html();
  newConfig =  newConfig.format(
    stripTags(opts.Name),
    opts.Target,
    opts.Default,
    opts.Mode,
    opts.Description,
    opts.Type,
    opts.Display,
    opts.Required,
    opts.Mask,
    escapeQuote(opts.Value),
    opts.Buttons,
    opts.Required=='true' ? 'required' : '',
    sprintf('Container %s',opts.Type),
    icons[opts.Type] || 'question'
  );
  newConfig = "<div id='ConfigNum"+opts.Number+"' class='config_"+opts.Display+"'' >"+newConfig+"</div>";
  newConfig = $($.parseHTML(newConfig));
  value     = newConfig.find("input[name='confValue[]']");
  if (opts.Type == "Path") {
    value.attr("onclick", "openFileBrowser(this,$(this).val(),$(this).val(),'',true,false);");
  } else if (opts.Type == "Device") {
    value.attr("onclick", "openFileBrowser(this,'/dev','/dev','',true,true);")
  } else if (opts.Type == "Variable" && opts.Default.split("|").length > 1) {
    var valueOpts = opts.Default.split("|");
    var newValue = "<select name='confValue[]' class='selectVariable' default='"+valueOpts[0]+"'>";
    for (var i = 0; i < valueOpts.length; i++) {
      newValue += "<option value='"+valueOpts[i]+"' "+(opts.Value == valueOpts[i] ? "selected" : "")+">"+valueOpts[i]+"</option>";
    }
    newValue += "</select>";
    value.replaceWith(newValue);
  } else if (opts.Type == "Port") {
    value.addClass("numbersOnly");
  }
  if (opts.Mask == "true") {
    value.prop("autocomplete","new-password");
    value.prop("type", "password");
  }
  return newConfig.prop('outerHTML');
}

function stripTags(string) {
  return string.replace(/(<([^>]+)>)/ig,"");
}

function escapeQuote(string) {
  return string.replace(new RegExp('"','g'),"&quot;");
}

function makeAllocations(container,current) {
  var html = [];
  for (var i=0,ct; ct=container[i]; i++) {
    var highlight = ct.Name.toLowerCase()==current.toLowerCase() ? "font-weight:bold" : "";
    html.push($("#templateAllocations").html().format(highlight,ct.Name,ct.Port));
  }
  return html.join('');
}

function getVal(el, name) {
  var el = $(el).find("*[name="+name+"]");
  if (el.length) {
    return ($(el).attr('type') == 'checkbox') ? ($(el).is(':checked') ? "on" : "off") : $(el).val();
  } else {
    return "";
  }
}

function dialogStyle() {
  $('.ui-dialog-titlebar-close').css({'display':'none'});
  $('.ui-dialog-title').css({'text-align':'center','width':'100%','font-size':'1.8rem'});
  $('.ui-dialog-content').css({'padding-top':'15px','vertical-align':'bottom'});
  $('.ui-button-text').css({'padding':'0px 5px'});
}

function addConfigPopup() {
  var title = "_(Add Configuration)_";
  var popup = $("#dialogAddConfig");

  // Load popup the popup with the template info
  popup.html($("#templatePopupConfig").html());

  // Add switchButton to checkboxes
  popup.find(".switch").switchButton({labels_placement:"right",on_label:"_(Yes)_",off_label:"_(No)_"});
  popup.find(".switch-button-background").css("margin-top", "6px");

  // Load Mode field if needed and enable field
  toggleMode(popup.find("*[name=Type]:first"),false);

  // Start Dialog section
  popup.dialog({
    title: title,
    height: 'auto',
    width: 900,
    resizable: false,
    modal: true,
    buttons: {
    "_(Add)_": function() {
        $(this).dialog("close");
        confNum += 1;
        var Opts = Object;
        var Element = this;
        ["Name","Target","Default","Mode","Description","Type","Display","Required","Mask","Value"].forEach(function(e){
          Opts[e] = getVal(Element, e);
        });
        if (!Opts.Name){
          Opts.Name = makeName(Opts.Type);
        }

        if (Opts.Required == "true") {
          Opts.Buttons  = "<span class='advanced'><button type='button' onclick='editConfigPopup("+confNum+",false)'>_(Edit)_</button>";
          Opts.Buttons += "<button type='button' onclick='removeConfig("+confNum+")'>_(Remove)_</button></span>";
        } else {
          Opts.Buttons  = "<button type='button' onclick='editConfigPopup("+confNum+",false)'>_(Edit)_</button>";
          Opts.Buttons += "<button type='button' onclick='removeConfig("+confNum+")'>_(Remove)_</button>";
        }
        Opts.Number = confNum;
        if (Opts.Type == "Device") {
          Opts.Target = Opts.Value;
        }
        newConf = makeConfig(Opts);
        $("#configLocation").append(newConf);
        reloadTriggers();
        $('input[name="contName"]').trigger('change'); // signal change
      },
    "_(Cancel)_": function() {
        $(this).dialog("close");
      }
    }
  });
  dialogStyle();
}

function editConfigPopup(num,disabled) {
  var title = "_(Edit Configuration)_";
  var popup = $("#dialogAddConfig");

  // Load popup the popup with the template info
  popup.html($("#templatePopupConfig").html());

  // Load existing config info
  var config = $("#ConfigNum"+num);
  config.find("input").each(function(){
    var name = $(this).attr("name").replace("conf", "").replace("[]", "");
    popup.find("*[name='"+name+"']").val($(this).val());
  });

  // Hide passwords if needed
  if (popup.find("*[name='Mask']").val() == "true") {
    popup.find("*[name='Value']").prop("type", "password");
  }

  // Load Mode field if needed
  var mode = config.find("input[name='confMode[]']").val();
  toggleMode(popup.find("*[name=Type]:first"),disabled);
  popup.find("*[name=Mode]:first").val(mode);

  // Add switchButton to checkboxes
  popup.find(".switch").switchButton({labels_placement:"right",on_label:"_(Yes)_",off_label:"_(No)_"});

  // Start Dialog section
  popup.find(".switch-button-background").css("margin-top", "6px");
  popup.dialog({
    title: title,
    height: 'auto',
    width: 900,
    resizable: false,
    modal: true,
    buttons: {
    "_(Save)_": function() {
        $(this).dialog("close");
        var Opts = Object;
        var Element = this;
        ["Name","Target","Default","Mode","Description","Type","Display","Required","Mask","Value"].forEach(function(e){
          Opts[e] = getVal(Element, e);
        });
        if (Opts.Display == "always-hide" || Opts.Display == "advanced-hide") {
          Opts.Buttons  = "<span class='advanced'><button type='button' onclick='editConfigPopup("+num+",<?=$disableEdit?>)'>_(Edit)_</button>";
          Opts.Buttons += "<button type='button' onclick='removeConfig("+num+")'>_(Remove)_</button></span>";
        } else {
          Opts.Buttons  = "<button type='button' onclick='editConfigPopup("+num+",<?=$disableEdit?>)'>_(Edit)_</button>";
          Opts.Buttons += "<button type='button' onclick='removeConfig("+num+")'>_(Remove)_</button>";
        }
        if (!Opts.Name){
          Opts.Name = makeName(Opts.Type);
        }

        Opts.Number = num;
        if (Opts.Type == "Device") {
          Opts.Target = Opts.Value;
        }
        newConf = makeConfig(Opts);
        if (config.hasClass("config_"+Opts.Display)) {
          config.html(newConf);
          config.removeClass("config_always config_always-hide config_advanced config_advanced-hide").addClass("config_"+Opts.Display);
        } else {
          config.remove();
          if (Opts.Display == 'advanced' || Opts.Display == 'advanced-hide') {
            $("#configLocationAdvanced").append(newConf);
          } else {
            $("#configLocation").append(newConf);
          }
        }
       reloadTriggers();
        $('input[name="contName"]').trigger('change'); // signal change
      },
    "_(Cancel)_": function() {
        $(this).dialog("close");
      }
    }
  });
  dialogStyle();
  $('.desc_readmore').readmore({maxHeight:10});
}

function removeConfig(num) {
  $('#ConfigNum'+num).fadeOut("fast", function() {$(this).remove();});
  $('input[name="contName"]').trigger('change'); // signal change
}

function prepareConfig(form) {
  var types = [], values = [], targets = [], vcpu = [];
  if ($('select[name="contNetwork"]').val()=='host') {
    $(form).find('input[name="confType[]"]').each(function(){types.push($(this).val());});
    $(form).find('input[name="confValue[]"]').each(function(){values.push($(this));});
    $(form).find('input[name="confTarget[]"]').each(function(){targets.push($(this));});
    for (var i=0; i < types.length; i++) if (types[i]=='Port') $(targets[i]).val($(values[i]).val());
  }
  $(form).find('input[id^="box"]').each(function(){if ($(this).prop('checked')) vcpu.push($('#'+$(this).prop('id').replace('box','cpu')).text());});
  form.contCPUset.value = vcpu.join(',');
}

function makeName(type) {
  var i = $("#configLocation input[name^='confType'][value='"+type+"']").length+1;
  return "Host "+type.replace('Variable','Key')+" "+i;
}

function toggleMode(el,disabled) {
  var div        = $(el).closest('div');
  var targetDiv  = div.find('#Target');
  var valueDiv   = div.find('#Value');
  var defaultDiv = div.find('#Default');
  var mode       = div.find('#Mode');
  var value      = valueDiv.find('input[name=Value]');
  var target     = targetDiv.find('input[name=Target]');
  var driver     = drivers[$('select[name="contNetwork"]')[0].value];
  value.unbind();
  target.unbind();
  valueDiv.css('display', '');
  defaultDiv.css('display', '');
  targetDiv.css('display', '');
  mode.html('');
  $(el).prop('disabled',disabled);
  switch ($(el)[0].selectedIndex) {
  case 0: // Path
    mode.html("<dl><dt>_(Access Mode)_:</dt><dd><select name='Mode'><option value='rw'>_(Read/Write)_</option><option value='rw,slave'>_(Read/Write - Slave)_</option><option value='rw,shared'>_(Read/Write - Shared)_</option><option value='ro'>_(Read Only)_</option><option value='ro,slave'>_(Read Only - Slave)_</option><option value='ro,shared'>_(Read Only - Shared)_</option></select></dd></dl>");
    value.bind("click", function(){openFileBrowser(this,$(this).val(),$(this).val(),'',true,false);});
    targetDiv.find('#dt1').text("_(Container Path)_");
    valueDiv.find('#dt2').text("_(Host Path)_");
    break;
  case 1: // Port
    mode.html("<dl><dt>_(Connection Type)_:</dt><dd><select name='Mode'><option value='tcp'>_(TCP)_</option><option value='udp'>_(UDP)_</option></select></dd></dl>");
    value.addClass("numbersOnly");
    if (driver=='bridge') {
      if (target.val()) target.prop('disabled',<?=$disableEdit?>); else target.addClass("numbersOnly");
      targetDiv.find('#dt1').text("_(Container Port)_");
      targetDiv.show();
    } else {
      targetDiv.hide();
    }
    if (driver!='null') {
      valueDiv.find('#dt2').text("_(Host Port)_");
      valueDiv.show();
    } else {
      valueDiv.hide();
      mode.html('');
    }
    break;
  case 2: // Variable
    targetDiv.find('#dt1').text("_(Key)_");
    valueDiv.find('#dt2').text("_(Value)_");
    break;
  case 3: // Label
    targetDiv.find('#dt1').text("_(Key)_");
    valueDiv.find('#dt2').text("_(Value)_");
    break;
  case 4: // Device
    targetDiv.hide();
    defaultDiv.hide();
    valueDiv.find('#dt2').text("_(Value)_");
    value.bind("click", function(){openFileBrowser(this,'/dev','/dev','',true,true);});
    break;
  }
  reloadTriggers();
}

function loadTemplate(el) {
  var template = $(el).val();
  if (template.length) {
    $('#formTemplate').find("input[name='xmlTemplate']").val(template);
    $('#formTemplate').submit();
  }
}

function rmTemplate(tmpl) {
  var name = tmpl.split(/[\/]+/).pop();
  swal({title:"_(Are you sure)_?",text:"_(Remove template)_: "+name,type:"warning",html:true,showCancelButton:true,confirmButtonText:"_(Proceed)_",cancelButtonText:"_(Cancel)_"},function(){$("#rmTemplate").val(tmpl);$("#formTemplate1").submit();});
}

function openFileBrowser(el, top, root, filter, on_folders, on_files, close_on_select) {
  if (on_folders === undefined) on_folders = true;
  if (on_files   === undefined) on_files = true;
  if (!filter && !on_files) filter = 'HIDE_FILES_FILTER';
  if (!root.trim()) {root = "/mnt/user/"; top = "/mnt/";}
  p = $(el);
  // Skip if fileTree is already open
  if (p.next().hasClass('fileTree')) return null;
  // create a random id
  var r = Math.floor((Math.random()*10000)+1);
  // Add a new span and load fileTree
  p.after("<span id='fileTree"+r+"' class='textarea fileTree'></span>");
  var ft = $('#fileTree'+r);
  ft.fileTree({top:top, root:root, filter:filter, allowBrowsing:true},
    function(file){if(on_files){p.val(file);p.trigger('change');if(close_on_select){ft.slideUp('fast',function(){ft.remove();});}}},
    function(folder){if(on_folders){p.val(folder.replace(/\/\/+/g,'/'));p.trigger('change');if(close_on_select){$(ft).slideUp('fast',function(){$(ft).remove();});}}}
  );
  // Format fileTree according to parent position, height and width
  ft.css({'left':p.position().left,'top':(p.position().top+p.outerHeight()),'width':(p.width())});
  // close if click elsewhere
  $(document).mouseup(function(e){if(!ft.is(e.target) && ft.has(e.target).length === 0){ft.slideUp('fast',function(){$(ft).remove();});}});
  // close if parent changed
  p.bind("keydown", function(){ft.slideUp('fast', function(){$(ft).remove();});});
  // Open fileTree
  ft.slideDown('fast');
}

function resetField(el) {
  var target = $(el).prev();
  reset = target.attr("default");
  if (reset.length) target.val(reset);
}

function prepareCategory() {
  var values = $.map($('#catSelect option'),function(option) {
    if ($(option).is(":selected")) return option.value;
  });
  $("input[name='contCategory']").val(values.join(" "));
}

$(function() {
  var ctrl = "<span class='status'><input type='checkbox' class='advancedview'></span>";
<?if ($tabbed):?>
  $('.tabs').append(ctrl);
<?else:?>
  $('div[class=title] .right').append(ctrl);
<?endif;?>
  $('.advancedview').switchButton({labels_placement:'left', on_label: "_(Advanced View)_", off_label: "_(Basic View)_"});
  $('.advancedview').change(function() {
    var status = $(this).is(':checked');
    toggleRows('advanced', status, 'basic');
    load_contOverview();
    $("#catSelect").dropdownchecklist("destroy");
    $("#catSelect").dropdownchecklist({emptyText:"_(Select categories)_...", maxDropHeight:200, width:300, explicitClose:"..._(close)_"});
  });
});
</script>

<?php
if (isset($xml["Config"])) {
  foreach ($xml["Config"] as $config) {
    if (isset($config["Target"]) && is_array($config) && strpos($config["Target"], "TAILSCALE_") === 0) {
      $tailscaleTargetFound = true;
      break;
    }
  }
}
?>

<div id="canvas">
<form markdown="1" method="POST" autocomplete="off" onsubmit="prepareConfig(this)">
<input type="hidden" name="csrf_token" value="<?=$var['csrf_token']?>">
<input type="hidden" name="contCPUset" value="">
<?if ($xmlType=='edit'):?>
<?if ($DockerClient->doesContainerExist($templateName)):?>
<input type="hidden" name="existingContainer" value="<?=$templateName?>">
<?endif;?>
<?else:?>
<div markdown="1" class="TemplateDropDown">
_(Template)_:
: <select id="TemplateSelect" onchange="loadTemplate(this);">
  <?echo mk_option(0,"",_('Select a template'));
  $rmadd = '';
  $templates = [];
  $templates['default'] = $DockerTemplates->getTemplates('default');
  $templates['user'] = $DockerTemplates->getTemplates('user');
  foreach ($templates as $section => $template) {
    $title = ucfirst($section)." templates";
    printf("<optgroup class='title bold' label='[ %s ]'>", htmlspecialchars($title));
    foreach ($template as $value){
      if ( $value['name'] == "my-ca_profile" || $value['name'] == "ca_profile" ) continue;
      $name = str_replace('my-', '', $value['name']);
      $selected = (isset($xmlTemplate) && $value['path']==$xmlTemplate) ? ' selected ' : '';
      if ($selected && $section=='default') $showAdditionalInfo = 'advanced';
      if ($selected && $section=='user') $rmadd = $value['path'];
      printf("<option class='list' value='%s:%s' $selected>%s</option>", htmlspecialchars($section), htmlspecialchars($value['path']), htmlspecialchars($name));
    }
    if (!$template) echo("<option class='list' disabled>&lt;"._('None')."&gt;</option>");
    printf("</optgroup>");
  }
  ?></select><?if ($rmadd):?><i class="fa fa-window-close button" title="<?=htmlspecialchars($rmadd)?>" onclick="rmTemplate('<?=addslashes(htmlspecialchars($rmadd))?>')"></i><?endif;?>

> _(Templates are a quicker way to setting up Docker Containers on your Unraid server.  There are two types of templates:)_
>
> _(**Default templates**<br>)_
> _(When valid repositories are added to your Docker Repositories page, they will appear in a section on this drop down for you to choose (master categorized by author, then by application template).)_
> _(After selecting a default template, the page will populate with new information about the application in the Description field, and will typically provide instructions for how to setup the container.)_
> _(Select a default template when it is the first time you are configuring this application.)_
>
> _(**User-defined templates**<br>)_
> _(Once you've added an application to your system through a Default template,)_
> _(the settings you specified are saved to your USB flash device to make it easy to rebuild your applications in the event an upgrade were to fail or if another issue occurred.)_
> _(To rebuild, simply select the previously loaded application from the User-defined list and all the settings for the container will appear populated from your previous setup.)_
> _(Clicking create will redownload the necessary files for the application and should restore you to a working state.)_
> _(To delete a User-defined template, select it from the list above and click the red X to the right of it.)_

</div>
<?endif;?>

<div markdown="1" class="<?=$showAdditionalInfo?>">
_(Name)_:
: <input type="text" name="contName" pattern="[a-zA-Z0-9][a-zA-Z0-9_.\-]+" required>

> _(Give the container a name or leave it as default.  Two characters minimum.  First character must be a-z A-Z 0-9  Remaining characters a-z A-Z 0-9 . - _)_

</div>
<div markdown="1" class="basic">
_(Overview)_:
: <span id="contDescription" class="boxed blue-text"></span>

</div>
<div markdown="1" class="advanced">
_(Overview)_:
: <textarea name="contOverview" spellcheck="false" cols="80" rows="15" style="width:56%"></textarea>

> _(A description for the application container.  Supports basic HTML mark-up.)_

</div>
<div markdown="1" class="basic">
_(Additional Requirements)_:
: <span id="contRequires" class="boxed blue-text"></span>

</div>
<div markdown="1" class="advanced">
_(Additional Requirements)_:
: <textarea name="contRequires" spellcheck="false" cols="80" Rows="3" style="width:56%"></textarea>

> _(Any additional requirements the container has.  Supports basic HTML mark-up.)_

</div>

<div markdown="1" class="<?=$showAdditionalInfo?>">
_(Repository)_:
: <input type="text" name="contRepository" required>

> _(The repository for the application on the Docker Registry.  Format of authorname/appname.)_
> _(Optionally you can add a : after appname and request a specific version for the container image.)_

</div>
<div markdown="1" class="<?=$authoring?>">
_(Categories)_:
: <input type="hidden" name="contCategory">
  <select id="catSelect" size="1" multiple="multiple" style="display:none" onchange="prepareCategory();">
  <optgroup label="_(Categories)_">
  <option value="AI:">_(AI)_</option>
  <option value="Backup:">_(Backup)_</option>
  <option value="Cloud:">_(Cloud)_</option>
  <option value="Crypto:">_(Crypto Currency)_</option>
  <option value="Downloaders:">_(Downloaders)_</option>
  <option value="Drivers:">_(Drivers)_</option>
  <option value="GameServers:">_(Game Servers)_</option>
  <option value="HomeAutomation:">_(Home Automation)_</option>
  <option value="Productivity:">_(Productivity)_</option>
  <option value="Security:">_(Security)_</option>
  <option value="Tools:">_(Tools)_</option>
  <option value="Other:">_(Other)_</option>
  </optgroup>
  <optgroup label="_(MediaApp)_">
  <option value="MediaApp:Video">_(MediaApp)_:_(Video)_</option>
  <option value="MediaApp:Music">_(MediaApp)_:_(Music)_</option>
  <option value="MediaApp:Books">_(MediaApp)_:_(Books)_</option>
  <option value="MediaApp:Photos">_(MediaApp)_:_(Photos)_</option>
  <option value="MediaApp:Other">_(MediaApp)_:_(Other)_</option>
  </optgroup>
  <optgroup label="_(MediaServer)_">
  <option value="MediaServer:Video">_(MediaServer)_:_(Video)_</option>
  <option value="MediaServer:Music">_(MediaServer)_:_(Music)_</option>
  <option value="MediaServer:Books">_(MediaServer)_:_(Books)_</option>
  <option value="MediaServer:Photos">_(MediaServer)_:_(Photos)_</option>
  <option value="MediaServer:Other">_(MediaServer)_:_(Other)_</option>
  </optgroup>
  <optgroup label="_(Network)_">
  <option value="Network:Web">_(Network)_:_(Web)_</option>
  <option value="Network:DNS">_(Network)_:_(DNS)_</option>
  <option value="Network:FTP">_(Network)_:_(FTP)_</option>
  <option value="Network:Proxy">_(Network)_:_(Proxy)_</option>
  <option value="Network:Voip">_(Network)_:_(Voip)_</option>
  <option value="Network:Management">_(Network)_:_(Management)_</option>
  <option value="Network:Messenger">_(Network)_:_(Messenger)_</option>
  <option value="Network:VPN">_(Network)_:_(VPN)_</option>
  <option value="Network:Privacy">_(Network)_:_(Privacy)_</option>
  <option value="Network:Other">_(Network)_:_(Other)_</option>
  </optgroup>
  <optgroup label="_(Development Status)_">
  <option value="Status:Stable">_(Status)_:_(Stable)_</option>
  <option value="Status:Beta">_(Status)_:_(Beta)_</option>
  </optgroup>
  </select>

_(Support Thread)_:
: <input type="text" name="contSupport">

> _(Link to a support thread on Lime-Technology's forum.)_

_(Project Page)_:
: <input type="text" name="contProject">

> _(Link to the project page (eg: www.plex.tv))_

_(Read Me First)_:
: <input type="text" name="contReadMe">

> _(Link to a readme file or page)_

</div>
<div markdown="1" class="advanced">
_(Registry URL)_:
: <input type="text" name="contRegistry"></td>

> _(The path to the container's repository location on the Docker Hub.)_

</div>
<div markdown="1" class="noshow"> <!-- Deprecated for author to enter or change, but needs to be present -->
Donation Text:
: <input type="text" name="contDonateText">

Donation Link:
: <input type="text" name="contDonateLink">

Template URL:
: <input type="text" name="contTemplateURL">

</div>
<div markdown="1" class="advanced">
_(Icon URL)_:
: <input type="text" name="contIcon">

> _(Link to the icon image for your application (only displayed on dashboard if Show Dashboard apps under Display Settings is set to Icons).)_

_(WebUI)_:
: <input type="text" name="contWebUI">

> _(When you click on an application icon from the Docker Containers page, the WebUI option will link to the path in this field.)_
> _(Use [IP] to identify the IP of your host and [PORT:####] replacing the #'s for your port.)_

_(Extra Parameters)_:
: <input type="text" name="contExtraParams">

> _(If you wish to append additional commands to your Docker container at run-time, you can specify them here.<br>)_
> _(For all possible Docker run-time commands, see here: <a href="https://docs.docker.com/reference/run/" target="_blank">https://docs.docker.com/reference/run/</a>)_

_(Post Arguments)_:
: <input type="text" name="contPostArgs">

> _(If you wish to append additional arguments AFTER the container definition, you can specify them here.)_
> _(The content of this field is container specific.)_

_(CPU Pinning)_:
: <span style="display:inline-block"><?cpu_pinning()?></span>

> _(Checking a CPU core(s) will limit the container to run on the selected cores only. Selecting no cores lets the container run on all available cores (default))_

</div>
_(Network Type)_:
: <select name="contNetwork" onchange="showSubnet(this.value)">
  <?=mk_option(1,'bridge',_('Bridge'))?>
  <?=mk_option(1,'host',_('Host'))?>
  <?=mk_option(1,'container',_('Container'))?>
  <?=mk_option(1,'none',_('None'))?>
  <?foreach ($custom as $network):?>
  <?$name = $network;
  if (preg_match('/^(br|bond|eth)[0-9]+(\.[0-9]+)?$/',$network)) {
    [$eth,$x] = my_explode('.',$network);
    $eth = str_replace(['br','bond'],'eth',$eth);
    $n = $x ? 1 : 0; while (isset($$eth["VLANID:$n"]) && $$eth["VLANID:$n"] != $x) $n++;
    if (!empty($$eth["DESCRIPTION:$n"])) $name .= ' -- '.compress(trim($$eth["DESCRIPTION:$n"]));
  } elseif (preg_match('/^wg[0-9]+$/',$network)) {
    $conf = file("/etc/wireguard/$network.conf");
    if ($conf[1][0]=='#') $name .= ' -- '.compress(trim(substr($conf[1],1)));
  } elseif (substr($network,0,4)=='wlan') {
    $name .= '  -- '._('Wireless interface');
  }
  ?>
  <?=mk_option(1,$network,_('Custom')." : $name")?>
  <?endforeach;?></select>

<div markdown="1" class="myIP noshow">
_(Fixed IP address)_ (_(optional)_):
: <input type="text" name="contMyIP"><span id="myIP"></span>

> _(If the Bridge type is selected, the application's network access will be restricted to only communicating on the ports specified in the port mappings section.)_
> _(If the Host type is selected, the application will be given access to communicate using any port on the host that isn't already mapped to another in-use application/service.)_
> _(Generally speaking, it is recommended to leave this setting to its default value as specified per application template.)_
> 
> _(IMPORTANT NOTE:  If adjusting port mappings, do not modify the settings for the Container port as only the Host port can be adjusted.)_

</div>

<div markdown="1" class="netCONT noshow">
_(Container Network)_:
: <select name="netCONT" id="netCONT">
  <?php
  $container_name = !empty($xml['Name']) ? $xml['Name'] : '';
  foreach ($DockerClient->getDockerContainers() as $ct) {
    if ($ct['Name'] !== $container_name) {
      $list[] = $ct['Name'];
      echo mk_option($ct['Name'], $ct['Name'], $ct['Name']);
    }
  }
  ?>
</select>

> _(This allows your container to utilize the network configuration of another container. Select the appropriate container from the list.<br>This setup can be particularly beneficial if you wish to route your container's traffic through a VPN.)_

</div>

<div markdown="1" class="TSdivider noshow"><hr></div>

<?if ($TS_existing_vars == 'true'):?>
<div markdown="1" class="TSwarning noshow">
<b style="color:red;">_(WARNING)_</b>:
:  <b>_(Existing TAILSCALE variables found, please remove any existing modifications in the Template for Tailscale before using this function!)_</b>
</div>
<?endif;?>

<?if (empty($xml['TailscaleEnabled'])):?>
<div markdown="1" class="TSdeploy noshow">
<b>_(First deployment)_</b>:
:  <p>_(After deploying the container, open the log and follow the link to register the container to your Tailnet!)_</p>
</div>

<?if (!file_exists('/usr/local/sbin/tailscale')):?>
<div markdown="1" class="TSdeploy noshow">
<b>_(Recommendation)_</b>:
:  <p>_(For the best experience with Tailscale, install "Tailscale (Plugin)" from)_ <a href="/Apps?search=Tailscale%20(Plugin)" target='_blank'> Community Applications</a>.</p>
</div>
<?endif;?>

<?endif;?>

<div markdown="1" class='TSNetworkAllowed'>
_(Use Tailscale)_:
: <span class="flex flex-row items-center">
    <input type="checkbox" class="switch-on-off" name="contTailscale" id="contTailscale" <?php if (!empty($xml['TailscaleEnabled']) && $xml['TailscaleEnabled'] == 'true') echo 'checked'; ?> onchange="showTailscale(this)">
  </span>

> _(Enable Tailscale to add this container as a machine on your Tailnet.)_

</div>

<div markdown="1" class='TSNetworkNotAllowed'>
_(Use Tailscale)_:
: _(Option disabled as Network type is not bridge or custom)_

> _(Enable Tailscale to add this container as a machine on your Tailnet.)_

</div>
<div markdown="1" class="TSdivider noshow">
<b>_(NOTE)_</b>:
:  <i>_(This option will install Tailscale and dependencies into the container.)_</i>
</div>

<?if($TS_ExitNodeNeedsApproval):?>
<div markdown="1" class="TShostname noshow">
<b>Warning:</b>
: Exit Node not yet approved. Navigate to the <a href="<?=$TS_DirectMachineLink?>" target='_blank'>Tailscale website</a> and approve it.
</div>
<?endif;?>

<?if(!empty($TS_expiry_diff)):?>
<div markdown="1" class="TSdivider noshow">
<b>_(Warning)_</b>:
<?if($TS_expiry_diff->invert):?>
: <b>Tailscale Key expired!</b> <a href="<?=$TS_MachinesLink?>" target='_blank'>Renew/Disable key expiry</a> for '<b><?=$TS_HostNameActual?></b>'.
<?else:?>
: Tailscale Key will expire in <b><?=$TS_expiry_diff->days?> days</b>! <a href="<?=$TS_MachinesLink?>" target='_blank'>Disable Key Expiry</a> for '<b><?=$TS_HostNameActual?></b>'.
<?endif;?>
<label>See <a href="https://tailscale.com/kb/1028/key-expiry" target='_blank'>key-expiry</a>.</label>
</div>
<?endif;?>

<?if(!empty($TS_not_approved)):?>
<div markdown="1" class="TSdivider noshow">
<b>_(Warning)_</b>:
: The following route(s) are not approved: <b><?=trim($TS_not_approved)?></b>
</div>
<?endif;?>

<div markdown="1" class="TShostname noshow">
_(Tailscale Hostname)_:
: <input type="text" pattern="[A-Za-z0-9_\-]*" name="TShostname" <?php if (!empty($xml['TailscaleHostname'])) echo 'value="' . $xml['TailscaleHostname'] . '"'; ?> placeholder="_(Hostname for the container)_"> <?=$TS_HostNameWarning?>

> _(Provide the hostname for this container. It does not need to match the container name, but it must be unique on your Tailnet. Note that an HTTPS certificate will be generated for this hostname, which means it will be placed in a public ledger, so use a name that you don't mind being public.)_
> _(For more information see <a href="https://tailscale.com/kb/1153/enabling-https" target="_blank">enabling https</a>.)_

</div>

<div markdown="1" class="TSisexitnode noshow">
_(Be a Tailscale Exit Node)_:
: <select name="TSisexitnode" id="TSisexitnode" onchange="showTailscale(this)">
    <?=mk_option(1,'false',_('No'))?>
    <?=mk_option(1,'true',_('Yes'))?>
  </select>
  <span id='TSisexitnode_msg' style='font-style: italic;'></span>

> _(Enable this if other machines on your Tailnet should route their Internet traffic through this container, this is most useful for containers that connect to commercial VPN services.)_
> _(Be sure to authorize this Exit Node in your <a href="https://login.tailscale.com/admin/machines" target="_blank">Tailscale Machines Admin Panel</a>.)_
> _(For more details, see the Tailscale documentation on <a href="https://tailscale.com/kb/1103/exit-nodes" target="_blank">Exit Nodes</a>.)_

</div>

<div markdown="1" class="TSexitnodeip noshow">
_(Use a Tailscale Exit Node)_:
<?if($ts_en_check !== true && empty($ts_exit_nodes)):?>
: <input type="text" name="TSexitnodeip" <?php if (!empty($xml['TailscaleExitNodeIP'])) echo 'value="' . $xml['TailscaleExitNodeIP'] . '"'; ?> placeholder="_(IP/Hostname from Exit Node)_" onchange="processExitNodeoptions(this)">
<?else:?>
: <select name="TSexitnodeip" id="TSexitnodeip" onchange="processExitNodeoptions(this)">
  <?=mk_option(1,'',_('None'))?>
  <?foreach ($ts_exit_nodes as $ts_exit_node):?>
    <?=$node_offline = $ts_exit_node['status'] === 'offline' ? ' - OFFLINE' : '';?>
    <?=mk_option(1,$ts_exit_node['ip'],$ts_exit_node['ip'] . ' - ' . $ts_exit_node['hostname'] . $node_offline)?>
  <?endforeach;?></select>
<?endif;?>
  </select>
  <span id='TSexitnodeip_msg' style='font-style: italic;'></span>

> _(Optionally route this container's outgoing Internet traffic through an Exit Node on your Tailnet. Choose the Exit Node or input its Tailscale IP address.)_
> _(For more details, see <a href="https://tailscale.com/kb/1103/exit-nodes" target="_blank">Exit Nodes</a>.)_

</div>

<div markdown="1" class="TSallowlanaccess noshow">
_(Tailscale Allow LAN Access)_:
: <select name="TSallowlanaccess" id="TSallowlanaccess">
    <?=mk_option(1,'false',_('No'))?>
    <?=mk_option(1,'true',_('Yes'))?>
  </select>

> _(Only applies when this container is using an Exit Node. Enable this to allow the container to access the local network.)_
>
> _(<b>WARNING:</b>&nbsp;Even with this feature enabled, systems on your LAN may not be able to access the container unless they have Tailscale installed.)_

</div>

<div markdown="1" class="TSuserspacenetworking noshow">
_(Tailscale Userspace Networking)_:
: <select name="TSuserspacenetworking" id="TSuserspacenetworking" onchange="setExitNodeoptions()">
    <?=mk_option(1,'true',_('Enabled'))?>
    <?=mk_option(1,'false',_('Disabled'))?>
  </select>
  <span id='TSuserspacenetworking_msg' style='font-style: italic;'></span>

> _(When enabled, this container will operate in a restricted environment. Tailscale DNS will not work, and the container will not be able to initiate connections to other Tailscale machines. However, other machines on your Tailnet will still be able to communicate with this container.)_
>
> _(When disabled, this container will have full access to your Tailnet. Tailscale DNS will work, and the container can fully communicate with other machines on the Tailnet.)_
> _(However, systems on your LAN may not be able to access the container unless they have Tailscale installed.)_

</div>

<div markdown="1" class="TSssh noshow">
_(Enable Tailscale SSH)_:
: <select name="TSssh" id="TSssh">
    <?=mk_option(1,'false',_('No'))?>
    <?=mk_option(1,'true',_('Yes'))?>
  </select>

> _(Tailscale SSH is similar to the Docker "Console" option in the Unraid webgui, except you connect with an SSH client and authenticate via Tailscale.)_
> _(For more details, see the <a href="https://tailscale.com/kb/1193/tailscale-ssh" target="_blank">Tailscale SSH</a> documentation.)_

</div>

<div markdown="1" class="TSserve noshow">
_(Tailscale Serve)_:
: <select name="TSserve" id="TSserve" onchange="showServe(this.value)">
    <?=mk_option(1,'no',_('No'))?>
    <?=mk_option(1,'serve',_('Serve'))?>
    <?=mk_option(1,'funnel',_('Funnel'))?>
  </select>
<?=$TS_HTTPSDisabledWarning?><?php if (!empty($TS_webui_url)) echo '<label for="TSserve"><a href="' . $TS_webui_url . '" target="_blank">' . $TS_webui_url . '</a></label>'; ?>

> _(Enabling <b>Serve</b> will automatically reverse proxy the primary web service from this container and make it available on your Tailnet using https with a valid certificate!)_
>
> _(Note that when accessing the <b>Tailscale WebUI</b> url, no additional authentication layer is added beyond restricting it to your Tailnet - the container is still responsible for managing usernames/passwords that are allowed to access it. Depending on your configuration, direct access to the container may still be possible as well.)_
>
> _(For more details, see the <a href="https://tailscale.com/kb/1312/serve" target="_blank">Tailscale Serve</a> documentation.)_
>
> _(If the documentation recommends additional settings for a more complex use case, enable "Tailscale Show Advanced Settings". Support for these advanced settings is not available beyond confirming the commands are passed to Tailscale correctly.)_
>
> _(<b>Funnel</b> is similar to <b>Serve</b>, except that the web service is made available on the open Internet. Use with care as the service will likely be attacked. As with <b>Serve</b>, the container itself is responsible for handling any authentication.)_
>
> _(We recommend reading the <a href="https://tailscale.com/kb/1223/funnel" target="_blank">Tailscale Funnel</a> documentation before enabling this feature.)_
>
> _(<b>Note:</b>&nbsp;Enabling <b>Serve</b> or <b>Funnel</b> publishes the Tailscale hostname to a public ledger.)_
> _(For more details, see the Tailscale Documentation: <a href="https://tailscale.com/kb/1153/enabling-https" target="_blank">Enabling HTTPS</a>.)_

</div>

<div markdown="1" class="TSserveport noshow">
_(Tailscale Serve Port)_:
: <input type="text" name="TSserveport" value="<?php echo !empty($xml['TailscaleServePort']) ? $xml['TailscaleServePort'] : (!empty($TSwebuiport) ? $TSwebuiport : ''); ?>" placeholder="_(Will be detected automatically if possible)_">

> _(This field should specify the port for the primary web service this container offers. Note: it should specify the port in the container, not a port that was remapped on the host.)_
>
> _(The system attempted to determine the correct port automatically. If it used the wrong value then there is likely an issue with the "Web UI" field for this container, visible by switching from "Basic View" to "Advanced View" in the upper right corner of this page.)_
>
> _(In most cases this port is all you will need to specify in order to Serve the website in this container, although additional options are available below for more complex containers.)_
>
> _(This value is passed to the `<serve_port>` portion of this command which starts serve or funnel:<br>)_
> _(`tailscale [serve|funnel] --bg --<protocol><protocol_port><path> <serve_target>:<serve_port><local_path>`<br>)_
> _(For more details see the <a href="https://tailscale.com/kb/1242/tailscale-serve" target="_blank">Tailscale Serve Command Line</a> documentation.)_

</div>

<div markdown="1" class="TSadvanced noshow">
_(Tailscale Show Advanced Settings)_:
: <input type="checkbox" name="TSadvanced" class="switch-on-off" onchange="showTSAdvanced(this.checked)">

> _(Here there be dragons!)_

</div>

<div markdown="1" class="TSservetarget noshow">
_(Tailscale Serve Target)_:
: <input type="text" name="TSservetarget" <?php if (!empty($xml['TailscaleServeTarget'])) echo 'value="' . $xml['TailscaleServeTarget'] . '"'; ?> placeholder="_(Leave empty if unsure)_">

> _(When not specified, this value defaults to http://localhost. It is passed to the `<serve_target>` portion of this command which starts serve or funnel:<br>)_
> _(`tailscale [serve|funnel] --bg --<protocol><protocol_port><path> <serve_target>:<serve_port><local_path>`<br>)_
> _(For more details see the <a href="https://tailscale.com/kb/1242/tailscale-serve" target="_blank">Tailscale Serve Command Line</a> documentation.<br>)_
> _(Please note that only `localhost` or `127.0.0.1` are supported.)_

</div>

<div markdown="1" class="TSservelocalpath noshow">
_(Tailscale Serve Local Path)_:
: <input type="text" name="TSservelocalpath" <?php if (!empty($xml['TailscaleServeLocalPath'])) echo 'value="' . $xml['TailscaleServeLocalPath'] . '"'; ?> placeholder="_(Leave empty if unsure)_">

> _(When not specified, this value defaults to an empty string. It is passed to the `<local_path>` portion of this command which starts serve or funnel:<br>)_
> _(`tailscale [serve|funnel] --bg --<protocol><protocol_port><path> <serve_target>:<serve_port><local_path>`<br>)_
> _(For more details see the <a href="https://tailscale.com/kb/1242/tailscale-serve" target="_blank">Tailscale Serve Command Line</a> documentation.)_

</div>

<div markdown="1" class="TSserveprotocol noshow">
_(Tailscale Serve Protocol)_:
: <input type="text" name="TSserveprotocol" <?php if (!empty($xml['TailscaleServeProtocol'])) echo 'value="' . $xml['TailscaleServeProtocol'] . '"'; ?> placeholder="_(Leave empty if unsure, defaults to https)_">

> _(When not specified, this value defaults to "https". It is passed to the `<protocol>` portion of this command which starts serve or funnel:<br>)_
> _(`tailscale [serve|funnel] --bg --<protocol>=<protocol_port><path> <serve_target>:<serve_port><local_path>`<br>)_
> _(For more details see the <a href="https://tailscale.com/kb/1242/tailscale-serve" target="_blank">Tailscale Serve Command Line</a> documentation.)_

</div>

<div markdown="1" class="TSserveprotocolport noshow">
_(Tailscale Serve Protocol Port)_:
: <input type="text" name="TSserveprotocolport" <?php if (!empty($xml['TailscaleServeProtocolPort'])) echo 'value="' . $xml['TailscaleServeProtocolPort'] . '"'; ?> placeholder="_(Leave empty if unsure, defaults to =443)_">

> _(When not specified, this value defaults to "=443". It is passed to the `<protocol_port>` portion of this command which starts serve or funnel:<br>)_
> _(`tailscale [serve|funnel] --bg --<protocol><protocol_port><path> <serve_target>:<serve_port><local_path>`<br>)_
> _(For more details see the <a href="https://tailscale.com/kb/1242/tailscale-serve" target="_blank">Tailscale Serve Command Line</a> documentation.)_

</div>

<div markdown="1" class="TSservepath noshow">
_(Tailscale Serve Path)_:
: <input type="text" name="TSservepath" <?php if (!empty($xml['TailscaleServePath'])) echo 'value="' . $xml['TailscaleServePath'] . '"'; ?> placeholder="_(Leave empty if unsure)_">

> _(When not specified, this value defaults to an empty string. It is passed to the `<path>` portion of this command which starts serve or funnel:<br>)_
> _(`tailscale [serve|funnel] --bg --<protocol><protocol_port><path> <serve_target>:<serve_port><local_path>`<br>)_
> _(For more details see the <a href="https://tailscale.com/kb/1242/tailscale-serve" target="_blank">Tailscale Serve Command Line</a> documentation.)_

</div>

<div markdown="1" class="TSwebui noshow">
_(Tailscale WebUI)_:
: <input type="text" name="TSwebui" value="<?php echo !empty($TS_webui_url) ? $TS_webui_url : ''; ?>" placeholder="Will be determined automatically if possible" disabled>
<input type="hidden" name="TSwebui" <?php if (!empty($xml['TailscaleWebUI'])) echo 'value="' . $xml['TailscaleWebUI'] . '"'; ?>>

> _(If <b>Serve</b> is enabled this will be an https url with a proper domain name that is accessible over your Tailnet, no port needed!)_
>
> _(If <b>Funnel</b> is enabled the same url will be available on the Internet.)_
>
> _(If they are disabled then the url will be generated from the container's main "Web UI" field, but modified to use the Tailscale IP. If the wrong port is specified here then switch from "Basic View" to "Advanced View" and review the "Web UI" field for this container.)_

</div>

<div markdown="1" class="TSroutes noshow">
_(Tailscale Advertise Routes)_:
: <input type="text" pattern="[0-9:., \/]*" name="TSroutes" <?php if (!empty($xml['TailscaleRoutes'])) echo 'value="' . $xml['TailscaleRoutes'] . '"'?> placeholder="_(Leave empty if unsure)_">
