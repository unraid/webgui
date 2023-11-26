<?php
/* Copyright 2005-2023, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
/**
 * RebootDetails class is responsible for detecting the type and version of a system reboot required in the context of an unRAID server.
 *
 * Usage:
 * ```
 * $rebootDetails = new RebootDetails();
 * $rebootType = $rebootDetails->getRebootType();
 * ```
 */
class RebootDetails
{
    /**
     * @var string $rebootType Stores the type of reboot required, which can be 'update', 'downgrade', or 'thirdPartyDriversDownloading'.
     */
    private $rebootType = '';

    /**
     * Constructs a new RebootDetails object and automatically detects the reboot type during initialization.
     */
    public function __construct()
    {
        $this->detectRebootType();
    }

    /**
     * Detects the type of reboot required based on the contents of the unRAID server's README.md file.
     * Sets the $rebootType property accordingly.
     */
    private function detectRebootType()
    {
        $docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

        $rebootReadme = @file_get_contents("$docroot/plugins/unRAIDServer/README.md", false, null, 0, 20) ?: '';
        $rebootDetected = preg_match("/^\*\*(REBOOT REQUIRED|DOWNGRADE)/", $rebootReadme);

        $rebootForDowngrade = $rebootDetected && strpos($rebootReadme, 'DOWNGRADE') !== false;
        $rebootForUpdate = $rebootDetected && strpos($rebootReadme, 'REBOOT REQUIRED') !== false;

        $this->rebootType = $rebootForDowngrade ? 'downgrade' : ($rebootForUpdate ? 'update' : '');

        // Detect if third-party drivers were part of the update process
        $processWaitingThirdPartyDrivers = "inotifywait -q /boot/changes.txt -e move_self,delete_self";
        // Run the ps command to list processes and check if the process is running
        $ps_command = "ps aux | grep -E \"$processWaitingThirdPartyDrivers\" | grep -v \"grep -E\"";
        $output = shell_exec($ps_command) ?? '';
        if ($this->rebootType != '' && strpos($output, $processWaitingThirdPartyDrivers) !== false) {
            $this->rebootType = 'thirdPartyDriversDownloading';
        }
    }

    /**
     * Gets the type of reboot required, which can be 'update', 'downgrade', or 'thirdPartyDriversDownloading'.
     *
     * @return string The type of reboot required.
     */
    public function getRebootType()
    {
        return $this->rebootType;
    }

    /**
     * Detects and retrieves the version information related to the system reboot based on the contents of the '/boot/changes.txt' file.
     *
     * @return string The system version information or 'Not found' if not found, or 'File not found' if the file is not present.
     */
    public function getRebootVersion()
    {
        $file_path = '/boot/changes.txt';

        // Check if the file exists
        if (file_exists($file_path)) {
            // Open the file for reading
            $file = fopen($file_path, 'r');

            // Read the file line by line until we find a line that starts with '# Version'
            while (($line = fgets($file)) !== false) {
                if (strpos($line, '# Version') === 0) {
                    // Use a regular expression to extract the full version string
                    if (preg_match('/# Version\s+(\S+)/', $line, $matches)) {
                        $fullVersion = $matches[1];
                        return $fullVersion;
                    } else {
                        return 'Not found';
                    }
                    break;
                }
            }

            // Close the file
            fclose($file);
        } else {
            return 'File not found';
        }
    }
}
