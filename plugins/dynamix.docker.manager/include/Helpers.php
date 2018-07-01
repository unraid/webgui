<?

function xml_encode($string) {
  return htmlspecialchars($string, ENT_XML1, 'UTF-8');
}

function xml_decode($string) {
  return strval(html_entity_decode($string, ENT_XML1, 'UTF-8'));
}

function postToXML($post, $setOwnership=false) {
  $dom = new domDocument;
  $dom->appendChild($dom->createElement("Container"));
  $xml = simplexml_import_dom($dom);
  $xml['version']          = 2;
  $xml->Name               = xml_encode(preg_replace('/\s+/', '', $post['contName']));
  $xml->Repository         = xml_encode(trim($post['contRepository']));
  $xml->Registry           = xml_encode(trim($post['contRegistry']));
  $xml->Network            = xml_encode($post['contNetwork']);
  $xml->MyIP               = xml_encode($post['contMyIP']);
  $xml->Shell              = xml_encode($post['contShell']);
  $xml->Privileged         = strtolower($post['contPrivileged'])=='on' ? 'true' : 'false';
  $xml->Support            = xml_encode($post['contSupport']);
  $xml->Project            = xml_encode($post['contProject']);
  $xml->Overview           = xml_encode($post['contOverview']);
  $xml->Category           = xml_encode($post['contCategory']);
  $xml->WebUI              = xml_encode(trim($post['contWebUI']));
  $xml->TemplateURL        = xml_encode($post['contTemplateURL']);
  $xml->Icon               = xml_encode(trim($post['contIcon']));
  $xml->ExtraParams        = xml_encode($post['contExtraParams']);
  $xml->PostArgs           = xml_encode($post['contPostArgs']);
  $xml->DateInstalled      = xml_encode(time());
  $xml->DonateText         = xml_encode($post['contDonateText']);
  $xml->DonateLink         = xml_encode($post['contDonateLink']);

  // V1 compatibility
  $xml->Description        = xml_encode($post['contOverview']);
  $xml->Networking->Mode   = xml_encode($post['contNetwork']);
  $xml->Networking->addChild("Publish");
  $xml->addChild("Data");
  $xml->addChild("Environment");
  $xml->addChild("Labels");

  $size = is_array($post['confName']) ? count($post['confName']) : 0;
  for ($i = 0; $i < $size; $i++) {
    $Type                  = $post['confType'][$i];
    $config                = $xml->addChild('Config', xml_encode($post['confValue'][$i]));
    $config['Name']        = xml_encode($post['confName'][$i]);
    $config['Target']      = xml_encode($post['confTarget'][$i]);
    $config['Default']     = xml_encode($post['confDefault'][$i]);
    $config['Mode']        = xml_encode($post['confMode'][$i]);
    $config['Description'] = xml_encode($post['confDescription'][$i]);
    $config['Type']        = xml_encode($post['confType'][$i]);
    $config['Display']     = xml_encode($post['confDisplay'][$i]);
    $config['Required']    = xml_encode($post['confRequired'][$i]);
    $config['Mask']        = xml_encode($post['confMask'][$i]);
    // V1 compatibility
    if ($Type == 'Port') {
      $port                = $xml->Networking->Publish->addChild("Port");
      $port->HostPort      = $post['confValue'][$i];
      $port->ContainerPort = $post['confTarget'][$i];
      $port->Protocol      = $post['confMode'][$i];
    } elseif ($Type == 'Path') {
      $path                = $xml->Data->addChild("Volume");
      $path->HostDir       = $post['confValue'][$i];
      $path->ContainerDir  = $post['confTarget'][$i];
      $path->Mode          = $post['confMode'][$i];
    } elseif ($Type == 'Variable') {
      $variable            = $xml->Environment->addChild("Variable");
      $variable->Value     = $post['confValue'][$i];
      $variable->Name      = $post['confTarget'][$i];
      $variable->Mode      = $post['confMode'][$i];
    } elseif ($Type == 'Label') {
      $label               = $xml->Labels->addChild("Label");
      $label->Value        = $post['confValue'][$i];
      $label->Name         = $post['confTarget'][$i];
      $label->Mode         = $post['confMode'][$i];
    }
  }
  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml->asXML());
  return $dom->saveXML();
}

