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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
$var = (array)parse_ini_file('state/var.ini');

require_once "$docroot/webGui/include/Wrappers.php";
require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));
require_once "$docroot/plugins/dynamix.my.servers/include/state.php";

$serverState = new ServerState();

header('Content-type: application/json');

echo $serverState->getServerStateJson();
