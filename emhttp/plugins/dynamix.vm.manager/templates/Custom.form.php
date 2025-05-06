<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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
require_once "$docroot/webGui/include/Custom.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

// add translations
if (substr($_SERVER['REQUEST_URI'],0,4) != '/VMs') {
	$_SERVER['REQUEST_URI'] = 'vms';
	require_once "$docroot/webGui/include/Translations.php";
}

$arrValidMachineTypes = getValidMachineTypes();
$arrValidPCIDevices   = getValidPCIDevices();
$arrValidGPUDevices   = getValidGPUDevices();
$arrValidAudioDevices = getValidAudioDevices();
$arrValidSoundCards   = getValidSoundCards();
$arrValidOtherDevices = getValidOtherDevices();
$arrValidUSBDevices   = getValidUSBDevices();
$arrValidDiskDrivers  = getValidDiskDrivers();
$arrValidDiskBuses    = getValidDiskBuses();
$arrValidDiskDiscard  = getValidDiskDiscard();
$arrValidCdromBuses   = getValidCdromBuses();
$arrValidVNCModels    = getValidVNCModels();
$arrValidProtocols    = getValidVMRCProtocols();
$arrValidKeyMaps      = getValidKeyMaps();
$arrValidNetworks     = getValidNetworks();
$strCPUModel          = getHostCPUModel();
$templateslocation    = "/boot/config/plugins/dynamix.vm.manager/savedtemplates.json";

if (is_file($templateslocation)){
	$arrAllTemplates["User-templates"] = "";
	$ut = json_decode(file_get_contents($templateslocation),true);
	$arrAllTemplates = array_merge($arrAllTemplates, $ut);
}

$arrConfigDefaults = [
	'template' => [
		'name' => $strSelectedTemplate,
		'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
		'os' => $arrAllTemplates[$strSelectedTemplate]['os'],
		'storage' => "default"
	],
	'domain' => [
		'name' => $strSelectedTemplate,
		'persistent' => 1,
		'uuid' => $lv->domain_generate_uuid(),
		'clock' => 'localtime',
		'arch' => 'x86_64',
		'machine' => 'pc-i440fx',
		'mem' => 1024 * 1024,
		'maxmem' => 1024 * 1024,
		'password' => '',
		'cpumode' => 'host-passthrough',
		'cpumigrate' => 'on',
		'vcpus' => 1,
		'vcpu' => [0],
		'hyperv' => 1,
		'ovmf' => 1,
		'usbmode' => 'usb2',
		'memoryBacking' => '{"nosharepages":{}}'
	],
	'media' => [
		'cdrom' => '',
		'cdrombus' => 'ide',
		'drivers' => is_file($domain_cfg['VIRTIOISO']) ? $domain_cfg['VIRTIOISO'] : '',
		'driversbus' => 'ide' ,
		'cdromboot' => 2
	],
	'disk' => [
		[
			'new' => '',
			'size' => '',
			'driver' => 'raw',
			'dev' => 'hda',
			'select' => $domain_cfg['VMSTORAGEMODE'],
			'bus' => 'virtio' ,
			'boot' => 1,
			'serial' => 'vdisk1',
			'discard' => 'unmap'
		]
	],
	'gpu' => [
		[
			'id' => 'virtual',
			'protocol' => 'vnc',
			'autoport' => 'yes',
			'model' => 'qxl',
			'keymap' => 'none',
			'port' => -1 ,
			'wsport' => -1,
			'copypaste' => 'no',
			'render' => 'auto',
			'DisplayOptions' => ""
		]
	],
	'audio' => [
		[
			'id' => ''
		]
	],
	'pci' => [],
	'nic' => [
		[
			'network' => $domain_bridge,
			'mac' => $lv->generate_random_mac_addr(),
			'model' => 'virtio-net'
		]
	],
	'usb' => [],
	'shares' => [
		[
			'source' => '',
			'target' => '',
			'mode' => ''
		]
	]
];
$hdrXML = "<?xml version='1.0' encoding='UTF-8'?>\n"; // XML encoding declaration

// Merge in any default values from the VM template
if ($arrAllTemplates[$strSelectedTemplate] && $arrAllTemplates[$strSelectedTemplate]['overrides']) {
	$arrConfigDefaults = array_replace_recursive($arrConfigDefaults, $arrAllTemplates[$strSelectedTemplate]['overrides']);
}
// create new VM
if (isset($_POST['createvm'])) {
	if (isset($_POST['xmldesc'])) {
		// XML view
		$new = $lv->domain_define($_POST['xmldesc'], $_POST['domain']['xmlstartnow']==1);
		if ($new){
			$lv->domain_set_autostart($new, $_POST['domain']['autostart']==1);
			$reply = ['success' => true];
		} else {
			$reply = ['error' => $lv->get_last_error()];
		}
	} else {
		// form view
		if ($lv->domain_new($_POST)) {
			// Fire off the vnc/spice popup if available
			$dom = $lv->get_domain_by_name($_POST['domain']['name']);
			$vmrcport = $lv->domain_get_vnc_port($dom);
			$wsport = $lv->domain_get_ws_port($dom);
			$protocol = $lv->domain_get_vmrc_protocol($dom);
			$reply = ['success' => true];
			if ($vmrcport > 0) {
				if ($protocol == "vnc") $vmrcscale = "&resize=scale"; else $vmrcscale = "";
				$reply['vmrcurl'] = autov('/plugins/dynamix.vm.manager/'.$protocol.'.html',true).'&autoconnect=true'.$vmrcscale.'&host='.$_SERVER['HTTP_HOST'];
				if ($protocol == "spice") $reply['vmrcurl'] .= '&port=/wsproxy/'.$vmrcport.'/'; else $reply['vmrcurl'] .= '&port=&path=/wsproxy/'.$wsport.'/';
			}
		} else {
			$reply = ['error' => $lv->get_last_error()];
		}
	}
	echo json_encode($reply);
	exit;
}

	// create new VM template
	if (isset($_POST['createvmtemplate'])) {
		$reply = addtemplatexml($_POST);
		echo json_encode($reply);
		exit;
	}

// update existing VM
if (isset($_POST['updatevm'])) {
	$uuid = $_POST['domain']['uuid'];
	$dom = $lv->domain_get_domain_by_uuid($uuid);
	$oldAutoStart = $lv->domain_get_autostart($dom)==1;
	$newAutoStart = $_POST['domain']['autostart']==1;
	$strXML = $lv->domain_get_xml($dom);

	if ($lv->domain_get_state($dom)=='running') {
		$arrErrors = [];
		$arrExistingConfig = domain_to_config($uuid);
		$arrNewUSBIDs = $_POST['usb'];

		// hot-attach any new usb devices
		foreach ($arrNewUSBIDs as $strNewUSBID) {
			if (strpos($strNewUSBID,"#remove")) continue;
			$remove = explode('#', $strNewUSBID);
			$strNewUSBID2 = $remove[0];
			foreach ($arrExistingConfig['usb'] as $arrExistingUSB) {
				if ($strNewUSBID2 == $arrExistingUSB['id']) continue 2;
			}
			[$strVendor,$strProduct] = my_explode(':', $strNewUSBID2);
			// hot-attach usb
			file_put_contents('/tmp/hotattach.tmp', "<hostdev mode='subsystem' type='usb'><source startupPolicy='optional'><vendor id='0x".$strVendor."'/><product id='0x".$strProduct."'/></source></hostdev>");
			exec("virsh attach-device ".escapeshellarg($uuid)." /tmp/hotattach.tmp --live 2>&1", $arrOutput, $intReturnCode);
			unlink('/tmp/hotattach.tmp');
			if ($intReturnCode != 0) {
				$arrErrors[] = implode(' ', $arrOutput);
			}
		}

		// hot-detach any old usb devices
		foreach ($arrExistingConfig['usb'] as $arrExistingUSB) {
			if (!in_array($arrExistingUSB['id'], $arrNewUSBIDs)) {
				[$strVendor, $strProduct] = my_explode(':', $arrExistingUSB['id']);
				file_put_contents('/tmp/hotdetach.tmp', "<hostdev mode='subsystem' type='usb'><source startupPolicy='optional'><vendor id='0x".$strVendor."'/><product id='0x".$strProduct."'/></source></hostdev>");
				exec("virsh detach-device ".escapeshellarg($uuid)." /tmp/hotdetach.tmp --live 2>&1", $arrOutput, $intReturnCode);
				unlink('/tmp/hotdetach.tmp');
				if ($intReturnCode != 0) $arrErrors[] = implode(' ',$arrOutput);
			}
		}
		$reply = !$arrErrors ? ['success' => true] : ['error' => implode(', ',$arrErrors)];
		echo json_encode($reply);
		exit;
	}

	// backup xml for existing domain in ram
	if ($dom && empty($_POST['xmldesc'])) {
		$oldName = $lv->domain_get_name($dom);
		$newName = $_POST['domain']['name'];
		$oldDir = $domain_cfg['DOMAINDIR'].$oldName;
		$newDir = $domain_cfg['DOMAINDIR'].$newName;
		if ($oldName && $newName && is_dir($oldDir) && !is_dir($newDir)) {
			// mv domain/vmname folder
			if (rename($oldDir, $newDir)) {
				// replace all disk paths in xml
				foreach ($_POST['disk'] as &$arrDisk) {
					if ($arrDisk['new']) $arrDisk['new'] = str_replace($oldDir, $newDir, $arrDisk['new']);
					if ($arrDisk['image']) $arrDisk['image'] = str_replace($oldDir, $newDir, $arrDisk['image']);
				}
			}
		}
	}
	$newuuid = $uuid;
	$olduuid = $uuid;
	// construct updated config
	if (isset($_POST['xmldesc'])) {
		// XML view
		$xml = $_POST['xmldesc'];
		$arrExistingConfig = custom::createArray('domain',$xml);
		$newuuid = $arrExistingConfig['uuid'];
		if ($_POST['template']['iconold'] != $_POST['template']['icon']) $xml = preg_replace('/icon="[^"]*"/','icon="' . $_POST['template']['icon'] . '"',$xml);
		$xml = str_replace($olduuid,$newuuid,$xml);
	} else {
		// form view
		if ($error = create_vdisk($_POST) === false) {
			$arrExistingConfig = custom::createArray('domain',$strXML);
			$arrUpdatedConfig = custom::createArray('domain',$lv->config_to_xml($_POST));
			array_update_recursive($arrExistingConfig, $arrUpdatedConfig);
			$arrConfig = array_replace_recursive($arrExistingConfig, $arrUpdatedConfig);
			$xml = custom::createXML('domain',$arrConfig)->saveXML();
			$xml = $lv->appendqemucmdline($xml,$_POST["qemucmdline"]);
		} else {
			echo json_encode(['error' => $error]);
			exit;
		}
	}
	// delete and create the VM
	$lv->nvram_backup($uuid);
	$lv->domain_undefine($dom);
	$lv->nvram_restore($uuid);
	if ($newuuid != $olduuid) $lv->nvram_rename($olduuid,$newuuid);
	$new = $lv->domain_define($xml);
	if ($new) {
		$lv->domain_set_autostart($new, $newAutoStart);
		$reply = ['success' => true];
	} else {
		// Failure -- try to restore existing VM
		$reply = ['error' => $lv->get_last_error()];
		$old = $lv->domain_define($strXML);
		if ($old) $lv->domain_set_autostart($old, $oldAutoStart);
	}
	echo json_encode($reply);
	exit;
}

if (isset($_GET['uuid'])) {
	// edit an existing VM
	$uuid = unscript($_GET['uuid']);
	$dom = $lv->domain_get_domain_by_uuid($uuid);
	$boolRunning = $lv->domain_get_state($dom)=='running';
	$strXML = $lv->domain_get_xml($dom);
	$boolNew = false;
	$arrConfig = array_replace_recursive($arrConfigDefaults, domain_to_config($uuid));
	$arrVMUSBs = getVMUSBs($strXML);
} else {
	// edit new VM
	$boolRunning = false;
	$strXML = '';
	$boolNew = true;
	$arrConfig = $arrConfigDefaults;
	$arrVMUSBs = getVMUSBs($strXML);
	$strXML = $lv->config_to_xml($arrConfig);
	$domXML = new DOMDocument();
	$domXML->preserveWhiteSpace = false;
	$domXML->formatOutput = true;
	$domXML->loadXML($strXML);
	$strXML= $domXML->saveXML();
}

// Add any custom metadata field defaults (e.g. os)
if (!$arrConfig['template']['os']) {
	$arrConfig['template']['os'] = ($arrConfig['domain']['clock']=='localtime' ? 'windows' : 'linux');
}

$os_type = ((empty($arrConfig['template']['os']) || stripos($arrConfig['template']['os'], 'windows') === false) ? 'other' : 'windows');
if (isset($arrConfig['clocks'])) $arrClocks = json_decode($arrConfig['clocks'],true); else {
	if ($os_type == "windows") {
		if ($arrConfig['domain']['hyperv'] == 1) $arrClocks = $arrDefaultClocks['hyperv']; else $arrClocks = $arrDefaultClocks['windows'];
	} else $arrClocks = $arrDefaultClocks['other'];
}

if (strpos($arrConfig['template']['name'],"User-") !== false) {
	$arrConfig['template']['name'] = str_replace("User-","",$arrConfig['template']['name']);
	unset($arrConfig['domain']['uuid']);
}

if ($usertemplate == 1) unset($arrConfig['domain']['uuid']);
$xml2 = build_xml_templates($strXML);
#disable rename if snapshots exist
$snapshots = getvmsnapshots($arrConfig['domain']['name']);

if ($snapshots!=null && count($snapshots) && !$boolNew) {
	$snaphidden = "";
	$namedisable = "disabled";
	$snapcount = count($snapshots);
} else {
	$snaphidden = "hidden";
	$namedisable = "";
	$snapcount = "0";
}
?>

<link rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/lib/codemirror.css')?>">
<link rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/show-hint.css')?>">

<div class="formview">
<input type="hidden" name="template[os]" id="template_os" value="<?=htmlspecialchars($arrConfig['template']['os'])?>">
<input type="hidden" name="domain[persistent]" value="<?=htmlspecialchars($arrConfig['domain']['persistent'])?>">
<input type="hidden" name="domain[uuid]" value="<?=htmlspecialchars($arrConfig['domain']['uuid'])?>">
<input type="hidden" name="domain[arch]" value="<?=htmlspecialchars($arrConfig['domain']['arch'])?>">
<input type="hidden" name="domain[oldname]" id="domain_oldname" value="<?=htmlspecialchars($arrConfig['domain']['name'])?>">
<!--<input type="hidden" name="template[oldstorage]" id="storage_oldname" value="<?=htmlspecialchars($arrConfig['template']['storage'])?>"> -->
<input type="hidden" name="domain[memoryBacking]" id="domain_memorybacking" value="<?=htmlspecialchars($arrConfig['domain']['memoryBacking'])?>">