function xmlToVar($xml) {
  global $subnet;
  $xml                = is_file($xml) ? simplexml_load_file($xml) : simplexml_load_string($xml);
  $out                = [];
  $out['Name']        = preg_replace('/\s+/', '', xml_decode($xml->Name));
  $out['Repository']  = xml_decode($xml->Repository);
  $out['Registry']    = xml_decode($xml->Registry);
  $out['Network']     = xml_decode($xml->Network);
  $out['MyIP']        = xml_decode($xml->MyIP ?? '');
  $out['Shell']       = xml_decode($xml->Shell ?? 'sh');
  $out['Privileged']  = xml_decode($xml->Privileged);
  $out['Support']     = xml_decode($xml->Support);
  $out['Project']     = xml_decode($xml->Project);
  $out['Overview']    = stripslashes(xml_decode($xml->Overview));
  $out['Category']    = xml_decode($xml->Category);
  $out['WebUI']       = xml_decode($xml->WebUI);
  $out['TemplateURL'] = xml_decode($xml->TemplateURL);
  $out['Icon']        = xml_decode($xml->Icon);
  $out['ExtraParams'] = xml_decode($xml->ExtraParams);
  $out['PostArgs']    = xml_decode($xml->PostArgs);
  $out['DonateText']  = xml_decode($xml->DonateText);
  $out['DonateLink']  = xml_decode($xml->DonateLink);
  $out['Config']      = [];
  if (isset($xml->Config)) {
    foreach ($xml->Config as $config) {
      $c = [];
      $c['Value'] = strlen(xml_decode($config)) ? xml_decode($config) : xml_decode($config['Default']);
      foreach ($config->attributes() as $key => $value) {
        $value = xml_decode($value);
        $val = strtolower($value);
        if ($key == 'Mode') {
          switch (xml_decode($config['Type'])) {
            case 'Path':
              $value = ($val=='rw'||$val=='rw,slave'||$val=='rw,shared'||$val=='ro'||$val=='ro,slave'||$val=='ro,shared') ? $value : "rw";
              break;
            case 'Port':
              $value = ($val=='tcp'||$val=='udp') ? $value : "tcp";
              break;
          }
        }
        $c[$key] = $value;
      }
      $out['Config'][] = $c;
    }
  }
  // some xml templates advertise as V2 but omit the new <Network> element
  // check for and use the V1 <Networking> element when this occurs
  if (empty($out['Network']) && isset($xml->Networking->Mode)) {
    $out['Network'] = xml_decode($xml->Networking->Mode);
  }
  // check if network exists
  if (!key_exists($out['Network'],$subnet)) $out['Network'] = 'none';
  // V1 compatibility
  if ($xml['version'] != '2') {
    if (isset($xml->Description)) {
      $out['Overview'] = stripslashes(xml_decode($xml->Description));
    }
    if (isset($xml->Networking->Publish->Port)) {
      $portNum = 0;
      foreach ($xml->Networking->Publish->Port as $port) {
        if (empty(xml_decode($port->ContainerPort))) continue;
        $portNum += 1;
        $out['Config'][] = [
          'Name'        => "Host Port ${portNum}",
          'Target'      => xml_decode($port->ContainerPort),
          'Default'     => xml_decode($port->HostPort),
          'Value'       => xml_decode($port->HostPort),
          'Mode'        => xml_decode($port->Protocol) ? xml_decode($port->Protocol) : "tcp",
          'Description' => ($out['Network'] == 'bridge') ? 'Container Port: '.xml_decode($port->ContainerPort) : 'n/a',
          'Type'        => 'Port',
          'Display'     => 'always',
          'Required'    => 'true',
          'Mask'        => 'false'
        ];
      }
    }
    if (isset($xml->Data->Volume)) {
      $volNum = 0;
      foreach ($xml->Data->Volume as $vol) {
        if (empty(xml_decode($vol->ContainerDir))) continue;
        $volNum += 1;
        $out['Config'][] = [
          'Name'        => "Host Path ${volNum}",
          'Target'      => xml_decode($vol->ContainerDir),
          'Default'     => xml_decode($vol->HostDir),
          'Value'       => xml_decode($vol->HostDir),
          'Mode'        => xml_decode($vol->Mode) ? xml_decode($vol->Mode) : "rw",
          'Description' => 'Container Path: '.xml_decode($vol->ContainerDir),
          'Type'        => 'Path',
          'Display'     => 'always',
          'Required'    => 'true',
          'Mask'        => 'false'
        ];
      }
    }
    if (isset($xml->Environment->Variable)) {
      $varNum = 0;
      foreach ($xml->Environment->Variable as $varitem) {
        if (empty(xml_decode($varitem->Name))) continue;
        $varNum += 1;
        $out['Config'][] = [
          'Name'        => "Key ${varNum}",
          'Target'      => xml_decode($varitem->Name),
          'Default'     => xml_decode($varitem->Value),
          'Value'       => xml_decode($varitem->Value),
          'Mode'        => '',
          'Description' => 'Container Variable: '.xml_decode($varitem->Name),
          'Type'        => 'Variable',
          'Display'     => 'always',
          'Required'    => 'false',
          'Mask'        => 'false'
        ];
      }
    }
    if (isset($xml->Labels->Variable)) {
      $varNum = 0;
      foreach ($xml->Labels->Variable as $varitem) {
        if (empty(xml_decode($varitem->Name))) continue;
        $varNum += 1;
        $out['Config'][] = [
          'Name'        => "Label ${varNum}",
          'Target'      => xml_decode($varitem->Name),
          'Default'     => xml_decode($varitem->Value),
          'Value'       => xml_decode($varitem->Value),
          'Mode'        => '',
          'Description' => 'Container Label: '.xml_decode($varitem->Name),
          'Type'        => 'Label',
          'Display'     => 'always',
          'Required'    => 'false',
          'Mask'        => 'false'
        ];
      }
    }
  }
  xmlSecurity($out);
  return $out;
}

