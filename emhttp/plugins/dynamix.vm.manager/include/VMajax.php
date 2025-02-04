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
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'vms';
require_once "$docroot/webGui/include/Translations.php";

function requireLibvirt() {
	global $lv;
	// Make sure libvirt is connected to qemu
	if (!isset($lv) || !$lv->enabled()) {
		header('Content-Type: application/json');
		die(json_encode(['error' => 'failed to connect to the hypervisor']));
	}
}

function scan($line, $text) {
	return stripos($line,$text)!==false;
}

function embed(&$bootcfg, $env, $key, $value) {
	if ($env === 'syslinux') {
		$size = count($bootcfg);
		$make = false;
		$new = strlen($value) ? "$key=$value" : false;
		$i = 0;
		while ($i < $size) {
			// find sections and exclude safemode
			if (scan($bootcfg[$i],'label ') && !scan($bootcfg[$i],'safe mode') && !scan($bootcfg[$i],'safemode')) {
				$n = $i + 1;
				// find the current requested setting
				while (!scan($bootcfg[$n],'label ') && $n < $size) {
					if (scan($bootcfg[$n],'append ')) {
						$cmd = preg_split('/\s+/',trim($bootcfg[$n]));
						// replace the existing setting
						for ($c = 1; $c < count($cmd); $c++) if (scan($cmd[$c],$key)) {$make |= ($cmd[$c]!=$new); $cmd[$c] = $new; break;}
						// or insert the new setting
						if ($c==count($cmd) && $new) {array_splice($cmd,-1,0,$new); $make = true;}
						$bootcfg[$n] = '  '.str_replace('  ',' ',implode(' ',$cmd));
					}
					$n++;
				}
				$i = $n - 1;
			}
			$i++;
		}
	} elseif ($env === 'grub') {
		$size = count($bootcfg);
		$make = false;
		$new = strlen($value) ? "$key=$value" : false;
		$i = 0;
		while ($i < $size) {
			// find sections and exclude safemode/memtest
			if (scan($bootcfg[$i],'menuentry ') && !scan($bootcfg[$i],'safe mode') && !scan($bootcfg[$i],'safemode') && !scan($bootcfg[$i],'memtest')) {
				$n = $i + 1;
				// find the current requested setting
				while (!scan($bootcfg[$n],'menuentry ') && $n < $size) {
					if (scan($bootcfg[$n],'linux ')) {
						$cmd = preg_split('/\s+/',trim($bootcfg[$n]));
						// replace the existing setting
						for ($c = 1; $c < count($cmd); $c++) if (scan($cmd[$c],$key)) {$make |= ($cmd[$c]!=$new); $cmd[$c] = $new; break;}
						// or insert the new setting
						if ($c == count($cmd) && $new) {
							$cmd[] = $new;
							$make = true;
						}
						$bootcfg[$n] = '  ' . str_replace('  ', ' ', implode(' ', $cmd));
					}
					$n++;
				}
				$i = $n - 1;
			}
			$i++;
		}
	}
	return $make;
}

$arrSizePrefix = [0 => '', 1 => 'K', 2 => 'M', 3 => 'G', 4 => 'T', 5 => 'P'];
$action        = unscript(_var($_REQUEST,'action'));
$uuid          = unscript(_var($_REQUEST,'uuid'));
$arrResponse   = [];

if ($uuid) {
	requireLibvirt();
	$domName = $lv->domain_get_name_by_uuid($uuid);
	if (!$domName) {
		header('Content-Type: application/json');
		die(json_encode(['error' => $lv->get_last_error()]));
	}
}

