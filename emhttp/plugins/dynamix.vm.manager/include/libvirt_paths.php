<?PHP
/* Copyright 2005-2026, Lime Technology
 *
 * Centralized libvirt path configuration.
 */
?>
<?
$libvirt_paths = @parse_ini_file('/etc/rc.d/rc.libvirt.conf', false, INI_SCANNER_RAW);
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

function libvirt_get_default_domain_dir() {
	$cfg = '/boot/config/domain.cfg';
	if (!file_exists($cfg)) {
		return null;
	}
	$lines = file($cfg, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return null;
	}
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || $line[0] === '#') {
			continue;
		}
		if (preg_match('/^DOMAINDIR="([^"]+)"/', $line, $m)) {
			return rtrim($m[1], '/');
		}
	}
	return null;
}

function libvirt_build_vm_entry($vm_name, $storage_name = null, $default_domain_dir = null, $uuid = null) {
	if (empty($vm_name)) {
		return null;
	}
	$default_domain_dir = $default_domain_dir ?? libvirt_get_default_domain_dir();
	$storage_name = ($storage_name === null || $storage_name === '' || strtolower($storage_name) === 'default')
		? 'default'
		: $storage_name;

	if ($storage_name === 'default') {
		$path_root = $default_domain_dir;
	} else {
		$path_root = preg_replace('#^/mnt/[^/]+/#', "/mnt/$storage_name/", $default_domain_dir, 1, $replaced);
		if ($replaced === 0) {
			$path_root = $default_domain_dir;
		}
	}

	$path = $path_root ? $path_root . '/' . $vm_name : null;
	$exists = ($path_root && is_dir($path_root . '/' . $vm_name));

	return [
		'uuid'       => $uuid,
		'storage'    => $storage_name,
		'path'       => $path,
		'path_shell' => $path ? escapeshellarg($path) : null,
		'exists'     => $exists,
	];
}

function libvirt_update_vms_json_entry($vm_name, array $entry) {
	$cfg = '/boot/config/plugins/dynamix.vm.manager/vms.json';
	$dir = dirname($cfg);
	if (!is_dir($dir)) {
		@mkdir($dir, 0755, true);
	}
	$vms = [];
	if (file_exists($cfg)) {
		$json = @json_decode(@file_get_contents($cfg), true);
		if (is_array($json)) {
			$vms = $json;
		}
	}
	$vms[$vm_name] = array_filter($entry, fn($v) => $v !== null);
	ksort($vms, SORT_NATURAL);
	@file_put_contents($cfg, json_encode($vms, JSON_PRETTY_PRINT));
}

function libvirt_remove_vms_json_entry($vm_name) {
	if (empty($vm_name)) {
		return false;
	}
	$cfg = '/boot/config/plugins/dynamix.vm.manager/vms.json';
	if (!file_exists($cfg)) {
		return false;
	}
	$json = @json_decode(@file_get_contents($cfg), true);
	if (!is_array($json) || !array_key_exists($vm_name, $json)) {
		return false;
	}
	unset($json[$vm_name]);
	ksort($json, SORT_NATURAL);
	@file_put_contents($cfg, json_encode($json, JSON_PRETTY_PRINT));
	return true;
}
?>