function xmlSecurity(&$template) {
  foreach ($template as &$element) {
    if ( is_array($element) ) {
      xmlSecurity($element);
    } else {
      if ( is_string($element) ) {
        $tempElement = htmlspecialchars_decode($element);
        $tempElement = str_replace("[","<",$tempElement);
        $tempElement = str_replace("]",">",$tempElement);
        if ( preg_match('#<script(.*?)>(.*?)</script>#is',$tempElement) || preg_match('#<iframe(.*?)>(.*?)</iframe>#is',$tempElement) ) {
          $element = "REMOVED";
        }
      }
    }
  }
}

function xmlToCommand($xml, $create_paths=false) {
  global $docroot, $var, $driver;
  $xml           = xmlToVar($xml);
  $cmdName       = strlen($xml['Name']) ? '--name='.escapeshellarg($xml['Name']) : '';
  $cmdPrivileged = strtolower($xml['Privileged'])=='true' ? '--privileged=true' : '';
  $cmdNetwork    = '--net='.escapeshellarg(strtolower($xml['Network']));
  $cmdMyIP       = $xml['MyIP'] ? '--ip='.escapeshellarg($xml['MyIP']) : '';
  $Volumes       = [''];
  $Ports         = [''];
  $Variables     = [''];
  $Labels        = [''];
  $Devices       = [''];
  // Bind Time
  $Variables[]   = 'TZ="' . $var['timeZone'] . '"';
  // Add HOST_OS variable
  $Variables[]   = 'HOST_OS="unRAID"';

  foreach ($xml['Config'] as $key => $config) {
    $confType        = strtolower(strval($config['Type']));
    $hostConfig      = strlen($config['Value']) ? $config['Value'] : $config['Default'];
    $containerConfig = strval($config['Target']);
    $Mode            = strval($config['Mode']);
    if ($confType != "device" && !strlen($containerConfig)) continue;
    if ($confType == "path") {
      $Volumes[] = escapeshellarg($hostConfig).':'.escapeshellarg($containerConfig).':'.escapeshellarg($Mode);
      if ( ! file_exists($hostConfig) && $create_paths ) {
        @mkdir($hostConfig, 0777, true);
        @chown($hostConfig, 99);
        @chgrp($hostConfig, 100);
      }
    } elseif ($confType == 'port') {
      switch ($driver[$xml['Network']]) {
      case 'host':
      case 'macvlan':
        // Export ports as variable if network is set to host or macvlan
        $Variables[] = strtoupper(escapeshellarg($Mode.'_PORT_'.$containerConfig).'='.escapeshellarg($hostConfig));
        break;
      case 'bridge':
        // Export ports as port if network is set to (custom) bridge
        $Ports[] = escapeshellarg($hostConfig.':'.$containerConfig.'/'.$Mode);
        break;
      case 'none':
        // No export of ports if network is set to none
      }
    } elseif ($confType == "label") {
      $Labels[] = escapeshellarg($containerConfig).'='.escapeshellarg($hostConfig);
    } elseif ($confType == "variable") {
      $Variables[] = escapeshellarg($containerConfig).'='.escapeshellarg($hostConfig);
    } elseif ($confType == "device") {
      $Devices[] = escapeshellarg($hostConfig);
    }
  }

  $cmd = sprintf($docroot.'/plugins/dynamix.docker.manager/scripts/docker create %s %s %s %s %s %s %s %s %s %s %s %s',
         $cmdName, $cmdNetwork, $cmdMyIP, $cmdPrivileged, implode(' -e ', $Variables), implode(' -l ', $Labels), implode(' -p ', $Ports), implode(' -v ', $Volumes), implode(' --device=', $Devices), $xml['ExtraParams'], escapeshellarg($xml['Repository']), $xml['PostArgs']);
  return [preg_replace('/\s+/', ' ', $cmd), $xml['Name'], $xml['Repository']];
}
function stopContainer($name) {
  global $DockerClient;
  $waitID = mt_rand();
  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Stopping container: ".addslashes(htmlspecialchars($name))."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();
  $retval = $DockerClient->stopContainer($name);
  $out = ($retval === true) ? "Successfully stopped container '$name'" : "Error: ".$retval;
  echo "<script>stop_Wait($waitID);addLog('<b>".addslashes(htmlspecialchars($out))."</b>');</script>\n";
  @flush();
}

