<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 *
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

$allowedPCIClass = ['0x02'];
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

/**
 * Get available kernel modules for this PCI device based on its modalias
 */
function getModulesFromModalias(string $pci): array {
    $modaliasFile = "/sys/bus/pci/devices/{$pci}/modalias";
    if (!is_readable($modaliasFile)) return [];
    $alias = trim(file_get_contents($modaliasFile));
    $cmd = sprintf('modprobe -R %s 2>/dev/null', escapeshellarg($alias));
    $out = trim(shell_exec($cmd));
    return $out ? preg_split('/\s+/', $out) : [];
}

/**
 * Enumerate SR-IOV capable PCI devices (keyed by PCI address).
 *
 * Example JSON:
 * {
 *   "0000:03:00.0": {
 *     "class": "Ethernet controller",
 *     "class_id": "0x0200",
 *     "name": "Intel Corporation X710 for 10GbE SFP+",
 *     "driver": "i40e",
 *     "module": "i40e",
 *     "vf_param": "max_vfs",
 *     "total_vfs": 64,
 *     "num_vfs": 8,
 *     "vfs": [
 *       {"pci": "0000:03:10.0", "iface": "enp3s0f0v0", "mac": "52:54:00:aa:00:01"}
 *     ]
 *   }
 * }
 */

