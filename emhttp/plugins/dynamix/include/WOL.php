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
global $lv;
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/SysDriversHelpers.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
// add translations
$_SERVER['REQUEST_URI'] = 'tools';
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "/usr/local/emhttp/plugins/dynamix.vm.manager/include/libvirt.php";
$vms = $lv->get_domains();
sort($vms,SORT_NATURAL);
foreach($vms as $vm){
  $arrEntries['VM'][$vm]['interfaces'] = $lv->get_nic_info($vm);
  $arrEntries['VM'][$vm]['name'] = $vm;
}

$DockerClient    = new DockerClient();
$containers      = $DockerClient->getDockerJSON("/containers/json?all=1");
foreach($containers as $ct)
  $arrEntries['Docker'][substr($ct["Names"][0],1)] = [
      'interfaces' => ['0 '=> ['mac' => isset($ct["NetworkSettings"]["Networks"]["bridge"]["MacAddress"]) ?  $ct["NetworkSettings"]["Networks"]["bridge"]["MacAddress"] : ""]],
      'name' => substr($ct["Names"][0],1),
  ];

$lxc = explode("\n",shell_exec("lxc-ls -1")) ;
$lxcpath = trim(shell_exec("lxc-config lxc.lxcpath"));
foreach ($lxc as $lxcname) {
  if ($lxcname == "") continue;
  $value = explode("=",shell_exec("cat $lxcpath/$lxcname/config  | grep 'hwaddr'"));
  $arrEntries['LXC'][$lxcname]['interfaces'][0]['mac'] = trim($value[1]);
  $arrEntries['LXC'][$lxcname]['name'] = $lxcname;
}

if (is_file("/boot/config/wol.json")) $user_mac = json_decode(file_get_contents("/boot/config/wol.json"),true); else $user_mac = [];

foreach($arrEntries as $key => $data) {
  $type=$key;
  foreach($data as $data2){
    $name=$data2['name'];
    if (isset($user_mac[$type][$name])) {
        $name=$name;
      #var_dump($name);
      $arrEntries[$type][$name]['enable'] = $user_mac[$type][$name]['enable'];
      $arrEntries[$type][$name]['user_mac'] = strtolower($user_mac[$type][$name]['user_mac']);
    } else {
      $arrEntries[$type][$name]['enable'] = 'enable';
      $arrEntries[$type][$name]['user_mac'] = 'None Defined';
    }
  }
}

switch ($_POST['table']) {

case 't1load':
  $arrMacs = $arrEntries; 
  $html =  "<thead><tr><th>"._('Service')."</th><th>"._('Name')."</th><th>"._('Mac Address')."</th><th>"._('Enabled')."</th><th>"._('User Mac Address')."</th></tr></thead>";
  $html .= "<tbody>";
  ksort($arrMacs);
  foreach($arrMacs as $systype => $m) {
    foreach($m as $macaddr) {  
    if ($systype == "") continue;

    $html .=  "<tr id='row$systype'>";
   $macs = "";
   foreach($macaddr['interfaces'] as $intdetail)
   {
    $macs .= " {$intdetail['mac']}" ;
   }
    $html .= "<td>$systype</td>";
    $selecttypename="enable;".$systype.";".$macaddr['name'];
    $mactypename=htmlspecialchars("user_mac;".$systype.";".$macaddr['name']);
    $mactypeid=htmlspecialchars("user_mac".$systype."".$macaddr['name']);
    $user_mac_str = '<input type="text" name="'.$mactypename.'" id="'.$mactypeid.'" class="narrow" value="'.htmlspecialchars($macaddr['user_mac']).'" title="'._("random mac, you can supply your own").'" /><a><i  onclick="maccreate(\''.$mactypeid.'\')" class="fa fa-refresh mac_generate" title="re-generate random mac address"></i></a>';
    $html .= "<td>{$macaddr['name']}</td><td id=\"status$systype\">$macs</td><td>";
    $html .="<select name='$selecttypename' class='audio narrow'>";
    $html .= mk_option($macaddr["enable"]  , "disable", _("Disabled"));
    $html .= mk_option($macaddr["enable"]  , "enable", _("Enabled"));
    $html .= "</select></td><td>".$user_mac_str."</td></tr>";
    $text = "";
  }
}
  $html .=  "</tbody>";
  
  $rtn = array();
  $rtn['html'] = $html;
  echo json_encode($rtn);
  break;

case "macaddress":
        $seed = 1;
        $prefix = '52:54:AA';
        $prefix.':'.$lv->macbyte(($seed * rand()) % 256).':'.$lv->macbyte(($seed * rand()) % 256).':'.$lv->macbyte(($seed * rand()) % 256);
        echo json_encode(['mac' => $prefix]);
        break; 

}
?>
