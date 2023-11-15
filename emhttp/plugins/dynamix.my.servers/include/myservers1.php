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
?>
<style>
#header {
    z-index: 102 !important;
    display: -webkit-box;
    display: -ms-flexbox;
    display: flex;
    -webkit-box-pack: justify;
    -ms-flex-pack: justify;
    justify-content: space-between;
    -webkit-box-align: center;
    -ms-flex-align: center;
    align-items: center;
}
#header unraid-i18n-host {
    font-size: 16px;
    margin-left: auto;
    height: 100%;
}

/**
 * Tools page, rotate the Downgrade icon to prevent needing to add a new icon to the icon font.
 * The pseudo element is targeted here otherwise the rotation of the span would mess up spacing with the text.
 */
a[href="/Tools/Downgrade"] .icon-update:before {
    display: inline-block; /* required otherwise the rotation won't work */
    rotate: 180deg;
}
</style>
<?php
// Set the path for the local manifest file
$localManifestFile = '/usr/local/emhttp/plugins/dynamix.my.servers/unraid-components/manifest.json';

// Load the local manifest
$localManifest = json_decode(file_get_contents($localManifestFile), true);

$searchText = 'unraid-components.client.mjs';
$fileValue = null;

foreach ($localManifest as $key => $value) {
    if (strpos($key, $searchText) !== false && isset($value["file"])) {
        $fileValue = $value["file"];
        break;
    }
}

if ($fileValue !== null) {
    $prefixedPath = '/plugins/dynamix.my.servers/unraid-components/';
    echo '<script src="' . $prefixedPath . $fileValue . '"></script>';
} else {
    echo '<script>console.error("%cNo matching key containing \'' . $searchText . '\' found.", "font-weight: bold; color: white; background-color: red");</script>';
}
