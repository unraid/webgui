<?php
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

ini_set('memory_limit', '512M'); /* Increase memory limit */

/* Include required files */
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/ColorCoding.php";

/* Get and sort log files by modification time */
$logs = glob($_POST['log'].'*', GLOB_NOSORT);
array_multisort(array_map('filemtime', $logs), SORT_ASC, $logs);

/* Calculate the sum of lines in all log files */
$sum = 0;
foreach ($logs as $log) {
	$sum += count(file($log));
}

$max = $_POST['max'];
$row = 0;

foreach ($logs as $log) {
	$fh = fopen($log, 'r');
	if ($fh === false) {
		continue;
	}

	while (($line = fgets($fh)) !== false) {
		if ($max > 0 && $max < $sum - $row++) {
			continue;
		}
		$span = '<span class="text">';
		foreach ($match as $type) {
			foreach ($type['text'] as $text) {
				if (preg_match("/$text/i", $line)) {
					$span = '<span class="'.$type['class'].'">';
					break 2;
				}
			}
		}
		echo $span, htmlspecialchars($line), "</span>";
	}
	fclose($fh);
}

ini_restore('memory_limit'); /* Restore original memory limit */
?>