function removeContainer($name, $cache=false) {
  global $DockerClient;
  $waitID = mt_rand();
  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Removing container: ".addslashes(htmlspecialchars($name))."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();
  $retval = $DockerClient->removeContainer($name, false, $cache);
  $out = ($retval === true) ? "Successfully removed container '$name'" : "Error: ".$retval;
  echo "<script>stop_Wait($waitID);addLog('<b>".addslashes(htmlspecialchars($out))."</b>');</script>\n";
  @flush();
}

function removeImage($image) {
  global $DockerClient;
  $waitID = mt_rand();
  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Removing orphan image: ".addslashes(htmlspecialchars($image))."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();
  $retval = $DockerClient->removeImage($image);
  $out = ($retval === true) ? "Successfully removed image '$image'" : "Error: ".$retval;
  echo "<script>stop_Wait($waitID);addLog('<b>".addslashes(htmlspecialchars($out))."</b>');</script>\n";
  @flush();
}

function pullImage($name, $image) {
  global $DockerClient, $DockerTemplates, $DockerUpdate;
  $waitID = mt_rand();
  if (!preg_match("/:\S+$/", $image)) $image .= ":latest";
  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Pulling image: ".addslashes(htmlspecialchars($image))."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();
  $alltotals = [];
  $laststatus = [];
  $strError = '';
  $DockerClient->pullImage($image, function ($line) use (&$alltotals, &$laststatus, &$waitID, &$strError, $image, $DockerClient, $DockerUpdate) {
    $cnt = json_decode($line, true);
    $id = (isset($cnt['id'])) ? trim($cnt['id']) : '';
    $status = (isset($cnt['status'])) ? trim($cnt['status']) : '';
    if (isset($cnt['error'])) {
      $strError = $cnt['error'];
    }
    if ($waitID !== false) {
      echo "<script>stop_Wait($waitID);</script>\n";
      @flush();
      $waitID = false;
    }
    if (empty($status)) return;
    if (!empty($id)) {
      if (!empty($cnt['progressDetail']) && !empty($cnt['progressDetail']['total'])) {
        $alltotals[$id] = $cnt['progressDetail']['total'];
      }
      if (empty($laststatus[$id])) {
        $laststatus[$id] = '';
      }
      switch ($status) {
        case 'Waiting':
          // Omit
          break;
        case 'Downloading':
          if ($laststatus[$id] != $status) {
            echo "<script>addToID('${id}','".addslashes(htmlspecialchars($status))."');</script>\n";
          }
          $total = $cnt['progressDetail']['total'];
          $current = $cnt['progressDetail']['current'];
          if ($total > 0) {
            $percentage = round(($current / $total) * 100);
            echo "<script>progress('${id}',' ".$percentage."% of ".$DockerClient->formatBytes($total)."');</script>\n";
          } else {
            // Docker must not know the total download size (http-chunked or something?)
            // just show the current download progress without the percentage
            $alltotals[$id] = $current;
            echo "<script>progress('${id}',' ".$DockerClient->formatBytes($current)."');</script>\n";
          }
          break;
        default:
          if ($laststatus[$id] == "Downloading") {
            echo "<script>progress('${id}',' 100% of ".$DockerClient->formatBytes($alltotals[$id])."');</script>\n";
          }
          if ($laststatus[$id] != $status) {
            echo "<script>addToID('${id}','".addslashes(htmlspecialchars($status))."');</script>\n";
          }
          break;
      }
      $laststatus[$id] = $status;
    } else {
      if (strpos($status, 'Status: ') === 0) {
        echo "<script>addLog('".addslashes(htmlspecialchars($status))."');</script>\n";
      }
      if (strpos($status, 'Digest: ') === 0) {
        $DockerUpdate->setUpdateStatus($image, substr($status, 8));
      }
    }
    @flush();
  });
  echo "<script>addLog('<br><b>TOTAL DATA PULLED:</b> " . $DockerClient->formatBytes(array_sum($alltotals)) . "');</script>\n";
  @flush();
  if (!empty($strError)) {
    echo "<script>addLog('<br><span class=\"error\"><b>Error:</b> ".addslashes(htmlspecialchars($strError))."</span>');</script>\n";
    @flush();
    return false;
  }
  return true;
}