<table>
	<tr class="<?=$snaphidden?>">
		<td></td>
		<td><span class="orange-text"><i class="fa fa-fw fa-warning"></i> _(Rename disabled, <?=$snapcount?> snapshot(s) exists)_.</span></td>
		<td></td>
	</tr>
	<tr id="zfs-name" class="hidden">
		<td></td>
		<td>
			<span class="orange-text"><i class="fa fa-fw fa-warning"></i> _(Name contains invalid characters or does not start with an alphanumberic for a ZFS storage location)_</span><br>
			<span class="green-text"><i class="fa fa-fw fa-info-circle"></i> _(Only these special characters are valid Underscore (_) Hyphen (-) Colon (:) Period (.))_</span>
		</td>
		<td></td>
	</tr>
	<tr>
		<td>_(Name)_:</td>
		<td>
			<span class="width"><input <?=$namedisable?> type="text" name="domain[name]" id="domain_name" oninput="checkName(this.value)" class="textTemplate" placeholder="_(e.g.)_ _(My Workstation)_" value="<?=htmlspecialchars($arrConfig['domain']['name'])?>" required/></span>
		</td>
		<td>
			<textarea class="xml" id="xmlname" rows="1" disabled><?=htmlspecialchars($xml2['name'])."\n".htmlspecialchars($xml2['uuid'])."\n".htmlspecialchars($xml2['metadata'])?></textarea>
		</td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>Give the VM a name (e.g. Work, Gaming, Media Player, Firewall, Bitcoin Miner)</p>
</blockquote>

<table>
	<tr class="advanced">
		<td>_(Description)_:</td>
		<td>
			<span class="width"><input type="text" name="domain[desc]" placeholder="_(description of virtual machine)_ (_(optional)_)" value="<?=htmlspecialchars($arrConfig['domain']['desc'])?>"/></span>
		</td>
		<td>
			<textarea class="xml" id="xmldesription" rows="1" disabled><?=htmlspecialchars($xml2['description'])?></textarea>
		</td>
	</tr>
</table>

<div class="advanced">
	<blockquote class="inline_help">
		<p>Give the VM a brief description (optional field).</p>
	</blockquote>
</div>

<table>
	<tr class="advanced">
		<td>_(WebUI)_:</td>
		<td>
			<span class="width"><input type="url" name="template[webui]" placeholder="_(Web UI to start from menu)_ (_(optional)_)" value="<?=htmlspecialchars($arrConfig['template']['webui'])?>"/></span>
		</td>
		<td></td>
	</tr>
</table>

<div class="advanced">
	<blockquote class="inline_help">
		<p>Specify a URL that for menu to start. Substitution variables are
			<br>[IP] IP address, this will take the first IP on the VM. Guest Agent must be installed for this to work.
			<br>[PORT:XX] Port Number in XX.
			<br>[VMNAME] VM Name will have spaces replaced with -
		</p>
	</blockquote>
</div>

<table>
	<tr>
		<?if (!$boolNew) $disablestorage = "disabled"; else $disablestorage = "";?>
		<td>_(Override Storage Location)_:</td>
		<td>
			<span class="width"><select <?=$disablestorage?> name="template[storage]" onchange="get_storage_fstype(this)" class="disk_select narrow" id="storage_location">
			<?
			$default_storage=htmlspecialchars($arrConfig['template']['storage']);
			echo mk_option($default_storage, 'default', _('Default'));
			$strShareUserLocalInclude = '';
			$strShareUserLocalExclude = '';
			$strShareUserLocalUseCache = 'no';
			// Get the share name and its configuration
			$arrDomainDirParts = explode('/', $domain_cfg['DOMAINDIR']);
			$strShareName = $arrDomainDirParts[3];
			if (!empty($strShareName) && is_file('/boot/config/shares/'.$strShareName.'.cfg')) {
				$arrShareCfg = parse_ini_file('/boot/config/shares/'.$strShareName.'.cfg');
				if (!empty($arrShareCfg['shareInclude'])) {
					$strShareUserLocalInclude = $arrShareCfg['shareInclude'];
				}
				if (!empty($arrShareCfg['shareExclude'])) {
					$strShareUserLocalExclude = $arrShareCfg['shareExclude'];
				}
				if (!empty($arrShareCfg['shareUseCache'])) {
					$strShareUserLocalUseCache = $arrShareCfg['shareUseCache'];
				}
			}
			// Available cache pools
			foreach ($pools as $pool) {
				if (isSubpool($pool)) continue;
				$strLabel = $pool.' - '.my_scale($disks[$pool]['fsFree']*1024, $strUnit).' '.$strUnit.' '._('free');
				echo mk_option($default_storage, $pool, $strLabel);
			}
			// Determine which disks from the array are available for this share:
			foreach ($disks as $name => $disk) {
				if ((strpos($name, 'disk') === 0) && (!empty($disk['device']))) {
					if ((!empty($strShareUserLocalInclude) && (strpos($strShareUserLocalInclude.',', $name.',') === false)) ||
						(!empty($strShareUserLocalExclude) && (strpos($strShareUserLocalExclude.',', $name.',') !== false)) ||
						(!empty($var['shareUserInclude']) && (strpos($var['shareUserInclude'].',', $name.',') === false)) ||
						(!empty($var['shareUserExclude']) && (strpos($var['shareUserExclude'].',', $name.',') !== false))) {
						// skip this disk based on local and global share settings
						continue;
					}
					$strLabel = _(my_disk($name),3).' - '.my_scale($disk['fsFree']*1024, $strUnit).' '.$strUnit.' '._('free');
					echo mk_option($default_storage, $name, $strLabel);
				}
			}
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>
		Specify the overide storage pool for VM. This option allows you to specify the physical pool/disk used to store the disk images and snapshot data.
		Default will follow standard processing and store images in the default location for the share defined in the settings.
		A pool/disk(Volume) will be the location for images if the default is overridden.
	</p>
</blockquote>

<?$migratehidden = $arrConfig['domain']['cpumode']=='host-passthrough' ? "" : "hidden";?>

<table>
	<tr class="advanced">
		<td><span class="advanced">_(CPU)_ </span>_(Mode)_:</td>
		<td>
			<span class="width"><select id="cpu" name="domain[cpumode]" class="cpu narrow">
			<?mk_dropdown_options(['host-passthrough' => _('Host Passthrough').' ('.$strCPUModel.')', 'custom' => _('Emulated').' ('._('QEMU64').')'], $arrConfig['domain']['cpumode']);?>
			</select></span>
			<span class="advanced label <?=$migratehidden?>" id="domain_cpumigrate_text">_(Migratable)_:</span>
			<select name="domain[cpumigrate]" id="domain_cpumigrate" class="narrow second <?=$migratehidden?>">
			<?
			echo mk_option($arrConfig['domain']['cpumigrate'], 'on', 'On');
			echo mk_option($arrConfig['domain']['cpumigrate'], 'off', 'Off');
			?>
			</select>
		</td>
		<td>
			<textarea class="xml" id="xmlcpu" rows="1" disabled ><?=htmlspecialchars($xml2['cpu'])?></textarea>
		</td>
	</tr>
</table>

<div class="advanced">
	<blockquote class="inline_help">
		<p>There are two CPU modes available to choose:</p>
		<p>
			<b>Host Passthrough</b><br>
			With this mode, the CPU visible to the guest should be exactly the same as the host CPU even in the aspects that libvirt does not understand. For the best possible performance, use this setting.
		</p>
		<p>
			<b>Emulated</b><br>
			If you are having difficulties with Host Passthrough mode, you can try the emulated mode which doesn't expose the guest to host-based CPU features. This may impact the performance of your VM.
		</p>
		<p>
			<b>Migratable</b><br>
			Migratable attribute may be used to explicitly request such features to be removed from (on) or kept in (off) the virtual CPU. Off will not remove any host features when using Host Passthrough. Not support on emulated.
		</p>
	</blockquote>
</div>

<table>
	<tr class="advanced">
		<?$cpus = cpu_list();
		$corecount = 0;
		foreach ($cpus as $pair) {
			unset($cpu1,$cpu2);
			[$cpu1, $cpu2] = my_preg_split('/[,-]/',$pair);
			if (!$cpu2) 	$corecount++; else $corecount=$corecount+2;
		}
		if (is_array($arrConfig['domain']['vcpu'])) {$coredisable = "disabled"; $vcpubuttontext = "Deselect all";} else {$coredisable = ""; $vcpubuttontext = "Select all";}
		?>
		<td><span class="advanced">_(vCPUs)_:</span></td>
		<td>
			<span class="width"><select id="vcpus" <?=$coredisable?> name="domain[vcpus]" class="domain_vcpus narrow">
			<?for ($i = 1; $i <= ($corecount); $i++) echo mk_option($arrConfig['domain']['vcpus'], $i, $i);?>
			</select>
			<input type="button" value="_(<?=$vcpubuttontext?>)_" id="btnvCPUSelect"/></span>
		</td>
		<td></td>
	</tr>
</table>

<div class="advanced">
	<blockquote class="inline_help">
		<p>There are two CPU modes available to choose:</p>
		<p>
			<b>vCPUs Allocated</b><br>
			Set the number of vCPUs allocated to the VM when not using pinning. The host will dynamically allocate workload for the VM across the whole system.
	</blockquote>
</div>

<table>
	<tr>
		<td>_(Pinned Cores)_:</td>
		<td>
			<div class="textarea four">
			<?
			$is_intel_cpu = is_intel_cpu();
			$core_types = $is_intel_cpu ? get_intel_core_types() : [];
			foreach ($cpus as $pair) {
				unset($cpu1,$cpu2);
				[$cpu1, $cpu2] = my_preg_split('/[,-]/',$pair);
				$extra = ($arrConfig['domain']['vcpu'] && in_array($cpu1, $arrConfig['domain']['vcpu'])) ? ($arrConfig['domain']['vcpus'] > 1 ? 'checked' : 'checked disabled') : '';
				if ($is_intel_cpu && count($core_types) > 0) $core_type = "{$core_types[$cpu1]}"; else $core_type = "";
				if (!$cpu2) {
					echo "<label for='vcpu$cpu1' title='$core_type' class='checkbox'>cpu $cpu1<input type='checkbox' name='domain[vcpu][]' class='domain_vcpu' id='vcpu$cpu1' value='$cpu1' $extra><span class='checkmark'></span></label>";
				} else {
					echo "<label for='vcpu$cpu1' title='$core_type'  class='cpu1 checkbox'>cpu $cpu1 / $cpu2<input type='checkbox' name='domain[vcpu][]' class='domain_vcpu' id='vcpu$cpu1' value='$cpu1' $extra><span class='checkmark'></span></label>";
					$extra = ($arrConfig['domain']['vcpu'] && in_array($cpu2, $arrConfig['domain']['vcpu'])) ? ($arrConfig['domain']['vcpus'] > 1 ? 'checked' : 'checked disabled') : '';
					echo "<label for='vcpu$cpu2' title='$core_type' class='cpu2 checkbox'><input type='checkbox' name='domain[vcpu][]' class='domain_vcpu' id='vcpu$cpu2' value='$cpu2' $extra><span class='checkmark'></span></label>";
				}
			}
			?>
			</div>
		</td>
		<td>
			<textarea class="xml" id="xmlvcpu" rows="5" disabled ><?=htmlspecialchars($xml2['vcpu'])."\n".htmlspecialchars($xml2['cputune'])?></textarea>
		</td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>The number of available cores in your system is determined by multiplying the number of CPU cores on your processor(s) by the number of threads. But this will only be for cores that support hyperthreding.</p>
	<p>Select which pinned CPUs you wish to allow your VM to use. If no pinned cores are selected the vCPU value is used to determin the allocation within the VM.</p>
</blockquote>

<table>
	<tr>
		<td>
			<span class="advanced">_(Initial)_ </span>_(Memory)_:
		</td>
		<td>
			<span class="width"><select name="domain[mem]" id="domain_mem" class="narrow">
			<?
			echo mk_option($arrConfig['domain']['mem'], 128 * 1024, '128 MB');
			echo mk_option($arrConfig['domain']['mem'], 256 * 1024, '256 MB');
			for ($i = 1; $i <= ($maxmem*2); $i++) {
				$label = ($i * 512).' MB';
				$value = $i * 512 * 1024;
				echo mk_option($arrConfig['domain']['mem'], $value, $label);
			}
			?>
			</select></span>
			<span class="advanced label">_(Max)_ _(Memory)_:</span>
			<select name="domain[maxmem]" id="domain_maxmem" class="narrow second">
			<?
			echo mk_option($arrConfig['domain']['maxmem'], 128 * 1024, '128 MB');
			echo mk_option($arrConfig['domain']['maxmem'], 256 * 1024, '256 MB');
			for ($i = 1; $i <= ($maxmem*2); $i++) {
				$label = ($i * 512).' MB';
				$value = $i * 512 * 1024;
				echo mk_option($arrConfig['domain']['maxmem'], $value, $label);
			}
			?>
			</select>
		</td>
		<td>
			<textarea class="xml" id="xmlmem" rows="2" disabled ><?=htmlspecialchars($xml2['memory'])."\n".htmlspecialchars($xml2['currentMemory'])."\n".htmlspecialchars($xml2['memoryBacking'])?></textarea>
		</td>
	</tr>
</table>

<div class="basic">
	<blockquote class="inline_help">
		<p>Select how much memory to allocate to the VM at boot.</p>
	</blockquote>
</div>

<div class="advanced">
	<blockquote class="inline_help">
		<p>For VMs where no PCI devices are being passed through (GPUs, sound, etc.), you can set different values to initial and max memory to allow for memory ballooning.  If you are passing through a PCI device, only the initial memory value is used and the max memory value is ignored.  For more information on KVM memory ballooning, see <a href="http://www.linux-kvm.org/page/FAQ#Is_dynamic_memory_management_for_guests_supported.3F" target="_new">here</a>.</p>
	</blockquote>
</div>

<?
if (!isset($arrValidMachineTypes[$arrConfig['domain']['machine']])) {
	$arrConfig['domain']['machine'] = ValidateMachineType($arrConfig['domain']['machine']);
}
?>

<table>
	<tr class="advanced">
		<td>_(Machine)_:</td>
		<td>
			<span class="width"><select name="domain[machine]" id="domain_machine" class="narrow">
			<?mk_dropdown_options($arrValidMachineTypes, $arrConfig['domain']['machine']);?>
			</select></span>
		</td>
		<td>
			<textarea class="xml" id="xmlos" rows="5" cols="200" disabled ><?=htmlspecialchars($xml2['os'])."\n".htmlspecialchars($xml2['features'])?></textarea>
		</td>
	</tr>
</table>

<div class="advanced">
	<blockquote class="inline_help">
		<p>The machine type option primarily affects the success some users may have with various hardware and GPU pass through.  For more information on the various QEMU machine types, see these links:</p>
		<a href="http://wiki.qemu.org/Documentation/Platforms/PC" target="_blank">http://wiki.qemu.org/Documentation/Platforms/PC</a><br>
		<a href="http://wiki.qemu.org/Features/Q35" target="_blank">http://wiki.qemu.org/Features/Q35</a><br>
		<p>As a rule of thumb, try to get your configuration working with i440fx first and if that fails, try adjusting to Q35 to see if that changes anything.</p>
	</blockquote>
</div>