switch ($action) {
case 'domain-autostart':
	requireLibvirt();
	$arrResponse = $lv->domain_set_autostart($domName, $_REQUEST['autostart']!='false')
	? ['success' => true, 'autostart' => (bool)$lv->domain_get_autostart($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-start':
	requireLibvirt();
	$arrResponse = $lv->domain_start($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-start-console':
	requireLibvirt();
	$arrResponse = $lv->domain_start($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	$dom = $lv->get_domain_by_name($domName);
	$vmrcport = $lv->domain_get_vnc_port($dom);
	$wsport = $lv->domain_get_ws_port($dom);
	$protocol = $lv->domain_get_vmrc_protocol($dom);
	if ($vmrcport > 0) {
		$vmrcurl  = autov('/plugins/dynamix.vm.manager/'.$protocol.'.html',true).'&autoconnect=true&host='._var($_SERVER,'HTTP_HOST');
		if ($protocol == "spice") $vmrcurl  .= '&vmname='. urlencode($domName) .'&port=/wsproxy/'.$vmrcport.'/'; else $vmrcurl .= '&port=&path=/wsproxy/'.$wsport.'/';
	}
	if ($protocol == "vnc") $vmrcscale = "&resize=scale"; else $vmrcscale = "";
	$arrResponse['vmrcurl'] = $vmrcurl.$vmrcscale;
	break;

case 'domain-start-consoleRV':
	requireLibvirt();
	$arrResponse = $lv->domain_start($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	$dom = $lv->get_domain_by_name($domName);
	$vmrcport = $lv->domain_get_vnc_port($dom);
	$wsport = $lv->domain_get_ws_port($dom);
	$protocol = $lv->domain_get_vmrc_protocol($dom);
	if ($protocol == "spice") $port= $vmrcport ; else $port=$vmrcport ;
	$vvarray = array() ;
	$vvarray[] = "[virt-viewer]\n";
	$vvarray[] = "type=$protocol\n";
	$vvarrayhost = _var($_SERVER,'HTTP_HOST');
	if (strpos($vvarrayhost,":")) $vvarrayhost = parse_url($vvarrayhost,PHP_URL_HOST);
	$vvarray[] = "host=$vvarrayhost\n" ; 
	$vvarray[] = "port=$port\n" ;
	$vvarray[] = "delete-this-file=1\n" ;
	if (!is_dir("/mnt/user/system/remoteviewer")) mkdir("/mnt/user/system/remoteviewer") ;
	$vvfile = "/mnt/user/system/remoteviewer/rv"._var($_SERVER,'HTTP_HOST').".$port.vv" ;
	file_put_contents($vvfile,$vvarray) ;
	$arrResponse['vvfile'] = $vvfile;
	break;

case 'domain-consoleRDP':
	requireLibvirt();
	$dom = $lv->get_domain_by_name($domName);
	$rdpvarray = array() ;
	$myIP=get_vm_ip($dom);
	if ($myIP == NULL)  {$arrResponse['error'] = "No IP, guest agent not installed?"; break; } 
	$rdparray[] = "full address:s: $myIP\n";
	#$rdparray[] = "administrative session:1\n";
	if (!is_dir("/mnt/user/system/remoteviewer")) mkdir("/mnt/user/system/remoteviewer") ;
	$rdpfile = "/mnt/user/system/remoteviewer/rv"._var($_SERVER,'HTTP_HOST').".$port.rdp" ;
	file_put_contents($rdpfile,$rdparray) ;
	$arrResponse['vvfile'] = $rdpfile;
	break;

case 'domain-consoleRV':
	requireLibvirt();
	$dom = $lv->get_domain_by_name($domName);
	$vmrcport = $lv->domain_get_vnc_port($dom);
	$wsport = $lv->domain_get_ws_port($dom);
	$protocol = $lv->domain_get_vmrc_protocol($dom);
	if ($protocol == "spice") $port= $vmrcport ; else $port=$vmrcport ;
	$vvarray = array() ;
	$vvarray[] = "[virt-viewer]\n";
	$vvarray[] = "type=$protocol\n";
	$vvarrayhost = _var($_SERVER,'HTTP_HOST');
	if (strpos($vvarrayhost,":")) $vvarrayhost = parse_url($vvarrayhost,PHP_URL_HOST);
	$vvarray[] = "host=$vvarrayhost\n" ;
	$vvarray[] = "port=$port\n" ;
	$vvarray[] = "delete-this-file=1\n" ;
	if (!is_dir("/mnt/user/system/remoteviewer")) mkdir("/mnt/user/system/remoteviewer") ;
	$vvfile = "/mnt/user/system/remoteviewer/rv"._var($_SERVER,'HTTP_HOST').".$port.vv" ;
	file_put_contents($vvfile,$vvarray) ;
	$arrResponse['vvfile'] = $vvfile;
	break;

case 'domain-openWebUI':
	requireLibvirt();
	$dom = $lv->get_domain_by_name($domName);
	$WebUI = unscript(_var($_REQUEST,'vmrcurl'));
	$myIP = get_vm_ip($dom);
	if (strpos($WebUI,"[IP]") && $myIP == NULL)  $arrResponse['error'] = "No IP, guest agent not installed?"; 
	$WebUI = preg_replace("%\[IP\]%", $myIP, $WebUI);
	$vmnamehypen = str_replace(" ","-",$domName);
	$WebUI = preg_replace("%\[VMNAME\]%", $vmnamehypen, $WebUI);
	if (preg_match("%\[PORT:(\d+)\]%", $WebUI, $matches)) {
		$ConfigPort = $matches[1] ?? '';
		$WebUI = preg_replace("%\[PORT:\d+\]%", $ConfigPort, $WebUI);	
	}
	$arrResponse['vmrcurl'] = $WebUI;
	break;

	
case 'domain-pause':
	requireLibvirt();
	$arrResponse = $lv->domain_suspend($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-resume':
	requireLibvirt();
	$arrResponse = $lv->domain_resume($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-pmsuspend':
	requireLibvirt();
	// No support in libvirt-php to do a dompmsuspend, use virsh tool instead
	exec("virsh dompmsuspend ".escapeshellarg($uuid)." disk 2>&1", $arrOutput, $intReturnCode);
	$arrResponse = $intReturnCode==0
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => str_replace('error: ', '', implode('. ', $arrOutput))];
	break;

case 'domain-pmwakeup':
	requireLibvirt();
	// No support in libvirt-php to do a dompmwakeup, use virsh tool instead
	exec("virsh dompmwakeup ".escapeshellarg($uuid)." 2>&1", $arrOutput, $intReturnCode);
	$arrResponse = $intReturnCode==0
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => str_replace('error: ', '', implode('. ', $arrOutput))];
	break;

case 'domain-restart':
	requireLibvirt();
	$arrResponse = $lv->domain_reboot($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-save':
	requireLibvirt();
	$arrResponse = $lv->domain_save($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-stop':
	requireLibvirt();
	$arrResponse = $lv->domain_shutdown($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	$n = 30; // wait for VM to die
	while ($arrResponse['success'] && $lv->domain_get_state($domName)=='running') {
		sleep(1); if(!--$n) break;
	}
	break;

case 'domain-destroy':
	requireLibvirt();
	$arrResponse = $lv->domain_destroy($domName)
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-delete':
	requireLibvirt();
	$arrResponse = $lv->domain_delete($domName)
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-undefine':
	requireLibvirt();
	$arrResponse = $lv->domain_undefine($domName)
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-define':
	requireLibvirt();
	$domName = $lv->domain_define($_REQUEST['xml']);
	$arrResponse = $domName
	? ['success' => true, 'state' => $lv->domain_get_state($domName)]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-state':
	requireLibvirt();
	$state = $lv->domain_get_state($domName);
	$arrResponse = $state
	? ['success' => true, 'state' => $state]
	: ['error' => $lv->get_last_error()];
	break;

case 'domain-diskdev':
	requireLibvirt();
	$arrResponse = $lv->domain_set_disk_dev($domName, $_REQUEST['olddev'], $_REQUEST['diskdev'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'cdrom-change':
	requireLibvirt();
	$arrResponse = $lv->domain_change_cdrom($domName, $_REQUEST['cdrom'], $_REQUEST['dev'], $_REQUEST['bus'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'change-media':
	requireLibvirt();
	$dev= $_REQUEST['dev'];
	$file= $_REQUEST['file'];
	$cmdstr = "virsh change-media '$domName' $dev $file";
	$rtn=shell_exec($cmdstr)
		? ['success' => true]
		: ['error' => "Change Media Failed"];
	break;

case 'change-media-both':
	requireLibvirt();
	$res = $lv->get_domain_by_name($domName);
	$cdroms = $lv->get_cdrom_stats($res) ;
	$hda = $hdb = false ;
	foreach ($lv->get_cdrom_stats($res) as $cd){
		if ($cd['device'] == 'hda') $hda = true ;
		if ($cd['device'] == 'hdb') $hdb = true ;
	}
	$file= $_REQUEST['file'];
	if ($file != "" && $hda == false) {
		$cmdstr = "virsh attach-disk '$domName' '$file' hda --type cdrom --targetbus sata --config" ;
	} else {
		if ($file == "") $cmdstr = "virsh change-media '$domName' hda --eject --current";
		else $cmdstr = "virsh change-media '$domName' hda '$file'";
	}
	$rtn=shell_exec($cmdstr)
		? ['success' => true]
		: ['error' => "Change Media Failed"];

	if (isset($rtn['error'])) return ;

	$file2 = $_REQUEST['file2'];
	if ($file2 != "" && $hdb == false) {
		$cmdstr = "virsh attach-disk '$domName' '$file2' hdb --type cdrom --targetbus sata --config" ;
	} else  {
		if ($file2 == "") $cmdstr = "virsh change-media '$domName' hdb --eject --current";
		else $cmdstr = "virsh change-media '$domName' hdb '$file2' ";
	}
	$rtn=shell_exec($cmdstr)
		? ['success' => true]
		: ['error' => "Change Media Failed"];
	break;

case 'memory-change':
	requireLibvirt();
	$arrResponse = $lv->domain_set_memory($domName, $_REQUEST['memory']*1024)
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'vcpu-change':
	requireLibvirt();
	$arrResponse = $lv->domain_set_vcpu($domName, $_REQUEST['vcpu'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'bootdev-change':
	requireLibvirt();
	$arrResponse = $lv->domain_set_boot_device($domName, $_REQUEST['bootdev'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'disk-remove':
	requireLibvirt();
	// libvirt-php has an issue with detaching a disk, use virsh tool instead
	exec("virsh detach-disk ".escapeshellarg($uuid)." ".escapeshellarg($_REQUEST['dev'])." 2>&1", $arrOutput, $intReturnCode);
	$arrResponse = $intReturnCode==0
	? ['success' => true]
	: ['error' => str_replace('error: ', '', implode('. ', $arrOutput))];
	break;

case 'snap-create':
	requireLibvirt();
	$arrResponse = $lv->domain_snapshot_create($domName)
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'snap-create-external':
	requireLibvirt();
	$arrResponse = vm_snapshot($domName,$_REQUEST['snapshotname'],$_REQUEST['desc'],$_REQUEST['free'],$_REQUEST['fstype'],$_REQUEST['memorydump']) ;
	break;

case 'snap-images':
	requireLibvirt();
	$html = vm_snapimages($domName,$_REQUEST['snapshotname'],$_REQUEST['only']) ;
	$arrResponse = ['html' => $html , 'success' => true] ;
	break;

case 'snap-list':
	requireLibvirt();
	$arrResponse = ($data = getvmsnapshots($domName)) 
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	$datartn = "";
	foreach($data as $snap=>$snapdetail) {
		$snapshotdatetime = date("Y-m-d H:i:s",$snapdetail["creationtime"]) ;
		$datartn  .= "<option value='$snap'>$snap  $snapshotdatetime</option>" ;
	}
	$arrResponse['html'] = $datartn ;
	break;

case 'snap-revert-external':
	requireLibvirt();
	$arrResponse = vm_revert($domName,$_REQUEST['snapshotname'],$_REQUEST['remove'], $_REQUEST['removemeta']) ;
	break;

case 'snap-remove-external':
	requireLibvirt();
	$arrResponse = vm_snapremove($domName,$_REQUEST['snapshotname']) ;
	break;

case 'snap-delete':
	requireLibvirt();
	$arrResponse = $lv->domain_snapshot_delete($domName, $_REQUEST['snap'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'snap-revert':
	requireLibvirt();
	$arrResponse = $lv->domain_snapshot_revert($domName, $_REQUEST['snap'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'snap-desc':
	requireLibvirt();
	$arrResponse = $lv->snapshot_set_metadata($domName, $_REQUEST['snap'], $_REQUEST['snapdesc'])
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	break;

case 'get_storage_fstype':
	$fstype = get_storage_fstype(unscript(_var($_REQUEST,'storage')));
	$arrResponse = ['fstype' => $fstype , 'success' => true] ;
	break;

case 'vm-removal':
	requireLibvirt();
	$arrResponse = ($data = getvmsnapshots($domName)) 
	? ['success' => true]
	: ['error' => $lv->get_last_error()];
	$datartn = $disksrtn = "";
	foreach($data as $snap=>$snapdetail) {
		$snapshotdatetime = date("Y-m-d H:i:s",$snapdetail["creationtime"]) ;
		$datartn  .= "$snap  $snapshotdatetime\n" ;
	}
	$disks = $lv->get_disk_stats($domName);

	foreach($disks as $diskid=>$diskdetail) {
	if ($diskid == 0) $pathinfo = pathinfo($diskdetail['file']);
	}

	$list = glob($pathinfo['dirname']."/*");
	$uuid = $lv->domain_get_uuid($domName);

	$list2 = glob("/etc/libvirt/qemu/nvram/*$uuid*");
	$listnew = array();
	$list=array_merge($list,$list2);
	foreach($list as $key => $listent)
	{
		$pathinfo = pathinfo($listent);
		$listnew[] = "{$pathinfo['basename']} ({$pathinfo['dirname']})";
	}
	sort($listnew,SORT_NATURAL);
	$listcount = count($listnew);
	$snapcount = count($data);
	$disksrtn=implode("\n",$listnew);



	if (strpos($dirname,'/mnt/user/')===0) {
		$realdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($dirname)." 2>/dev/null"));
		if (!empty($realdisk)) {
			$dirname = str_replace('/mnt/user/', "/mnt/$realdisk/", $dirname);
		}
	}
	$fstype = trim(shell_exec(" stat -f -c '%T' $dirname"));
	$html = '<table class="snapshot">
	<tr><td>'._('VM Being removed').':</td><td><span id="VMBeingRemoved">'.$domName.'</span></td></tr>
	<tr><td>'._('Remove all files').':</td><td><input type="checkbox" id="All" checked value="" ></td></tr>
	<tr><td>'._('Files being removed').':</td><td><textarea id="textfiles" class="xml" rows="'.$listcount.'" style="white-space: pre; overflow: auto; width:600px" disabled>'.$disksrtn.'</textarea></td></tr>
	<tr><td>'._('Snapshots being removed').':</td><td><textarea id="textsnaps" rows="'.$snapsount.'" cols="80" disabled>'.$datartn.'</textarea></td></tr>
	</table>';
	$arrResponse = ['html' => $html , 'success' => true] ;
	break;

case 'disk-create':
	$disk = $_REQUEST['disk'];
	$driver = $_REQUEST['driver'];
	$size = str_replace(["KB","MB","GB","TB","PB", " ", ","], ["K","M","G","T","P", "", ""], strtoupper($_REQUEST['size']));
	$dir = dirname($disk);
	#if (!is_dir($dir)) mkdir($dir);
	if (!is_dir($dir)) my_mkdir($dir);
	// determine the actual disk if user share is being used
	$dir = transpose_user_path($dir);
	#@exec("chattr +C -R ".escapeshellarg($dir)." >/dev/null");
	$strLastLine = exec("qemu-img create -q -f ".escapeshellarg($driver)." ".escapeshellarg($disk)." ".escapeshellarg($size)." 2>&1", $out, $status);
	$arrResponse = empty($status)
	? ['success' => true]
	: ['error' => $strLastLine];
	break;

case 'disk-resize':
	$disk = $_REQUEST['disk'];
	$capacity = str_replace(["KB","MB","GB","TB","PB", " ", ","], ["K","M","G","T","P", "", ""], strtoupper($_REQUEST['cap']));
	$old_capacity = str_replace(["KB","MB","GB","TB","PB", " ", ","], ["K","M","G","T","P", "", ""], strtoupper($_REQUEST['oldcap']));
	if (substr($old_capacity,0,-1) < substr($capacity,0,-1)){
		$strLastLine = exec("qemu-img resize -q ".escapeshellarg($disk)." ".escapeshellarg($capacity)." 2>&1", $out, $status);
		$arrResponse = empty($status)
		? ['success' => true]
		: ['error' => $strLastLine];
	} else {
		$arrResponse = ['error' => "Disk capacity has to be greater than ".$old_capacity];
	}
	break;

case 'file-info':
	$file = $_REQUEST['file'];
	$arrResponse = [
		'isfile' => (!empty($file) ? is_file($file) : false),
		'isdir' => (!empty($file) ? is_dir($file) : false),
		'isblock' => (!empty($file) ? is_block($file) : false),
		'resizable' => false
	];
	// if file, get size and format info
	if (is_file($file)) {
		$json_info = getDiskImageInfo($file);
		if (!empty($json_info)) {
			$intDisplaySize = (int)$json_info['virtual-size'];
			$intShifts = 0;
			while (!empty($intDisplaySize) && (floor($intDisplaySize) == $intDisplaySize) && isset($arrSizePrefix[$intShifts])) {
				$arrResponse['display-size'] = $intDisplaySize.$arrSizePrefix[$intShifts];
				$intDisplaySize /= 1024;
				$intShifts++;
			}
			$arrResponse['virtual-size'] = $json_info['virtual-size'];
			$arrResponse['actual-size'] = $json_info['actual-size'];
			$arrResponse['format'] = $json_info['format'];
			$arrResponse['dirty-flag'] = $json_info['dirty-flag'];
			$arrResponse['resizable'] = true;
		}
	} elseif (is_block($file)) {
		$strDevSize = trim(shell_exec("blockdev --getsize64 ".escapeshellarg($file)));
		if (!empty($strDevSize) && is_numeric($strDevSize)) {
			$arrResponse['actual-size'] = (int)$strDevSize;
			$arrResponse['format'] = 'raw';
			$intDisplaySize = (int)$strDevSize;
			$intShifts = 0;
			while (!empty($intDisplaySize) && ($intDisplaySize >= 2) && isset($arrSizePrefix[$intShifts])) {
				$arrResponse['display-size'] = round($intDisplaySize, 0).$arrSizePrefix[$intShifts];
				$intDisplaySize /= 1000; // 1000 looks better than 1024 for block devs
				$intShifts++;
			}
		}
	}
	break;

case 'generate-mac':
	requireLibvirt();
	$arrResponse = ['mac' => $lv->generate_random_mac_addr()];
	break;

case 'get-vm-icons':
	$arrImages = [];
	foreach (glob("$docroot/plugins/dynamix.vm.manager/templates/images/*.png") as $png_file) {
		$arrImages[] = [
			'custom' => false,
			'basename' => basename($png_file),
			'url' => '/plugins/dynamix.vm.manager/templates/images/'.basename($png_file)
		];
	}
	$arrResponse = $arrImages;
	break;

case 'get-usb-devices':
	$arrValidUSBDevices = getValidUSBDevices();
	$arrResponse = $arrValidUSBDevices;
	break;

case 'hot-attach-usb':
	//TODO - If usb is a block device, then attach as a <disk type="usb"> otherwise <hostdev type="usb">
	/*
		<hostdev mode='subsystem' type='usb'>
			<source startupPolicy='optional'>
				<vendor id='0x1234'/>
				<product id='0xbeef'/>
			</source>
		</hostdev>
	<disk type='block' device='disk'>
			<driver name='qemu' type='raw'/>
			<source dev='/dev/sda'/>
			<target dev='hdX' bus='virtio'/>
		</disk>
	*/
	break;

case 'hot-detach-usb':
	//TODO
	break;

case 'cmdlineoverride':
	if (is_file('/boot/syslinux/syslinux.cfg')) {
		$cfg = '/boot/syslinux/syslinux.cfg';
		$env = 'syslinux';
		$bootcfg = file($cfg, FILE_IGNORE_NEW_LINES+FILE_SKIP_EMPTY_LINES);
	} elseif (is_file('/boot/grub/grub.cfg')) {
		$cfg = '/boot/grub/grub.cfg';
		$env = 'grub';
		$bootcfg = file($cfg, FILE_IGNORE_NEW_LINES);
	}
	$m1 = embed($bootcfg, $env, 'pcie_acs_override', $_REQUEST['pcie']);
	$m2 = embed($bootcfg, $env, 'vfio_iommu_type1.allow_unsafe_interrupts', $_REQUEST['vfio']);
	if ($m1||$m2) file_put_contents($cfg, implode("\n",$bootcfg)."\n");
	$arrResponse = ['success' => true, 'modified' => $m1|$m2];
	break;

case 'reboot':
	if (is_file('/boot/syslinux/syslinux.cfg')) {
		$cfg = '/boot/syslinux/syslinux.cfg';
		$env = 'syslinux';
	} elseif (is_file('/boot/grub/grub.cfg')) {
		$cfg = '/boot/grub/grub.cfg';
		$env = 'grub';
	}
	$bootcfg = file($cfg, FILE_IGNORE_NEW_LINES+FILE_SKIP_EMPTY_LINES);
	$cmdline = explode(' ',file_get_contents('/proc/cmdline'));
	$pcie = $vfio = '';
	foreach ($cmdline as $cmd) {
		if (scan($cmd,'pcie_acs_override')) $pcie = explode('=',$cmd)[1];
		if (scan($cmd,'allow_unsafe_interrupts')) $vfio = explode('=',$cmd)[1];
	}
	$m1 = embed($bootcfg, $env, 'pcie_acs_override', $pcie);
	$m2 = embed($bootcfg, $env, 'vfio_iommu_type1.allow_unsafe_interrupts', $vfio);
	$arrResponse = ['success' => true, 'modified' => $m1|$m2];
	break;

case 'virtio-win-iso-info':
	$path = $_REQUEST['path'];
	$file = $_REQUEST['file'];
	$pid = pgrep('-f "VirtIOWin_'.basename($file, '.iso').'_install.sh"', false);
	if (empty($file)) {
		$arrResponse = ['exists' => false, 'pid' => $pid];
		break;
	}
	if (is_file($file)) {
		$arrResponse = ['exists' => true, 'pid' => $pid, 'path' => $file];
		break;
	}
	if (empty($path) || !is_dir($path)) {
		$path = '/mnt/user/isos/';
	} else {
		$path = str_replace('//', '/', $path.'/');
	}
	$file = $path.$file;
	$arrResponse = is_file($file)
	? ['exists' => true, 'pid' => $pid, 'path' => $file]
	: ['exists' => false, 'pid' => $pid];
	break;

case 'virtio-win-iso-download':
	$arrDownloadVirtIO = [];
	$strKeyName = basename($_REQUEST['download_version'], '.iso');
	if (array_key_exists($strKeyName, $virtio_isos)) {
		$arrDownloadVirtIO = $virtio_isos[$strKeyName];
	}
	if (empty($arrDownloadVirtIO)) {
		$arrResponse = ['error' => _('Unknown version').': '.$_REQUEST['download_version']];
	} elseif (empty($_REQUEST['download_path'])) {
		$arrResponse = ['error' => _('Specify a ISO storage path first')];
	} elseif (!is_dir($_REQUEST['download_path'])) {
		$arrResponse = ['error' => _("ISO storage path doesn't exist, please create the user share (or empty folder) first")];
	} elseif (substr(realpath($_REQUEST['download_path'])?:'',0,5) != '/mnt/') {
		$arrResponse = ['error' => _('Invalid storage path')];
	} else {
		@mkdir($_REQUEST['download_path'], 0777, true);
		$_REQUEST['download_path'] = realpath($_REQUEST['download_path']).'/';
		// Check free space
		if (disk_free_space($_REQUEST['download_path']) < $arrDownloadVirtIO['size']+10000) {
			$arrResponse['error'] = _('Not enough free space, need at least').' '.ceil($arrDownloadVirtIO['size']/1000000).'MB';
			break;
		}
		$boolCheckOnly = !empty($_REQUEST['checkonly']);
		$strInstallScript = '/tmp/VirtIOWin_'.$strKeyName.'_install.sh';
		$strInstallScriptPgrep = '-f "VirtIOWin_'.$strKeyName.'_install.sh"';
		$strTargetFile = $_REQUEST['download_path'].$arrDownloadVirtIO['name'];
		$strLogFile = $strTargetFile.'.log';
		$strMD5File = $strTargetFile.'.md5';
		$strMD5StatusFile = $strTargetFile.'.md5status';
		// Save to /boot/config/domain.conf
		$domain_cfg['MEDIADIR'] = $_REQUEST['download_path'];
		$domain_cfg['VIRTIOISO'] = $strTargetFile;
		$tmp = ''; $monitor = '/tmp/wget.monitor'; $dots = '... ';
		foreach ($domain_cfg as $key => $value) $tmp .= "$key=\"$value\"\n";
		file_put_contents($domain_cfgfile, $tmp);
		$strDownloadCmd = 'wget -cO '.escapeshellarg($strTargetFile).' '.escapeshellarg($arrDownloadVirtIO['url']);
		$strDownloadPgrep = '-f "wget.*'.$strTargetFile.'.*'.$arrDownloadVirtIO['url'].'"';
		$strVerifyCmd = $arrDownloadVirtIO['md5'] ? 'md5sum -c '.escapeshellarg($strMD5File) : 'md5sum '.escapeshellarg($strTargetFile);
		$strVerifyPgrep = '-f "md5sum.*'.($arrDownloadVirtIO['md5'] ? $strMD5File : $strTargetFile).'"';
		$strCleanCmd = '(chmod 777 '.escapeshellarg($_REQUEST['download_path']).' '.escapeshellarg($strTargetFile).'; chown nobody:users '.escapeshellarg($_REQUEST['download_path']).' '.escapeshellarg($strTargetFile).'; rm -f '.escapeshellarg($strMD5File).' '.escapeshellarg($strMD5StatusFile).')';
		//$strCleanPgrep = '-f "chmod.*chown.*rm.*'.$strMD5StatusFile.'"';
		$strAllCmd = "#!/bin/bash\n\n";
		$strAllCmd .= $strDownloadCmd.' >>'.escapeshellarg($strLogFile)." 2>$monitor && sleep 1 && ";
		$strAllCmd .= 'echo "'.$arrDownloadVirtIO['md5'].'  '.$strTargetFile.'" >'.escapeshellarg($strMD5File).' && sleep 3 && ';
		$strAllCmd .= $strVerifyCmd.' >'.escapeshellarg($strMD5StatusFile).' 2>/dev/null && sleep 3 && ';
		$strAllCmd .= $strCleanCmd.' >>'.escapeshellarg($strLogFile).' 2>&1 && ';
		$strAllCmd .= 'rm -f '.escapeshellarg($strLogFile).' '.escapeshellarg($strInstallScript).' '.escapeshellarg($monitor);
		$arrResponse = [];
		if (file_exists($strTargetFile)) {
			if (!file_exists($strLogFile)) {
				if (!pgrep($strDownloadPgrep, false)) {
					// Status = done
					$arrResponse['status'] = _('Done');
					$arrResponse['localpath'] = $strTargetFile;
					$arrResponse['localfolder'] = dirname($strTargetFile);
				} else {
					// Status = cleanup
					$arrResponse['status'] = _('Cleanup').$dots;
				}
			} else {
				if (pgrep($strDownloadPgrep, false)) {
					// Get Download progress and eta
					[$done,$eta] = my_explode(' ',exec("tail -2 $monitor|awk 'NF==9 {print \$7,\$9;exit}'"));
					$arrResponse['status'] = _('Downloading').$dots.$done.',&nbsp;&nbsp;'._('ETA').': '.$eta;
				} elseif (pgrep($strVerifyPgrep, false)) {
					// Status = running md5 check
					$arrResponse['status'] = _('Verifying').$dots;
				} elseif (file_exists($strMD5StatusFile)) {
					// Status = running extract
					$arrResponse['status'] = _('Cleanup').$dots;
					// Examine md5 status
					$strMD5StatusContents = file_get_contents($strMD5StatusFile);
					if (strpos($strMD5StatusContents, ': FAILED')!==false) {
						// ERROR: MD5 check failed
						unset($arrResponse['status']);
						$arrResponse['error'] = _('MD5 verification failed, your download is incomplete or corrupted');
					} elseif (!$arrDownloadVirtIO['md5']) {
						// MD5 checksum & size missing: add these
						$fedora = '/var/tmp/fedora-virtio-isos';
						if (file_exists($fedora)) {
							$virtio_isos = unserialize(file_get_contents($fedora));
							$iso = basename($arrDownloadVirtIO['name'], '.iso');
							$virtio_isos[$iso]['size'] = filesize($strTargetFile);
							$virtio_isos[$iso]['md5'] = explode(' ',$strMD5StatusContents)[0];
							file_put_contents($fedora,serialize($virtio_isos));
						}
					}
				} elseif (!file_exists($strMD5File)) {
					@unlink($monitor);
					// Status = running md5 check
					$arrResponse['status'] = _('Downloading').$dots.'100%';
					if (!pgrep($strInstallScriptPgrep, false) && !$boolCheckOnly) {
						// Run all commands
						file_put_contents($strInstallScript, $strAllCmd);
						chmod($strInstallScript, 0777);
						exec($strInstallScript.' >/dev/null 2>&1 &');
					}
				}
			}
		} elseif (!$boolCheckOnly) {
			@unlink($monitor);
			if (!pgrep($strInstallScriptPgrep, false)) {
				// Run all commands
				file_put_contents($strInstallScript, $strAllCmd);
				chmod($strInstallScript, 0777);
				exec($strInstallScript.' >/dev/null 2>&1 &');
			}
			$arrResponse['status'] = _('Downloading').$dots.'0%';
		}
		$arrResponse['pid'] = pgrep($strInstallScriptPgrep, false);
	}
	break;

case 'virtio-win-iso-cancel':
	$arrDownloadVirtIO = [];
	$strKeyName = basename($_REQUEST['download_version'], '.iso');
	if (array_key_exists($strKeyName, $virtio_isos)) {
		$arrDownloadVirtIO = $virtio_isos[$strKeyName];
	}
	if (empty($arrDownloadVirtIO)) {
		$arrResponse = ['error' => _('Unknown version').': '.$_REQUEST['download_version']];
	} elseif (empty($_REQUEST['download_path'])) {
		$arrResponse = ['error' => _('ISO storage path was empty')];
	} elseif (!is_dir($_REQUEST['download_path'])) {
		$arrResponse = ['error' => _("ISO storage path doesn't exist")];
	} else {
		$strInstallScriptPgrep = '-f "VirtIOWin_'.$strKeyName.'_install.sh"';
		$pid = pgrep($strInstallScriptPgrep, false);
		if (!$pid) {
			$arrResponse = ['error' => _('Not running')];
		} else {
			if (!posix_kill($pid, SIGTERM)) {
				$arrResponse = ['error' => _("Wasn't able to stop the process")];
			} else {
				$strTargetFile = $_REQUEST['download_path'].$arrDownloadVirtIO['name'];
				$strLogFile = $strTargetFile.'.log';
				$strMD5File = $strTargetFile.'.md5';
				$strMD5StatusFile = $strTargetFile.'.md5status';
				@unlink($strTargetFile);
				@unlink($strMD5File);
				@unlink($strMD5StatusFile);
				@unlink($strLogFile);
				$arrResponse['status'] = _('Done');
			}
		}
	}
	break;

case 'virtio-win-iso-remove':
	$path = $_REQUEST['path'];
	$file = $_REQUEST['file'];
	$pid = pgrep('-f "VirtIOWin_'.basename($file, '.iso').'_install.sh"', false);
	if (empty($file) || substr($file, -4) !== '.iso') {
		$arrResponse = ['success' => false];
		break;
	}
	if ($pid !== false) {
		$arrResponse = ['success' => false];
		break;
	}
	if (is_file($file)) {
		foreach (glob($file.'*') as $name) unlink($name);
		$arrResponse = ['success' => true];
		break;
	}
	if (empty($path) || !is_dir($path)) {
		$path = '/mnt/user/isos/';
	} else {
		$path = str_replace('//', '/', $path.'/');
	}
	if (is_file($path.$file)) {
		foreach (glob($path.$file.'*') as $name) unlink($name);
		$arrResponse = ['success' => true];
	}
	break;

case 'vm-template-remove':
	$template = $_REQUEST['template'];	
	$templateslocation = "/boot/config/plugins/dynamix.vm.manager/savedtemplates.json";
	if (is_file($templateslocation)){
		$ut = json_decode(file_get_contents($templateslocation),true) ;
		unset($ut[$template]);
		file_put_contents($templateslocation,json_encode($ut,JSON_PRETTY_PRINT));
	}
	$arrResponse = ['success' => true];
	break;

case 'vm-template-save':
	$template = $_REQUEST['template'];	
	$name = $_REQUEST['name'];	
	$replace = $_REQUEST['replace'];	

	if (is_file($name) && $replace == "no"){
		$arrResponse = ['success' => false, 'error' => _("File exists.")];
	} else {
		$error = file_put_contents($name,json_encode($template));
		if ($error !== false)  $arrResponse = ['success' => true]; 
		else {
			$arrResponse = ['success' => false, 'error' => _("File write failed.")];
		}
	}
	break;

case 'vm-template-import':
	$template = $_REQUEST['template'];	
	$name = $_REQUEST['name'];	
	$replace = $_REQUEST['replace'];	
	$templateslocation = "/boot/config/plugins/dynamix.vm.manager/savedtemplates.json";

	if ($template==="*file") {
		$template=json_decode(file_get_contents($name));
	}

	$namepathinfo = pathinfo($name);
	$template_name = $namepathinfo['filename'];

	if (is_file($templateslocation)){
		$ut = json_decode(file_get_contents($templateslocation),true) ;
		if (isset($ut[$template_name]) && $replace == "no"){
			$arrResponse = ['success' => false, 'error' => _("Template exists.")];
		} else {
			$ut[$template_name] = $template;
			$error = file_put_contents($templateslocation,json_encode($ut,JSON_PRETTY_PRINT));;
			if ($error !== false)  $arrResponse = ['success' => true]; 
			else {
				$arrResponse = ['success' => false, 'error' => _("Tempalte file write failed.")];
			}
		}
	}
	break;
	
default:
	$arrResponse = ['error' => _('Unknown action')." '$action'"];
	break;
}

header('Content-Type: application/json');
die(json_encode($arrResponse));
