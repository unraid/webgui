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
 * $rebootType = $rebootDetails->rebootType;
 * ```
 */
class RebootDetails
{
    const CURRENT_CHANGES_TXT_PATH = '/boot/changes.txt';
    const CURRENT_README_RELATIVE_PATH = 'plugins/unRAIDServer/README.md';
    const CURRENT_VERSION_PATH = '/etc/unraid-version';
    const PREVIOUS_BZ_ROOT_PATH = '/boot/previous/bzroot';
    const PREVIOUS_CHANGES_TXT_PATH = '/boot/previous/changes.txt';

    private $currentVersion = '';

    public $rebootType = ''; // 'update', 'downgrade', 'thirdPartyDriversDownloading'
    public $rebootReleaseDate = '';
    public $rebootVersion = '';

    public $previousReleaseDate = '';
    public $previousVersion = '';

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

        /**
         * Read the reboot readme, and see if it says "REBOOT REQUIRED" or "DOWNGRADE"
         * only relying on the README.md file to save reads from the flash drive.
         * because we started allowing downgrades from the account.unraid.net Update OS page, we can't
         * fully rely on the README.md value of being accurate.
         * For instance if on 6.13.0-beta.2.1 then chose to "Downgrade" to 6.13.0-beta.1.10 from the account app
         * the README.md file would still say "REBOOT REQUIRED".
         */
        $rebootReadme = @file_get_contents("$docroot/" . self::CURRENT_README_RELATIVE_PATH, false, null, 0, 20) ?: '';
        $rebootDetected = preg_match("/^\*\*(REBOOT REQUIRED|DOWNGRADE)/", $rebootReadme);
        if (!$rebootDetected) {
            return;
        }
        /**
         * if a reboot is required, then:
         * get current Unraid version from /etc/unraid-version
         * then get the version of the last update from self::CURRENT_CHANGES_TXT_PATH
         * if they're different, then a reboot is required
         * if the version in self::CURRENT_CHANGES_TXT_PATH is less than the current version, then a downgrade is required
         * if the version in self::CURRENT_CHANGES_TXT_PATH is greater than the current version, then an update is required
         */
        $this->setCurrentVersion();
        $this->setRebootDetails();
        if ($this->currentVersion == '' || $this->rebootVersion == '') {
            return; // return to prevent potential incorrect outcome
        }

        $compareVersions = version_compare($this->rebootVersion, $this->currentVersion);
        switch ($compareVersions) {
            case -1:
                $this->setRebootType('downgrade');
                break;
            case 0:
                // we should never get here, but if we do, then no reboot is required and just return
                return;
            case 1:
                $this->setRebootType('update');
                break;
        }

        // Detect if third-party drivers were part of the update process
        $processWaitingThirdPartyDrivers = "inotifywait -q " . self::CURRENT_CHANGES_TXT_PATH . " -e move_self,delete_self";
        // Run the ps command to list processes and check if the process is running
        $ps_command = "ps aux | grep -E \"$processWaitingThirdPartyDrivers\" | grep -v \"grep -E\"";
        $output = shell_exec($ps_command) ?? '';
        if ($this->rebootType != '' && strpos($output, $processWaitingThirdPartyDrivers) !== false) {
            $this->setRebootType('thirdPartyDriversDownloading');
        }
    }

    /**
     * Detects and retrieves the version information related to the system reboot based on the contents of the '/boot/changes.txt' file.
     *
     * @return string The system version information or 'Not found' if not found, or 'File not found' if the file is not present.
     */
    private function readChangesTxt(string $file_path = self::CURRENT_CHANGES_TXT_PATH)
    {
        // Check if the file exists
        if (file_exists($file_path)) {
            exec("head -n4 $file_path", $rows);
            foreach ($rows as $row) {
                $i = stripos($row,'version');
                if ($i !== false) {
                    [$version, $releaseDate] = explode(' ', trim(substr($row, $i+7)));
                    break;
                }
            }

            return [
                'releaseDate' => $releaseDate ?? 'Not found',
                'version' => $version ?? 'Not found',
            ];
        } else {
            return 'File not found';
        }
    }

    /**
     * Sets the current version of the Unraid server for comparison with the reboot version.
     */
    private function setCurrentVersion() {
        // output ex: version="6.13.0-beta.2.1"
        $raw = @file_get_contents(self::CURRENT_VERSION_PATH) ?: '';
        // Regular expression to match the version between the quotes
        $pattern = '/version="([^"]+)"/';
        if (preg_match($pattern, $raw, $matches)) {
            $this->currentVersion = $matches[1];
        }
    }

    private function setRebootDetails()
    {
        $rebootDetails = $this->readChangesTxt();
        $this->rebootReleaseDate = $rebootDetails['releaseDate'];
        $this->rebootVersion = $rebootDetails['version'];
    }

    private function setRebootType($rebootType)
    {
        $this->rebootType = $rebootType;
    }

    /**
     * If self::PREVIOUS_BZ_ROOT_PATH exists, then the user has the option to downgrade to the previous version.
     * Parse the text file /boot/previous/changes.txt to get the version number of the previous version.
     * Then we move some files around and reboot.
     */
    public function setPrevious()
    {
        if (@file_exists(self::PREVIOUS_BZ_ROOT_PATH) && @file_exists(self::PREVIOUS_CHANGES_TXT_PATH)) {
            $parseOutput = $this->readChangesTxt(self::PREVIOUS_CHANGES_TXT_PATH);
            $this->previousVersion = $parseOutput['version'];
            $this->previousReleaseDate = $parseOutput['releaseDate'];
        }
    }
}