<table>
	<tr class="advanced">
		<td>_(BIOS)_:</td>
		<td>
			<span class="width"><select name="domain[ovmf]" id="domain_ovmf" onchange="BIOSChange(this.value)" class="narrow">
			<?
			echo mk_option($arrConfig['domain']['ovmf'], '0', _('SeaBIOS'));
			if (file_exists('/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi.fd')) {
				echo mk_option($arrConfig['domain']['ovmf'], '1', _('OVMF'));
			} else {
				echo mk_option('', '0', _('OVMF').' ('._('Not Available').')', 'disabled');
			}
			if (file_exists('/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi-tpm.fd')) {
				echo mk_option($arrConfig['domain']['ovmf'], '2', _('OVMF TPM'));
			} else {
				echo mk_option('', '0', _('OVMF TPM').' ('._('Not Available').')', 'disabled');
			}
			?>
			</select></span>
			<?$usbboothidden = $arrConfig['domain']['ovmf']!='0' ? "" : "hidden";?>
			<span id="USBBoottext" class="advanced label <?=$usbboothidden?>">_(Enable USB boot)_:</span>
			<select name="domain[usbboot]" id="domain_usbboot" class="narrow second <?=$usbboothidden?>" onchange="USBBootChange(this)">
			<?
			echo mk_option($arrConfig['domain']['usbboot'], 'No', 'No');
			echo mk_option($arrConfig['domain']['usbboot'], 'Yes', 'Yes');
			?>
			</select>
		</td>
		<td></td>
	</tr>
</table>

<div class="advanced">
<blockquote class="inline_help">
	<p>
		<b>SeaBIOS</b><br>
		is the default virtual BIOS used to create virtual machines and is compatible with all guest operating systems (Windows, Linux, etc.).
	</p>
	<p>
		<b>OVMF</b><br>
		(Open Virtual Machine Firmware) adds support for booting VMs using UEFI, but virtual machine guests must also support UEFI.  Assigning graphics devices to a OVMF-based virtual machine requires that the graphics device also support UEFI.
	</p>
	<p>
		<b>OVMF TPM</b><br>
		(Open Virtual Machine Firmware) adds support for booting VMs using UEFI with TPM and Secure Boot available, but virtual machine guests must also support UEFI.  Assigning graphics devices to a OVMF-based virtual machine requires that the graphics device also support UEFI.<br>
	</p>
	<p>
		Once a VM is created this setting cannot be adjusted.
	</p>
	<p>
		<b>USB Boot</b><br>
		Adds support for booting from USB devices using UEFI. No device boot orders can be specified at the same time as this option.<br>
	</p>
</blockquote>
</div>

<table class="domain_os windows">
	<tr class="advanced">
		<td>_(Hyper-V)_:</td>
		<td>
			<span class="width"><select name="domain[hyperv]" id="hyperv"<?if ($boolNew && $os_type=="windows"):?> class="narrow" onchange="HypervChgNew(this)"<?endif?>>
			<?mk_dropdown_options([_('No'), _('Yes')], $arrConfig['domain']['hyperv']);?>
			</select></span>
		</td>
		<td></td>
	</tr>
</table>

<div class="domain_os windows">
<div class="advanced">
<blockquote class="inline_help">
	<p>Exposes the guest to hyper-v extensions for Microsoft operating systems.</p>
</blockquote>
</div>
</div>

<table>
	<tr class="advanced">
		<td>_(USB Controller)_:</td>
		<td>
			<span class="width"><select name="domain[usbmode]" id="usbmode" class="narrow">
			<?
			echo mk_option($arrConfig['domain']['usbmode'], 'usb2', _('2.0 (EHCI)'));
			echo mk_option($arrConfig['domain']['usbmode'], 'usb3', _('3.0 (nec XHCI)'));
			echo mk_option($arrConfig['domain']['usbmode'], 'usb3-qemu', _('3.0 (qemu XHCI)'));
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
</table>

<div class="advanced">
<blockquote class="inline_help">
	<p>
		<b>USB Controller</b><br>
		Select the USB Controller to emulate.  Some OSes won't support USB3 (e.g. Windows 7).  Qemu XHCI is the same code base as Nec XHCI but without several hacks applied over the years.  Recommended to try qemu XHCI before resorting to nec XHCI.
	</p>
</blockquote>
</div>

<table>
	<tr>
		<td>_(OS Install ISO)_:</td>
		<td>
			<span class="width"><input type="text" name="media[cdrom]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfilter="iso" data-pickmatch="^[^.].*" data-pickroot="<?=htmlspecialchars($domain_cfg['MEDIADIR'])?>" class="cdrom" value="<?=htmlspecialchars($arrConfig['media']['cdrom'])?>" placeholder="_(Click and Select cdrom image to install operating system)_"></span>
		</td>
		<td>
			<textarea class="xml" id="xmlvdiskhda" rows="1" disabled wrap="soft"><?=htmlspecialchars($xml2['devices']['disk']['hda'])?></textarea>
		</td>
	</tr>
	<tr class="advanced">
		<td>_(OS Install CDRom Bus)_:</td>
		<td>
			<span class="width"><select name="media[cdrombus]" class="cdrom_bus narrow">
			<?mk_dropdown_options($arrValidCdromBuses, $arrConfig['media']['cdrombus']);?>
			</select></span>
			<span class="label">_(Boot Order)_:</span>
			<input type="number" size="5" maxlength="5" id="cdboot" class="trim bootorder second" name="media[cdromboot]" value="<?=$arrConfig['media']['cdromboot']?>">
		</td>
		<td></td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>Select the virtual CD-ROM (ISO) that contains the installation media for your operating system.  Clicking this field displays a list of ISOs found in the directory specified on the Settings page.</p>
	<p class="advanced">
		<b>CDRom Bus</b><br>
		Specify what interface this virtual cdrom uses to connect inside the VM.
	</p>
</blockquote>

<table class="domain_os windows">
	<tr class="advanced">
		<td>_(VirtIO Drivers ISO)_:</td>
		<td>
			<span class="width"><input type="text" name="media[drivers]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfilter="iso" data-pickmatch="^[^.].*" data-pickroot="<?=htmlspecialchars($domain_cfg['MEDIADIR'])?>" class="cdrom" value="<?=htmlspecialchars($arrConfig['media']['drivers'])?>" placeholder="_(Download, Click and Select virtio drivers image)_"></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(VirtIO Drivers CDRom Bus)_:</td>
		<td>
			<span class="width"><select name="media[driversbus]" class="cdrom_bus narrow">
			<?mk_dropdown_options($arrValidCdromBuses, $arrConfig['media']['driversbus']);?>
			</select></span>
		</td>
		<td>
			<textarea class="xml" id="xmlvdiskhdb" rows="1" disabled wrap="soft"><?=htmlspecialchars($xml2['devices']['disk']['hdb'])?></textarea>
		</td>
	</tr>
</table>

<div class="domain_os windows">
	<div class="advanced">
		<blockquote class="inline_help">
			<p>Specify the virtual CD-ROM (ISO) that contains the VirtIO Windows drivers as provided by the Fedora Project.  Download the latest ISO from here: <a href="https://docs.fedoraproject.org/en-US/quick-docs/creating-windows-virtual-machines-using-virtio-drivers/index.html#virtio-win-direct-downloads" target="_blank">https://docs.fedoraproject.org/en-US/quick-docs/creating-windows-virtual-machines-using-virtio-drivers/index.html#virtio-win-direct-downloads</a></p>
			<p>When installing Windows, you will reach a step where no disk devices will be found.  There is an option to browse for drivers on that screen.  Click browse and locate the additional CD-ROM in the menu.  Inside there will be various folders for the different versions of Windows.  Open the folder for the version of Windows you are installing and then select the AMD64 subfolder inside (even if you are on an Intel system, select AMD64).  Three drivers will be found.  Select them all, click next, and the vDisks you have assigned will appear.</p>
			<p>
				<b>CDRom Bus</b><br>
				Specify what interface this virtual cdrom uses to connect inside the VM.
			</p>
		</blockquote>
	</div>
</div>

<?foreach ($arrConfig['disk'] as $i => $arrDisk) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : _('Primary');
?>
<table data-category="vDisk" data-multiple="true" data-minimum="1" data-maximum="24" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr>
		<td>_(vDisk Location)_:</td>
		<td>
			<select name="disk[<?=$i?>][select]" class="disk_select narrow">
			<?
			if ($i == 0) echo '<option value="">'._('None').'</option>';
			$default_option = $arrDisk['select'];
			if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {
				$boolShowAllDisks = (strpos($domain_cfg['DOMAINDIR'], '/mnt/user/') === 0);
				if (!empty($arrDisk['new'])) {
					if (strpos($domain_cfg['DOMAINDIR'], dirname(dirname($arrDisk['new']))) === false ||
						basename(dirname($arrDisk['new'])) != $arrConfig['domain']['name'] || (
						basename($arrDisk['new']) != 'vdisk'.($i+1).'.img') && basename($arrDisk['new']) != 'vdisk'.($i+1).'.qcow2') {
						$default_option = 'manual';
					}
					if (file_exists(dirname(dirname($arrDisk['new'])).'/'.$arrConfig['domain']['name'].'/vdisk'.($i+1).'.img') || file_exists(dirname(dirname($arrDisk['new'])).'/'.$arrConfig['domain']['name'].'/vdisk'.($i+1).'.qcow2')) {
						// hide all the disks because the auto disk already has been created
						$boolShowAllDisks = false;
					}
				}
				echo mk_option($default_option, 'auto', _('Auto'));
				if ($boolShowAllDisks) {
					$strShareUserLocalInclude = '';
					$strShareUserLocalExclude = '';
					$strShareUserLocalUseCache = 'no';
					// Get the share name and its configuration
					$arrDomainDirParts = explode('/', $domain_cfg['DOMAINDIR']);
					$strShareName = $arrDomainDirParts[3];
					if (!empty($strShareName) && is_file('/boot/config/shares/'.$strShareName.'.cfg')) {
						$arrShareCfg = parse_ini_file('/boot/config/shares/'.$strShareName.'.cfg');
						if (!empty($arrShareCfg['shareInclude'])) {
							$strShareUserLocalInclude = $arrShareCfg['shareInclude'];
						}
						if (!empty($arrShareCfg['shareExclude'])) {
							$strShareUserLocalExclude = $arrShareCfg['shareExclude'];
						}
						if (!empty($arrShareCfg['shareUseCache'])) {
							$strShareUserLocalUseCache = $arrShareCfg['shareUseCache'];
						}
					}
					// Available cache pools
					foreach ($pools as $pool) {
						if (isSubpool($pool)) continue;
						$strLabel = $pool.' - '.my_scale($disks[$pool]['fsFree']*1024, $strUnit).' '.$strUnit.' '._('free');
						echo mk_option($default_option, $pool, $strLabel);
					}
					// Determine which disks from the array are available for this share:
					foreach ($disks as $name => $disk) {
						if ((strpos($name, 'disk') === 0) && (!empty($disk['device']))) {
							if ((!empty($strShareUserLocalInclude) && (strpos($strShareUserLocalInclude.',', $name.',') === false)) ||
								(!empty($strShareUserLocalExclude) && (strpos($strShareUserLocalExclude.',', $name.',') !== false)) ||
								(!empty($var['shareUserInclude']) && (strpos($var['shareUserInclude'].',', $name.',') === false)) ||
								(!empty($var['shareUserExclude']) && (strpos($var['shareUserExclude'].',', $name.',') !== false))) {
								// skip this disk based on local and global share settings
								continue;
							}
							$strLabel = _(my_disk($name),3).' - '.my_scale($disk['fsFree']*1024, $strUnit).' '.$strUnit.' '._('free');
							echo mk_option($default_option, $name, $strLabel);
						}
					}
				}
			}
			echo mk_option($default_option, 'manual', _('Manual'));
			?>
			</select>
			<input type="text" name="disk[<?=$i?>][new]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickmatch="^[^.].*" data-pickroot="/mnt/" class="disk" id="disk_<?=$i?>" value="<?=htmlspecialchars($arrDisk['new'])?>" placeholder="_(Separate sub-folder and image will be created based on Name)_">
			<div class="disk_preview"></div>
		</td>
		<td>
			<textarea class="xml" id="xmlvdisk<?=$i?>" rows="4" disabled wrap="soft"><?=htmlspecialchars($xml2['devices']['disk'][$arrDisk['dev']])?></textarea>
		</td>
	</tr>
	<input type="hidden" name="disk[<?=$i?>][storage]" id="disk[<?=$i?>][storage]" value="<?=htmlspecialchars($arrConfig['template']['storage'])?>">
	<tr class="disk_file_options">
		<td>_(vDisk Size)_:</td>
		<td>
			<span class="width"><input type="text" name="disk[<?=$i?>][size]" value="<?=htmlspecialchars($arrDisk['size'])?>" class="trim" placeholder="_(e.g.)_ 10M, 1G, 10G..."></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(vDisk Type)_:</td>
		<td>
			<span class="width"><select name="disk[<?=$i?>][driver]" class="disk_driver narrow">
			<?mk_dropdown_options($arrValidDiskDrivers, $arrDisk['driver']);?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_bus_options">
		<td>_(vDisk Bus)_:</td>
		<td>
			<span class="width"><select name="disk[<?=$i?>][bus]" class="disk_bus narrow" onchange="BusChange(this.value,<?=$i?>)">
			<?mk_dropdown_options($arrValidDiskBuses, $arrDisk['bus']);?>
			</select></span>
			<span class="label">_(Boot Order)_:</span>
			<input type="number" size="5" maxlength="5" id="disk[<?=$i?>][boot]" class="trim bootorder second" name="disk[<?=$i?>][boot]" value="<?=$arrDisk['boot']?>">
			<span class="label">_(Discard)_:</span>
			<select name="disk[<?=$i?>][discard]" class="narrow second">
			<?mk_dropdown_options($arrValidDiskDiscard, $arrDisk['discard']);?>
			</select>
			<?if ($arrDisk['bus'] == "virtio" || $arrDisk['bus'] == "usb") $ssddisabled = "hidden"; else $ssddisabled = "";?>
			<span id="disk[<?=$i?>][rotatetext]" class="label <?=$ssddisabled?>">_(SSD)_:</span>
			<input type="checkbox" id="disk[<?=$i?>][rotation]" class="rotation <?=$ssddisabled?>" onchange="updateSSDCheck(this)" name="disk[<?=$i?>][rotation]" <?=$arrDisk['rotation'] ? "checked ":"";?> value="<?=$arrDisk['rotation']?>">
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_bus_options">
		<td>_(Serial)_:</td>
		<td>
			<span class="width"><input type="text" size="20" maxlength="20" id="disk[<?=$i?>][serial]" class="trim disk_serial" name="disk[<?=$i?>][serial]" value="<?=$arrDisk['serial']?>"></span>
		</td>
		<td></td>
	</tr>
</table>

<?if ($i == 0) {?>
<blockquote class="inline_help">
	<p>
		<b>vDisk Location</b><br>
		Specify a path to a user share in which you wish to store the VM or specify an existing vDisk.  The primary vDisk will store the operating system for your VM.
	</p>
	<p>
		<b>NOTE</b>: Unraid will automatically "dereference" vdisk paths when starting a VM.
		That is, if a vdisk path is specified as being on a user share, we use the SYSTEM.LOCATION extended attribute to find out what physical disk the image exists on.
		We then pass this path when starting a VM via qemu.  This ensures that VM I/O bypasses shfs (FUSE user share file system) for better performance.
		It also means that a vdisk image file can be moved from one physical device to another without changing the VM XML file.
	</p>
	<p>
		Example: /mnt/user/domains/Windows/vdisk1.img will be dereferenced to /mnt/cache/domains/Windows/vdisk1.img (for vdisk1.img physically located in the "cache" volume).
	</p>
	<p>
		<b>vDisk Size</b><br>
		Specify a number followed by a letter.  M for megabytes, G for gigabytes.
	</p>
	<p class="advanced">
		<b>vDisk Type</b><br>
		Select RAW for best performance.  QCOW2 implementation is still in development.
	</p>
	<p class="advanced">
		<b>vDisk Bus</b><br>
		Select virtio for best performance.
	</p>
	<p class="advanced">
		<b>vDisk Boot Order</b><br>
		Specify the order the devices are used for booting.
	</p>
	<p class="advanced">
		<b>vDisk Discard</b><br>
		Specify if unmap(Trim) requests are sent to underlaying filesystem.
	</p>
	<p class="advanced">
		<b>vDisk SSD Flag</b><br>
		Specify the vdisk shows as SSD within the guest, only supported on SCSI, SATA and IDE bus types.
	</p>
	<p class="advanced">
		<b>vDisk Serial</b><br>
		Set the device serial number presented to the VM.
	</p>
	<p>
		Additional devices can be added/removed by clicking the symbols to the left.
	</p>
</blockquote>
<?}?>
<?}?>

