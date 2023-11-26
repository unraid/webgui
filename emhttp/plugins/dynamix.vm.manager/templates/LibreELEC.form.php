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
	require_once "$docroot/webGui/include/Custom.php";
	require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

	// add translations
	if (substr($_SERVER['REQUEST_URI'],0,4) != '/VMs') {
		$_SERVER['REQUEST_URI'] = 'vms';
		require_once "$docroot/webGui/include/Translations.php";
	}

	$arrValidMachineTypes = getValidMachineTypes();
	$arrValidGPUDevices = getValidGPUDevices();
	$arrValidAudioDevices = getValidAudioDevices();
	$arrValidOtherDevices = getValidOtherDevices();
	$arrValidUSBDevices = getValidUSBDevices();
	$arrValidDiskDrivers = getValidDiskDrivers();
	$arrValidProtocols = getValidVMRCProtocols();
	$arrValidNetworks = getValidNetworks();
	$strCPUModel = getHostCPUModel();
	$arrValidKeyMaps = getValidKeyMaps();

	// Read localpaths in from libreelec.cfg
	$strLibreELECConfig = "/boot/config/plugins/dynamix.vm.manager/libreelec.cfg";
	$arrLibreELECConfig = [];

	if (file_exists($strLibreELECConfig)) {
		$arrLibreELECConfig = parse_ini_file($strLibreELECConfig);
	} elseif (!file_exists(dirname($strLibreELECConfig))) {
		@mkdir(dirname($strLibreELECConfig), 0777, true);
	}

	// Compare libreelec.cfg and populate 'localpath' in $arrOEVersion
	foreach ($arrLibreELECConfig as $strID => $strLocalpath) {
		if (array_key_exists($strID, $arrLibreELECVersions)) {
			$arrLibreELECVersions[$strID]['localpath'] = $strLocalpath;
			if (file_exists($strLocalpath)) {
				$arrLibreELECVersions[$strID]['valid'] = '1';
			}
		}
	}

	if (isset($_POST['delete_version'])) {
		$arrDeleteLibreELEC = [];
		if (array_key_exists($_POST['delete_version'], $arrLibreELECVersions)) {
			$arrDeleteLibreELEC = $arrLibreELECVersions[$_POST['delete_version']];
		}
		$reply = [];
		if (empty($arrDeleteLibreELEC)) {
			$reply = ['error' => _('Unknown version').': ' . $_POST['delete_version']];
		} else {
			// delete img file
			@unlink($arrDeleteLibreELEC['localpath']);

			// Save to strLibreELECConfig
			unset($arrLibreELECConfig[$_POST['delete_version']]);
			$text = '';
			foreach ($arrLibreELECConfig as $key => $value) $text .= "$key=\"$value\"\n";
			file_put_contents($strLibreELECConfig, $text);
			$reply = ['status' => 'ok'];
		}

		echo json_encode($reply);
		exit;
	}

	if (isset($_POST['download_path'])) {
		$arrDownloadLibreELEC = [];
		if (array_key_exists($_POST['download_version'], $arrLibreELECVersions)) {
			$arrDownloadLibreELEC = $arrLibreELECVersions[$_POST['download_version']];
		}
		if (empty($arrDownloadLibreELEC)) {
			$reply = ['error' => _('Unknown version').': ' . $_POST['download_version']];
		} elseif (empty($_POST['download_path'])) {
			$reply = ['error' => _('Please choose a folder the LibreELEC image will download to')];
		} else {
			@mkdir($_POST['download_path'], 0777, true);
			$_POST['download_path'] = realpath($_POST['download_path']) . '/';

			// Check free space
			if (disk_free_space($_POST['download_path']) < $arrDownloadLibreELEC['size']+10000) {
				$reply = ['error' => _('Not enough free space, need at least').' ' . ceil($arrDownloadLibreELEC['size']/1000000).'MB'];
				echo json_encode($reply);
				exit;
			}

			$boolCheckOnly = !empty($_POST['checkonly']);
			$strInstallScript = '/tmp/LibreELEC_' . $_POST['download_version'] . '_install.sh';
			$strInstallScriptPgrep = '-f "LibreELEC_' . $_POST['download_version'] . '_install.sh"';
			$strTempFile = $_POST['download_path'] . basename($arrDownloadLibreELEC['url']);
			$strLogFile = $strTempFile . '.log';
			$strMD5File = $strTempFile . '.md5';
			$strMD5StatusFile = $strTempFile . '.md5status';
			$strExtractedFile = $_POST['download_path'] . basename($arrDownloadLibreELEC['url'], 'tar.xz') . 'img';

			// Save to strLibreELECConfig
			$arrLibreELECConfig[$_POST['download_version']] = $strExtractedFile;
			$text = '';
			foreach ($arrLibreELECConfig as $key => $value) $text .= "$key=\"$value\"\n";
			file_put_contents($strLibreELECConfig, $text);

			$strDownloadCmd = 'wget -nv -c -O ' . escapeshellarg($strTempFile) . ' ' . escapeshellarg($arrDownloadLibreELEC['url']);
			$strDownloadPgrep = '-f "wget.*' . $strTempFile . '.*' . $arrDownloadLibreELEC['url'] . '"';
			$strVerifyCmd = 'md5sum -c ' . escapeshellarg($strMD5File);
			$strVerifyPgrep = '-f "md5sum.*' . $strMD5File . '"';
			$strExtractCmd = 'tar Jxf ' . escapeshellarg($strTempFile) . ' -C ' . escapeshellarg(dirname($strTempFile));
			$strExtractPgrep = '-f "tar.*' . $strTempFile . '.*' . dirname($strTempFile) . '"';
			$strCleanCmd = '(chmod 777 ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strExtractedFile) . '; chown nobody:users ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strExtractedFile) . '; rm ' . escapeshellarg($strTempFile) . ' ' . escapeshellarg($strMD5File) . ' ' . escapeshellarg($strMD5StatusFile) . ')';
			$strCleanPgrep = '-f "chmod.*chown.*rm.*' . $strMD5StatusFile . '"';
			$strAllCmd = "#!/bin/bash\n\n";
			$strAllCmd .= $strDownloadCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'echo "' . $arrDownloadLibreELEC['md5'] . '  ' . $strTempFile . '" > ' . escapeshellarg($strMD5File) . ' && ';
			$strAllCmd .= $strVerifyCmd . ' >' . escapeshellarg($strMD5StatusFile) . ' 2>/dev/null && ';
			$strAllCmd .= $strExtractCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= $strCleanCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'rm ' . escapeshellarg($strLogFile) . ' && ';
			$strAllCmd .= 'rm ' . escapeshellarg($strInstallScript);

			$reply = [];
			if (file_exists($strExtractedFile)) {
				if (!file_exists($strTempFile)) {
					// Status = done
					$reply['status'] = 'Done';
					$reply['localpath'] = $strExtractedFile;
					$reply['localfolder'] = dirname($strExtractedFile);
				} else {
					if (pgrep($strExtractPgrep, false)) {
						// Status = running extract
						$reply['status'] = _('Extracting').' ... ';
					} else {
						// Status = cleanup
						$reply['status'] = _('Cleanup').' ... ';
					}
				}
			} elseif (file_exists($strTempFile)) {
				if (pgrep($strDownloadPgrep, false)) {
					// Get Download percent completed
					$intSize = filesize($strTempFile);
					$strPercent = 0;
					if ($intSize > 0) {
						$strPercent = round(($intSize / $arrDownloadLibreELEC['size']) * 100);
					}
					$reply['status'] = _('Downloading').' ... ' . $strPercent . '%';
				} elseif (pgrep($strVerifyPgrep, false)) {
					// Status = running md5 check
					$reply['status'] = _('Verifying').' ... ';
				} elseif (file_exists($strMD5StatusFile)) {
					// Status = running extract
					$reply['status'] = _('Extracting').' ... ';
					if (!pgrep($strExtractPgrep, false)) {
						// Examine md5 status
						$strMD5StatusContents = file_get_contents($strMD5StatusFile);
						if (strpos($strMD5StatusContents, ': FAILED') !== false) {
							// ERROR: MD5 check failed
							unset($reply['status']);
							$reply['error'] = _('MD5 verification failed, your download is incomplete or corrupted').'.';
						}
					}
				} elseif (!file_exists($strMD5File)) {
					// Status = running md5 check
					$reply['status'] = _('Downloading').' ... 100%';
					if (!pgrep($strInstallScriptPgrep, false) && !$boolCheckOnly) {
						// Run all commands
						file_put_contents($strInstallScript, $strAllCmd);
						chmod($strInstallScript, 0777);
						exec($strInstallScript . ' >/dev/null 2>&1 &');
					}
				}
			} elseif (!$boolCheckOnly) {
				if (!pgrep($strInstallScriptPgrep, false)) {
					// Run all commands
					file_put_contents($strInstallScript, $strAllCmd);
					chmod($strInstallScript, 0777);
					exec($strInstallScript . ' >/dev/null 2>&1 &');
				}
				$reply['status'] = _('Downloading').' ... ';
			}
			$reply['pid'] = pgrep($strInstallScriptPgrep, false);
		}
		echo json_encode($reply);
		exit;
	}

	$arrLibreELECVersion = reset($arrLibreELECVersions);
	$strLibreELECVersionID = key($arrLibreELECVersions);

	$arrConfigDefaults = [
		'template' => [
			'name' => $strSelectedTemplate,
			'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
			'libreelec' => $strLibreELECVersionID
		],
		'domain' => [
			'name' => $strSelectedTemplate,
			'persistent' => 1,
			'uuid' => $lv->domain_generate_uuid(),
			'clock' => 'utc',
			'arch' => 'x86_64',
			'machine' => getLatestMachineType('q35'),
			'mem' => 512 * 1024,
			'maxmem' => 512 * 1024,
			'password' => '',
			'cpumode' => 'host-passthrough',
			'vcpus' => 1,
			'vcpu' => [0],
			'hyperv' => 0,
			'ovmf' => 1,
			'usbmode' => 'usb3'
		],
		'media' => [
			'cdrom' => '',
			'cdrombus' => '',
			'drivers' => '',
			'driversbus' => ''
		],
		'disk' => [
			[
				'image' => $arrLibreELECVersion['localpath'],
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'readonly' => 1,
				'boot' => 1
			]
		],
		'gpu' => [
			[
				'id' => 'virtual',
				'protocol' => 'vnc',
				'autoport' => 'yes',
				'model' => 'qxl',
				'keymap' => 'en-us',
				'port' => -1 ,
				'wsport' => -1
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
				'source' => (is_dir('/mnt/user/appdata') ? '/mnt/user/appdata/LibreELEC/' : ''),
				'target' => 'appconfig'
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
			if (isset($_POST['shares'][0]['source'])) {
				@mkdir($_POST['shares'][0]['source'], 0777, true);
			}
			$_POST['clock'] = $arrDefaultClocks["other"] ;
			if ($lv->domain_new($_POST)){
				$reply = ['success' => true];
			} else {
				$reply = ['error' => $lv->get_last_error()];
			}
		}
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
				if (strpos($strNewUSBID,"#remove")) continue ;
				$remove = explode('#', $strNewUSBID) ;
				$strNewUSBID2 = $remove[0] ;
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
			$newDir = $domain_cfg['DOMAINDIR'].$newdName;
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

		// construct updated config
		if (isset($_POST['xmldesc'])) {
			// XML view
			$xml = $_POST['xmldesc'];
		} else {
			// form view
			if (isset($_POST['shares'][0]['source'])) {
				@mkdir($_POST['shares'][0]['source'], 0777, true);
			}
			$_POST['clock'] = $arrDefaultClocks["other"] ;
			$arrExistingConfig = custom::createArray('domain',$strXML);
			$arrUpdatedConfig = custom::createArray('domain',$lv->config_to_xml($_POST));
			array_update_recursive($arrExistingConfig, $arrUpdatedConfig);
			$arrConfig = array_replace_recursive($arrExistingConfig, $arrUpdatedConfig);
			$xml = custom::createXML('domain',$arrConfig)->saveXML();
		}
		// delete and create the VM
		$lv->nvram_backup($uuid);
		$lv->domain_undefine($dom);
		$lv->nvram_restore($uuid);
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
		$arrVMUSBs = getVMUSBs($strXML) ;
	} else {
		// edit new VM
		$boolRunning = false;
		$strXML = '';
		$boolNew = true;
		$arrConfig = $arrConfigDefaults;
		$arrVMUSBs = getVMUSBs($strXML) ;
	}

	if (array_key_exists($arrConfig['template']['libreelec'], $arrLibreELECVersions)) {
		$arrConfigDefaults['disk'][0]['image'] = $arrLibreELECVersions[$arrConfig['template']['libreelec']]['localpath'];
	}
?>

<link rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/lib/codemirror.css')?>">
<link rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/show-hint.css')?>">
<style type="text/css">
	.CodeMirror { border: 1px solid #eee; cursor: text; margin-top: 15px; margin-bottom: 10px; }
	.CodeMirror pre.CodeMirror-placeholder { color: #999; }
	#libreelec_image {
		color: #BBB;
		display: none;
		transform: translate(0px, 3px);
	}
	.delete_libreelec_image {
		cursor: pointer;
		margin-left: -5px;
		margin-right: 5px;
		color: #CC0011;
		font-size: 1.4rem;
		transform: translate(0px, 3px);
	}
</style>

<div class="formview">
<input type="hidden" name="domain[persistent]" value="<?=htmlspecialchars($arrConfig['domain']['persistent'])?>">
<input type="hidden" name="domain[uuid]" value="<?=htmlspecialchars($arrConfig['domain']['uuid'])?>">
<input type="hidden" name="domain[clock]" id="domain_clock" value="<?=htmlspecialchars($arrConfig['domain']['clock'])?>">
<input type="hidden" name="domain[arch]" value="<?=htmlspecialchars($arrConfig['domain']['arch'])?>">
<input type="hidden" name="domain[oldname]" value="<?=htmlspecialchars($arrConfig['domain']['name'])?>">

<input type="hidden" name="disk[0][image]" id="disk_0" value="<?=htmlspecialchars($arrConfig['disk'][0]['image'])?>">
<input type="hidden" name="disk[0][dev]" value="<?=htmlspecialchars($arrConfig['disk'][0]['dev'])?>">
<input type="hidden" name="disk[0][readonly]" value="1">
<input type="hidden" class="bootorder" name="disk[0][boot]" value="1">

	<div class="installed">
		<table>
			<tr>
				<td>_(Name)_:</td>
				<td><input type="text" name="domain[name]" id="domain_name" class="textTemplate" title="_(Name of virtual machine)_" placeholder="_(e.g.)_ _(LibreELEC)_" value="<?=htmlspecialchars($arrConfig['domain']['name'])?>" required /></td>
			</tr>
		</table>
		<blockquote class="inline_help">
			<p>Give the VM a name (e.g. LibreELEC Family Room, LibreELEC Theatre, LibreELEC)</p>
		</blockquote>

		<table>
			<tr class="advanced">
				<td>_(Description)_:</td>
				<td><input type="text" name="domain[desc]" title="_(description of virtual machine)_" placeholder="_(description of virtual machine)_ (_(optional)_)" value="<?=htmlspecialchars($arrConfig['domain']['desc'])?>" /></td>
			</tr>
		</table>
		<div class="advanced">
			<blockquote class="inline_help">
				<p>Give the VM a brief description (optional field).</p>
			</blockquote>
		</div>
	</div>

	<table>
		<tr>
			<td>_(LibreELEC Version)_:</td>
			<td>
				<select name="template[libreelec]" id="template_libreelec" class="narrow" title="_(Select the LibreELEC version to use)_">
				<?
					foreach ($arrLibreELECVersions as $strOEVersion => $arrOEVersion) {
						$strDefaultFolder = '';
						if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {
							$strDefaultFolder = str_replace('//', '/', $domain_cfg['DOMAINDIR'].'/LibreELEC/');
						}
						$strLocalFolder = ($arrOEVersion['localpath'] == '' ? $strDefaultFolder : dirname($arrOEVersion['localpath']));
						echo mk_option($arrConfig['template']['libreelec'], $strOEVersion, $arrOEVersion['name'], 'localpath="' . $arrOEVersion['localpath'] . '" localfolder="' . $strLocalFolder . '" valid="' . $arrOEVersion['valid'] . '"');
					}
				?>
				</select> <i class="fa fa-trash delete_libreelec_image installed" title="_(Remove LibreELEC image)_"></i> <span id="libreelec_image" class="installed"></span>
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Select which LibreELEC version to download or use for this VM</p>
	</blockquote>

	<div class="available">
		<table>
			<tr>
				<td>_(Download Folder)_:</td>
				<td>
					<input type="text" autocomplete="off" spellcheck="false" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="" id="download_path" placeholder="_(e.g.)_ /mnt/user/domains/" title="_(Folder to save the LibreELEC image to)_" />
				</td>
			</tr>
		</table>
		<blockquote class="inline_help">
			<p>Choose a folder where the LibreELEC image will downloaded to</p>
		</blockquote>

		<table>
			<tr>
				<td></td>
				<td>
					<input type="button" value="_(Download)_" busyvalue="_(Downloading)_..." readyvalue="_(Download)_" id="btnDownload" /><span id="download_status"></span>
				</td>
			</tr>
		</table>
	</div>

	<div class="installed">
		<table>
			<tr>
				<td>_(Config Folder)_:</td>
				<td>
					<input type="text" name="shares[0][source]" autocomplete="off" spellcheck="false" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="<?=htmlspecialchars($arrConfig['shares'][0]['source'])?>" placeholder="_(e.g.)_ /mnt/user/appdata/libreelec" title="_(path on Unraid share to save LibreELEC settings)_" required/>
					<input type="hidden" value="<?=htmlspecialchars($arrConfig['shares'][0]['target'])?>" name="shares[0][target]" />
				</td>
			</tr>
		</table>
		<blockquote class="inline_help">
			<p>Choose a folder or type in a new name off of an existing folder to specify where LibreELEC will save configuration files.  If you create multiple LibreELEC VMs, these Config Folders must be unique for each instance.</p>
		</blockquote>

		<table>
			<tr class="advanced">
				<td>_(CPU Mode)_:</td>
				<td>
					<select name="domain[cpumode]" title="_(define type of cpu presented to this vm)_">
					<?mk_dropdown_options(['host-passthrough' => _('Host Passthrough').' (' . $strCPUModel . ')', 'custom' => _('Emulated').' ('._('QEMU64').')'], $arrConfig['domain']['cpumode']);?>
					</select>
				</td>
			</tr>
		</table>
		<div class="advanced">
			<blockquote class="inline_help">
				<p>There are two CPU modes available to choose:</p>
				<p>
					<b>Host Passthrough</b><br>
					With this mode, the CPU visible to the guest should be exactly the same as the host CPU even in the aspects that libvirt does not understand.  For the best possible performance, use this setting.
				</p>
				<p>
					<b>Emulated</b><br>
					If you are having difficulties with Host Passthrough mode, you can try the emulated mode which doesn't expose the guest to host-based CPU features.  This may impact the performance of your VM.
				</p>
			</blockquote>
		</div>

		<table>
			<tr>
				<td>_(Logical CPUs)_:</td>
				<td>
					<div class="textarea four">
					<?
					$cpus = cpu_list();
					foreach ($cpus as $pair) {
						unset($cpu1,$cpu2);
						[$cpu1, $cpu2] = my_preg_split('/[,-]/',$pair);
						$extra = in_array($cpu1, $arrConfig['domain']['vcpu']) ? ($arrConfig['domain']['vcpus'] > 1 ? 'checked' : 'checked disabled') : '';
						if (!$cpu2) {
							echo "<label for='vcpu$cpu1' class='checkbox'>cpu $cpu1<input type='checkbox' name='domain[vcpu][]' class='domain_vcpu' id='vcpu$cpu1' value='$cpu1' $extra><span class='checkmark'></span></label>";
						} else {
							echo "<label for='vcpu$cpu1' class='cpu1 checkbox'>cpu $cpu1 / $cpu2<input type='checkbox' name='domain[vcpu][]' class='domain_vcpu' id='vcpu$cpu1' value='$cpu1' $extra><span class='checkmark'></span></label>";
							$extra = in_array($cpu2, $arrConfig['domain']['vcpu']) ? ($arrConfig['domain']['vcpus'] > 1 ? 'checked' : 'checked disabled') : '';
							echo "<label for='vcpu$cpu2' class='cpu2 checkbox'><input type='checkbox' name='domain[vcpu][]' class='domain_vcpu' id='vcpu$cpu2' value='$cpu2' $extra><span class='checkmark'></span></label>";
						}
					}
					?>
					</div>
				</td>
			</tr>
		</table>
		<blockquote class="inline_help">
			<p>The number of logical CPUs in your system is determined by multiplying the number of CPU cores on your processor(s) by the number of threads.</p>
			<p>Select which logical CPUs you wish to allow your VM to use. (minimum 1).</p>
		</blockquote>

		<table>
			<tr>
				<td><span class="advanced">_(Initial)_ </span>_(Memory)_:</td>
				<td>
					<select name="domain[mem]" id="domain_mem" class="narrow" title="_(define the amount memory)_">
					<?
						for ($i = 1; $i <= ($maxmem*2); $i++) {
							$label = ($i * 512) . ' MB';
							$value = $i * 512 * 1024;
							echo mk_option($arrConfig['domain']['mem'], $value, $label);
						}
					?>
					</select>
				</td>

				<td class="advanced">_(Max)_ _(Memory)_:</td>
				<td class="advanced">
					<select name="domain[maxmem]" id="domain_maxmem" class="narrow" title="_(define the maximum amount of memory)_">
					<?
						for ($i = 1; $i <= ($maxmem*2); $i++) {
							$label = ($i * 512) . ' MB';
							$value = $i * 512 * 1024;
							echo mk_option($arrConfig['domain']['maxmem'], $value, $label);
						}
					?>
					</select>
				</td>
				<td></td>
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

		<table>
			<tr class="advanced">
				<td>_(Machine)_:</td>
				<td>
					<select name="domain[machine]" class="narrow" id="domain_machine" title="_(Select the machine model)_.  _(i440fx will work for most)_.  _(Q35 for a newer machine model with PCIE)_">
					<?mk_dropdown_options($arrValidMachineTypes, $arrConfig['domain']['machine']);?>
					</select>
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
					<select name="domain[ovmf]" id="domain_ovmf" class="narrow" title="_(Select the BIOS)_.  _(SeaBIOS will work for most)_.  _(OVMF requires a UEFI-compatable OS)_ (_(e.g.)_ _(Windows 8/2012, newer Linux distros)_) _(and if using graphics device passthrough it too needs UEFI)_" onchange="BIOSChange(this)">
					<?
						echo mk_option($arrConfig['domain']['ovmf'], '0', _('SeaBIOS'));

						if (file_exists('/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi.fd')) {
							echo mk_option($arrConfig['domain']['ovmf'], '1', _('OVMF'));
						} else {
							echo mk_option('', '0', _('OVMF').' ('._('Not Available').')', 'disabled');
						}
					?>
					</select>
					<?
			$usbboothidden =  "hidden" ;
				if ($arrConfig['domain']['ovmf'] != '0') $usbboothidden = "" ;
				?>
				<span id="USBBoottext" class="advanced" <?=$usbboothidden?>>_(Enable USB boot)_:</span>

				<select name="domain[usbboot]" id="domain_usbboot" class="narrow" title="_(define OS boot options)_" <?=$usbboothidden?> onchange="USBBootChange(this)">
				<?
					echo mk_option($arrConfig['domain']['usbboot'], 'No', 'No');
					echo mk_option($arrConfig['domain']['usbboot'], 'Yes', 'Yes');
				?>
				</select>
				</td>
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
					Once a VM is created this setting cannot be adjusted.
				</p>
				<p>
				<b>USB Boot</b><br>
				Adds support for booting from USB devices using UEFI. No device boot orders can be specified at the same time as this option.<br>
				</p>
			</blockquote>
		</div>

		<table>
			<tr class="advanced">
				<td>_(USB Controller)_:</td>
				<td>
					<select name="domain[usbmode]" id="usbmode" class="narrow" title="_(Select the USB Controller to emulate)_.">
					<?
						echo mk_option($arrConfig['domain']['usbmode'], 'usb2', _('2.0 (EHCI)'));
						echo mk_option($arrConfig['domain']['usbmode'], 'usb3', _('3.0 (nec XHCI)'));
						echo mk_option($arrConfig['domain']['usbmode'], 'usb3-qemu', _('3.0 (qemu XHCI)'));
					?>
					</select>
				</td>
			</tr>
		</table>
		<div class="advanced">
			<blockquote class="inline_help">
				<p>
					<b>USB Controller</b><br>
					Select the USB Controller to emulate.  Qemu XHCI is the same code base as Nec XHCI but without several hacks applied over the years.  Recommended to try qemu XHCI before resorting to nec XHCI.
				</p>
			</blockquote>
		</div>

		<?foreach ($arrConfig['gpu'] as $i => $arrGPU) {
			$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

			?>
			<table data-category="Graphics_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidGPUDevices)?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
				<tr>
					<td>_(Graphics Card)_:</td>
					<td>
						<select name="gpu[<?=$i?>][id]" class="gpu narrow">
						<?
							if ($i == 0) {
								// Only the first video card can be VNC or SPICE
								echo mk_option($arrGPU['id'], 'virtual', _('Virtual'));
							} else {
								echo mk_option($arrGPU['id'], '', _('None'));
							}

							foreach($arrValidGPUDevices as $arrDev) {
								echo mk_option($arrGPU['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
							}
						?>
						</select>
					</td>
				</tr>
				<?if ($i == 0) {
					$hiddenport = $hiddenwsport = "hidden" ;
					if ($arrGPU['autoport'] == "no"){
					if ($arrGPU['protocol'] == "vnc") $hiddenport = $hiddenwsport = "" ;
					if ($arrGPU['protocol'] == "spice") $hiddenport = "" ;
					}
					?>
					<tr class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
					<td>_(VM Console Protocol)_:</td>
					<td>
						<select id="protocol" name="gpu[<?=$i?>][protocol]" class="narrow" title="_(protocol for virtual console)_" onchange="ProtocolChange(this)" >
						<?mk_dropdown_options($arrValidProtocols, $arrGPU['protocol']);?>
						</select>
					</td>
					</tr>
					<tr  id="autoportline" name="autoportline" class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced autoportline">
						<td>_(VM Console AutoPort)_:</td>
					<td>
						<select  id="autoport" name="gpu[<?=$i?>][autoport]" class="narrow" onchange="AutoportChange(this)">
							<?
							echo mk_option($arrGPU['autoport'], 'yes', _('Yes'));
							echo mk_option($arrGPU['autoport'], 'no', _('No'));
							?>
						</select>

					<span id="Porttext"  <?=$hiddenport?>>_(VM Console Port)_:</span>

				    <input type="number" size="5" maxlength="5"  id="port" class="narrow" style="width: 50px;" name="gpu[<?=$i?>][port]"  title="_(port for virtual console)_"  value="<?=$arrGPU['port']?>"  <?=$hiddenport?> >

					<span id="WSPorttext" <?=$hiddenwsport?>>_(VM Console WS Port)_:</span>

				    <input type="number" size="5" maxlength="5" id="wsport" class="narrow" style="width: 50px;" name="gpu[<?=$i?>][wsport]"  title="_(wsport for virtual console)_"  value="<?=$arrGPU['wsport']?>" <?=$hiddenwsport?> >
				</td>
				</tr>
			<?}?>
				<tr class="<?if ($arrGPU['id'] == 'virtual') echo 'was';?>advanced romfile">
					<td>_(Graphics ROM BIOS)_:</td>
					<td>
						<input type="text" name="gpu[<?=$i?>][rom]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfilter="rom,bin" data-pickmatch="^[^.].*" data-pickroot="/mnt/" value="<?=htmlspecialchars($arrGPU['rom'])?>" placeholder="_(Path to ROM BIOS file)_ (_(optional)_)" title="_(Path to ROM BIOS file)_ (_(optional)_)" />
					</td>
				</tr>
			</table>
			<?if ($i == 0) {?>
			<blockquote class="inline_help">
				<p>
					<b>Graphics Card</b><br>
					If you wish to assign a graphics card to the VM, select it from this list, otherwise leave it set to virtual.
				</p>
				<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
					<b>virtual video protocol VDA/SPICE</b><br>
					If you wish to assign a protocol type, specify one here.
				</p>

				<b>Graphics Card</b><br>
				If you wish to assign a graphics card to the VM, select it from this list, otherwise leave it set to virtual.
				</p>

				<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
					<b>virtual video protocol VDC/SPICE</b><br>
					If you wish to assign a protocol type, specify one here.
				</p>

				<p class="<?if ($arrGPU['id'] != 'virtual') echo 'was';?>advanced protocol">
					<b>virtual auto port</b><br>
					Set it you want to specify a manual port for VNC or Spice. VNC needs two ports where Spice only requires one. Leave as auto yes for the system to set.
				</p>


				<p class="<?if ($arrGPU['id'] == 'virtual') echo 'was';?>advanced romfile">
					<b>Graphics ROM BIOS</b><br>
					If you wish to use a custom ROM BIOS for a Graphics card, specify one here.
				</p>

				<?if (count($arrValidGPUDevices) > 1) {?>
				<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
				<?}?>
			</blockquote>
			<?}?>
		<?}?>
		<script type="text/html" id="tmplGraphics_Card">
			<table>
				<tr>
					<td>_(Graphics Card)_:</td>
					<td>
						<select name="gpu[{{INDEX}}][id]" class="gpu narrow">
						<?
							echo mk_option('', '', _('None'));

							foreach($arrValidGPUDevices as $arrDev) {
								echo mk_option('', $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
							}
						?>
						</select>
					</td>
				</tr>
				<tr class="advanced romfile">
					<td>_(Graphics ROM BIOS)_:</td>
					<td>
						<input type="text" name="gpu[{{INDEX}}][rom]" autocomplete="off" spellcheck="false" data-pickcloseonfile="true" data-pickfilter="rom,bin" data-pickmatch="^[^.].*" data-pickroot="/mnt/" value="" placeholder="_(Path to ROM BIOS file)_ (_(optional)_)" title="_(Path to ROM BIOS file)_ (_(optional)_)" />
					</td>
				</tr>
			</table>
		</script>

		<?foreach ($arrConfig['audio'] as $i => $arrAudio) {
			$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

			?>
			<table data-category="Sound_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidAudioDevices)?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
				<tr>
					<td>_(Sound Card)_:</td>
					<td>
						<select name="audio[<?=$i?>][id]" class="audio narrow">
						<?
							echo mk_option($arrAudio['id'], '', _('None'));

							foreach($arrValidAudioDevices as $arrDev) {
								echo mk_option($arrAudio['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
							}
						?>
						</select>
					</td>
				</tr>
			</table>
			<?if ($i == 0) {?>
			<blockquote class="inline_help">
				<p>Select a sound device to assign to your VM.  Most modern GPUs have a built-in audio device, but you can also select the on-board audio device(s) if present.</p>
				<?if (count($arrValidAudioDevices) > 1) {?>
				<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
				<?}?>
			</blockquote>
			<?}?>
		<?}?>
		<script type="text/html" id="tmplSound_Card">
			<table>
				<tr>
					<td>_(Sound Card)_:</td>
					<td>
						<select name="audio[{{INDEX}}][id]" class="audio narrow">
						<?
							foreach($arrValidAudioDevices as $arrDev) {
								echo mk_option('', $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
							}
						?>
						</select>
					</td>
				</tr>
			</table>
		</script>

		<?
		if ( $arrConfig['nic'] == false) {
			$arrConfig['nic']['0'] =
		  	[
			  'network' => $domain_bridge,
			  'mac' => "",
			  'model' => 'virtio-net'
		  	] ;
	  	}
	  	foreach ($arrConfig['nic'] as $i => $arrNic) {
		$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

			?>
			<table data-category="Network" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
				<tr class="advanced">
					<td>_(Network MAC)_:</td>
					<td>
						<input type="text" name="nic[<?=$i?>][mac]" class="narrow" value="<?=htmlspecialchars($arrNic['mac'])?>" title="_(random mac, you can supply your own)_" /> <i class="fa fa-refresh mac_generate" title="_(re-generate random mac address)_"></i>
					</td>
				</tr>
				<tr class="advanced">
					<td>_(Network Source)_:</td>
					<td>
						<select name="nic[<?=$i?>][network]">
						<?
						foreach (array_keys($arrValidNetworks) as $key) {

							echo mk_option("", $key, "- "._($key)." -", "disabled");

							foreach ($arrValidNetworks[$key] as $strNetwork) {
								echo mk_option($arrNic['network'], $strNetwork, $strNetwork);
							}
						}
						?>
						</select>
					</td>
				</tr>
				<tr class="advanced">
					<td>_(Network Model)_:</td>
					<td>
						<select name="nic[<?=$i?>][model]">
						<?
						echo mk_option($arrNic['model'], 'virtio-net', 'virtio-net');
						echo mk_option($arrNic['model'], 'virtio', 'virtio');
						?>
						</select>
					</td>
				</tr>
				<tr class="advanced">
					<td>_(Boot Order)_:</td>
					<td>
					<input type="number" size="5" maxlength="5" id="nic[<?=$i?>][boot]" class="narrow bootorder" <?=$bootdisable?>  style="width: 50px;" name="nic[<?=$i?>][boot]"   title="_(Boot order)_"  value="<?=$arrNic['boot']?>" >
					</td>
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

					<p>Use boot order to set device as bootable and boot sequence.</p>

					<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
				</blockquote>
			</div>
			<?}?>
		<?}?>
		<script type="text/html" id="tmplNetwork">
			<table>
				<tr class="advanced">
					<td>_(Network MAC)_:</td>
					<td>
						<input type="text" name="nic[{{INDEX}}][mac]" class="narrow" value="" title="_(random mac, you can supply your own)_" /> <i class="fa fa-refresh mac_generate" title="_(re-generate random mac address)_"></i>
					</td>
				</tr>
				<tr class="advanced">
					<td>_(Network Source)_:</td>
					<td>
						<select name="nic[{{INDEX}}][network]">
						<?
						foreach (array_keys($arrValidNetworks) as $key) {

							echo mk_option("", $key, "- "._($key)." -", "disabled");

							foreach ($arrValidNetworks[$key] as $strNetwork) {
								echo mk_option($domain_bridge, $strNetwork, $strNetwork);
							}
						}
						?>
						</select>
					</td>
				</tr>
				<tr class="advanced">
					<td>_(Network Model)_:</td>
					<td>
						<select name="nic[{{INDEX}}][model]">
						<?
						echo mk_option(1, 'virtio-net', 'virtio-net');
						echo mk_option(1, 'virtio', 'virtio');
						?>
						</select>
					</td>
				</tr>
				<tr class="advanced">
					<td>_(Boot Order)_:</td>
					<td>
					<input type="number" size="5" maxlength="5" id="nic[{{INDEX}}][boot]" class="narrow bootorder" <?=$bootdisable?>  style="width: 50px;" name="nic[{{INDEX}}][boot]"   title="_(Boot order)_"  value="" >
					</td>
				</tr>
			</table>
		</script>

		<table>
			<tr><td></td>
			<td>_(Select)_&nbsp&nbsp_(Optional)_&nbsp&nbsp_(Boot Order)_</td></tr></div>
			<tr>
			<tr>
				<td>_(USB Devices)_:</td>
				<td>
					<div class="textarea" style="width:850px">
					<?
						if (!empty($arrVMUSBs)) {
							foreach($arrVMUSBs as $i => $arrDev) {
							?>
							<label for="usb<?=$i?>">&nbsp&nbsp&nbsp&nbsp<input type="checkbox" name="usb[]" id="usb<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?if (count(array_filter($arrConfig['usb'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) echo 'checked="checked"';?>
							/> &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp <input type="checkbox" name="usbopt[<?=htmlspecialchars($arrDev['id'])?>]]" id="usbopt<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?if ($arrDev["startupPolicy"] =="optional") echo 'checked="checked"';?>/>&nbsp&nbsp&nbsp&nbsp&nbsp
							<input type="number" size="5" maxlength="5" id="usbboot<?=$i?>" class="narrow bootorder" <?=$bootdisable?>  style="width: 50px;" name="usbboot[<?=htmlspecialchars($arrDev['id'])?>]]"   title="_(Boot order)_"  value="<?=$arrDev['usbboot']?>" >
							<?=htmlspecialchars($arrDev['name'])?> (<?=htmlspecialchars($arrDev['id'])?>)</label><br/>
							<?
							}
						} else {
							echo "<i>"._('None available')."</i>";
						}
					?>
					</div>
				</td>
			</tr>
		</table>
		<blockquote class="inline_help">
			<p>If you wish to assign any USB devices to your guest, you can select them from this list.</p>
			<p>Use boot order to set device as bootable and boot sequence.</p>
			<p>Select optional if you want device to be ignored when VM starts if not present.</p>
		</blockquote>

	<table>
	<tr><td></td>
		<td>_(Select)_&nbsp&nbsp_(Boot Order)_</td></tr></div>
		<tr>
			<tr>
				<td>_(Other PCI Devices)_:</td>
				<td>
				<div class="textarea" style="width: 850px">
				<?
					$intAvailableOtherPCIDevices = 0;

						if (!empty($arrValidOtherDevices)) {
							foreach($arrValidOtherDevices as $i => $arrDev) {
							$bootdisable = $extra = $pciboot = '';
							if ($arrDev["typeid"] != "0108") $bootdisable = ' disabled="disabled"' ;
							if (count($pcidevice=array_filter($arrConfig['pci'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) {
								$extra .= ' checked="checked"';
								foreach ($pcidevice as $pcikey => $pcidev)  $pciboot = $pcidev["boot"];  ;

								} elseif (!in_array($arrDev['driver'], ['pci-stub', 'vfio-pci'])) {
									//$extra .= ' disabled="disabled"';
									continue;
								}
								$intAvailableOtherPCIDevices++;
						?>
						<label for="pci<?=$i?>">&nbsp&nbsp&nbsp&nbsp<input type="checkbox" name="pci[]" id="pci<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?=$extra?>/> &nbsp
						<input type="number" size="5" maxlength="5" id="pciboot<?=$i?>" class="narrow pcibootorder" <?=$bootdisable?>  style="width: 50px;" name="pciboot[<?=htmlspecialchars($arrDev['id'])?>]"   title="_(Boot order)_"  value="<?=$pciboot?>" >
						<?=htmlspecialchars($arrDev['name'])?> | <?=htmlspecialchars($arrDev['type'])?> (<?=htmlspecialchars($arrDev['id'])?>)</label><br/>
					<?
						}
					}

						if (empty($intAvailableOtherPCIDevices)) {
							echo "<i>"._('None available')."</i>";
						}
					?>
					</div>
				</td>
			</tr>
		</table>
		<blockquote class="inline_help">
			<p>If you wish to assign any other PCI devices to your guest, you can select them from this list.</p>
		    <p>Use boot order to set device as bootable and boot sequence. Only NVMe devices (PCI types 0108) supported for boot order.</p>
		</blockquote>

		<table>
			<tr>
				<td></td>
				<td>
				<?if (!$boolNew) {?>
					<input type="hidden" name="updatevm" value="1" />
					<input type="button" value="_(Update)_" busyvalue="_(Updating)_..." readyvalue="_(Update)_" id="btnSubmit" />
				<?} else {?>
					<label for="domain_start"><input type="checkbox" name="domain[startnow]" id="domain_start" value="1" checked="checked"/> _(Start VM after creation)_</label>
					<br>
					<input type="hidden" name="createvm" value="1" />
					<input type="button" value="_(Create)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnSubmit" />
				<?}?>
					<input type="button" value="_(Cancel)_" id="btnCancel" />
				</td>
			</tr>
		</table>
		<?if ($boolNew) {?>
		<blockquote class="inline_help">
			<p>Click Create to return to the Virtual Machines page where your new VM will be created.</p>
		</blockquote>
		<?}?>
	</div>


<div class="xmlview">
	<textarea id="addcode" name="xmldesc" placeholder="_(Copy &amp; Paste Domain XML Configuration Here)_." autofocus><?=htmlspecialchars($hdrXML).htmlspecialchars($strXML)?></textarea>

	<table>
		<tr>
			<td></td>
			<td>
			<?if (!$boolRunning) {?>
				<?if ($strXML) {?>
					<input type="hidden" name="updatevm" value="1" />
					<input type="button" value="_(Update)_" busyvalue="_(Updating)_..." readyvalue="_(Update)_" id="btnSubmit" />
				<?} else {?>
					<label for="xmldomain_start"><input type="checkbox" name="domain[xmlstartnow]" id="xmldomain_start" value="1" checked="checked"/> _(Start VM after creation)_</label>
					<br>
					<input type="hidden" name="createvm" value="1" />
					<input type="button" value="_(Create)_" busyvalue="_(Creating)_..." readyvalue="_(Create)_" id="btnSubmit" />
				<?}?>
				<input type="button" value="_(Cancel)_" id="btnCancel" />
			<?} else {?>
				<input type="button" value="_(Back)_" id="btnCancel" />
			<?}?>
			</td>
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

function BIOSChange(bios) {
		var value = bios.value;
		if (value == "0") {
			document.getElementById("USBBoottext").style.visibility="hidden";
			document.getElementById("domain_usbboot").style.visibility="hidden";
		} else {
			document.getElementById("USBBoottext").style.display="inline";
			document.getElementById("USBBoottext").style.visibility="visible";
			document.getElementById("domain_usbboot").style.display="inline";
			document.getElementById("domain_usbboot").style.visibility="visible";
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
		$devlist = [] ;
		foreach($arrValidOtherDevices as $i => $arrDev) {
			if ($arrDev["typeid"] != "0108") $devlist[$arrDev['id']] = "N" ; else $devlist[$arrDev['id']] = "Y" ;
		}
		echo json_encode($devlist) ;
		?>

	for(var i = 0; i < bootelements.length; i++) {
		let bootpciid = bootelements[i].name.split('[') ;
		bootpciid= bootpciid[1].replace(']', '') ;

		if (usbbootvalue == "Yes") {
		bootelements[i].value = "";
		bootelements[i].setAttribute("disabled","disabled");
		} else {
			// Put check for PCI Type 0108 and only remove disable if 0108.
			if (bootpcidevs[bootpciid] === "Y") 	bootelements[i].removeAttribute("disabled");
		}
	}
}

function USBBootChange(usbboot) {
	// Remove all boot orders if changed to Yes
	var value = usbboot.value ;
	SetBootorderfields(value) ;
}

function AutoportChange(autoport) {
		if (autoport.value == "yes") {
			document.getElementById("port").style.visibility="hidden";
			document.getElementById("Porttext").style.visibility="hidden";
			document.getElementById("wsport").style.visibility="hidden";
			document.getElementById("WSPorttext").style.visibility="hidden";
		} else {
			var protocol = document.getElementById("protocol").value ;
			document.getElementById("port").style.display="inline";
			document.getElementById("port").style.visibility="visible";
			document.getElementById("Porttext").style.display="inline";
			document.getElementById("Porttext").style.visibility="visible";
			if (protocol == "vnc") {
				document.getElementById("wsport").style.display="inline";
				document.getElementById("wsport").style.visibility="visible";
				document.getElementById("WSPorttext").style.display="inline";
				document.getElementById("WSPorttext").style.visibility="visible";
			} else {
				document.getElementById("wsport").style.visibility="hidden";
				document.getElementById("WSPorttext").style.visibility="hidden";
			}
		}
	}

function ProtocolChange(protocol) {
		var autoport = document.getElementById("autoport").value ;
		if (autoport == "yes") {
			document.getElementById("port").style.visibility="hidden";
			document.getElementById("Porttext").style.visibility="hidden";
			document.getElementById("wsport").style.visibility="hidden";
			document.getElementById("WSPorttext").style.visibility="hidden";
		} else {
			document.getElementById("port").style.display="inline";
			document.getElementById("port").style.visibility="visible";
			document.getElementById("Porttext").style.display="inline";
			document.getElementById("Porttext").style.visibility="visible";
			if (protocol.value == "vnc") {
				document.getElementById("wsport").style.display="inline";
				document.getElementById("wsport").style.visibility="visible";
				document.getElementById("WSPorttext").style.display="inline";
				document.getElementById("WSPorttext").style.visibility="visible";
			} else {
				document.getElementById("wsport").style.visibility="hidden";
				document.getElementById("WSPorttext").style.visibility="hidden";
			}
		}
	}


$(function() {
	function completeAfter(cm, pred) {
		var cur = cm.getCursor();
		if (!pred || pred()) setTimeout(function() {
			if (!cm.state.completionActive)
				cm.showHint({completeSingle: false});
		}, 100);
		return CodeMirror.Pass;
	}

	function completeIfAfterLt(cm) {
		return completeAfter(cm, function() {
			var cur = cm.getCursor();
			return cm.getRange(CodeMirror.Pos(cur.line, cur.ch - 1), cur) == "<";
		});
	}

	function completeIfInTag(cm) {
		return completeAfter(cm, function() {
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

	SetBootorderfields("<?=$arrConfig['domain']['usbboot']?>") ;

	function resetForm() {
		$("#vmform .domain_vcpu").change(); // restore the cpu checkbox disabled states
		<?if ($boolRunning):?>
		$("#vmform").find('input[type!="button"],select,.mac_generate').prop('disabled', true);
		$("#vmform").find('input[name^="usb"]').prop('disabled', false);
		<?endif?>
	}

	$('.advancedview').change(function () {
		if ($(this).is(':checked')) {
			setTimeout(function() {
				editor.refresh();
			}, 100);
		}
	});

	$("#vmform .domain_vcpu").change(function changeVCPUEvent() {
		var $cores = $("#vmform .domain_vcpu:checked");

		if ($cores.length == 1) {
			$cores.prop("disabled", true);
		} else {
			$("#vmform .domain_vcpu").prop("disabled", false);
		}
	});

	$("#vmform #domain_mem").change(function changeMemEvent() {
		$("#vmform #domain_maxmem").val($(this).val());
	});

	$("#vmform #domain_maxmem").change(function changeMaxMemEvent() {
		if (parseFloat($(this).val()) < parseFloat($("#vmform #domain_mem").val())) {
			$("#vmform #domain_mem").val($(this).val());
		}
	});

	$("#vmform").on("spawn_section", function spawnSectionEvent(evt, section, sectiondata) {
		if (sectiondata.category == 'Graphics_Card') {
			$(section).find(".gpu").change();
		}
	});

	$("#vmform").on("change", ".gpu", function changeGPUEvent() {
		var myvalue = $(this).val();
		var mylabel = $(this).children('option:selected').text();
		var myindex = $(this).closest('table').data('index');

		if (myindex == 0) {
			$vnc_sections = $('.autoportline,.protocol,.vncmodel,.vncpassword,.vnckeymap');
			if (myvalue == 'virtual') {
				$vnc_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($vnc_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
			} else {
				slideUpRows($vnc_sections);
				$vnc_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
			}
		}

		$romfile = $(this).closest('table').find('.romfile');
		if (myvalue == 'virtual' || myvalue == '') {
			slideUpRows($romfile.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
			$romfile.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
		} else {
			$romfile.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
			slideDownRows($romfile.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

			$("#vmform .gpu").not(this).each(function () {
				if (myvalue == $(this).val()) {
					$(this).prop("selectedIndex", 0).change();
				}
			});
		}
	});

	$("#vmform").on("click", ".mac_generate", function generateMac() {
		var $input = $(this).prev('input');

		$.getJSON("/plugins/dynamix.vm.manager/include/VMajax.php?action=generate-mac", function (data) {
			if (data.mac) {
				$input.val(data.mac);
			}
		});
	});

	$("#vmform .formview #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $panel = $('.formview');
		var form = $button.closest('form');

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
			if (gpu != 'virtual' && !gpus.includes(gpu)) form.append('<input type="hidden" name="pci[]" value="'+gpu+'#remove">');
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
		var postdata = form.find('input,select').serialize().replace(/'/g,"%27");
		<?if (!$boolNew):?>
		// keep checkbox visually unchecked
		form.find('input[name="usb[]"],input[name="pci[]"],input[name="usbopt[]"]').each(function(){
			if ($(this).val().indexOf('#remove')>0) $(this).prop('checked',false);
		});
		<?endif?>

		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/LibreELEC.form.php", postdata, function( data ) {
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

	$("#vmform .xmlview #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $panel = $('.xmlview');

		editor.save();

		$panel.find('input').prop('disabled', false); // enable all inputs otherwise they wont post

		var postdata = $panel.closest('form').serialize().replace(/'/g,"%27");

		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/LibreELEC.form.php", postdata, function( data ) {
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

	var checkDownloadTimer = null;
	var checkOrInitDownload = function(checkonly) {
		clearTimeout(checkDownloadTimer);

		var $button = $("#vmform #btnDownload");
		var $form = $button.closest('form');

		var postdata = {
			download_version: $('#vmform #template_libreelec').val(),
			download_path: $('#vmform #download_path').val(),
			checkonly: ((typeof checkonly === 'undefined') ? false : !!checkonly) ? 1 : 0
		};

		$form.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/LibreELEC.form.php", postdata, function( data ) {
			if (data.error) {
				$("#vmform #download_status").html($("#vmform #download_status").html() + '<br><span style="color: red">' + data.error + '</span>');
			} else if (data.status) {
				var old_list = $("#vmform #download_status").html().split('<br>');

				if (old_list.pop().split(' ... ').shift() == data.status.split(' ... ').shift()) {
					old_list.push(data.status);
					$("#vmform #download_status").html(old_list.join('<br>'));
				} else {
					$("#vmform #download_status").html($("#vmform #download_status").html() + '<br>' + data.status);
				}

				if (data.pid) {
					checkDownloadTimer = setTimeout(checkOrInitDownload, 1000);
					return;
				}

				if (data.status == 'Done') {
					$("#vmform #template_libreelec").find('option:selected').attr({
						localpath: data.localpath,
						localfolder:  data.localfolder,
						valid: '1'
					});
					$("#vmform #template_libreelec").change();
				}
			}

			$button.val($button.attr('readyvalue'));
			$form.find('input').prop('disabled', false);
		}, "json");
	};

	$("#vmform #btnDownload").click(function changeVirtIOVersion() {
		checkOrInitDownload(false);
	});

	// Fire events below once upon showing page
	$("#vmform #template_libreelec").change(function changeLibreELECVersion() {
		clearTimeout(checkDownloadTimer);

		$selected = $(this).find('option:selected');

		if ($selected.attr('valid') === '0') {
			$("#vmform .available").slideDown('fast');
			$("#vmform .installed").slideUp('fast');
			$("#vmform #download_status").html('');
			$("#vmform #download_path").val($selected.attr('localfolder'));
			if ($selected.attr('localpath') !== '') {
				// Check status of current running job (but dont initiate a new download)
				checkOrInitDownload(true);
			}
		} else {
			$("#vmform .available").slideUp('fast');
			$("#vmform .installed").slideDown('fast', function () {
				resetForm();

				// attach delete libreelec image onclick event
				$("#vmform .delete_libreelec_image").off().click(function deleteOEVersion() {
					swal({title:"_(Are you sure)_?",text:"_(Remove this LibreELEC file)_:\n"+$selected.attr('localpath'),type:"warning",showCancelButton:true,confirmButtonText:"_(Proceed)_",cancelButtonText:"_(Cancel)_"},function() {
						$.post("/plugins/dynamix.vm.manager/templates/LibreELEC.form.php", {delete_version: $selected.val()}, function(data) {
							if (data.error) {
								swal({title:"_(VM image deletion error)_",text:data.error,type:"error",confirmButtonText:"_(Ok)_"});
							} else if (data.status == 'ok') {
								$selected.attr({
									localpath: '',
									valid: '0'
								});
							}
							$("#vmform #template_libreelec").change();
						}, "json");
					});
				}).hover(function () {
					$("#vmform #libreelec_image").css('color', '#666');
				}, function () {
					$("#vmform #libreelec_image").css('color', '#BBB');
				});
			});
			$("#vmform #disk_0").val($selected.attr('localpath'));
			$("#vmform #libreelec_image").html($selected.attr('localpath'));
		}
	}).change(); // Fire now too!

	$("#vmform .gpu").change();

	resetForm();
});
</script>
