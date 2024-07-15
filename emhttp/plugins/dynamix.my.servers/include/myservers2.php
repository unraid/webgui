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
require_once("$docroot/plugins/dynamix.my.servers/include/state.php");
require_once("$docroot/plugins/dynamix.my.servers/include/translations.php");

$serverState = new ServerState();
$wCTranslations = new WebComponentTranslations();
?>
<script>
window.LOCALE_DATA = '<?= $wCTranslations->getTranslationsJson(true) ?>';
/**
 * So we're not needing to modify DefaultLayout with an additional include, we'll add the Modals web component to the bottom of the body.
 */
const i18nHostWebComponent = 'unraid-i18n-host';
const modalsWebComponent = 'unraid-modals';
if (!document.getElementsByTagName(modalsWebComponent).length) {
    const $body = document.getElementsByTagName('body')[0];
    const $i18nHost = document.createElement(i18nHostWebComponent);
    const $modals = document.createElement(modalsWebComponent);
    $body.appendChild($i18nHost);
    $i18nHost.appendChild($modals);
}
</script>

<unraid-i18n-host>
    <unraid-user-profile server="<?= $serverState->getServerStateJsonForHtmlAttr() ?>"></unraid-user-profile>
</unraid-i18n-host>
