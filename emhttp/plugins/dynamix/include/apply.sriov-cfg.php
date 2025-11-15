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
require_once "$docroot/webGui/include/Secure.php";
require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
require_once "$docroot/webGui/include/SriovHelpers.php";
require_once "$docroot/webGui/include/Translations.php";

function json_response($success, $error = null, $details = [])
{
    header('Content-Type: application/json');

    // Normalize details
    if (is_array($details)) {
        if (count($details) === 1) {
            // Collapse single-item arrays to a single value
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
    file_put_contents("/tmp/vfactionjson",$json);
    echo $json;
    exit;
}

function safe_file_put_contents($path, $data)
{
    // Check that the file or its directory is writable
    $dir = is_dir($path) ? $path : dirname($path);
    if (!is_writable($dir)) {
        throw new RuntimeException("Path not writable: $path");
    }

    $result = @file_put_contents($path, $data);
    if ($result === false) {
        $err = error_get_last();
        throw new RuntimeException("Failed to write to $path: " . ($err['message'] ?? 'unknown error'));
    }

    return $result;
}


function action_settings($pciid)
{
    $sriov = json_decode(getSriovInfoJson(), true);
    $sriov_devices_settings = parseVFSettings();
    $vfs = $sriov[$pciid]['vfs'] ?? [];

    $results = [];

    foreach ($vfs as $vf) {
        $vfpci = $vf['pci'];
        if (!isset($sriov_devices_settings[$vfpci])) continue;

        $vfio = $sriov_devices_settings[$vfpci]['vfio'];
        $mac  = $sriov_devices_settings[$vfpci]['mac'];

        // Skip if no action needed
        if ($vfio == 0 && $mac == "") continue;

        if ($mac == "") $mac="00:00:00:00:00:00";

        $cmd = "/usr/local/sbin/sriov-vfsettings.sh " .
               escapeshellarg($vfpci) . " " .
               escapeshellarg($vf['vd']) . " " .
               escapeshellarg($vfio) . " " .
               escapeshellarg($mac) . " 2>&1"; // capture stderr too

        $output = [];
        $ret = 0;
        exec($cmd, $output, $ret);

        // Clean output: remove blank lines and trim whitespace
        $output = array_filter(array_map('trim', $output));

        if ($ret !== 0) {
            // Only include relevant lines for error reporting
            $results[$vfpci] = [
                'success' => false,
                'error'   => implode("\n", $output) ?: sprintf(_("Unknown error (exit code %s)"),$ret)
            ];
        } else {
            // Success: include minimal details or last few lines
            $results[$vfpci] = [
                'success' => true,
                'details' => _('Applied VF settings')
            ];
        }
    }
        file_put_contents("/tmp/vfactionres",json_encode($results) . "\n");
    return $results;
}


$type = _var($_POST, 'type');
$pciid = _var($_POST, 'pciid');
$vd = _var($_POST, 'vd');

if (!isset($pciid) || !isset($vd)) {
    echo json_response(false, _("Missing PCI ID or virtual device"));
    exit;
}



switch ($type) {
    // --------------------------------------------------------
    // SR-IOV enable/disable & VF count changes
    // --------------------------------------------------------
    case "sriov":
        $numvfs     = _var($_POST, 'numvfs');
        $currentvfs = _var($_POST, 'currentvfs');
        $filepath   = "/sys/bus/pci/devices/$pciid/sriov_numvfs";

        if (!is_writable($filepath)) {
            json_response(false, "Cannot modify $filepath");
        }

        try {
            // Disable all VFs
            if ($numvfs == 0) {
                safe_file_put_contents($filepath, 0);
                json_response(true, null, [_("Disabled all VFs")]);
            }

            // Change VF count
            if ($numvfs != $currentvfs) {
                safe_file_put_contents($filepath, 0);
                safe_file_put_contents($filepath, $numvfs);

                $results = action_settings($pciid);

                $all_success = array_reduce($results, fn($ok, $r) => $ok && ($r['success'] ?? false), true);

                safe_file_put_contents(
                    "/tmp/vfactionres2",
                    json_encode(['all_success' => $all_success, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );

                json_response(true, null, [sprintf(_("Changed VF count to %d"), $numvfs), $results]);
            }

            // Reapply VF settings
            safe_file_put_contents($filepath, $numvfs);
            $results = action_settings($pciid);
            json_response(true, null, [_("Reapplied VF settings"), $results]);

        } catch (Throwable $e) {
            // Log internal details for debugging
            @file_put_contents("/tmp/vfaction_error.log", date('c') . " " . $e->getMessage() . "\n", FILE_APPEND);

            json_response(false, _("Failed to change VF count") . ": " . $e->getMessage());
        }
        break;


    // --------------------------------------------------------
    // VF driver binding, MAC changes
    // --------------------------------------------------------
    case "sriovsettings":
        $mac = _var($_POST, 'mac');
        $vfio = _var($_POST, 'vfio');
        $currentvfio = _var($_POST, 'currentvfio');
        $currentmac = _var($_POST, 'currentmac');

        $sriov = json_decode(getSriovInfoJson(), true);
        if ($vfio == "true") $vfio = 1; else $vfio = 0;
        if ($currentvfio == "true") $currentvfio = 1; else $currentvfio = 0;

        try {
            // Case 1: MAC changed and currently under vfio
            if ($currentmac != $mac && $currentvfio == 1) {
                $rtn = setVfMacAddress($pciid, $mac, "vfio-pci");
                echo json_encode($rtn);
                break;
            }

            // Case 2: VFIO binding changed
            if ($currentvfio != $vfio && $currentmac == $mac) {
                $driver = ($vfio == 1) ? "vfio-pci" : "original";
                $rtn = rebindVfDriver($pciid, $sriov, $driver);
                echo json_encode($rtn);
                break;
            }

            // Case 3: MAC changed but still under host driver
            if ($currentmac != $mac && $vfio == 0) {
                $rtn = setVfMacAddress($pciid, $mac, null);
                echo json_encode($rtn);
                break;
            }

            // Nothing changed
            echo json_response(true, null, [_("No changes detected")]);
        } catch (Throwable $e) {
            echo json_response(false, _("Error applying VF settings").": ".$e->getMessage());
        }
        break;

    // --------------------------------------------------------
    // Check PCI device in use
    // --------------------------------------------------------
    case "inuse":
        $pcitype = _var($_POST, 'pcitype');
        $result = is_pci_inuse($pciid, $pcitype);
        echo json_encode($result);
        break;

    default:
        echo json_response(false, _("Unknown request type").": $type");
        break;
}

