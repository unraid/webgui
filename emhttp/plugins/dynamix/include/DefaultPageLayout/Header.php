<?php
// Unraid 7.3+ ships a single consolidated header web component (<unraid-header>)
// that owns the whole header and its responsive layout. Older releases keep the
// legacy multi-component header (logo/version + user profile) as a fallback.
$headerOsVersion = @parse_ini_file('/etc/unraid-version')['version'] ?? '0';
$headerUseConsolidated = version_compare($headerOsVersion, '7.3', '>=');
?>
<div id="header" class="<?=$display['banner']?>">
<?php if ($headerUseConsolidated): ?>
    <?php
        require_once "$docroot/plugins/dynamix.my.servers/include/state.php";
        $headerServerState = new ServerState();
    ?>
    <script>
    window.LOCALE = <?= json_encode($_SESSION['locale'] ?? 'en_US', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <unraid-header server="<?= $headerServerState->getServerStateJsonForHtmlAttr() ?>"></unraid-header>
    <? if ($display['usage'] && $themeHelper->isSidebarTheme()): ?>
        <span id='array-usage-sidenav'></span>
    <? endif; ?>
<?php else: ?>
    <unraid-header-os-version></unraid-header-os-version>
    <? if ($display['usage'] && $themeHelper->isSidebarTheme()): ?>
        <span id='array-usage-sidenav'></span>
    <? endif; ?>
    <?include "$docroot/plugins/dynamix.my.servers/include/myservers2.php"?>
<?php endif; ?>
</div>
