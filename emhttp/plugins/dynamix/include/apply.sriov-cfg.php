<?PHP
/* Copyright 2025-, Lime Technology
 * Copyright 2025-, Simon Fairweather.
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

#VFSETTINGS=0000:04:11.5|8086:1520|0|62:00:04:11:05:01 0000:04:10.5|8086:1520|1|62:00:04:10:05:01
#VFS=0000:04:00.1|8086:1521|3 0000:04:00.0|8086:1521|2

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
require_once "$docroot/webGui/include/SriovHelpers.php";
require_once "$docroot/webGui/include/Translations.php";

function acknowledge_PCI($pciaddr)
{
    $savedfile = "/boot/config/savedpcidata.json";
    $saved = loadSavedData($savedfile);
    if (!$saved) {echo "ERROR"; return;};
    $current = loadCurrentPCIData();
    $saved[$pciaddr] = $current[$pciaddr];
    file_put_contents($savedfile,json_encode($saved,JSON_PRETTY_PRINT));
}

function json_response($success, $error = null, $details = [])
{
    header('Content-Type: application/json');

    # Normalize details
    if (is_array($details)) {
        if (count($details) === 1) {
            # Collapse single-item arrays to a single value
            $details = reset($details);
        } elseif (empty($details)) {
            $details = null;
        }
    }

    $response = [
        'success' => (bool)$success,
        'error'   => $error,
        'details' => $details
    ];

    $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo $json;
    exit;
}

function safe_file_put_contents($path, $data)
{
    # If the file exists but is not writable
    if (file_exists($path) && !is_writable($path)) {
        throw new RuntimeException("File not writable: $path", 1001);
    }

    # If file does not exist → make sure directory is writable
    $dir = dirname($path);
    if (!file_exists($path) && !is_writable($dir)) {
        throw new RuntimeException("Directory not writable for file creation: $dir", 1002);
    }

    # Attempt the write
    $result = @file_put_contents($path, $data);

    if ($result === false) {
        # PHP error (rare for sysfs)
        $e = error_get_last();
        if (!empty($e['message'])) {
            throw new RuntimeException(
                "Failed writing to $path: " . $e['message'],
                1003
            );
        }

        # Sysfs-specific: read back and check
        $after = @file_get_contents($path);
        if ((string)$after !== (string)$data) {
            throw new RuntimeException(
                "Kernel rejected write to sysfs file ($path)",
                1004
            );
        }

        throw new RuntimeException("Unknown write failure: $path", 1005);
    }

    return $result;
}


function action_settings($pciid)
{
    $sriov = json_decode(getSriovInfoJson(), true);
    $sriov_devices_settings = parseVFSettings();
    $vfs = $sriov[$pciid]['vfs'] ?? [];
    $pci_device_diffs = comparePCIData();


    $results = [];

    foreach ($vfs as $vf) {
        $vfpci = $vf['pci'];
        if (array_key_exists($vfpci,$pci_device_diffs)) {
            #Acknowledge PCI addition
            if ($pci_device_diffs[$vfpci]['status'] == "added") acknowledge_PCI($vfpci);
        }
        if (!isset($sriov_devices_settings[$vfpci])) continue;

        $vfio = $sriov_devices_settings[$vfpci]['vfio'];
        $mac  = $sriov_devices_settings[$vfpci]['mac'];

        # Skip if no action needed
        if ($vfio == 0 && $mac == "") continue;

        if ($mac == "") $mac="00:00:00:00:00:00";

        $cmd = "/usr/local/sbin/sriov-vfsettings.sh " .
               escapeshellarg($vfpci) . " " .
               escapeshellarg($vf['vd']) . " " .
               escapeshellarg($vfio) . " " .
               escapeshellarg($mac) . " 2>&1"; # capture stderr too

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        # Clean output: remove blank lines and trim whitespace
        $output = array_filter(array_map('trim', $output));

        if ($ret !== 0) {
            # Only include relevant lines for error reporting
            $results[$vfpci] = [
                'success' => false,
                'error'   => implode("\n", $output) ?: sprintf(_("Unknown error (exit code %s)"),$ret)
            ];
        } else {
            # Success: include minimal details or last few lines
            $results[$vfpci] = [
                'success' => true,
                'details' => _('Applied VF settings')
            ];
        }
    }
    return $results;
}


$type = _var($_POST, 'type');
$pciid = _var($_POST, 'pciid');
$vd = _var($_POST, 'vd');

if (!isset($pciid) || !isset($vd)) {
    json_response(false, _("Missing PCI ID or virtual device"));
    exit;
}



switch ($type) {
    # --------------------------------------------------------
    # SR-IOV enable/disable & VF count changes
    # --------------------------------------------------------
    case "sriov":
        $numvfs     = _var($_POST, 'numvfs');
        $currentvfs = _var($_POST, 'currentvfs');
        $filepath   = "/sys/bus/pci/devices/$pciid/sriov_numvfs";

        if (!is_writable($filepath)) {
            json_response(false, _("Cannot modify VF configuration file") . ": $filepath");
        }

        try {
            #  Disable all VFs
            if ($numvfs == 0) {
                safe_file_put_contents($filepath, 0);
                json_response(true, null, [_("Disabled all VFs")]);
            }

            # — VF count changed
            if ($numvfs != $currentvfs) {

                # Reset VFs and apply new count
                safe_file_put_contents($filepath, 0);
                safe_file_put_contents($filepath, $numvfs);

                # Now apply fresh VF settings
                $results = action_settings($pciid);

                $all_success = array_reduce($results, fn($ok, $r) =>
                    $ok && ($r['success'] ?? false), true
                );

                json_response(true, null, [
                    sprintf(_("Changed VF count to %d"), $numvfs),
                    $results
                ]);
            }

            # No changes
            json_response(true, null, [_("No changes needed")]);

        } catch (Throwable $e) {
            json_response(false, _("Failed to change VF configuration") . ": " . $e->getMessage());
        }


    # --------------------------------------------------------
    # VF driver binding, MAC changes
    # --------------------------------------------------------
    case "sriovsettings":

        $mac         = _var($_POST, 'mac');
        $vfio        = _var($_POST, 'vfio');
        $currentvfio = _var($_POST, 'currentvfio');
        $currentmac  = _var($_POST, 'currentmac');

        # Normalize booleans
        $vfio        = filter_var($vfio,        FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $currentvfio = filter_var($currentvfio, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        # Normalize empty MAC to safe zero-MAC
        if ($mac === "") $mac = "00:00:00:00:00:00";

        $sriov = json_decode(getSriovInfoJson(), true);

        try {
            # MAC changed AND currently bound to VFIO
            if ($currentmac !== $mac) {
                #Check if driver is required to change before actioning the MAC change.
                $driver = ($vfio == 1) ? "vfio-pci" : "original";
                $rtn = setVfMacAddress($pciid, $sriov, $mac, $driver);
                json_response(
                    $rtn['success'] ?? false,
                    $rtn['error']   ?? null,
                    $rtn['details'] ?? _("MAC address updated under VFIO")
                );
            }

            # VFIO binding changed but MAC unchanged
            if ($currentvfio !== $vfio && $currentmac === $mac) {
                $driver = ($vfio == 1) ? "vfio-pci" : "original";
                $rtn = rebindVfDriver($pciid, $sriov, $driver);
                json_response(
                    $rtn['success'] ?? false,
                    $rtn['error']   ?? null,
                    $rtn['details'] ?? _("VFIO binding updated")
                );
            }

            # No changes
            json_response(true, null, [_("No changes detected")]);

        } catch (Throwable $e) {
            json_response(false, _("Error applying VF settings") . ": " . $e->getMessage());
        }

        break;


    # --------------------------------------------------------
    # Check PCI device in use
    # --------------------------------------------------------
    case "inuse":
        $pcitype = _var($_POST, 'pcitype');
        is_pci_inuse($pciid, $pcitype);
        // Note: is_pci_inuse() sends JSON response and exits
        break;

    default:
        json_response(false, _("Unknown request type").": $type");
        break;
}

