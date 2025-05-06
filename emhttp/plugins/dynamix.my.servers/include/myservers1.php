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
/* overriding #header .logo svg */
#header .logo .partner-logo svg {
    fill: var(--header-text-primary);
    width: auto;
    height: 28px;
}
</style>
<?php
require_once("$docroot/plugins/dynamix.my.servers/include/web-components-extractor.php");

$wcExtractor = new WebComponentsExtractor();
echo $wcExtractor->getScriptTagHtml();
