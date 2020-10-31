#!/usr/bin/php
<?
$disks = parse_ini_file("/var/local/emhttp/disks.ini",true);
foreach ($disks as $disk => $prop) {
	if ( is_dir("/mnt/$disk") && $prop['status'] !== "DISK_NP_DSBL" && $prop['status'] !== "DISK_NP") {
		passthru("/usr/local/emhttp/plugins/dynamix/scripts/newperms ".escapeshellarg("/mnt/$disk"));
	}
}
?>