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