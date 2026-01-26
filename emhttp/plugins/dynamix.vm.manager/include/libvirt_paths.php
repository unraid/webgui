<?PHP
/* Copyright 2005-2026, Lime Technology
 *
 * Centralized libvirt path configuration.
 */
?>
<?
$libvirt_paths = @parse_ini_file('/etc/libvirt/paths.conf', false, INI_SCANNER_RAW);
if (!is_array($libvirt_paths)) {
	$libvirt_paths = [];
}

if (!defined('LIBVIRT_QEMU_DIR')) {
	define('LIBVIRT_QEMU_DIR', $libvirt_paths['LIBVIRT_QEMU_DIR'] ?? '/etc/libvirt/qemu');
}
if (!defined('LIBVIRT_NVRAM_DIR')) {
	define('LIBVIRT_NVRAM_DIR', $libvirt_paths['LIBVIRT_NVRAM_DIR'] ?? (LIBVIRT_QEMU_DIR . '/nvram'));
}
if (!defined('LIBVIRT_SNAPSHOTDB_DIR')) {
	define('LIBVIRT_SNAPSHOTDB_DIR', $libvirt_paths['LIBVIRT_SNAPSHOTDB_DIR'] ?? (LIBVIRT_QEMU_DIR . '/snapshotdb'));
}

function libvirt_get_vms_json() {
	static $vms_json_cache = null;
	if ($vms_json_cache !== null) {
		return $vms_json_cache;
	}
	$vms_json_path = '/boot/config/plugins/dynamix.vm.manager/vms.json';
	if (!file_exists($vms_json_path)) {
		$vms_json_cache = [];
		return $vms_json_cache;
	}
	$json = @json_decode(file_get_contents($vms_json_path), true);
	$vms_json_cache = is_array($json) ? $json : [];
	return $vms_json_cache;
}

function libvirt_get_vm_path($vm_name) {
	if (empty($vm_name)) {
		return null;
	}
	$vms_json = libvirt_get_vms_json();
	return $vms_json[$vm_name]['path'] ?? null;
}

function libvirt_get_nvram_dir($vm_path = null, $vm_name = null) {
	if (empty($vm_path) && !empty($vm_name) && file_exists('/boot/config/plugins/dynamix.vm.manager/vm_newmodel')) {
		$vm_path = libvirt_get_vm_path($vm_name);
	}
	if (!empty($vm_path) && file_exists('/boot/config/plugins/dynamix.vm.manager/vm_newmodel')) {
		return rtrim($vm_path, '/') . '/nvram';
	}
	return LIBVIRT_NVRAM_DIR;
}

function libvirt_get_snapshotdb_dir($vm_path = null, $vm_name = null) {
	if (empty($vm_path) && !empty($vm_name) && file_exists('/boot/config/plugins/dynamix.vm.manager/vm_newmodel')) {
		$vm_path = libvirt_get_vm_path($vm_name);
	}
	if (!empty($vm_path) && file_exists('/boot/config/plugins/dynamix.vm.manager/vm_newmodel')) {
		return rtrim($vm_path, '/') . '/snapshotdb';
	}
	return LIBVIRT_SNAPSHOTDB_DIR;
}
?>