function execCommand($command) {
  if ( dockerRunSecurity($command) ) {
    $command = "logger 'docker command execution halted due to security violation (Bash command execution or redirection)'";
  }
  // $command should have all its args already properly run through 'escapeshellarg'
  $descriptorspec = [
    0 => ['pipe', 'r'],   // stdin is a pipe that the child will read from
    1 => ['pipe', 'w'],   // stdout is a pipe that the child will write to
    2 => ['pipe', 'w']    // stderr is a pipe that the child will write to
  ];
  $id = mt_rand();
  echo '<p class="logLine" id="logBody"></p>';
  echo '<script>addLog(\'<fieldset style="margin-top:1px;" class="CMD"><legend>Command:</legend>';
  echo 'root@localhost:# '.addslashes(htmlspecialchars($command)).'<br>';
  echo '<span id="wait'.$id.'">Please wait </span>';
  echo '<p class="logLine" id="logBody"></p></fieldset>\');show_Wait('.$id.');</script>';
  @flush();
  $proc = proc_open($command." 2>&1", $descriptorspec, $pipes, '/', []);
  while ($out = fgets( $pipes[1] )) {
    $out = preg_replace("%[\t\n\x0B\f\r]+%", '', $out);
    echo '<script>addLog("'.htmlspecialchars($out).'");</script>';
    @flush();
  }
  $retval = proc_close($proc);
  echo '<script>stop_Wait('.$id.');</script>';
  $out = $retval ?  'The command failed.' : 'The command finished successfully!';
  echo '<script>addLog(\'<br><b>'.$out.'</b>\');</script>';
  return $retval===0;
}

function dockerRunSecurity($command) {
  $testCommand = htmlspecialchars_decode($command);
  $cmdSplit = explode("'",$testCommand);
  for ($i=0; $i<count($cmdSplit); $i=$i+2) {
    $tstCommand .= $cmdSplit[$i];
  }
  foreach ( [";","|",">","&&"] as $invalidChars ) {
    if ( strpos($tstCommand,$invalidChars) ) {
      return true;
    }
  }
  return false;
}

function getXmlVal($xml, $element, $attr=null, $pos=0) {
  $xml = (is_file($xml)) ? simplexml_load_file($xml) : simplexml_load_string($xml);
  $element = $xml->xpath("//$element")[$pos];
  return isset($element) ? (isset($element[$attr]) ? strval($element[$attr]) : strval($element)) : "";
}

function setXmlVal(&$xml, $value, $el, $attr=null, $pos=0) {
  $xml = (is_file($xml)) ? simplexml_load_file($xml) : simplexml_load_string($xml);
  $element = $xml->xpath("//$el")[$pos];
  if (!isset($element)) $element = $xml->addChild($el);
  if ($attr) {
    $element[$attr] = $value;
  } else {
    $element->{0} = $value;
  }
  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml->asXML());
  $xml = $dom->saveXML();
}

function getAllocations() {
  global $DockerClient, $host;
  foreach ($DockerClient->getDockerContainers() as $ct) {
    $list = $port = [];
    $nat = $ip = false;
    $list['Name'] = $ct['Name'];
    foreach ($ct['Ports'] as $tmp) {
      $nat = $tmp['NAT'];
      $ip = $tmp['IP'];
      $port[] = $tmp['PublicPort'];
    }
    sort($port);
    $ip = $ct['NetworkMode']=='host'||$nat ? $host : ($ip ?: DockerUtil::myIP($ct['Name']) ?: '0.0.0.0');
    $list['Port'] = "$ip : ".(implode(' ',array_unique($port)) ?: '???')." -- {$ct['NetworkMode']}";
    $ports[] = $list;
  }
  return $ports;
}
?>