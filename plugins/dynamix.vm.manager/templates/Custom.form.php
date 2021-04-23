<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2015-2020, Derek Macias, Eric Schultz, Jon Panozzo.
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
	if (substr($_SERVER['REQUEST_URI'],0,4) != '/VMs') {
		$_SERVER['REQUEST_URI'] = 'vms';
		require_once "$docroot/webGui/include/Translations.php";
	}
	require_once "$docroot/webGui/include/Helpers.php";
	require_once "$docroot/webGui/include/Custom.php";
	require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

	$arrValidMachineTypes = getValidMachineTypes();
	$arrValidGPUDevices = getValidGPUDevices();
	$arrValidAudioDevices = getValidAudioDevices();
	$arrValidOtherDevices = getValidOtherDevices();
	$arrValidUSBDevices = getValidUSBDevices();
	$arrValidDiskDrivers = getValidDiskDrivers();
	$arrValidDiskBuses = getValidDiskBuses();
	$arrValidCdromBuses = getValidCdromBuses();
	$arrValidVNCModels = getValidVNCModels();
	$arrValidKeyMaps = getValidKeyMaps();
	$arrValidBridges = getNetworkBridges();
	$strCPUModel = getHostCPUModel();

	$arrConfigDefaults = [
		'template' => [
			'name' => $strSelectedTemplate,
			'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
			'os' => $arrAllTemplates[$strSelectedTemplate]['os']
		],
		'domain' => [
			'name' => $strSelectedTemplate,
			'persistent' => 1,
			'uuid' => $lv->domain_generate_uuid(),
			'clock' => 'localtime',
			'arch' => 'x86_64',
			'machine' => 'pc',
			'mem' => 1024 * 1024,
			'maxmem' => 1024 * 1024,
			'password' => '',
			'cpumode' => 'host-passthrough',
			'vcpus' => 1,
			'vcpu' => [0],
			'hyperv' => 1,
			'ovmf' => 1,
			'usbmode' => 'usb2'
		],
		'media' => [
			'cdrom' => '',
			'cdrombus' => 'ide',
			'drivers' => is_file($domain_cfg['VIRTIOISO']) ? $domain_cfg['VIRTIOISO'] : '',
			'driversbus' => 'ide'
		],
		'disk' => [
			[
				'new' => '',
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'select' => $domain_cfg['VMSTORAGEMODE'],
				'bus' => 'virtio'
			]
		],
		'gpu' => [
			[
				'id' => 'vnc',
				'model' => 'qxl',
				'keymap' => 'en-us'
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
				'target' => ''
			]
		]
	];
	$hdrXML = "<?xml version='1.0' encoding='UTF-8'?>\n"; // XML encoding declaration

	// Merge in any default values from the VM template
	if ($arrAllTemplates[$strSelectedTemplate] && $arrAllTemplates[$strSelectedTemplate]['overrides']) {
		$arrConfigDefaults = array_replace_recursive($arrConfigDefaults, $arrAllTemplates[$strSelectedTemplate]['overrides']);
	}
	// create new VM
	if ($_POST['createvm']) {
		if ($_POST['xmldesc']) {
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
				// Fire off the vnc popup if available
				$dom = $lv->get_domain_by_name($_POST['domain']['name']);
				$vncport = $lv->domain_get_vnc_port($dom);
				$wsport = $lv->domain_get_ws_port($dom);
				$reply = ['success' => true];
				if ($vncport > 0) {
					$reply['vncurl'] = autov('/plugins/dynamix.vm.manager/vnc.html',true).'&autoconnect=true&host='.$_SERVER['HTTP_HOST'].'&port=&path=/wsproxy/'.$wsport.'/';
				}
			} else {
				$reply = ['error' => $lv->get_last_error()];
			}
		}
		echo json_encode($reply);
		exit;
	}

	// update existing VM
	if ($_POST['updatevm']) {
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
				foreach ($arrExistingConfig['usb'] as $arrExistingUSB) {
					if ($strNewUSBID == $arrExistingUSB['id']) continue 2;
				}
				list($strVendor,$strProduct) = explode(':', $strNewUSBID);
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
					list($strVendor, $strProduct) = explode(':', $arrExistingUSB['id']);
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
		if ($dom && !$_POST['xmldesc']) {
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
		if ($_POST['xmldesc']) {
			// XML view
			$xml = $_POST['xmldesc'];
		} else {
			// form view
			if ($error = create_vdisk($_POST) === false) {
				$arrExistingConfig = custom::createArray('domain',$strXML);
				$arrUpdatedConfig = custom::createArray('domain',$lv->config_to_xml($_POST));
				array_update_recursive($arrExistingConfig, $arrUpdatedConfig);
				$arrConfig = array_replace_recursive($arrExistingConfig, $arrUpdatedConfig);
				$xml = custom::createXML('domain',$arrConfig)->saveXML();
			} else {
				echo json_encode(['error' => $error]);
				exit;
			}
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

	if ($_GET['uuid']) {
		// edit an existing VM
		$uuid = $_GET['uuid'];
		$dom = $lv->domain_get_domain_by_uuid($uuid);
		$boolRunning = $lv->domain_get_state($dom)=='running';
		$strXML = $lv->domain_get_xml($dom);
		$boolNew = false;
		$arrConfig = array_replace_recursive($arrConfigDefaults, domain_to_config($uuid));
	} else {
		// edit new VM
		$boolRunning = false;
		$strXML = '';
		$boolNew = true;
		$arrConfig = $arrConfigDefaults;
	}
	// Add any custom metadata field defaults (e.g. os)
	if (!$arrConfig['template']['os']) {
		$arrConfig['template']['os'] = ($arrConfig['domain']['clock']=='localtime' ? 'windows' : 'linux');
	}
?>

<link rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/lib/codemirror.css')?>">
<link rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/show-hint.css')?>">
<style type="text/css">
	.CodeMirror { border: 1px solid #eee; cursor: text; margin-top: 15px; margin-bottom: 10px; }
	.CodeMirror pre.CodeMirror-placeholder { color: #999; }
</style>

<div class="formview">
<input type="hidden" name="template[os]" id="template_os" value="<?=htmlspecialchars($arrConfig['template']['os'])?>">
<input type="hidden" name="domain[persistent]" value="<?=htmlspecialchars($arrConfig['domain']['persistent'])?>">
<input type="hidden" name="domain[uuid]" value="<?=htmlspecialchars($arrConfig['domain']['uuid'])?>">
<input type="hidden" name="domain[clock]" id="domain_clock" value="<?=htmlspecialchars($arrConfig['domain']['clock'])?>">
<input type="hidden" name="domain[arch]" value="<?=htmlspecialchars($arrConfig['domain']['arch'])?>">
<input type="hidden" name="domain[oldname]" id="domain_oldname" value="<?=htmlspecialchars($arrConfig['domain']['name'])?>">

	<table>
		<tr>
			<td>_(Name)_:</td>
			<td><input type="text" name="domain[name]" id="domain_name" class="textTemplate" title="_(Name of virtual machine)_" placeholder="_(e.g.)_ _(My Workstation)_" value="<?=htmlspecialchars($arrConfig['domain']['name'])?>" required /></td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Give the VM a name (e.g. Work, Gaming, Media Player, Firewall, Bitcoin Miner)</p>
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

	<table>
		<tr class="advanced">
			<td>_(CPU Mode)_:</td>
			<td>
				<select name="domain[cpumode]" title="_(define type of cpu presented to this vm)_">
				<?mk_dropdown_options(['host-passthrough' => _('Host Passthrough').' (' . $strCPUModel . ')', 'emulated' => _('Emulated').' ('._('QEMU64').')'], $arrConfig['domain']['cpumode']);?>
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
					list($cpu1, $cpu2) = preg_split('/[,-]/',$pair);
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
					echo mk_option($arrConfig['domain']['mem'], 128 * 1024, '128 MB');
					echo mk_option($arrConfig['domain']['mem'], 256 * 1024, '256 MB');
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
					echo mk_option($arrConfig['domain']['maxmem'], 128 * 1024, '128 MB');
					echo mk_option($arrConfig['domain']['maxmem'], 256 * 1024, '256 MB');
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
				<select name="domain[ovmf]" id="domain_ovmf" class="narrow" title="_(Select the BIOS)_.  _(SeaBIOS will work for most)_.  _(OVMF requires a UEFI-compatable OS)_ (_(e.g.)_ _(Windows 8/2012, newer Linux distros)_) _(and if using graphics device passthrough it too needs UEFI)_" <?if (!empty($arrConfig['domain']['state'])) echo 'disabled="disabled"';?>>
				<?
					echo mk_option($arrConfig['domain']['ovmf'], '0', _('SeaBIOS'));

					if (file_exists('/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi.fd')) {
						echo mk_option($arrConfig['domain']['ovmf'], '1', _('OVMF'));
					} else {
						echo mk_option('', '0', _('OVMF').' ('._('Not Available').')', 'disabled');
					}
				?>
				</select>
				<?if (!empty($arrConfig['domain']['state'])) {?>
					<input type="hidden" name="domain[ovmf]" value="<?=htmlspecialchars($arrConfig['domain']['ovmf'])?>">
				<?}?>
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
		</blockquote>
	</div>

	<table class="domain_os windows">
		<tr class="advanced">
			<td>_(Hyper-V)_:</td>
			<td>
				<select name="domain[hyperv]" id="hyperv" class="narrow" title="_(Hyperv tweaks for Windows)_">
				<?mk_dropdown_options([_('No'), _('Yes')], $arrConfig['domain']['hyperv']);?>
				</select>
			</td>
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
				<select name="domain[usbmode]" id="usbmode" class="narrow" title="_(Select the USB Controller to emulate)_.  _(Some OSes won't support USB3)_ (_(e.g.)_ _(Windows 7/XP)_)">
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
				Select the USB Controller to emulate.  Some OSes won't support USB3 (e.g. Windows 7).  Qemu XHCI is the same code base as Nec XHCI but without several hacks applied over the years.  Recommended to try qemu XHCI before resorting to nec XHCI.
			</p>
		</blockquote>
	</div>

	<table>
		<tr>
			<td>_(OS Install ISO)_:</td>
			<td>
				<input type="text" data-pickcloseonfile="true" data-pickfilter="iso" data-pickmatch="^[^.].*" data-pickroot="<?=htmlspecialchars($domain_cfg['MEDIADIR'])?>" name="media[cdrom]" class="cdrom" value="<?=htmlspecialchars($arrConfig['media']['cdrom'])?>" placeholder="_(Click and Select cdrom image to install operating system)_">
			</td>
		</tr>
		<tr class="advanced">
			<td>_(OS Install CDRom Bus)_:</td>
			<td>
				<select name="media[cdrombus]" class="cdrom_bus narrow">
				<?mk_dropdown_options($arrValidCdromBuses, $arrConfig['media']['cdrombus']);?>
				</select>
			</td>
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
				<input type="text" data-pickcloseonfile="true" data-pickfilter="iso" data-pickmatch="^[^.].*" data-pickroot="<?=htmlspecialchars($domain_cfg['MEDIADIR'])?>" name="media[drivers]" class="cdrom" value="<?=htmlspecialchars($arrConfig['media']['drivers'])?>" placeholder="_(Download, Click and Select virtio drivers image)_">
			</td>
		</tr>
		<tr class="advanced">
			<td>_(VirtIO Drivers CDRom Bus)_:</td>
			<td>
				<select name="media[driversbus]" class="cdrom_bus narrow">
				<?mk_dropdown_options($arrValidCdromBuses, $arrConfig['media']['driversbus']);?>
				</select>
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
						if ($i == 0) {
							echo '<option value="">'._('None').'</option>';
						}

						$default_option = $arrDisk['select'];

						if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {

							$boolShowAllDisks = (strpos($domain_cfg['DOMAINDIR'], '/mnt/user/') === 0);

							if (!empty($arrDisk['new'])) {
								if (strpos($domain_cfg['DOMAINDIR'], dirname(dirname($arrDisk['new']))) === false || 
									basename(dirname($arrDisk['new'])) != $arrConfig['domain']['name'] || 
									basename($arrDisk['new']) != 'vdisk'.($i+1).'.img') {
									$default_option = 'manual';
								}
								if (file_exists(dirname(dirname($arrDisk['new'])).'/'.$arrConfig['domain']['name'].'/vdisk'.($i+1).'.img')) {
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
					</select><input type="text" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickmatch="^[^.].*" data-pickroot="/mnt/" name="disk[<?=$i?>][new]" class="disk" id="disk_<?=$i?>" value="<?=htmlspecialchars($arrDisk['new'])?>" placeholder="_(Separate sub-folder and image will be created based on Name)_"><div class="disk_preview"></div>
				</td>
			</tr>

			<tr class="disk_file_options">
				<td>_(vDisk Size)_:</td>
				<td>
					<input type="text" name="disk[<?=$i?>][size]" value="<?=htmlspecialchars($arrDisk['size'])?>" class="narrow" placeholder="_(e.g.)_ 10M, 1G, 10G...">
				</td>
			</tr>

			<tr class="advanced disk_file_options">
				<td>_(vDisk Type)_:</td>
				<td>
					<select name="disk[<?=$i?>][driver]" class="narrow" title="_(type of storage image)_">
					<?mk_dropdown_options($arrValidDiskDrivers, $arrDisk['driver']);?>
					</select>
				</td>
			</tr>

			<tr class="advanced disk_bus_options">
				<td>_(vDisk Bus)_:</td>
				<td>
					<select name="disk[<?=$i?>][bus]" class="disk_bus narrow">
					<?mk_dropdown_options($arrValidDiskBuses, $arrDisk['bus']);?>
					</select>
				</td>
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

			<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
		</blockquote>
		<?}?>
	<?}?>
	<script type="text/html" id="tmplvDisk">
		<table>
			<tr>
				<td>_(vDisk Location)_:</td>
				<td>
					<select name="disk[{{INDEX}}][select]" class="disk_select narrow">
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
					</select><input type="text" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickmatch="^[^.].*" data-pickroot="/mnt/" name="disk[{{INDEX}}][new]" class="disk" id="disk_{{INDEX}}" value="" placeholder="_(Separate sub-folder and image will be created based on Name)_"><div class="disk_preview"></div>
				</td>
			</tr>

			<tr class="disk_file_options">
				<td>_(vDisk Size)_:</td>
				<td>
					<input type="text" name="disk[{{INDEX}}][size]" value="" class="narrow" placeholder="_(e.g.)_ 10M, 1G, 10G...">
				</td>
			</tr>

			<tr class="advanced disk_file_options">
				<td>_(vDisk Type)_:</td>
				<td>
					<select name="disk[{{INDEX}}][driver]" class="narrow" title="_(type of storage image)_">
					<?mk_dropdown_options($arrValidDiskDrivers, '');?>
					</select>
				</td>
			</tr>

			<tr class="advanced disk_bus_options">
				<td>_(vDisk Bus)_:</td>
				<td>
					<select name="disk[{{INDEX}}][bus]" class="disk_bus narrow">
					<?mk_dropdown_options($arrValidDiskBuses, '');?>
					</select>
				</td>
			</tr>
		</table>
	</script>

	<?foreach ($arrConfig['shares'] as $i => $arrShare) {
		$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

		?>
		<table class="domain_os other" data-category="Share" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
			<tr class="advanced">
				<td>_(Unraid Share)_:</td>
				<td>
					<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="<?=htmlspecialchars($arrShare['source'])?>" name="shares[<?=$i?>][source]" placeholder="_(e.g.)_ /mnt/user/..." title="_(path of Unraid share)_" />
				</td>
			</tr>

			<tr class="advanced">
				<td>_(Unraid Mount tag)_:</td>
				<td>
					<input type="text" value="<?=htmlspecialchars($arrShare['target'])?>" name="shares[<?=$i?>][target]" placeholder="_(e.g.)_ _(shares)_ (_(name of mount tag inside vm)_)" title="_(mount tag inside vm)_" />
				</td>
			</tr>
		</table>
		<?if ($i == 0) {?>
		<div class="domain_os other">
			<div class="advanced">
				<blockquote class="inline_help">
					<p>
						<b>Unraid Share</b><br>
						Used to create a VirtFS mapping to a Linux-based guest.  Specify the path on the host here.
					</p>

					<p>
						<b>Unraid Mount tag</b><br>
						Specify the mount tag that you will use for mounting the VirtFS share inside the VM.  See this page for how to do this on a Linux-based guest: <a href="http://wiki.qemu.org/Documentation/9psetup" target="_blank">http://wiki.qemu.org/Documentation/9psetup</a>
					</p>

					<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
				</blockquote>
			</div>
		</div>
		<?}?>
	<?}?>
	<script type="text/html" id="tmplShare">
		<table class="domain_os other">
			<tr class="advanced">
				<td>_(Unraid Share)_:</td>
				<td>
					<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="" name="shares[{{INDEX}}][source]" placeholder="_(e.g.)_ /mnt/user/..." title="_(path of Unraid share)_" />
				</td>
			</tr>

			<tr class="advanced">
				<td>_(Unraid Mount tag)_:</td>
				<td>
					<input type="text" value="" name="shares[{{INDEX}}][target]" placeholder="_(e.g.)_ _(shares)_ (_(name of mount tag inside vm)_)" title="_(mount tag inside vm)_" />
				</td>
			</tr>
		</table>
	</script>

	<?foreach ($arrConfig['gpu'] as $i => $arrGPU) {
		$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

		?>
		<table data-category="Graphics_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidGPUDevices)+1?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
			<tr>
				<td>_(Graphics Card)_:</td>
				<td>
					<select name="gpu[<?=$i?>][id]" class="gpu narrow">
					<?
						if ($i == 0) {
							// Only the first video card can be VNC
							echo mk_option($arrGPU['id'], 'vnc', _('VNC'));
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

			<?if ($i == 0) {?>
			<tr class="<?if ($arrGPU['id'] != 'vnc') echo 'was';?>advanced vncmodel">
				<td>_(VNC Video Driver)_:</td>
				<td>
					<select id="vncmodel" name="gpu[<?=$i?>][model]" class="narrow" title="_(video for VNC)_">
					<?mk_dropdown_options($arrValidVNCModels, $arrGPU['model']);?>
					</select>
				</td>
			</tr>
			<tr class="vncpassword">
				<td>_(VNC Password)_:</td>
				<td><input type="password" name="domain[password]" autocomplete='new-password' title="_(password for VNC)_" placeholder="_(password for VNC)_ (_(optional)_)" /></td>
			</tr>
			<tr class="<?if ($arrGPU['id'] != 'vnc') echo 'was';?>advanced vnckeymap">
				<td>_(VNC Keyboard)_:</td>
				<td>
					<select name="gpu[<?=$i?>][keymap]" title="_(keyboard for VNC)_">
					<?mk_dropdown_options($arrValidKeyMaps, $arrGPU['keymap']);?>
					</select>
				</td>
			</tr>
			<?}?>
			<tr class="<?if ($arrGPU['id'] == 'vnc') echo 'was';?>advanced romfile">
				<td>_(Graphics ROM BIOS)_:</td>
				<td>
					<input type="text" data-pickcloseonfile="true" data-pickfilter="rom,bin" data-pickmatch="^[^.].*" data-pickroot="/" value="<?=htmlspecialchars($arrGPU['rom'])?>" name="gpu[<?=$i?>][rom]" placeholder="_(Path to ROM BIOS file)_ (_(optional)_)" title="_(Path to ROM BIOS file)_ (_(optional)_)" />
				</td>
			</tr>
		</table>
		<?if ($i == 0) {?>
		<blockquote class="inline_help">
			<p>
				<b>Graphics Card</b><br>
				If you wish to assign a graphics card to the VM, select it from this list, otherwise leave it set to VNC.
			</p>

			<p class="<?if ($arrGPU['id'] != 'vnc') echo 'was';?>advanced vncmodel">
				<b>VNC Video Driver</b><br>
				If you wish to assign a different video driver to use for a VNC connection, specify one here.
			</p>

			<p class="vncpassword">
				<b>VNC Password</b><br>
				If you wish to require a password to connect to the VM over a VNC connection, specify one here.
			</p>

			<p class="<?if ($arrGPU['id'] != 'vnc') echo 'was';?>advanced vnckeymap">
				<b>VNC Keyboard</b><br>
				If you wish to assign a different keyboard layout to use for a VNC connection, specify one here.
			</p>

			<p class="<?if ($arrGPU['id'] == 'vnc') echo 'was';?>advanced romfile">
				<b>Graphics ROM BIOS</b><br>
				If you wish to use a custom ROM BIOS for a Graphics card, specify one here.
			</p>

			<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
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
					<input type="text" data-pickcloseonfile="true" data-pickfilter="rom,bin" data-pickmatch="^[^.].*" data-pickroot="/" value="" name="gpu[{{INDEX}}][rom]" placeholder="_(Path to ROM BIOS file)_ (_(optional)_)" title="_(Path to ROM BIOS file)_ (_(optional)_)" />
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
			<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
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

	<?foreach ($arrConfig['nic'] as $i => $arrNic) {
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
				<td>_(Network Bridge)_:</td>
				<td>
					<select name="nic[<?=$i?>][network]">
					<?
					foreach ($arrValidBridges as $strBridge) {
						echo mk_option($arrNic['network'], $strBridge, $strBridge);
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
		</table>
		<?if ($i == 0) {?>
		<div class="advanced">
			<blockquote class="inline_help">
				<p>
					<b>Network MAC</b><br>
					By default, a random MAC address will be assigned here that conforms to the standards for virtual network interface controllers.  You can manually adjust this if desired.
				</p>

				<p>
					<b>Network Bridge</b><br>
					The default libvirt managed network bridge (virbr0) will be used, otherwise you may specify an alternative name for a private network bridge to the host.
				</p>

				<p>
					<b>Network Model</b><br>
					Default and recommended is 'virtio-net', which gives improved stability. To improve performance 'virtio' can be selected, but this may lead to stability issues.
				</p>

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
				<td>_(Network Bridge)_:</td>
				<td>
					<select name="nic[{{INDEX}}][network]">
					<?
					foreach ($arrValidBridges as $strBridge) {
						echo mk_option($domain_bridge, $strBridge, $strBridge);
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
		</table>
	</script>

	<table>
		<tr>
			<td>_(USB Devices)_:</td>
			<td>
				<div class="textarea" style="width: 540px">
				<?
					if (!empty($arrValidUSBDevices)) {
						foreach($arrValidUSBDevices as $i => $arrDev) {
						?>
						<label for="usb<?=$i?>"><input type="checkbox" name="usb[]" id="usb<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?if (count(array_filter($arrConfig['usb'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) echo 'checked="checked"';?>/> <?=htmlspecialchars($arrDev['name'])?> (<?=htmlspecialchars($arrDev['id'])?>)</label><br/>
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
	</blockquote>

	<table>
		<tr>
			<td>_(Other PCI Devices)_:</td>
			<td>
				<div class="textarea" style="width: 540px">
				<?
					$intAvailableOtherPCIDevices = 0;

					if (!empty($arrValidOtherDevices)) {
						foreach($arrValidOtherDevices as $i => $arrDev) {
							$extra = '';
							if (count(array_filter($arrConfig['pci'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) {
								$extra .= ' checked="checked"';
							} elseif (!in_array($arrDev['driver'], ['pci-stub', 'vfio-pci'])) {
								//$extra .= ' disabled="disabled"';
								continue;
							}
							$intAvailableOtherPCIDevices++;
					?>
						<label for="pci<?=$i?>"><input type="checkbox" name="pci[]" id="pci<?=$i?>" value="<?=htmlspecialchars($arrDev['id'])?>" <?=$extra?>/> <?=htmlspecialchars($arrDev['name'])?> | <?=htmlspecialchars($arrDev['type'])?> (<?=htmlspecialchars($arrDev['id'])?>)</label><br/>
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
		<p>Click Create to generate the vDisks and return to the Virtual Machines page where your new VM will be created.</p>
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

	function resetForm() {
		$("#vmform .domain_vcpu").change(); // restore the cpu checkbox disabled states
		<?if (!empty($arrConfig['domain']['state'])) echo '$(\'#vmform #domain_ovmf\').prop(\'disabled\', true); // restore bios disabled state' . "\n";?>
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

	var regenerateDiskPreview = function (disk_index) {
		var domaindir = '<?=$domain_cfg['DOMAINDIR']?>' + $('#domain_oldname').val();
		var tl_args = arguments.length;

		$("#vmform .disk").closest('table').each(function (index) {
			var $table = $(this);

			if (tl_args && disk_index != $table.data('index')) {
				return;
			}

			var disk_select = $table.find(".disk_select option:selected").val();
			var $disk_file_sections = $table.find('.disk_file_options');
			var $disk_bus_sections = $table.find('.disk_bus_options');
			var $disk_input = $table.find('.disk');
			var $disk_preview = $table.find('.disk_preview');

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
					} else {
						$disk_file_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
						slideDownRows($disk_file_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

						$disk_input.attr('name', $disk_input.attr('name').replace('image', 'new'));
					}
				});

			} else if (disk_select !== '') {

				// Auto disk
				var auto_disk_path = domaindir + '/vdisk' + (index+1) + '.img';
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

	<?if ($boolNew):?>
	$("#vmform #domain_name").on("input change", function changeNameEvent() {
		$('#vmform #domain_oldname').val($(this).val());
		regenerateDiskPreview();
	});
	<?endif?>

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

	$("#vmform #domain_machine").change(function changeMachineEvent() {
		// Cdrom Bus: select IDE for i440 and SATA for q35
		if ($(this).val().indexOf('i440fx') != -1) {
			$('#vmform .cdrom_bus').val('ide');
		} else {
			$('#vmform .cdrom_bus').val('sata');
		}
	});

	$("#vmform #domain_ovmf").change(function changeBIOSEvent() {
		// using OVMF - disable vmvga vnc option
		if ($(this).val() == '1' && $("#vmform #vncmodel").val() == 'vmvga') {
			$("#vmform #vncmodel").val('qxl');
		}
		$("#vmform #vncmodel option[value='vmvga']").prop('disabled', ($(this).val() == '1'));
	}).change(); // fire event now

	$("#vmform").on("spawn_section", function spawnSectionEvent(evt, section, sectiondata) {
		if (sectiondata.category == 'vDisk') {
			regenerateDiskPreview(sectiondata.index);
		}
		if (sectiondata.category == 'Graphics_Card') {
			$(section).find(".gpu").change();
		}
	});

	$("#vmform").on("destroy_section", function destroySectionEvent(evt, section, sectiondata) {
		if (sectiondata.category == 'vDisk') {
			regenerateDiskPreview();
		}
	});

	$("#vmform").on("input change", ".cdrom", function changeCdromEvent() {
		if ($(this).val() == '') {
			slideUpRows($(this).closest('table').find('.cdrom_bus').closest('tr'));
		} else {
			slideDownRows($(this).closest('table').find('.cdrom_bus').closest('tr'));
		}
	});

	$("#vmform").on("change", ".disk_select", function changeDiskSelectEvent() {
		regenerateDiskPreview($(this).closest('table').data('index'));
	});

	$("#vmform").on("input change", ".disk", function changeDiskEvent() {
		var $input = $(this);
		var config = $input.data();

		if (config.hasOwnProperty('pickfilter')) {
			regenerateDiskPreview($input.closest('table').data('index'));
		}
	});

	$("#vmform").on("change", ".gpu", function changeGPUEvent() {
		var myvalue = $(this).val();
		var mylabel = $(this).children('option:selected').text();
		var myindex = $(this).closest('table').data('index');

		if (myindex == 0) {
			$vnc_sections = $('.vncmodel,.vncpassword,.vnckeymap');
			if (myvalue == 'vnc') {
				$vnc_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($vnc_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
			} else {
				slideUpRows($vnc_sections);
				$vnc_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
			}
		}

		$romfile = $(this).closest('table').find('.romfile');
		if (myvalue == 'vnc' || myvalue == '') {
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

		$.getJSON("/plugins/dynamix.vm.manager/include/VMajax.php?action=generate-mac", function( data ) {
			if (data.mac) {
				$input.val(data.mac);
			}
		});
	});

	$("#vmform .formview #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $panel = $('.formview');
		var form = $button.closest('form');

		$("#vmform .disk_select option:selected").not("[value='manual']").closest('table').each(function () {
			var v = $(this).find('.disk_preview').html();
			$(this).find('.disk').val(v);
		});

		$panel.find('input').prop('disabled', false); // enable all inputs otherwise they wont post

		<?if (!$boolNew):?>
		// signal devices to be added or removed
		form.find('input[name="usb[]"],input[name="pci[]"]').each(function(){
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
			if (gpu != 'vnc' && !gpus.includes(gpu)) form.append('<input type="hidden" name="pci[]" value="'+gpu+'#remove">');
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
		form.find('input[name="usb[]"],input[name="pci[]"]').each(function(){
			if ($(this).val().indexOf('#remove')>0) $(this).prop('checked',false);
		});
		<?endif?>

		$panel.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/Custom.form.php", postdata, function( data ) {
			if (data.success) {
				if (data.vncurl) {
					var vnc_window=window.open(data.vncurl, '_blank', 'scrollbars=yes,resizable=yes');
					try {
						vnc_window.focus();
					} catch (e) {
						swal({title:"_(Browser error)_",text:"_(Pop-up Blocker is enabled! Please add this site to your exception list)_",type:"warning",confirmButtonText:"_(Ok)_"},function(){ done() });
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
				$('#vmform #domain_machine').val($(this).val()).change();
				return false;
			}
		});
	} else {
		$('#vmform #domain_clock').val('utc');
		$("#vmform #domain_machine option").each(function(){
			if ($(this).val().indexOf('q35') != -1) {
				$('#vmform #domain_machine').val($(this).val()).change();
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