<script type="text/html" id="tmplvDisk">
<table>
	<tr>
		<td>_(vDisk Location)_:</td>
		<td>
			<span class="width"><select name="disk[{{INDEX}}][select]" class="disk_select narrow">
			<?
			if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {
				$default_option = $domain_cfg['VMSTORAGEMODE'];
				echo mk_option($default_option, 'auto', _('Auto'));
				if (strpos($domain_cfg['DOMAINDIR'], '/mnt/user/') === 0) {
					$strShareUserLocalInclude = '';
					$strShareUserLocalExclude = '';
					$strShareUserLocalUseCache = 'no';
					// Get the share name and its configuration
					$arrDomainDirParts = explode('/', $domain_cfg['DOMAINDIR']);
					$strShareName = $arrDomainDirParts[3];
					if (!empty($strShareName) && is_file('/boot/config/shares/'.$strShareName.'.cfg')) {
						$arrShareCfg = parse_ini_file('/boot/config/shares/'.$strShareName.'.cfg');
						if (!empty($arrShareCfg['shareInclude'])) {
							$strShareUserLocalInclude = $arrShareCfg['shareInclude'];
						}
						if (!empty($arrShareCfg['shareExclude'])) {
							$strShareUserLocalExclude = $arrShareCfg['shareExclude'];
						}
						if (!empty($arrShareCfg['shareUseCache'])) {
							$strShareUserLocalUseCache = $arrShareCfg['shareUseCache'];
						}
					}
					// Available cache pools
					foreach ($pools as $pool) {
						if (isSubpool($pool)) continue;
						$strLabel = $pool.' - '.my_scale($disks[$pool]['fsFree']*1024, $strUnit).' '.$strUnit.' '._('free');
						echo mk_option($default_option, $pool, $strLabel);
					}
					// Determine which disks from the array are available for this share:
					foreach ($disks as $name => $disk) {
						if ((strpos($name, 'disk') === 0) && (!empty($disk['device']))) {
							if ((!empty($strShareUserLocalInclude) && (strpos($strShareUserLocalInclude.',', $name.',') === false)) ||
								(!empty($strShareUserLocalExclude) && (strpos($strShareUserLocalExclude.',', $name.',') !== false)) ||
								(!empty($var['shareUserInclude']) && (strpos($var['shareUserInclude'].',', $name.',') === false)) ||
								(!empty($var['shareUserExclude']) && (strpos($var['shareUserExclude'].',', $name.',') !== false))) {
								// skip this disk based on local and global share settings
								continue;
							}
							$strLabel = _(my_disk($name),3).' - '.my_scale($disk['fsFree']*1024, $strUnit).' '.$strUnit.' '._('free');
							echo mk_option($default_option, $name, $strLabel);
						}
					}
				}
			}
			echo mk_option('', 'manual', _('Manual'));
			?>
			</select></span>
			<input type="text" name="disk[{{INDEX}}][new]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickmatch="^[^.].*" data-pickroot="/mnt/" class="disk" id="disk_{{INDEX}}" value="" placeholder="_(Separate sub-folder and image will be created based on Name)_">
			<div class="disk_preview"></div>
		</td>
		<td></td>
	</tr>
	<input type="hidden" name="disk[{{INDEX}}][storage]" id="disk[{{INDEX}}][storage]" value="<?=htmlspecialchars($arrConfig['template']['storage'])?>">
	<tr class="disk_file_options">
		<td>_(vDisk Size)_:</td>
		<td>
			<span class="width"><input type="text" name="disk[{{INDEX}}][size]" value="" class="trim" placeholder="_(e.g.)_ 10M, 1G, 10G..."></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(vDisk Type)_:</td>
		<td>
			<span class="width"><select name="disk[{{INDEX}}][driver]" class="disk_driver narrow">
			<?mk_dropdown_options($arrValidDiskDrivers, '');?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_bus_options">
		<td>_(vDisk Bus)_:</td>
		<td>
			<span class="width"><select name="disk[{{INDEX}}][bus]" class="disk_bus narrow" onchange="BusChange(this.value,{{INDEX}})">
			<?mk_dropdown_options($arrValidDiskBuses, '');?>
			</select></span>
			<span class="label">_(Boot Order)_:</span>
			<input type="number" size="5" maxlength="5" id="disk[{{INDEX}}][boot]" class="trim bootorder second" name="disk[{{INDEX}}][boot]" value="">
			<span class="label">_(Discard)_:</span>
			<select name="disk[{{INDEX}}][discard]" class="narrow second">
			<?mk_dropdown_options($arrValidDiskDiscard, "unmap");?>
			</select>
			<span id="disk[{{INDEX}}][rotatetext]" class="label hidden">_(SSD)_:</span>
			<input type="checkbox" id="disk[{{INDEX}}][rotation]" class="rotation hidden" onchange="updateSSDCheck(this)" name="disk[{{INDEX}}[rotation]" value='0'>
		</td>
		<td></td>
	<tr class="advanced disk_bus_options">
		<td>_(Serial)_:</td>
		<td>
			<span class="width"><input type="text" size="20" maxlength="20" id="disk[{{INDEX}}[serial]" class="trim disk_serial" name="disk[{{INDEX}}][serial]" value=""></span>
		</td>
		<td></td>
	</tr>
</table>
</script>

<?
$arrUnraidShares = getUnraidShares();
foreach ($arrConfig['shares'] as $i => $arrShare) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';
?>
<table data-category="Share" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr class="advanced">
		<td>_(Unraid Share Mode)_:</td>
		<td>
			<span class="width"><select name="shares[<?=$i?>][mode]" class="disk_bus narrow">
			<?if ($os_type != "windows") echo mk_option($arrShare['mode'], "9p", _('9p Mode'));;?>
			<?echo mk_option($arrShare['mode'], "virtiofs", _('Virtiofs Mode'));;?>
			</select></span>
			<span class="label">_(Unraid Share)_:</span>
			<span class="width"><select name="shares[<?=$i?>][unraid]" class="disk_bus narrow second" onchange="ShareChange(this)" >
			<?
			$UnraidShareDisabled = ' disabled="disabled"';
			$arrUnraidIndex = array_search("User:".$arrShare['target'],$arrUnraidShares);
			if ($arrUnraidIndex != false && substr($arrShare['source'],0,10) != '/mnt/user/') $arrUnraidIndex = false;
			if ($arrUnraidIndex == false) $arrUnraidIndex = array_search("Disk:".$arrShare['target'],$arrUnraidShares);
			if ($arrUnraidIndex == false) { $arrUnraidIndex = ''; $UnraidShareDisabled = "";}
			mk_dropdown_options($arrUnraidShares, $arrUnraidIndex);
			?>
			</select></span>
		</td>
		<td>
			<textarea class="xml" id="xmlshare<?=$i?>" rows="4" wrap="soft" disabled ><?=htmlspecialchars($xml2['devices']['filesystem'][$i])?></textarea>
		</td>
	</tr>
	<tr class="advanced">
		<td>
			<text id="shares[<?=$i?>]sourcetext" > _(Unraid Source Path)_: </text>
		</td>
		<td>
			<span class="width"><input type="text" <?=$UnraidShareDisabled?> id="shares[<?=$i?>][source]" name="shares[<?=$i?>][source]" autocomplete="off" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="<?=htmlspecialchars($arrShare['source'])?>" placeholder="_(e.g.)_ /mnt/user/..."></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>
			<span id="shares[<?=$i?>][targettext]" >_(Unraid Mount Tag)_:</span>
		</td>
		<td>
			<span class="width"><input type="text" <?=$UnraidShareDisabled?> name="shares[<?=$i?>][target]" id="shares[<?=$i?>][target]" value="<?=htmlspecialchars($arrShare['target'])?>" placeholder="_(e.g.)_ _(shares)_ (_(name of mount tag inside vm)_)"></span>
		</td>
		<td></td>
	</tr>
</table>

<?if ($i == 0) {?>
<div>
<div class="advanced">
<blockquote class="inline_help">
	<p>
		<b>Unraid Share Mode</b><br>
		Used to create a VirtFS mapping to a Linux-based guest.  Specify the mode you want to use either 9p or Virtiofs.
	</p>
	<p>
		<b>Unraid Share</b><br>
		Set tag and path to match the selected Unraid share.
	</p>
	<p>
		<b>Unraid Source Path</b><br>
		Specify the path on the host here.
	</p>
	<p>
		<b>Unraid Mount tag</b><br>
		Specify the mount tag that you will use for mounting the VirtFS share inside the VM.  See this page for how to do this on a Linux-based guest: <a href="http://wiki.qemu.org/Documentation/9psetup" target="_blank">http://wiki.qemu.org/Documentation/9psetup</a>
	</p>
	<p>
		For Windows additional software needs to be installed: <a href="https://virtio-fs.gitlab.io/howto-windows.html" target="_blank">https://virtio-fs.gitlab.io/howto-windows.html</a>
	</p>
	<p>
		Additional devices can be added/removed by clicking the symbols to the left.
	</p>
</blockquote>
</div>
</div>
<?}?>
<?}?>

<script type="text/html" id="tmplShare">
<table class="domain_os other">
	<tr class="advanced">
		<td>_(Unraid Share Mode)_:</td>
		<td>
			<span class="width"><select name="shares[{{INDEX}}][mode]" class="disk_bus narrow">
			<?if ($os_type != "windows") echo mk_option($arrShare['mode'], "9p", _('9p Mode'));;?>
			<?echo mk_option('', "virtiofs", _('Virtiofs Mode'));;?>
			</select></span>
			<span class="label">_(Unraid Share)_:</span>
			<select name="shares[{{INDEX}}][unraid]" class="disk_bus narrow second" onchange="ShareChange(this)" >
			<?mk_dropdown_options($arrUnraidShares, '');?>
			</select>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(Unraid Source Path)_:</td>
		<td>
			<span class="width"><input type="text" name="shares[{{INDEX}}][source]" id="shares[{{INDEX}}][source]" autocomplete="off" spellcheck="false" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="" placeholder="_(e.g.)_ /mnt/user/..."></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(Unraid Mount Tag)_:</td>
		<td>
			<span class="width"><input type="text" name="shares[{{INDEX}}][target]" id="shares[{{INDEX}}][target]" value="" placeholder="_(e.g.)_ _(shares)_ (_(name of mount tag inside vm)_)"></span>
		</td>
		<td></td>
	</tr>
</table>
</script>

<?foreach ($arrConfig['gpu'] as $i => $arrGPU) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';
	$bootgpuhidden = "hidden";
