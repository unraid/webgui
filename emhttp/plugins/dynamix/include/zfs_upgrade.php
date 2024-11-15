<?PHP
/* Copyright 2024, Lime Technology
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
/* zfs_upgrade.php
 * This script upgrades a ZFS pool passed as an argument.
 */

$docroot = $_SERVER['DOCUMENT_ROOT'];
require_once "$docroot/webGui/include/Wrappers.php";
 
/* Check to see if a pool has already been upgraded. */
function is_upgraded_ZFS_pool($pool_name) {

	/* See if the pool is aready upgraded. */
	$upgrade	= trim(shell_exec("/usr/sbin/zpool status ".escapeshellarg($pool_name)." | /usr/bin/grep 'Enable all features using.'") ?? "");

	return ($upgrade ? false : true);
}

if (isset($_POST['name'])) {
	$poolName = $_POST['name'];

	if (! is_upgraded_ZFS_pool($poolName)) {
		/* Execute the zpool upgrade command */
		$command = "/usr/sbin/zpool upgrade ".escapeshellarg($poolName)." 2>/dev/null";
		exec($command, $output, $return_var);

		/* Check if the command was successful */
		if ($return_var === 0) {
			my_logger("ZFS pool '$poolName' upgraded successfully.");
		} else {
			my_logger("Failed to upgrade ZFS pool '$poolName'.");
		}
	} else {
		my_logger("ZFS pool '$poolName' is already upgraded.");
	}
} else {
	my_logger("No pool name provided.");
}

?>
