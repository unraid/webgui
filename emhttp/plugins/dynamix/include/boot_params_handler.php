<?php
/**
 * Boot Parameters Handler
 * PHP backend for managing boot parameters through AJAX calls
 */

// Include webGUI session handling for CSRF validation 
$docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Wrappers.php";

// CSRF protection is enforced by Wrappers.php on all POST requests

$operation = $_POST['operation'] ?? '';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (empty($operation)) {
    echo json_encode(['error' => 'No operation specified']);
    exit;
}

/**
 * Execute shell script with environment variables
 */
function executeShellScript($env) {
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w")
    );

    $process = proc_open(
        '/usr/local/emhttp/plugins/dynamix/scripts/manage_boot_params.sh',
        $descriptorspec,
        $pipes,
        null,
        $env
    );

    if (is_resource($process)) {
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode === 0) {
            return ['success' => true, 'output' => $output];
        } else {
            return ['success' => false, 'error' => $error ?: "Script execution failed"];
        }
    } else {
        return ['success' => false, 'error' => 'Failed to start script process'];
    }
}

/**
 * Handle different operations
 */
switch ($operation) {
    case 'read_config':
        // Read current configuration
        $env = ['OPERATION' => 'read_config'];
        $result = executeShellScript($env);

        if ($result['success']) {
            // Parse JSON output from shell script
            $config = json_decode($result['output'], true);

            // Validate JSON parsing succeeded
            if ($config === null) {
                echo json_encode(['error' => 'Shell script returned invalid JSON. Please check configuration.']);
                break;
            }

            // Defensive check: Comments structure must be object (not array) for frontend JavaScript compatibility
            if (isset($config['custom_params_comments']) &&
                is_array($config['custom_params_comments']) &&
                empty($config['custom_params_comments'])) {
                $config['custom_params_comments'] = new stdClass();
            }

            // Read the full syslinux.cfg file to display
            $syslinux_file = '/boot/syslinux/syslinux.cfg';
            if (file_exists($syslinux_file)) {
                $config['full_config'] = file_get_contents($syslinux_file);
            } else {
                $config['full_config'] = '';
            }

            echo json_encode($config);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'write_config':
        // Write new configuration
        $env = [
            'OPERATION' => 'write_config',
            'NVME_DISABLE' => $_POST['nvme_disable'] ?? '0',
            'ACS_OVERRIDE' => $_POST['acs_override'] ?? '',
            'VFIO_UNSAFE' => $_POST['vfio_unsafe'] ?? '0',
            'EFIFB_OFF' => $_POST['efifb_off'] ?? '0',
            'VESAFB_OFF' => $_POST['vesafb_off'] ?? '0',
            'SIMPLEFB_OFF' => $_POST['simplefb_off'] ?? '0',
            'SYSFB_BLACKLIST' => $_POST['sysfb_blacklist'] ?? '0',
            'ACPI_LAX' => $_POST['acpi_lax'] ?? '0',
            'GHES_DISABLE' => $_POST['ghes_disable'] ?? '0',
            'USB_AUTOSUSPEND' => $_POST['usb_autosuspend'] ?? '0',
            'PCIE_ASPM_OFF' => $_POST['pcie_aspm_off'] ?? '0',
            'PCIE_PORT_PM_OFF' => $_POST['pcie_port_pm_off'] ?? '0',
            'PCI_NOAER' => $_POST['pci_noaer'] ?? '0',
            'PCI_REALLOC' => $_POST['pci_realloc'] ?? '0',
            'CUSTOM_PARAMS' => $_POST['custom_params'] ?? '',
            'CUSTOM_PARAMS_COMMENTS' => $_POST['custom_params_comments'] ?? '',
            'DEFAULT_BOOT_ENTRY' => $_POST['default_boot_entry'] ?? '',
            'TIMEOUT' => $_POST['timeout'] ?? '50',
            // Per-label application toggles
            'APPLY_TO_UNRAID_OS' => $_POST['apply_to_unraid_os'] ?? '1',
            'APPLY_TO_GUI_MODE' => $_POST['apply_to_gui_mode'] ?? '1',
            'APPLY_TO_SAFE_MODE' => $_POST['apply_to_safe_mode'] ?? '0',
            'APPLY_TO_GUI_SAFE_MODE' => $_POST['apply_to_gui_safe_mode'] ?? '0',
            // Framebuffer exclusion flag for GUI mode entries
            'EXCLUDE_FRAMEBUFFER_FROM_GUI' => $_POST['exclude_framebuffer_from_gui'] ?? '0'
        ];

        $result = executeShellScript($env);

        if ($result['success']) {
            // Parse output to extract success message and config
            $output = $result['output'];

            if (strpos($output, 'SUCCESS') !== false) {
                // Extract config between markers
                if (preg_match('/---CONFIG-START---(.*?)---CONFIG-END---/s', $output, $matches)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Configuration updated successfully',
                        'config' => trim($matches[1])
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Configuration updated successfully',
                        'config' => ''
                    ]);
                }
            } else {
                echo json_encode(['error' => 'Unexpected output from script']);
            }
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'list_backups':
        // List available backups
        $env = ['OPERATION' => 'list_backups'];
        $result = executeShellScript($env);

        if ($result['success']) {
            // Output is already JSON from shell script
            echo $result['output'];
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'restore_backup':
        // Restore from backup
        $backup_filename = $_POST['backup_filename'] ?? '';

        if (empty($backup_filename)) {
            echo json_encode(['error' => 'No backup filename specified']);
            exit;
        }

        // Sanitize filename
        $backup_filename = basename($backup_filename);
        // Format: syslinux.cfg.bak.YYYY-MMM-DD_HH-MM-SS
        if (!preg_match('/^syslinux\.cfg\.bak\.[0-9]{4}-[A-Za-z]{3}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}$/i', $backup_filename)) {
            echo json_encode(['error' => 'Invalid backup filename format']);
            exit;
        }

        $env = [
            'OPERATION' => 'restore_backup',
            'BACKUP_FILENAME' => $backup_filename
        ];

        $result = executeShellScript($env);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['output']
            ]);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'delete_all_backups':
        // Delete all backup files
        $env = ['OPERATION' => 'delete_all_backups'];
        $result = executeShellScript($env);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'All backups deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'reset_default':
        // Reset to default (all parameters off, apply to ALL boot entries including safe mode)
        $env = [
            'OPERATION' => 'write_config',
            'NVME_DISABLE' => '0',
            'ACS_OVERRIDE' => '',
            'VFIO_UNSAFE' => '0',
            'EFIFB_OFF' => '0',
            'VESAFB_OFF' => '0',
            'SIMPLEFB_OFF' => '0',
            'SYSFB_BLACKLIST' => '0',
            'ACPI_LAX' => '0',
            'GHES_DISABLE' => '0',
            'USB_AUTOSUSPEND' => '0',
            'PCIE_ASPM_OFF' => '0',
            'PCIE_PORT_PM_OFF' => '0',
            'PCI_NOAER' => '0',
            'PCI_REALLOC' => '0',
            'CUSTOM_PARAMS' => '',
            'CUSTOM_PARAMS_COMMENTS' => '{}',
            'DEFAULT_BOOT_ENTRY' => '',
            'TIMEOUT' => '50',  // Reset to default 5 seconds
            // Per-label application toggles - apply to ALL entries for reset
            'APPLY_TO_UNRAID_OS' => '1',
            'APPLY_TO_GUI_MODE' => '1',
            'APPLY_TO_SAFE_MODE' => '1',
            'APPLY_TO_GUI_SAFE_MODE' => '1',
            'EXCLUDE_FRAMEBUFFER_FROM_GUI' => '0'
        ];

        $result = executeShellScript($env);

        if ($result['success']) {
            $output = $result['output'];

            if (strpos($output, 'SUCCESS') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reset to default configuration'
                ]);
            } else {
                echo json_encode(['error' => 'Unexpected output from script']);
            }
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'write_raw_config':
        // Write raw syslinux.cfg content directly
        $raw_config = $_POST['raw_config'] ?? '';

        if (empty($raw_config)) {
            echo json_encode(['error' => 'No configuration content provided']);
            exit;
        }

        // Validate size (100KB limit)
        if (strlen($raw_config) > 100000) {
            echo json_encode(['error' => 'Configuration file too large (max 100KB)']);
            exit;
        }

        // Basic structure validation - must contain required labels
        if (strpos($raw_config, 'label Unraid OS') === false) {
            echo json_encode(['error' => 'Invalid configuration: Missing required label "Unraid OS"']);
            exit;
        }

        if (strpos($raw_config, 'initrd=') === false) {
            echo json_encode(['error' => 'Invalid configuration: Missing required "initrd=" parameter']);
            exit;
        }

        // Pass to shell script
        $env = [
            'OPERATION' => 'write_raw_config',
            'RAW_CONFIG' => $raw_config
        ];

        $result = executeShellScript($env);

        if ($result['success']) {
            $output = $result['output'];

            if (strpos($output, 'SUCCESS') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Raw configuration saved successfully'
                ]);
            } else {
                echo json_encode(['error' => 'Unexpected output from script']);
            }
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'detect_boot_mode':
        // Detect boot mode using kernel-provided directory (/sys/firmware/efi exists only for UEFI boot)
        $boot_mode = is_dir('/sys/firmware/efi') ? 'uefi' : 'legacy';

        // Get current framebuffer settings from syslinux.cfg
        $syslinux_file = '/boot/syslinux/syslinux.cfg';
        $current_efifb = '0';
        $current_vesafb = '0';

        if (file_exists($syslinux_file)) {
            $config = file_get_contents($syslinux_file);
            $current_efifb = (strpos($config, 'video=efifb:off') !== false) ? '1' : '0';
            $current_vesafb = (strpos($config, 'video=vesafb:off') !== false) ? '1' : '0';
        }

        echo json_encode([
            'boot_mode' => $boot_mode,
            'current_config' => [
                'efifb' => $current_efifb,
                'vesafb' => $current_vesafb
            ]
        ]);
        break;

    case 'check_hardware':
        // Check if hardware has changed
        $env = ['OPERATION' => 'check_hardware'];
        $result = executeShellScript($env);

        if ($result['success']) {
            // Parse JSON output from shell script
            $hw_status = json_decode($result['output'], true);
            echo json_encode($hw_status);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    case 'acknowledge_hardware_change':
        // Update stored hardware IDs to current system
        $env = ['OPERATION' => 'update_hardware'];
        $result = executeShellScript($env);

        if ($result['success']) {
            // Parse JSON output from shell script
            $response = json_decode($result['output'], true);
            echo json_encode($response);
        } else {
            echo json_encode(['error' => $result['error']]);
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown operation: ' . $operation]);
        break;
}
?>