?>
<table data-category="Graphics_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidGPUDevices)+1?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr>
		<td>_(Graphics Card)_:</td>
		<td>
			<span class="width"><select name="gpu[<?=$i?>][id]" class="gpu narrow">
			<?
			if ($i == 0) {
				// Only the first video card can be VNC or SPICE
				echo mk_option($arrGPU['id'], 'virtual', _('Virtual'));
			} else {
				echo mk_option($arrGPU['id'], '', _('None'));
			}
			echo mk_option($arrGPU['id'], 'nogpu', _('No GPU'));
			foreach ($arrValidGPUDevices as $arrDev) {
				echo mk_option($arrGPU['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
			}
			?>
			</select></span>
			<?
			if ($arrGPU['id'] != 'virtual' && $arrGPU['id'] != 'nogpu') $multifunction = ""; else $multifunction = " disabled ";
			?>
			<span id="GPUMulti<?=$i?>" name="gpu[<?=$i?>][multi]" class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced label gpumultiline<?=$i?>">_(Multifunction)_:</span>
			<select id="GPUMultiSel<?=$i?>" name="gpu[<?=$i?>][multi]" class="<?if ($arrGPU['id']!='virtual') echo 'was';?>advanced narrow second gpumultiselect<?=$i?>" <?=$multifunction?>>
			<?
			echo mk_option($arrGPU['guest']['multi'], 'off', 'Off');
			echo mk_option($arrGPU['guest']['multi'], 'on', 'On');
			?>
			</select>
		</td>
		<td>
		<?if ($arrGPU['id'] == 'virtual') {?>
			<textarea class="xml" id="xmlgraphics<?=$i?>" rows="5" disabled ><?=htmlspecialchars($xml2['devices']['graphics'][0])."\n".htmlspecialchars($xml2['devices']['video'][0])."\n".htmlspecialchars($xml2['devices']['audio'][0])?></textarea>
		<?} else {?>
			<textarea class="xml" id="xmlgraphics<?=$i?>" rows="5" disabled ><?=htmlspecialchars($xml2['devices']['vga'][$arrGPU['id']])?></textarea>
		<?}?>
		</td>
	</tr>
	<?if ($i == 0) {
		$hiddenport = $hiddenwsport = "hidden";
		if ($arrGPU['autoport'] == "no") {
			if ($arrGPU['protocol'] == "vnc") $hiddenport = $hiddenwsport = "";
			if ($arrGPU['protocol'] == "spice") $hiddenport = "";
		}
	?>
	<tr class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
		<td>_(VM Console Protocol)_:</td>
		<td>
			<span class="width"><select id="protocol" name="gpu[<?=$i?>][protocol]" class="narrow" onchange="ProtocolChange(this)" >
			<?mk_dropdown_options($arrValidProtocols, $arrGPU['protocol']);?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr id="copypasteline" name="copypaste" class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced copypaste">
		<td>_(VM Console enable Copy/paste)_:</td>
		<td>
			<span class="width"><select id="copypaste" name="gpu[<?=$i?>][copypaste]" class="narrow">
			<?
			echo mk_option($arrGPU['copypaste'], 'no', _('No'));
			echo mk_option($arrGPU['copypaste'], 'yes', _('Yes'));
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr id="autoportline" name="autoportline" class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced autoportline">
		<td>_(VM Console AutoPort)_:</td>
		<td>
			<span class="width"><select id="autoport" name="gpu[<?=$i?>][autoport]" class="narrow" onchange="AutoportChange(this)">
			<?
			echo mk_option($arrGPU['autoport'], 'yes', _('Yes'));
			echo mk_option($arrGPU['autoport'], 'no', _('No'));
			?>
			</select></span>
			<span id="Porttext" class="label <?=$hiddenport?>">_(VM Console Port)_:</span>
			<input id="port" type="number" size="5" maxlength="5" class="trim second <?=$hiddenport?>" name="gpu[<?=$i?>][port]" value="<?=$arrGPU['port']?>">
			<span id="WSPorttext" class="label <?=$hiddenwsport?>">_(VM Console WS Port)_:</span>
			<input id="wsport" type="number" size="5" maxlength="5" class="trim second <?=$hiddenwsport?>" name="gpu[<?=$i?>][wsport]" value="<?=$arrGPU['wsport']?>">
		</td>
		<td></td>
	</tr>
	<tr class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced vncmodel">
		<td>_(VM Console Video Driver)_:</td>
		<td>
			<span class="width"><select id="vncmodel" name="gpu[<?=$i?>][model]" class="narrow" onchange="VMConsoleDriverChange(this)">
			<?mk_dropdown_options($arrValidVNCModels, $arrGPU['model']);?>
			</select></span>
			<?if ($arrGPU['model'] == "virtio3d") $vmcrender = ""; else $vmcrender = "hidden";?>
			<span id="vncrendertext" class="label <?=$vncrender?>">_(Render GPU)_:</span>
			<select id="vncrender" name="gpu[<?=$i?>][render]" class="second <?=$vncrender?>">
			<?
			echo mk_option($arrGPU['render'], 'auto', _('Auto'));
			foreach ($arrValidGPUDevices as $arrDev) {
				if (($arrDev['vendorid'] == "10de" && ($arrDev['driver'] == "nvidia" && !is_file("/etc/libvirt/virglnv"))) || $arrDev['driver'] == "vfio-pci") continue;
				echo mk_option($arrGPU['render'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
			}
			?>
			</select>
			<?
			$arrGPU['DisplayOptions'] = htmlentities($arrDisplayOptions[$arrGPU['DisplayOptions']]['qxlxml'],ENT_QUOTES);
			if ($arrGPU['model'] == "qxl") $vncdspopt = ""; else $vncdspopt = "hidden";
			?>
			<span id="vncdspopttext" class="label <?=$vncdspopt?>">_(Display(s) and RAM)_:</span>
			<select id="vncdspopt" name="gpu[<?=$i?>][DisplayOptions]" class="second <?=$vncdspopt?>">
			<?
			foreach ($arrDisplayOptions as $key => $value) echo mk_option($arrGPU['DisplayOptions'], htmlentities($value['qxlxml'],ENT_QUOTES), _($value['text']));
			?>
			</select>
		</td>
		<td></td>
	</tr>
	<tr class="vncpassword">
		<td>_(VM Console Password)_:</td>
		<td>
			<span class="width"><input type="password" name="domain[password]" autocomplete='new-password' value="<?=$arrGPU['password']?>" placeholder="_(password for VM Console)_ (_(optional)_)"/></span>
		</td>
		<td></td>
	</tr>
	<tr class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced vnckeymap">
		<td>_(VM Console Keyboard)_:</td>
		<td>
			<span class="width"><select name="gpu[<?=$i?>][keymap]" class="narrow">
			<?mk_dropdown_options($arrValidKeyMaps, $arrGPU['keymap']);?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<?}?>
	<tr class="<?if ($arrGPU['id'] == 'virtual' || $arrGPU['id'] == 'nogpu') echo 'was';?>advanced romfile">
		<td>_(Graphics ROM BIOS)_:</td>
		<td>
			<span class="width"><input type="text" name="gpu[<?=$i?>][rom]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfilter="rom,bin" data-pickmatch="^[^.].*" data-pickroot="/mnt/" value="<?=htmlspecialchars($arrGPU['rom'])?>" placeholder="_(Path to ROM BIOS file)_ (_(optional)_)"></span>
		</td>
		<td></td>
	</tr>
	<?
	if ($arrValidGPUDevices[$arrGPU['id']]['bootvga'] == "1") $bootgpuhidden = "";
	?>
	<tr id="gpubootvga<?=$i?>" class="<?=$bootgpuhidden?>"><td>_(Graphics ROM Needed)_?:</td><td><span class="orange-text"><i class="fa fa-warning"></i> _(GPU is primary adapter, vbios may be required)_.</span></td></tr>
</table>

<?if ($i == 0 || $i == 1) {?>
<blockquote class="inline_help">
	<p>
		<b>Graphics Card</b><br>
		If you wish to assign a graphics card to the VM, select it from this list, otherwise leave it set to virtual.
	</p>
	<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
		<b>Virtual video protocol VNC/SPICE</b><br>
		If you wish to assign a protocol type, specify one here.
	</p>
	<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
		<b>Virtual enable copy paste for VNC/SPICE</b><br>
		If you enable copy paste you need to install additional software on the client in addition to the QEMU agent if that has been installed. <a href="https://www.spice-space.org/download.html" target="_blank">https://www.spice-space.org/download.html </a>is the location for spice-vdagent for both window and linux. Note copy paste function will not work with web spice viewer you need to use virt-viewer.
	</p>
	<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
		<b>Virtual auto port</b><br>
		Set it you want to specify a manual port for VNC or Spice. VNC needs two ports where Spice only requires one. Leave as auto yes for the system to set.
	</p>
	<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced vncmodel">
		<b>Virtual Video Driver</b><br>
		If you wish to assign a different video driver to use for a VM Console connection, specify one here.
		QXL has an option of setting number of screens and vram.
		Virtio3d allows render device to be specified or auto.(This allow GPU to be used in a VM without passthru for 3D acceleration no screen output)
	</p>
	<p class="vncpassword">
		<b>Virtual Password</b><br>
		If you wish to require a password to connect to the VM over a VM Console connection, specify one here.
	</p>
	<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced vnckeymap">
		<b>Virtual Keyboard</b><br>
		If you wish to assign a different keyboard layout to use for a VM Console connection, specify one here.
	</p>
	<p class="<?if ($arrGPU['id'] == 'virtual') echo 'was';?>advanced romfile">
		<b>Graphics ROM BIOS</b><br>
		If you wish to use a custom ROM BIOS for a Graphics card, specify one here.
	</p>
	<p>
		Additional devices can be added/removed by clicking the symbols to the left.
	</p>
</blockquote>
<?}?>
<?}?>

<script type="text/html" id="tmplGraphics_Card">
<table>
	<tr>
		<td>_(Graphics Card)_:</td>
		<td>
			<span class="width"><select name="gpu[{{INDEX}}][id]" class="gpu narrow">
			<?
			echo mk_option('', '', _('None'));
			foreach ($arrValidGPUDevices as $arrDev) echo mk_option('', $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
			?>
			</select></span>
			<span id="GPUMulti" name="gpu[{{INDEX}}][multi]" class="label">_(Multifunction)_:</span>
			<select name="gpu[{{INDEX}}][multi]" class="narrow second">
			<?
			echo mk_option("off", 'off', 'Off');
			echo mk_option("off", 'on', 'On');
			?>
			</select>
		</td>
		<td></td>
	</tr>
	<tr class="advanced romfile">
		<td>_(Graphics ROM BIOS)_:</td>
		<td>
			<span class="width"><input type="text" name="gpu[{{INDEX}}][rom]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfilter="rom,bin" data-pickmatch="^[^.].*" data-pickroot="/mnt/" value="" placeholder="_(Path to ROM BIOS file)_ (_(optional)_)"></span>
		</td>
		<td></td>
	</tr>
	<tr id="gpubootvga{{INDEX}}" class="hidden"><td>_(Graphics ROM Needed)_?:</td><td><span class="orange-text"><i class="fa fa-warning"></i> _(GPU is primary adapter, vbios may be required)_.</span></td></tr>
</table>
</script>

<?foreach ($arrConfig['audio'] as $i => $arrAudio) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';
?>
<table data-category="Sound_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidAudioDevices)?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr>
		<td>_(Sound Card)_:</td>
		<td>
			<span class="width"><select name="audio[<?=$i?>][id]" class="audio narrow">
			<?
			echo mk_option($arrAudio['id'], '', _('None'));
			foreach ($arrValidAudioDevices as $arrDev) echo mk_option($arrAudio['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
			foreach ($arrValidSoundCards as $arrSound) echo mk_option($arrAudio['id'], $arrSound['id'], $arrSound['name'].' ('._("Virtual").')');
			?>
			</select></span>
		</td>
		<td>
			<textarea class="xml" id="xmlaudio<?=$i?>" rows="5" disabled ><?=htmlspecialchars($xml2['devices']['audio'][$arrAudio['id']])?></textarea>
		</td>
	</tr>
</table>

<?if ($i == 0) {?>
<blockquote class="inline_help">
	<p>Select a sound device to assign to your VM.  Most modern GPUs have a built-in audio device, but you can also select the on-board audio device(s) if present.</p>
	<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
</blockquote>
<?}?>
<?}?>

<script type="text/html" id="tmplSound_Card">
<table>
	<tr>
		<td>_(Sound Card)_:</td>
		<td>
			<span class="width"><select name="audio[{{INDEX}}][id]" class="audio narrow">
			<?
			foreach ($arrValidAudioDevices as $arrDev) echo mk_option('', $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
			foreach ($arrValidSoundCards as $arrSound) echo mk_option($arrAudio['id'], $arrSound['id'], $arrSound['name'].' ('._("Virtual").')');
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
</table>
</script>

<?if ($arrConfig['nic'] == false) {
	$arrConfig['nic']['0'] =
	[
		'network' => $domain_bridge,
		'mac' => "",
		'model' => 'virtio-net'
	];
}
foreach ($arrConfig['nic'] as $i => $arrNic) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';
	$disabled = $arrNic['network']=='wlan0' ? 'disabled' : '';
?>
<table data-category="Network" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr class="advanced">
		<td>_(Network MAC)_:</td>
		<td>
			<span class="width"><input type="text" name="nic[<?=$i?>][mac]" class="narrow" value="<?=htmlspecialchars($arrNic['mac'])?>" <?=$disabled?>><i class="fa fa-refresh mac_generate <?=$i?>" <?=$disabled?>></i></span>
		</td>
		<td>
			<textarea class="xml" id="xmlnet<?=$i?>" rows="5" disabled ><?=htmlspecialchars($xml2['devices']['interface'][$i])?></textarea>
		</td>
	</tr>
	<tr class="advanced">
		<td>_(Network Source)_:</td>
		<td>
			<span class="width"><select name="nic[<?=$i?>][network]" class="narrow" onchange="updateMAC(<?=$i?>,this.value)">
			<?
			foreach (array_keys($arrValidNetworks) as $key) {
				echo mk_option("", $key, "- "._($key)." -", "disabled");
				foreach ($arrValidNetworks[$key] as $strNetwork) echo mk_option($arrNic['network'], $strNetwork, $strNetwork);
			}
			$wlan0_hidden = $arrNic['network'] == 'wlan0' ? '' : 'hidden';
			?>
			</select><span class="wlan0 orange-text <?=$wlan0_hidden?>"><i class="fa fa-fw fa-warning"></i> _(Manual configuration required)_ <input type="button" class="wlan0_info" value="_(Info)_" onclick="wlan0_info()"></span></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(Network Model)_:</td>
		<td>
			<span class="width"><select name="nic[<?=$i?>][model]" class="narrow">
			<?
			echo mk_option($arrNic['model'], 'virtio-net', 'virtio-net');
			echo mk_option($arrNic['model'], 'virtio', 'virtio');
			echo mk_option($arrNic['model'], 'e1000', 'e1000');
			echo mk_option($arrNic['model'], 'rtl8139', 'rtl8139');
			echo mk_option($arrNic['model'], 'vmxnet3', 'vmxnet3');
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(Boot Order)_:</td>
		<td>
			<span class="width"><input type="number" size="5" maxlength="5" id="nic[<?=$i?>][boot]" class="trim bootorder" <?=$bootdisable?> name="nic[<?=$i?>][boot]" value="<?=$arrNic['boot']?>"></span>
		</td>
		<td></td>
	</tr>
</table>

<?if ($i == 0) {?>
<div class="advanced">
<blockquote class="inline_help">
	<p>
		<b>Network MAC</b><br>
		By default, a random MAC address will be assigned here that conforms to the standards for virtual network interface controllers.  You can manually adjust this if desired.
	</p>
	<p>
		<b>Network Source</b><br>
		The default libvirt managed network bridge (virbr0) will be used, otherwise you may specify an alternative name for a private network to the host.
	</p>
	<p>
		<b>Network Model</b><br>
		Default and recommended is 'virtio-net', which gives improved stability. To improve performance 'virtio' can be selected, but this may lead to stability issues.
	</p>
	<p>
		Use boot order to set device as bootable and boot sequence.
	</p>
	<p>
		Additional devices can be added/removed by clicking the symbols to the left.
	</p>
</blockquote>
</div>
<?}?>
<?}?>

<script type="text/html" id="tmplNetwork">
<table>
	<tr class="advanced">
		<td>_(Network MAC)_:</td>
		<td>
			<span class="width"><input type="text" name="nic[{{INDEX}}][mac]" class="narrow" value=""> <i class="fa fa-refresh mac_generate INDEX"></i></span>
		</td>
	</tr>
	<tr class="advanced">
		<td>_(Network Source)_:</td>
		<td>
			<span class="width"><select name="nic[{{INDEX}}][network]" class="narrow" onchange="updateMAC(INDEX,this.value)">
			<?
			foreach (array_keys($arrValidNetworks) as $key) {
				echo mk_option("", $key, "- "._($key)." -", "disabled");
				foreach ($arrValidNetworks[$key] as $strNetwork) echo mk_option($domain_bridge, $strNetwork, $strNetwork);
			}
			?>
			</select><span class="wlan0 orange-text hidden"><i class="fa fa-fw fa-warning"></i> _(Manual configuration required)_ <input type="button" class="wlan0_info" value="_(Info)_" onclick="wlan0_info()"></span></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(Network Model)_:</td>
		<td>
			<span class="width"><select name="nic[{{INDEX}}][model]" class="narrow">
			<?
			echo mk_option(1, 'virtio-net', 'virtio-net');
			echo mk_option(1, 'virtio', 'virtio');
			echo mk_option($arrNic['model'], 'e1000', 'e1000');
			echo mk_option($arrNic['model'], 'vmxnet3', 'vmxnet3');
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced">
		<td>_(Boot Order)_:</td>
		<td>
			<span class="width"><input type="number" size="5" maxlength="5" id="nic[{{INDEX}}][boot]" class="trim bootorder" <?=$bootdisable?> name="nic[{{INDEX}}][boot]" value=""></span>
		</td>
		<td></td>
	</tr>
</table>
</script>

<table>
	<tr>
		<td>_(USB Devices)_:</td>
		<td>
			<span class="space">_(Select)_</span><span class="space">_(Optional)_</span><span class="space">_(Boot Order)_</span><br>
			<?
			if (!empty($arrVMUSBs)) {
				foreach ($arrVMUSBs as $i => $arrDev) {
				?>
				<label for="usb<?=$i?>">
				<span class="space"><input type="checkbox" name="usb[]" id="usb<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?if (count(array_filter($arrConfig['usb'], function($arr) use ($arrDev){return ($arr['id']==$arrDev['id']);}))) echo 'checked';?>></span>
				<span class="space"><input type="checkbox" name="usbopt[<?=htmlspecialchars($arrDev['id'])?>]" id="usbopt<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>"<?if ($arrDev["startupPolicy"]=="optional") echo ' checked=';?>></span>
				<input type="number" size="5" maxlength="5" id="usbboot<?=$i?>" class="trim bootorder" <?=$bootdisable?> name="usbboot[<?=htmlspecialchars($arrDev['id'])?>]" value="<?=$arrDev['usbboot']?>">
				<?=htmlspecialchars(substr($arrDev['name'],0,90))?> (<?=htmlspecialchars($arrDev['id'])?>)
				</label><br>
				<?
				}
			} else {
				echo "<i>"._('None available')."</i><br>";
			}
			?>
			<br>
		</td>
		<td>
			<textarea class="xml" id="xmlusb<?=$i?>" rows="5" disabled ><?=htmlspecialchars($xml2['devices']['allusb'])?></textarea>
		</td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>If you wish to assign any USB devices to your guest, you can select them from this list.</p>
	<p>Use boot order to set device as bootable and boot sequence.</p>
	<p>Select optional if you want device to be ignored when VM starts if not present.</p>
</blockquote>

<table>
	<tr>
		<td>_(Other PCI Devices)_:</td>
		<td>
			<span class="space">_(Select)_</span><span class="space">_(Boot Order)_</span><br>
			<?
			$intAvailableOtherPCIDevices = 0;
			if (!empty($arrValidOtherDevices)) {
				foreach ($arrValidOtherDevices as $i => $arrDev) {
					$bootdisable = $extra = $pciboot = '';
					if ($arrDev["typeid"] != "0108" && substr($arrDev["typeid"],0,2) != "02") $bootdisable = ' disabled="disabled"';
					if (count($pcidevice=array_filter($arrConfig['pci'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) {
						$extra .= ' checked="checked"';
						foreach ($pcidevice as $pcikey => $pcidev) $pciboot = $pcidev["boot"]; ;
					} elseif (!in_array($arrDev['driver'], ['pci-stub', 'vfio-pci'])) {
						//$extra .= ' disabled="disabled"';
						continue;
					}
					$intAvailableOtherPCIDevices++;
				?>
				<label for="pci<?=$i?>">&nbsp&nbsp&nbsp&nbsp<input type="checkbox" name="pci[]" id="pci<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?=$extra?>/> &nbsp
				<input type="number" size="5" maxlength="5" id="pciboot<?=$i?>" class="trim pcibootorder" <?=$bootdisable?> name="pciboot[<?=htmlspecialchars($arrDev['id'])?>]" value="<?=$pciboot?>" >
				<?=htmlspecialchars($arrDev['name'])?> | <?=htmlspecialchars($arrDev['type'])?> (<?=htmlspecialchars($arrDev['id'])?>)
				</label><br>
				<?
				}
			}
			if (empty($intAvailableOtherPCIDevices)) {
				echo "<i>"._('None available')."</i><br>";
			}
			?>
			<br>
		</td>
		<td>
			<textarea class="xml" id="xmlpci<?=$i?>" rows="2" disabled ><?=htmlspecialchars($xml2['devices']['other']["allotherpci"])?></textarea>
		</td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>If you wish to assign any other PCI devices to your guest, you can select them from this list.</p>
	<p>Use boot order to set device as bootable and boot sequence. Only NVMe and Network devices (PCI types 0108 and 02xx) supported for boot order.</p>
</blockquote>

<table>
	<tr>
		<td></td>
		<td>
		<?if (!$boolNew) {?>
			<input type="hidden" name="updatevm" value="1"/>
			<input type="button" value="_(Update)_" busyvalue="_(Updating)_..." readyvalue="_(Update)_" id="btnSubmit"/>
		<?} else {?>
			<label for="domain_start"><input type="checkbox" name="domain[startnow]" id="domain_start" value="1" checked="checked"/> _(Start VM after creation)_</label>
			<br>
			<input type="hidden" name="createvm" value="1"/>
			<input type="button" value="_(Create)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnSubmit"/>
		<?}?>
			<input type="button" value="_(Cancel)_" id="btnCancel"/>
		</td>
		<td></td>
	</tr>
</table>

<?if ($boolNew) {?>
<blockquote class="inline_help">
	<p>Click Create to generate the vDisks and return to the Virtual Machines page where your new VM will be created.</p>
</blockquote>
<?}?>

<hr>
<table>
	<tr>
		<td>
		_(QEMU Command Line)_:</td>
		<?$qemurows = $arrConfig['qemucmdline']=="" ? 2 : 15;?>
		<td>
			_(Advanced tuning options)_<br>
			<textarea id="qemucmdline" name="qemucmdline" class="xmlqemu" rows="<?=$qemurows?>" onchange="QEMUChgCmd(this)"><?=htmlspecialchars($arrConfig['qemucmdline'])."\n".htmlspecialchars($arrConfig['qemuoverride'])?></textarea>
		</td>
		<td></td>
	</tr>
</table>

<blockquote class="inline_help">
	<p>
		If you need to add QEMU arguments to the XML</p>
		Examples can be found on the Libvirt page => <a href="https://libvirt.org/kbase/qemu-passthrough-security.html " target="_blank">https://libvirt.org/kbase/qemu-passthrough-security.html</a>
	</p>
</blockquote>

<table class="timers">
	<tr>
		<td></td>
		<td>_(Clocks)_</td>
		<td></td>
	</tr>
	<tr>
		<td>_(Clocks Offset)_:</td>
		<td>
			<?$clockdisabled = "";?>
			<span class="width"><select name="domain[clock]" <?=$clockdisabled?> id="clockoffset" class="narrow <?=$arrConfig["domain"]['clock']?>">
			<?
			echo mk_option($arrConfig['domain']['clock'], 'localtime', 'Localtime');
			echo mk_option($arrConfig['domain']['clock'], 'utc', "UTC");
			?>
			</select></span>
		</td>
		<td>
			<textarea class="xml" id="xmlclock" rows="5" disabled ><?=htmlspecialchars($xml2['clock'])."\n".htmlspecialchars($xml2['on_poweroff'])."\n".htmlspecialchars($xml2['on_reboot'])."\n".htmlspecialchars($xml2['on_crash'])?></textarea>
		</td>
	</tr>
	<?
	$clockcount = 0;
	if (!empty($arrClocks)) {
		foreach ($arrClocks as $i => $arrTimer) {
			if ($i == 'offset') continue;
			if ($clockcount == 0) $clocksourcetext = _('Timer Source').':'; else $clocksourcetext = "";
	?>
	<tr>
		<td><?=$clocksourcetext?></td>
		<td>
			<span class="column1"><span><?=ucfirst($i)?>:</span></span>
			<span class="column2">_(Present)_:
			<select name="clock[<?=$i?>][present]" <?=$clockdisabled?> id="clock[<?=$i?>][present]" class="narrow second" <?=$arrTimer["present"]?>>
			<?
			echo mk_option($arrTimer["present"], 'yes', 'Yes');
			echo mk_option($arrTimer["present"], 'no', "No");
			?>
			</select></span>
			_(Tickpolicy)_:
			<select name="clock[<?=$i?>][tickpolicy]" <?=$clockdisabled?> id="clock[<?=$i?>][tickpolicy]" class="narrow second" <?=$arrTimer["tickpolicy"]?>>
			<?
			echo mk_option($arrTimer["tickpolicy"], 'delay', 'Delay');
			echo mk_option($arrTimer["tickpolicy"], 'catchup', 'Catchup');
			echo mk_option($arrTimer["tickpolicy"], 'merge', "Merge");
			echo mk_option($arrTimer["tickpolicy"], 'discard', "Discard");
			?>
			</select>
		</td>
	</tr>
	<?
			$clockcount++;
		}
	}
	?>
</table>

<blockquote class="inline_help">
	<p>Allows setting of timers and offset into the XML You should not need to modify these values.</p>
	<p>Windows tuning details can be found here <a href="https://forums.unraid.net/topic/134041-guide-optimizing-windows-vms-in-unraid/" target="_blank">https://forums.unraid.net/topic/134041-guide-optimizing-windows-vms-in-unraid/ </a></p>
	<p>Details can be found on the Libvirt XML page => <a href="https://libvirt.org/formatdomain.html#time-keeping" target="_blank">https://libvirt.org/formatdomain.html#time-keeping</a></p>
	<p>Defaults are:</p>
	<p>linux Hpet:no Hypervclock: no Pit yes rtc yes tickpolicy catchup.</p>
	<p>Windows Hpet:no Hypervclock: yes Pit yes rtc yes tickpolicy catchup.</p>
	<p>Windows and Hyperv Hpet:no Hypervclock: yes Pit no rtc no.</p>
</blockquote>

<?
if (!isset($arrConfig['evdev'])) $arrConfig['evdev'][0] = ['dev'=>"",'grab'=>"",'repeat'=>"",'grabToggle'=>""];
foreach ($arrConfig['evdev'] as $i => $arrEvdev) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';
?>
<table data-category="evdev" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr>
		<td>_(Evdev Device)_:</td>
		<td>
			<span class="width"><select name="evdev[<?=$i?>][dev]" class="dev narrow">
			<?
			echo mk_option($arrEvdev['dev'], '', _('None'));
			foreach (getValidevDev() as $line) echo mk_option($arrEvdev['dev'], $line , $line);
			?>
			</select></span>
		</td>
		<td>
			<textarea class="xml" id="xmlclock" rows="5" disabled ><?=htmlspecialchars($xml2['devices']['allinput'])?></textarea>
		</td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(Grab)_:</td>
		<td>
			<span class="width"><select name="evdev[<?=$i?>][grab]" class="evdev_grab narrow">
			<?
			echo mk_option($arrEvdev['grab'], '', _('None'));
			foreach (["all"] as $line) echo mk_option($arrEvdev['grab'],$line,ucfirst($line));
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(Repeat)_:</td>
		<td>
			<span class="width"><select name="evdev[<?=$i?>][repeat]" class="evdev_repeat narrow">
			<?
			echo mk_option($arrEvdev['repeat'], '', _('None'));
			foreach (["on","off"] as $line) echo mk_option($arrEvdev['repeat'],$line,ucfirst($line));
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(Grab Toggle)_:</td>
		<td>
			<span class="width"><select name="evdev[<?=$i?>][grabToggle]" class="evdev_grabtoggle narrow">
			<?
			echo mk_option($arrEvdev['grabToggle'], '', _('None'));
			foreach (["ctrl-ctrl", "alt-alt", "shift-shift", "meta-meta", "scrolllock" , "ctrl-scrolllock"] as $line) echo mk_option($arrEvdev['grabToggle'],$line,$line);
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
</table>

<?if ($i == 0) {?>
<div class="advanced">
<blockquote class="inline_help">
	<p>
		<b> Event Devices</b><br>
		Evdev is an input interface built into the Linux kernel. QEMU’s evdev passthrough support allows a user to redirect evdev events to a guest. These events can include mouse movements and key presses. By hitting both Ctrl keys at the same time, QEMU can toggle the input recipient. QEMU’s evdev passthrough also features almost no latency, making it perfect for gaming. The main downside to evdev passthrough is the lack of button rebinding – and in some cases, macro keys won’t even work at all.
		Optional items are normally only used for keyboards.
	</p>
	<p>
		<b>Device</b><br>
		Host device to passthrough to guest.
	</p>
	<p>
		<b>Grab</b><br>
		All grabs all input devices instead of just one
	</p>
	<p>
		<b>Repeat</b><br>
		Repeat with value 'on'/'off' to enable/disable auto-repeat events
	</p>
	<p>
		<b>GrabToggle</b><br>
		GrabToggle with values ctrl-ctrl, alt-alt, shift-shift, meta-meta, scrolllock or ctrl-scrolllock to change the grab key combination
	</p>
	<p>
		Additional devices can be added/removed by clicking the symbols to the left.
	</p>
</blockquote>
</div>
<?}?>
<?}?>

<script type="text/html" id="tmplevdev">
<table data-category="evdev" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
	<tr>
		<td>_(Evdev Device)_:</td>
		<td>
			<span class="width"><select name="evdev[{{INDEX}}][dev]" class="dev narrow">
			<?
			echo mk_option("", '', _('None'));
			foreach (getValidevDev() as $line) echo mk_option("", $line , $line);
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(Grab)_:</td>
		<td>
			<span class="width"><select name="evdev[{{INDEX}}][grab]" class="evdev_grab narrow">
			<?
			echo mk_option(""	, '', _('None'));
			foreach (["all"] as $line) echo mk_option("",$line,ucfirst($line));
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(Repeat)_:</td>
		<td>
			<span class="width"><select name="evdev[{{INDEX}}][repeat]" class="evdev_repeat narrow">
			<?
			echo mk_option("", '', _('None'));
			foreach (["on","off"] as $line) echo mk_option("",$line,ucfirst($line));
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
	<tr class="advanced disk_file_options">
		<td>_(Grab Toggle)_:</td>
		<td>
			<span class="width"><select name="evdev[{{INDEX}}][grabToggle]" class="evdev_grabtoggle narrow">
			<?
			echo mk_option("", '', _('None'));
			foreach (["ctrl-ctrl", "alt-alt", "shift-shift", "meta-meta", "scrolllock" , "ctrl-scrolllock"] as $line) echo mk_option("",$line,$line);
			?>
			</select></span>
		</td>
		<td></td>
	</tr>
</table>
</script>

<table>
	<tr class="xml">
		<td>_(Other XML)_:</td>
		<?$qemurows = $arrConfig['qemucmdline']=="" ? 2 : 15;?>
		<td></td>
		<td>
			<textarea id="xmlother" name="xmlother" disabled class="xml" rows="10"><?=htmlspecialchars($xml2['devices']['emulator'][0])."\n".htmlspecialchars($xml2['devices']['console'][0])."\n".htmlspecialchars($xml2['devices']['serial'][0])."\n".htmlspecialchars($xml2['devices']['channel'][0])."\n"?></textarea>
		</td>
	</tr>
</table>

<table>
	<tr>
		<td></td>
		<td>
		<?if (!$boolNew) {?>
			<input type="hidden" name="updatevm" value="1"/>
			<input type="button" value="_(Update)_" busyvalue="_(Updating)_..." readyvalue="_(Update)_" id="btnSubmit"/>
		<?} else {?>
			<input type="hidden" name="createvm" value="1"/>
			<input type="button" value="_(Create)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnSubmit"/>
		<?}?>
			<input type="button" value="_(Cancel)_" id="btnCancel"/>
			<input type="button" value=" _(Create/Modify Template)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnTemplateSubmit"/>
		</td>
		<td></td>
	</tr>
</table>
</div>

<?if ($boolNew) {?>
<blockquote class="inline_help">
	<p>Click Create to generate the vDisks and return to the Virtual Machines page where your new VM will be created.</p>
</blockquote>
<?}?>

<div class="xmlview">
<textarea id="addcode" name="xmldesc" placeholder="_(Copy &amp; Paste Domain XML Configuration Here)_." autofocus><?=htmlspecialchars($hdrXML).htmlspecialchars($strXML)?></textarea>
<table>
	<tr>
		<td></td>
		<td>
		<?if (!$boolRunning) {?>
		<?if ($strXML) {?>
			<input type="hidden" name="updatevm" value="1"/>
			<input type="button" value="_(Update)_" busyvalue="_(Updating)_..." readyvalue="_(Update)_" id="btnSubmit"/>
		<?} else {?>
			<label for="xmldomain_start"><input type="checkbox" name="domain[xmlstartnow]" id="xmldomain_start" value="1" checked="checked"/> _(Start VM after creation)_</label>
			<br>
			<input type="hidden" name="createvm" value="1"/>
			<input type="button" value="_(Create)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnSubmit"/>
		<?}?>
			<input type="button" value="_(Cancel)_" id="btnCancel"/>
			<input type="button" value=" _(Create/Modify Template)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnTemplateSubmit"/>
		<?} else {?>
			<input type="button" value="_(Back)_" id="btnCancel"/>
		<?}?>
		</td>
		<td></td>
	</tr>
</table>
</div>

<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/lib/codemirror.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/display/placeholder.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/fold/foldcode.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/show-hint.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/xml-hint.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/libvirt-schema.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/mode/xml/xml.js')?>"></script>
<script type="text/javascript">
var storageType = "<?=get_storage_fstype($arrConfig['template']['storage']);?>";
var storageLoc  = "<?=$arrConfig['template']['storage']?>";

function updateMAC(index,port) {
	$('input[name="nic['+index+'][mac]"').prop('disabled',port=='wlan0');
	$('i.mac_generate.'+index).prop('disabled',port=='wlan0');
	$('span.wlan0').removeClass('hidden');
	if (port != 'wlan0') {
		$('span.wlan0').addClass('hidden');
		$('i.mac_generate.'+index).click();
	}
}

function ShareChange(share) {
	var value = share.value;
	var text = share.options[share.selectedIndex].text;
	var strArray = text.split(":");
	var index = share.name.indexOf("]") + 1;
	var name = share.name.substr(0,index);
	if (strArray[0] === "User") {
		var path = "/mnt/user/" + strArray[1];
	} else {
		var path = "/mnt/" + strArray[1];
	}
	if (strArray[0] != "Manual") {
		$('#'+name+"[target]").val(strArray[1]);
		$('#'+name+"[source]").val(path);
		$('#'+name+"[target]").prop("disabled",true);
		$('#'+name+"[source]").prop("disabled",true);
	} else {
		$('#'+name+"[target]").prop("disabled",false);
		$('#'+name+"[source]").prop("disabled",false);
	}
}

function BusChange(value, index) {
	$('input[id="disk['+index+'][rotation]"]').removeClass('hidden');
	$('span[id="disk['+index+'][rotatetext]"]').removeClass('hidden');
	if (value == "virtio" || value == "usb" ) {
		$('input[id="disk['+index+'][rotation]"]').addClass('hidden');
		$('span[id="disk['+index+'][rotatetext]"]').addClass('hidden');
	}
}

function updateSSDCheck(ssd) {
	ssd.value = $(ssd).prop('checked') ? "1" : "0";
}

function BIOSChange(value) {
	$("#USBBoottext").removeClass('hidden');
	$("#domain_usbboot").removeClass('hidden');
	if (value == "0") {
		$("#USBBoottext").addClass('hidden');
		$("#domain_usbboot").addClass('hidden');
	}
}

function QEMUChgCmd(qemu) {
	var value = qemu.value;
	if (value != "") {
		$("#qemucmdline").attr("rows","15");
	} else {
		$("#qemucmdline").attr("rows","2");
	}
}

function HypervChgNew(hyperv) {
	var value = hyperv.value;
	if (value == "0") {
		var clockdefault = "windows";
		$("#clock[rtc][present]").val("<?=$arrDefaultClocks['windows']['rtc']['present']?>");
		$("#clock[pit][present]").val("<?=$arrDefaultClocks['windows']['pit']['present']?>");
	} else {
		var clockdefault = "hyperv";
		$("#clock[rtc][present]").val("<?=$arrDefaultClocks['hyperv']['rtc']['present']?>");
		$("#clock[pit][present]").val("<?=$arrDefaultClocks['hyperv']['pit']['present']?>");
	}
}

function SetBootorderfields(usbbootvalue) {
	var bootelements = document.getElementsByClassName("bootorder");
	for(var i = 0; i < bootelements.length; i++) {
		if (usbbootvalue == "Yes") {
		bootelements[i].value = "";
		bootelements[i].setAttribute("disabled","disabled");
		} else bootelements[i].removeAttribute("disabled");
	}
	var bootelements = document.getElementsByClassName("pcibootorder");
	const bootpcidevs = <?
		$devlist = [];
		foreach ($arrValidOtherDevices as $i => $arrDev) {
			if ($arrDev["typeid"] != "0108" && substr($arrDev["typeid"],0,2) != "02") $devlist[$arrDev['id']] = "N"; else $devlist[$arrDev['id']] = "Y";
		}
		echo json_encode($devlist);
	?>;
	for (var i = 0; i < bootelements.length; i++) {
		let bootpciid = bootelements[i].name.split('[');
		bootpciid= bootpciid[1].replace(']', '');
		if (usbbootvalue == "Yes") {
			bootelements[i].value = "";
			bootelements[i].setAttribute("disabled","disabled");
		} else {
			// Put check for PCI Type 0108 & 02xx and only remove disable if 0108 or 02xx.
			if (bootpcidevs[bootpciid] === "Y") 	bootelements[i].removeAttribute("disabled");
		}
	}
}

/* Remove characters not allowed in share name. */
function checkName(name) {
	var isValidName
	isValidName = /^[A-Za-z0-9][A-Za-z0-9\-_.: ]*$/.test(name);
	$('#zfs-name').removeClass();
	if (isValidName) {
		$('#btnSubmit').prop("disabled",false);
		$('#zfs-name').addClass('hidden')
	} else {
		if (storageType == "zfs") {
			$('#btnSubmit').prop("disabled",true);
		} else {
			$('#btnSubmit').prop("disabled",false);
			$('#zfs-name').addClass('hidden')
		}
	}
}

function get_storage_fstype(item) {
	storageLoc = item.value;
	$.post("/plugins/dynamix.vm.manager/include/VMajax.php", {action:"get_storage_fstype", storage:item.value}, function(data) {
		if (data.success) {
			if (data.fstype) {
				storageType=data.fstype;
				checkName($("#domain_name").val());
			}
		}
		if (data.error) {}
	}, "json");
}

function USBBootChange(usbboot) {
	// Remove all boot orders if changed to Yes
	var value = usbboot.value;
	SetBootorderfields(value);
}

function AutoportChange(autoport) {
	$("#port").removeClass('hidden');
	$("#Porttext").removeClass('hidden');
	$("#wsport").removeClass('hidden');
	$("#WSPorttext").removeClass('hidden');
	if (autoport.value == "yes") {
		$("#port").addClass('hidden');
		$("#Porttext").addClass('hidden');
		$("#wsport").addClass('hidden');
		$("#WSPorttext").addClass('hidden');
	} else {
		var protocol = document.getElementById("protocol").value;
		if (protocol != "vnc") {
			$("#wsport").addClass('hidden');
			$("#WSPorttext").addClass('hidden');
		}
	}
}

function VMConsoleDriverChange(driver) {
	$("#vncrender").removeClass('hidden');
	$("#vncrendertext").removeClass('hidden');
	if (driver.value != "virtio3d") {
		$("#vncrender").addClass('hidden');
		$("#vncrendertext").addClass('hidden');
	}
	$("#vncdspopt").removeClass('hidden');
	$("#vncdspopttext").removeClass('hidden');
	if (driver.value != "qxl") {
		$("#vncdspopt").addClass('hidden');
		$("#vncdspopttext").addClass('hidden');
	}
}

function ProtocolChange(protocol) {
	var autoport = $("#autoport").val();
	$("port").removeClass('hidden');
	$("Porttext").removeClass('hidden');
	$("wsport").removeClass('hidden');
	$("WSPorttext").removeClass('hidden');
	if (autoport == "yes") {
		$("port").addClass('hidden');
		$("Porttext").addClass('hidden');
		$("wsport").addClass('hidden');
		$("WSPorttext").addClass('hidden');
	}
}

function wlan0_info() {
	swal({
		title:"_(Manual Configuration Required)_",
		text:"<div class='wlan0'><i class='fa fa-fw fa-hand-o-right'></i> _(Configure the VM with a static IP address)_<br><br><i class='fa fa-fw fa-hand-o-right'></i> _(Only one VM can be active at the time)_<br><br><i class='fa fa-fw fa-hand-o-right'></i> _(Configure the same IP address on the ipvtap interface)_<br><span class='ipvtap'><i class='fa fa-fw fa-long-arrow-right'></i> ip addr add IP-ADDRESS dev shim-wlan0</span></div>",
		html:true,
		animation:"none",
		type:"info",
		confirmButtonText:"_(Ok)_"
	});
}

$(function() {
	function completeAfter(cm, pred) {
		var cur = cm.getCursor();
		if (!pred || pred()) setTimeout(function(){
			if (!cm.state.completionActive)
				cm.showHint({completeSingle: false});
		}, 100);
		return CodeMirror.Pass;
	}

	function completeIfAfterLt(cm) {
		return completeAfter(cm, function(){
			var cur = cm.getCursor();
			return cm.getRange(CodeMirror.Pos(cur.line, cur.ch - 1), cur) == "<";
		});
	}

	function completeIfInTag(cm) {
		return completeAfter(cm, function(){
			var tok = cm.getTokenAt(cm.getCursor());
			if (tok.type == "string" && (!/['"]/.test(tok.string.charAt(tok.string.length - 1)) || tok.string.length == 1)) return false;
			var inner = CodeMirror.innerMode(cm.getMode(), tok.state).state;
			return inner.tagName;
		});
	}

	var editor = CodeMirror.fromTextArea(document.getElementById("addcode"), {
		mode: "xml",
		lineNumbers: true,
		foldGutter: true,
		gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
		extraKeys: {
			"'<'": completeAfter,
			"'/'": completeIfAfterLt,
			"' '": completeIfInTag,
			"'='": completeIfInTag,
			"Ctrl-Space": "autocomplete"
		},
		hintOptions: {schemaInfo: getLibvirtSchema()}
	});

	SetBootorderfields("<?=$arrConfig['domain']['usbboot']?>");

	function resetForm() {
		$("#vmform .domain_vcpu").change(); // restore the cpu checkbox disabled states
		<?if (!empty($arrConfig['domain']['state'])):?>
		<?=$arrConfig['domain']['ovmf']==0 ? "$('#vmform #domain_ovmf').prop('disabled',true);\n" : "$('#vmform #domain_ovmf option[value=0]').prop('disabled',true);\n"?>
		<?endif?>
		<?if ($boolRunning):?>
		$("#vmform").find('input[type!="button"],select,.mac_generate').prop('disabled', true);
		$("#vmform").find('input[name^="usb"]').prop('disabled', false);
		<?endif?>
	}

	$('.advancedview').change(function(){
		if ($(this).is(':checked')) {
			setTimeout(function() {
				var xmlPanelHeight = window.outerHeight;
				if (xmlPanelHeight > 1024) xmlPanelHeight = xmlPanelHeight-550;
				editor.setSize(null,xmlPanelHeight);
				editor.refresh();
			}, 100);
		}
	});

	var regenerateDiskPreview = function(disk_index){
		var domaindir = '<?=$domain_cfg['DOMAINDIR']?>' + $('#domain_oldname').val();
		var tl_args = arguments.length;
		$("#vmform .disk").closest('table').each(function(index){
			var $table = $(this);
			if (tl_args && disk_index != $table.data('index')) {
				return;
			}
			var disk_select = $table.find(".disk_select option:selected").val();
			var $disk_file_sections = $table.find('.disk_file_options');
			var $disk_bus_sections = $table.find('.disk_bus_options');
			var $disk_input = $table.find('.disk');
			var $disk_preview = $table.find('.disk_preview');
			var $disk_serial = $table.find('.disk_serial');
			var $disk_driver = $table.find('.disk_driver').val();
			var $disk_ext = "img";
			if ($disk_driver == "raw") $disk_ext = "img";
				else if(disk_select != 'manual') $disk_ext = $disk_driver;
			if (disk_select == 'manual') {
				// Manual disk
				$disk_preview.fadeOut('fast', function() {
					$disk_input.fadeIn('fast');
				});
				$disk_bus_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($disk_bus_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
				$.getJSON("/plugins/dynamix.vm.manager/include/VMajax.php?action=file-info&file=" + encodeURIComponent($disk_input.val()), function( info ) {
					if (info.isfile || info.isblock) {
						slideUpRows($disk_file_sections);
						$disk_file_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
						$disk_input.attr('name', $disk_input.attr('name').replace('new', 'image'));
						if (info.isfile) $table.find('.disk_driver').val(info.format);
					} else {
						$disk_file_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
						slideDownRows($disk_file_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
						$disk_input.attr('name', $disk_input.attr('name').replace('image', 'new'));
					}
				});
			} else if (disk_select !== '') {
				// Auto disk
				var auto_disk_path = domaindir + '/vdisk' + (index+1) + '.' + $disk_ext;
				$disk_preview.html(auto_disk_path);
				$disk_input.fadeOut('fast', function() {
					$disk_preview.fadeIn('fast');
				});
				$disk_bus_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($disk_bus_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
				$.getJSON("/plugins/dynamix.vm.manager/include/VMajax.php?action=file-info&file=" + encodeURIComponent(auto_disk_path), function( info ) {
					if (info.isfile || info.isblock) {
						slideUpRows($disk_file_sections);
						$disk_file_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
						$disk_input.attr('name', $disk_input.attr('name').replace('new', 'image'));
					} else {
						$disk_file_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
						slideDownRows($disk_file_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
						$disk_input.attr('name', $disk_input.attr('name').replace('image', 'new'));
					}
				});
			} else {
				// No disk
				var $hide_el = $table.find('.disk_bus_options,.disk_file_options,.disk_preview,.disk');
				$disk_preview.html('');
				slideUpRows($hide_el);
				$hide_el.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
			}
		});
	};

	var setDiskserial = function (disk_index){
		var domaindir = '<?=$domain_cfg['DOMAINDIR']?>' + $('#domain_oldname').val();
		var tl_args = arguments.length;
		$("#vmform .disk").closest('table').each(function(index){
			var $table = $(this);
			if (tl_args && disk_index != $table.data('index')) {
				return;
			}
			var disk_select = $table.find(".disk_select option:selected").val();
			var $disk_serial = $table.find('.disk_serial');
			if (disk_select !== '') {
				// Auto disk serial
				var auto_serial = 'vdisk' + (index+1);
				$disk_serial.val(auto_serial);
			}
		});
	};

	<?if ($boolNew):?>
	$("#vmform #domain_name").on("input change", function changeNameEvent(){
		$('#vmform #domain_oldname').val($(this).val());
		regenerateDiskPreview();
	});
	<?endif?>

	$("#vmform .domain_vcpu").change(function changeVCPUEvent(){
		var $cores = $("#vmform .domain_vcpu:checked");
		if ($cores.length < 1) {
			$("#vmform .domain_vcpus").prop("disabled", false);
			$("#vmform .domain_vcpu").prop("disabled", false);
			$("#vmform .formview #btnvCPUSelect").prop("value", "_(Select All)_");
		} else {
			$("#vmform .domain_vcpus").prop("disabled", true).prop("value", $cores.length);
			$("#vmform .domain_vcpu").prop("disabled", false);
			$("#vmform .formview #btnvCPUSelect").prop("value", "_(Deselect All)_");
		}
	});

	$("#vmform #domain_mem").change(function changeMemEvent(){
		$("#vmform #domain_maxmem").val($(this).val());
	});

	$("#vmform #domain_maxmem").change(function changeMaxMemEvent(){
		if (parseFloat($(this).val()) < parseFloat($("#vmform #domain_mem").val())) {
			$("#vmform #domain_mem").val($(this).val());
		}
	});

	$("#vmform #domain_machine").change(function changeMachineEvent(){
		// Cdrom Bus: select IDE for i440 and SATA for q35
		if ($(this).val().indexOf('i440fx') != -1) {
			$('#vmform .cdrom_bus').val('ide');
		} else {
			$('#vmform .cdrom_bus').val('sata');
		}
	});

	$("#vmform #domain_ovmf").change(function changeBIOSEvent(){
		// using OVMF - disable vmvga vnc option
		if ($(this).val() != '0' && $("#vmform #vncmodel").val() == 'vmvga') {
			$("#vmform #vncmodel").val('qxl');
		}
		$("#vmform #vncmodel option[value='vmvga']").prop('disabled', ($(this).val() != '0'));
	}).change(); // fire event now

	$("#vmform").on("spawn_section", function spawnSectionEvent(evt, section, sectiondata){
		if (sectiondata.category == 'vDisk') {
			regenerateDiskPreview(sectiondata.index);
			setDiskserial(sectiondata.index);
		}
		if (sectiondata.category == 'Graphics_Card') {
			$(section).find(".gpu").change();
		}
	});

	$("#vmform").on("destroy_section", function destroySectionEvent(evt, section, sectiondata){
		if (sectiondata.category == 'vDisk') {
			regenerateDiskPreview();
		}
	});

	$("#vmform").on("input change", ".cdrom", function changeCdromEvent(){
		if ($(this).val() == '') {
			slideUpRows($(this).closest('table').find('.cdrom_bus').closest('tr'));
		} else {
			slideDownRows($(this).closest('table').find('.cdrom_bus').closest('tr'));
		}
	});

	$("#vmform").on("change", ".disk_select", function changeDiskSelectEvent(){
		regenerateDiskPreview($(this).closest('table').data('index'));
	});

	$("#vmform").on("change", ".disk_driver", function changeDiskSelectEvent(){
		regenerateDiskPreview($(this).closest('table').data('index'));
	});

	$("#vmform").on("input change", ".disk", function changeDiskEvent(){
		var $input = $(this);
		var config = $input.data();
		if (config.hasOwnProperty('pickfilter')) {
			regenerateDiskPreview($input.closest('table').data('index'));
		}
	});

	$("#vmform").on("change", ".cpu", function changeCPUEvent(){
		$("#domain_cpumigrate_text").removeClass('hidden');
		$("#domain_cpumigrate").removeClass('hidden');
		if ($(this).val() == "custom") {
			$("#domain_cpumigrate_text").addClass('hidden');
			$("#domain_cpumigrate").addClass('hidden');
		}
	});

	$("#vmform").on("change", ".gpu", function changeGPUEvent(){
		const ValidGPUs = <?=json_encode($arrValidGPUDevices)?>;
		var myvalue = $(this).val();
		var mylabel = $(this).children('option:selected').text();
		var myindex = $(this).closest('table').data('index');
		if (myindex == 0) {
			$vnc_sections = $('.autoportline,.protocol,.vncmodel,.vncpassword,.vnckeymap,.copypaste');
			if (myvalue == 'virtual') {
				$vnc_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($vnc_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
				var MultiSel = document.getElementById("GPUMultiSel0");
				MultiSel.disabled = true;
				$("#vncrender").removeClass('hidden');
				$("#vncrendertext").removeClass('hidden');
				if ($("#vncmodel").val() != "virtio3d") {
					$("#vncrender").addClass('hidden');
					$("#vncrendertext").addClass('hidden');
				}
				$("#vncdspopt").removeClass('hidden');
				$("#vncdspopttext").removeClass('hidden');
				if ($("#vncmodel").val() != "qxl") {
					$("#vncdspopt").addClass('hidden');
					$("#vncdspopttext").addClass('hidden');
				}
			} else {
				slideUpRows($vnc_sections);
				$vnc_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
				var MultiSel = document.getElementById("GPUMultiSel0");
				if (myvalue=="nogpu") MultiSel.disabled = true; else MultiSel.disabled = false;
			}
		}
		$("#gpubootvga"+myindex).removeClass();
		if (mylabel == "_(None)_") $("#gpubootvga"+myindex).addClass('hidden');
		if (myvalue != "_(virtual)_" && myvalue != "" && myvalue != "_(nogpu)_") {
			if (ValidGPUs[myvalue].bootvga != "1") $("#gpubootvga"+myindex).addClass('hidden');
		} else {
			$("#gpubootvga"+myindex).addClass('hidden');
		}
		$romfile = $(this).closest('table').find('.romfile');
		if (myvalue == "_(virtual)_" || myvalue == "" || myvalue == "_(nogpu)_") {
			slideUpRows($romfile.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
			$romfile.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
		} else {
			$romfile.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
			slideDownRows($romfile.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
			$("#vmform .gpu").not(this).each(function(){
				if (myvalue == $(this).val()) {
					$(this).prop("selectedIndex", 0).change();
				}
			});
		}
	});

	$("#vmform").on("click", ".mac_generate", function generateMac(){
		var $input = $(this).prev('input');
		$.getJSON("/plugins/dynamix.vm.manager/include/VMajax.php?action=generate-mac", function(data){
			if (data.mac) {
				$input.val(data.mac);
			}
		});
	});

	$("#vmform .formview #btnvCPUSelect").click(function selectcpus(){
		if (this.value == "_(Select All)_"){
			$('.domain_vcpu').prop('checked', true);
			var $cores = $("#vmform .domain_vcpu:checked");
			$("#vmform .domain_vcpus").prop("disabled", true).prop("value", $cores.length);
			this.value = "_(Deselect All)_";
		} else {
			$('.domain_vcpu').prop('checked', false);
			var $cores = $("#vmform .domain_vcpu:checked");
			$("#vmform .domain_vcpus").prop("disabled", false).prop("value", 1);
			this.value = "_(Select All)_";
		}
	});

	$("#vmform .formview #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $panel = $('.formview');
		var form = $button.closest('form');
		$("#vmform .disk_select option:selected").not("[value='manual']").closest('table').each(function(){
			var v = $(this).find('.disk_preview').html();
			$(this).find('.disk').val(v);
		});
		$panel.find('input').prop('disabled', false); // enable all inputs otherwise they wont post
		<?if (!$boolNew):?>
		// signal devices to be added or removed
		form.find('input[name="usb[]"],input[name="pci[]"],input[name="usbopt[]"]').each(function(){
			if (!$(this).prop('checked')) $(this).prop('checked',true).val($(this).val()+'#remove');
		});
		// remove unused graphic cards
		var gpus = [], i = 0;
		do {
			var gpu = form.find('select[name="gpu['+(i++)+'][id]"] option:selected').val();
			if (gpu) gpus.push(gpu);
		} while (gpu);
		form.find('select[name="gpu[0][id]"] option').each(function(){
			var gpu = $(this).val();
			if ((gpu != 'virtual' && gpu != 'nogpu') && !gpus.includes(gpu)) form.append('<input type="hidden" name="pci[]" value="'+gpu+'#remove">');
		});
		// remove unused sound cards
		var sound = [], i = 0;
		do {
			var audio = form.find('select[name="audio['+(i++)+'][id]"] option:selected').val();
			if (audio) sound.push(audio);
		} while (audio);
		form.find('select[name="audio[0][id]"] option').each(function(){
			var audio = $(this).val();
			if (audio && !sound.includes(audio)) form.append('<input type="hidden" name="pci[]" value="'+audio+'#remove">');
		});
		<?endif?>
		var postdata = form.find('input,select,textarea[name="qemucmdline"]').serialize().replace(/'/g,"%27");
		<?if (!$boolNew):?>
		// keep checkbox visually unchecked
		form.find('input[name="usb[]"],input[name="usbopt[]"],input[name="pci[]"]').each(function(){
			if ($(this).val().indexOf('#remove')>0) $(this).prop('checked',false);
		});
		<?endif?>
		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));
		$.post("/plugins/dynamix.vm.manager/templates/Custom.form.php", postdata, function(data){
			if (data.success) {
				if (data.vmrcurl) {
					var vmrc_window=window.open(data.vmrcurl, '_blank', 'scrollbars=yes,resizable=yes');
					try {
						vmrc_window.focus();
					} catch (e) {
						swal({title:"_(Browser error)_",text:"_(Pop-up Blocker is enabled! Please add this site to your exception list)_",type:"warning",confirmButtonText:"_(Ok)_"},function(){done();});
						return;
					}
				}
				done();
			}
			if (data.error) {
				swal({title:"_(VM creation error)_",text:data.error,type:"error",confirmButtonText:"_(Ok)_"});
				$panel.find('input').prop('disabled', false);
				$button.val($button.attr('readyvalue'));
				resetForm();
			}
		}, "json");
	});

	$("#vmform .formview #btnTemplateSubmit").click(function frmSubmit(){
		var $button = $(this);
		var $panel = $('.formview');
		var form = $button.closest('form');
		form.append('<input type="hidden" name="createvmtemplate" value="1"/>');
		var createVmInput = form.find('input[name="createvm"],input[name="updatevm"]');
		createVmInput.remove();
		$("#vmform .disk_select option:selected").not("[value='manual']").closest('table').each(function(){
			var v = $(this).find('.disk_preview').html();
			$(this).find('.disk').val(v);
		});
		$panel.find('input').prop('disabled', false); // enable all inputs otherwise they wont post
		<?if (!$boolNew):?>
		// signal devices to be added or removed
		form.find('input[name="usb[]"],input[name="pci[]"],input[name="usbopt[]"]').each(function(){
			if (!$(this).prop('checked')) $(this).prop('checked',true).val($(this).val()+'#remove');
		});
		// remove unused graphic cards
		var gpus = [], i = 0;
		do {
			var gpu = form.find('select[name="gpu['+(i++)+'][id]"] option:selected').val();
			if (gpu) gpus.push(gpu);
		} while (gpu);
		form.find('select[name="gpu[0][id]"] option').each(function(){
			var gpu = $(this).val();
			if ((gpu != 'virtual' && gpu != 'nogpu') && !gpus.includes(gpu)) form.append('<input type="hidden" name="pci[]" value="'+gpu+'#remove">');
		});
		// remove unused sound cards
		var sound = [], i = 0;
		do {
			var audio = form.find('select[name="audio['+(i++)+'][id]"] option:selected').val();
			if (audio) sound.push(audio);
		} while (audio);
		form.find('select[name="audio[0][id]"] option').each(function(){
			var audio = $(this).val();
			if (audio && !sound.includes(audio)) form.append('<input type="hidden" name="pci[]" value="'+audio+'#remove">');
		});
		<?endif?>
		var postdata = form.find('input,select,textarea[name="qemucmdline"]').serialize().replace(/'/g,"%27");
		<?if (!$boolNew):?>
		// keep checkbox visually unchecked
		form.find('input[name="usb[]"],input[name="usbopt[]"],input[name="pci[]"]').each(function(){
			if ($(this).val().indexOf('#remove')>0) $(this).prop('checked',false);
		});
		<?endif?>
		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));
		swal({
			title: "_(Template Name)_",
			text: "_(Enter name)_:\n_(If name already exists it will be replaced)_.",
			type: "input",
			showCancelButton: true,
			closeOnConfirm: false,
			//animation: "slide-from-top",
			inputPlaceholder: "_(Leaving blank will use OS name)_."
		},
		function(inputValue) {
			postdata=postdata+"&templatename="+inputValue;
			$.post("/plugins/dynamix.vm.manager/templates/Custom.form.php", postdata, function(data) {
				if (data.success) {
					if (data.vmrcurl) {
						var vmrc_window=window.open(data.vmrcurl, '_blank', 'scrollbars=yes,resizable=yes');
						try {
							vmrc_window.focus();
						} catch (e) {
							swal({title:"_(Browser error)_",text:"_(Pop-up Blocker is enabled! Please add this site to your exception list)_",type:"warning",confirmButtonText:"_(Ok)_"},function(){done();});
							return;
						}
					}
					done();
				}
				if (data.error) {
					swal({title:"_(VM creation error)_",text:data.error,type:"error",confirmButtonText:"_(Ok)_"});
					$panel.find('input').prop('disabled', false);
					$button.val($button.attr('readyvalue'));
					resetForm();
				}
			}, "json");
		});
	});

	$("#vmform .xmlview #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $panel = $('.xmlview');
		editor.save();
		$panel.find('input').prop('disabled', false); // enable all inputs otherwise they wont post
		var postdata = $panel.closest('form').serialize().replace(/'/g,"%27");
		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));
		$.post("/plugins/dynamix.vm.manager/templates/Custom.form.php", postdata, function( data ) {
			if (data.success) {
				done();
			}
			if (data.error) {
				swal({title:"_(VM creation error)_",text:data.error,type:"error",confirmButtonText:"_(Ok)_"});
				$panel.find('input').prop('disabled', false);
				$button.val($button.attr('readyvalue'));
				resetForm();
			}
		}, "json");
	});

	$("#vmform .xmlview #btnTemplateSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $panel = $('.xmlview');
		editor.save();
		$panel.find('input').prop('disabled', false); // enable all inputs otherwise they wont post
		var form = $button.closest('form');
		form.append('<input type="hidden" name="createvmtemplate" value="1"/>');
		var createVmInput = form.find('input[name="createvm"],input[name="updatevm"]');
		createVmInput.remove();
		var postdata = $panel.closest('form').serialize().replace(/'/g,"%27");
		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));
		swal({
			title: "_(Template Name)_",
			text: "_(Enter name)_:\n_(If name already exists it will be replaced)_.",
			type: "input",
			showCancelButton: true,
			closeOnConfirm: false,
			//animation: "slide-from-top",
			inputPlaceholder: "_(Leaving blank will use OS name)_."
		},
		function(inputValue) {
			postdata=postdata+"&templatename="+inputValue;
			$.post("/plugins/dynamix.vm.manager/templates/Custom.form.php", postdata, function(data) {
				if (data.success) {
					done();
				}
				if (data.error) {
					swal({title:"_(VM creation error)_",text:data.error,type:"error",confirmButtonText:"_(Ok)_"});
					$panel.find('input').prop('disabled', false);
					$button.val($button.attr('readyvalue'));
					resetForm();
				}
			}, "json");
		});
	});

	// Fire events below once upon showing page
	var os = $("#vmform #template_os").val() || 'linux';
	var os_casted = (os.indexOf('windows') == -1 ? 'other' : 'windows');
	$('#vmform .domain_os').not($('.' + os_casted)).hide();
	$('#vmform .domain_os.' + os_casted).not(isVMAdvancedMode() ? '.basic' : '.advanced').show();
	<?if ($boolNew):?>
	if (os_casted == 'windows') {
		$('#vmform #domain_clock').val('localtime');
		$("#vmform #domain_machine option").each(function(){
			if ($(this).val().indexOf('i440fx') != -1) {
				var usertemplate = <?=$usertemplate?>;
				if (usertemplate == 0) $('#vmform #domain_machine').val($(this).val()).change();
				return false;
			}
		});
	} else {
		$('#vmform #domain_clock').val('utc');
		$('#vmform #clockoffset').val('utc');
		$("#vmform #domain_machine option").each(function(){
			if ($(this).val().indexOf('q35') != -1) {
				var usertemplate = <?=$usertemplate?>;
				if (usertemplate == 0) $('#vmform #domain_machine').val($(this).val()).change();
				return false;
			}
		});
	}
	<?endif?>
	// disable usb3 option for windows7 / xp / server 2003 / server 2008
	var noUSB3 = (os == 'windows7' || os == 'windows2008' || os == 'windowsxp' || os == 'windows2003');
	if (noUSB3 && ($("#vmform #usbmode").val().indexOf('usb3')===0)) {
		$("#vmform #usbmode").val('usb2');
	}
	$("#vmform #usbmode option[value^='usb3']").prop('disabled', noUSB3);
	$("#vmform .gpu").change();
	$('#vmform .cdrom').change();
	regenerateDiskPreview();
	resetForm();
});
</script>