function getSriovInfoJson(bool $includeVfDetails = true): string {
    $results = [];
    $paths = glob('/sys/bus/pci/devices/*/sriov_totalvfs') ?: [];

    foreach ($paths as $totalvfFile) {
        $devdir = dirname($totalvfFile);
        $pci = basename($devdir);

        $total_vfs = (int) @file_get_contents($totalvfFile);
        $num_vfs   = (int) @file_get_contents("$devdir/sriov_numvfs");

        // Driver/module detection
        $driver = $module = $vf_param = null;
        $driver_link = "$devdir/driver";
        if (is_link($driver_link)) {
            $driver = basename(readlink($driver_link));
            $module_link = "$driver_link/module";
            $module = is_link($module_link) ? basename(readlink($module_link)) : $driver;
            $vf_param = detectVfParam($driver);
        }

        // Device class + numeric class + name
        [$class, $class_id, $name] = getPciClassNameAndId($pci);

        // Virtual functions
        $vfs = [];
        foreach (glob("$devdir/virtfn*") as $vf) {
            if (!is_link($vf)) continue;
            $vf_pci = basename(readlink($vf));
            $vf_entry = ['pci' => $vf_pci];

            if ($includeVfDetails) {
                // Vendor:Device formatted string
                $vendorFile = "/sys/bus/pci/devices/{$vf_pci}/vendor";
                $deviceFile = "/sys/bus/pci/devices/{$vf_pci}/device";
                $vendor = is_readable($vendorFile) ? trim(file_get_contents($vendorFile)) : null;
                $device = is_readable($deviceFile) ? trim(file_get_contents($deviceFile)) : null;
                $vf_entry['vd'] = ($vendor && $device) ? sprintf('%s:%s', substr($vendor, 2), substr($device, 2)) : null;

                // Network interface info
                $net = glob("/sys/bus/pci/devices/{$vf_pci}/net/*");
                if ($net && isset($net[0])) {
                    $iface = basename($net[0]);
                    $vf_entry['iface'] = $iface;
                    $macFile = "/sys/class/net/{$iface}/address";
                    if (is_readable($macFile)) {
                        $vf_entry['mac'] = trim(file_get_contents($macFile));
                    }
                }

                // IOMMU group
                $iommu_link = "/sys/bus/pci/devices/{$vf_pci}/iommu_group";
                if (is_link($iommu_link)) {
                    $vf_entry['iommu_group'] = basename(readlink($iommu_link));
                } else {
                    $vf_entry['iommu_group'] = null;
                }

                // --- Current driver ---
                $driver_link = "/sys/bus/pci/devices/{$vf_pci}/driver";
                if (is_link($driver_link)) {
                    $vf_entry['driver'] = basename(readlink($driver_link));
                } else {
                    $vf_entry['driver'] = null; // no driver bound
                }
                // Kernel modules (from modalias)
                $vf_entry['modules'] = getModulesFromModalias($vf_pci);
            }
            $vfs[$vf_pci] = $vf_entry;
        }

        $results[$pci] = [
            'class' => $class,
            'class_id' => $class_id,
            'name' => $name,
            'driver' => $driver,
            'module' => $module,
            'vf_param' => $vf_param,
            'total_vfs' => $total_vfs,
            'num_vfs' => $num_vfs,
            'vfs' => $vfs
        ];
    }

    ksort($results, SORT_NATURAL);
    return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function rebindVfDriver($vf, $sriov, $target = 'original')
{
    $res = ['pci'=>$vf,'success'=>false,'error'=>null,'details'=>[]];
    $vf_path = "/sys/bus/pci/devices/$vf";
    $physfn = "$vf_path/physfn";
    if (!is_link($physfn)) {
        $res['error'] = _("Missing physfn link");
        return $res;
    }
    $pf = basename(readlink($physfn));
    $vf_info = $sriov[$pf]['vfs'][$vf] ?? null;
    if (!$vf_info) {
        $res['error'] = _("VF not found in sriov for PF")." $pf";
        return $res;
    }

    $orig_mod = $vf_info['modules'][0] ?? $sriov[$pf]['module'] ?? null;
    $curr_drv = $vf_info['driver'] ?? null;
    if (!$orig_mod) {
        $res['error'] = _("No module info for")." $vf";
        return $res;
    }

    $drv_override = "$vf_path/driver_override";

    // Determine target driver
    $new_drv = ($target === 'vfio-pci') ? 'vfio-pci' : $orig_mod;

    // Step 1: Unbind current driver
    $curr_unbind = "/sys/bus/pci/drivers/$curr_drv/unbind";
    if (is_writable($curr_unbind))
        @file_put_contents($curr_unbind, $vf);

    // Step 2: Load target driver if needed
    $target_bind = "/sys/bus/pci/drivers/$new_drv/bind";
    if (!file_exists($target_bind))
        exec("modprobe " . escapeshellarg($new_drv) . " 2>/dev/null");

    // Step 3: Override driver binding
    if (is_writable($drv_override))
        @file_put_contents($drv_override, "$new_drv");
    $probe_path = "/sys/bus/pci/drivers_probe";
    if (is_writable($probe_path))
        @file_put_contents($probe_path, $vf);
    if (is_writable($drv_override))
        @file_put_contents($drv_override, "\n");

    // Step 4: Verify binding
    $drv_link = "$vf_path/driver";
    if (is_link($drv_link)) {
        $bound = basename(readlink($drv_link));
        if ($bound === $new_drv) {
            $res['success'] = true;
            $res['details'][] = _("Bound to")." $new_drv";
            return $res;
        }
        $res['error'] = sprintf(_("Bound to %s instead of %s"), $bound, $new_drv);
        return $res;
    }
    $res['error'] = _("No driver link after reprobe");
    return $res;
}


function detectVfParam(string $driver): ?string {
    if (!function_exists('shell_exec')) return null;
    $out = @shell_exec('modinfo ' . escapeshellarg($driver) . ' 2>/dev/null');
    if (!$out) return null;

    $lines = explode("\n", strtolower($out));
    $params = [];
    foreach ($lines as $line) {
        if (preg_match('/^parm:\s+(\S+)/', $line, $m)) $params[] = $m[1];
    }

    foreach (['max_vfs', 'num_vfs', 'sriov_numvfs', 'sriov_vfs'] as $key)
        if (in_array($key, $params, true)) return $key;

    foreach ($params as $p)
        if (preg_match('/vf/', $p)) return $p;

    return null;
}

/**
 * Robustly get PCI class (text + numeric ID) and device name from lspci/sysfs.
 */
function getPciClassNameAndId(string $pci): array {
    $class = 'Unknown';
    $class_id = null;
    $name = 'Unknown';

    // Numeric class code from sysfs
    $classFile = "/sys/bus/pci/devices/{$pci}/class";
    if (is_readable($classFile)) {
        $raw = trim(file_get_contents($classFile));
        $class_id = sprintf("0x%04x", (hexdec($raw) >> 8) & 0xFFFF);
    }

    // Try lspci -mm for machine-readable info
    $out = trim(@shell_exec('lspci -mm -s ' . escapeshellarg($pci) . ' 2>/dev/null'));
    if ($out && preg_match('/"([^"]+)"\s+"([^"]+)"\s+"([^"]+)"/', $out, $m)) {
        $class = $m[1];
        $name = trim($m[3]);
        return [$class, $class_id, $name];
    }

    // Fallback to regular lspci output
    $alt = trim(@shell_exec('lspci -s ' . escapeshellarg($pci) . ' 2>/dev/null'));
    if ($alt && preg_match('/^[\da-fA-F:.]+\s+([^:]+):\s+(.+)/', $alt, $m)) {
        $class = trim($m[1]);
        $name = trim($m[2]);
    }

    return [$class, $class_id, $name];
}

/**
 * Enumerate all VFs and group them by IOMMU group
 * Output: Flat array of groups and pci vfpci as separate elemements
 */
function getVfListByIommuGroup(): array {
    $groups = [];

    foreach (glob('/sys/bus/pci/devices/*/physfn') as $vf_physfn) {
        $vf_dir = dirname($vf_physfn);
        $vf_pci = basename($vf_dir);

        $iommu_link = "$vf_dir/iommu_group";
        if (is_link($iommu_link)) {
            $iommu_group = basename(readlink($iommu_link));
        } else {
            $iommu_group = "unknown";
        }

        $groups[] = "IOMMU group " . $iommu_group;
        $groups[] = $vf_pci;
    }

    return $groups;
}

// ----------------------
// Parse SR-IOV VF counts
// ----------------------
function parseVFvalues() {
    $sriov_devices = [];
    $DBDF_SRIOV_REGEX = '/^[[:xdigit:]]{4}:[[:xdigit:]]{2}:[[:xdigit:]]{2}\.[[:xdigit:]]\|[[:xdigit:]]{4}:[[:xdigit:]]{4}\|[[:digit:]]+$/';
    if (is_file("/boot/config/sriov.cfg")) {
        $file = trim(file_get_contents("/boot/config/sriov.cfg"));
        $file = preg_replace('/^VFS=/', '', $file); // Remove prefix
        $entries = preg_split('/\s+/', $file, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($entries as $entry) {
            if (preg_match($DBDF_SRIOV_REGEX, $entry)) {
                // Format: <DBDF>|<Vendor:Device>|<VF_count>
                [$dbdf, $ven_dev, $vf_count] = explode('|', $entry);
                $sriov_devices[$dbdf] = [
                    'dbdf'     => $dbdf,
                    'vendor'   => $ven_dev,
                    'vf_count' => (int)$vf_count,
                ];
            }
        }
    }
    return $sriov_devices;
}

// ---------------------------------
// Parse SR-IOV VF settings (VFIO+MAC)
// ---------------------------------
function parseVFSettings() {
  $sriov_devices_settings = [];
  $DBDF_SRIOV_SETTINGS_REGEX = '/^[[:xdigit:]]{4}:[[:xdigit:]]{2}:[[:xdigit:]]{2}\.[[:xdigit:]]\|[[:xdigit:]]{4}:[[:xdigit:]]{4}\|[01]\|([[:xdigit:]]{2}:){5}[[:xdigit:]]{2}$/';
  if (is_file("/boot/config/sriovvfs.cfg")) {
        $file = trim(file_get_contents("/boot/config/sriovvfs.cfg"));
        $file = preg_replace('/^VFSETTINGS=/', '', $file); // Remove prefix
        $entries = preg_split('/\s+/', $file, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($entries as $entry) {
            if (preg_match($DBDF_SRIOV_SETTINGS_REGEX, $entry)) {
                // Format: <DBDF>|<Vendor:Device>|<VFIO_flag>|<MAC>
                [$dbdf, $ven_dev, $vfio_flag, $mac] = explode('|', $entry);
                if ($mac === "00:00:00:00:00:00") $mac = "";
                $sriov_devices_settings[$dbdf] = [
                    'dbdf'     => $dbdf,
                    'vendor'   => $ven_dev,
                    'vfio'     => (int)$vfio_flag,
                    'mac'      => strtoupper($mac),
                ];
            }
        }
    }
    return $sriov_devices_settings;
}

/**
 * Safely set a MAC address for a VF.
 * Automatically detects PF, interface, and VF index.
 *
 * @param string      $vf_pci   PCI ID of VF (e.g. 0000:02:00.2)
 * @param string      $mac      MAC address to assign
 * @param string|null $rebindDriver  Driver to bind after change:
 *                                   - null → rebind to original driver
 *                                   - 'none' → leave unbound
 *                                   - 'vfio-pci' → bind to vfio-pci
 *
 * @return array  Result info (for JSON or logs)
 */
function setVfMacAddress(string $vf_pci, array $sriov, string $mac, ?string $rebindDriver = null): array {
    $vf_path = "/sys/bus/pci/devices/{$vf_pci}";
    $result = [
        'vf_pci' => $vf_pci,
        'mac' => $mac,
        'pf_pci' => null,
        'pf_iface' => null,
        'vf_index' => null,
        'driver_before' => null,
        'driver_after' => null,
        'unbind' => false,
        'mac_set' => false,
        'rebind' => false,
        'error' => null,
        'details' => []
    ];

    if ($mac != "" && preg_match('/^([a-fA-F0-9]{2}[:\-]){5}[a-fA-F0-9]{2}$/', $mac) != 1) {
        $result['error'] = _("MAC format is invalid.");
        return $result;
    }

    if (!is_dir($vf_path)) {
        $result['error'] = _("VF path not found").": $vf_path";
        return $result;
    }

    // --- Find parent PF (Physical Function) ---
    $pf_link = "$vf_path/physfn";
    if (!is_link($pf_link)) {
        $result['error'] = sprintf("No PF link for %s (not an SR-IOV VF?)",$vf_pci);
        return $result;
    }
    $pf_pci = basename(readlink($pf_link));
    $result['pf_pci'] = $pf_pci;

    // --- Detect PF network interface name ---
    $pf_net = glob("/sys/bus/pci/devices/{$pf_pci}/net/*");
    $pf_iface = ($pf_net && isset($pf_net[0])) ? basename($pf_net[0]) : null;
    if (!$pf_iface) {
        $result['error'] = _("Could not detect PF interface for")." $pf_pci";
        return $result;
    }
    $result['pf_iface'] = $pf_iface;

    // --- Detect VF index ---
    $vf_index = getVfIndex($pf_pci, $vf_pci);
    if ($vf_index === null) {
        $result['error'] = sprintf(_("Could not determine VF index for %s under %s"),$vf_pci,$pf_pci);
        return $result;
    }
    $result['vf_index'] = $vf_index;

    // --- Detect current driver ---
    $driver_link = "$vf_path/driver";
    $vf_driver = is_link($driver_link) ? basename(readlink($driver_link)) : null;
    $result['driver_before'] = $vf_driver;

    // --- Unbind from current driver ---
    if ($vf_driver) {
        $unbind_path = "/sys/bus/pci/drivers/{$vf_driver}/unbind";
        if (is_writable($unbind_path)) {
            file_put_contents($unbind_path, $vf_pci);
            $result['unbind'] = true;
        } else {
            $result['error'] = sprintf(_("Cannot unbind VF %s from %s (permissions)"),$vf_pci,$vf_driver);
            return $result;
        }
    }

    // --- Set MAC ---
    if ($mac=="") $mac="00:00:00:00:00:00";
    $cmd = sprintf(
        'ip link set %s vf %d mac %s 2>&1',
        escapeshellarg($pf_iface),
        $vf_index,
        escapeshellarg($mac)
    );
    exec($cmd, $output, $ret);

    if ($ret === 0) {
        $result['mac_set'] = true;
        $result['details'] = [sprintf(_("MAC address set to %s"),($mac != "00:00:00:00:00:00") ? strtoupper($mac) : _("Dynamic allocation"))];
    } else {
        $result['error'] = _("Failed to set MAC").": " . implode("; ", $output);
    }

    // --- Rebind logic ---
    if ($rebindDriver !== "none") {
        $result2 = rebindVfDriver($vf_pci,$sriov,$rebindDriver);
        if (isset($result2['error'])) $result['error'] = $result2['error']; 
        elseif (isset($result2['details']))  $result["details"] = array_merge($result['details'] , $result2['details']);
    }
    if ($result['error'] === null) {
        $result['success'] = true;
    }
    return $result;
}

/**
 * Helper: Determine VF index from PF/VF relationship
 */
function getVfIndex(string $pf_pci, string $vf_pci): ?int {
    $pf_path = "/sys/bus/pci/devices/{$pf_pci}";
    foreach (glob("$pf_path/virtfn*") as $vf_link) {
        if (is_link($vf_link)) {
            $target = basename(readlink($vf_link));
            if ($target === $vf_pci) {
                return (int)preg_replace('/[^0-9]/', '', basename($vf_link));
            }
        }
    }
    return null;
}

function build_pci_active_vm_map() {
    global $lv, $libvirt_running;
    if ($libvirt_running !== "yes") return [];
    $pcitovm = [];

    $vms = $lv->get_domains();
    foreach ($vms as $vm) {
        $vmpciids = $lv->domain_get_vm_pciids($vm);
        $res = $lv->get_domain_by_name($vm);
        $dom = $lv->domain_get_info($res);
        $state = $lv->domain_state_translate($dom['state']);
        if ($state === 'shutoff') continue;

        foreach ($vmpciids as $pciid => $pcidetail) {
            $pcitovm["0000:" . $pciid][$vm] = $state;
        }
    }

    return $pcitovm;
}

function is_pci_inuse($pciid, $type) {
    $actives = build_pci_active_vm_map();
    $sriov = json_decode(getSriovInfoJson(true), true);
    $inuse = false;
    $vms = [];

    switch ($type) {
        case "VF":
            if (isset($actives[$pciid])) {
                $inuse = true;
                $vms = array_keys($actives[$pciid]);
            }
            break;

        case "PF":
            if (isset($sriov[$pciid])) {
                $vfs = $sriov[$pciid]['vfs'] ?? [];
                foreach ($vfs as $vf) {
                    $vf_pci = $vf['pci'];
                    if (isset($actives[$vf_pci])) {
                        $inuse = true;
                        $vms = array_merge($vms, array_keys($actives[$vf_pci]));
                    }
                }
            }
            break;
    }

    // Remove duplicate VM names (in case multiple VFs from same VM)
    $vms = array_values(array_unique($vms));

    // Output consistent JSON structure
    $result = [
        "inuse" => $inuse,
        "vms"   => $vms
    ];

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}


?>