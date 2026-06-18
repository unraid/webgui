<div id="header" class="<?=$display['banner']?>">
    <unraid-header-os-version></unraid-header-os-version>
    <? if ($display['usage'] && $themeHelper->isSidebarTheme()): ?>
        <span id='array-usage-sidenav'></span>
    <? endif; ?>
    <?include "$docroot/plugins/dynamix.my.servers/include/myservers2.php"?>
</div>
