<?php
// Unraid 7.4+ ships a single consolidated header web component (<unraid-header>)
// that owns the whole header and its responsive layout. Older releases keep the
// legacy multi-component header (logo/version + user profile) as a fallback.
$headerOsVersion = @parse_ini_file('/etc/unraid-version')['version'] ?? '0';
$headerUseConsolidated = version_compare($headerOsVersion, '7.4', '>=');
$headerClass = trim($display['banner'] . ($headerUseConsolidated ? ' unraid-consolidated-header' : ''));
?>
<div id="header" class="<?=$headerClass?>">
<?php if ($headerUseConsolidated): ?>
    <?php
        require_once "$docroot/plugins/dynamix.my.servers/include/state.php";
        $headerServerState = new ServerState();
    ?>
    <script>
    window.LOCALE = <?= json_encode($_SESSION['locale'] ?? 'en_US', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <?php
        // The consolidated header owns the array-usage bar for sidebar themes,
        // where the legacy #array-usage-sidenav widget used to be injected.
        $headerShowArrayUsage = ($display['usage'] && $themeHelper->isSidebarTheme()) ? 'true' : 'false';
        $headerLogoStyle = (($display['headerLogo'] ?? '') === 'theme') ? 'theme' : '';
    ?>
    <style>
        /* Light-DOM fallback: paint the logo before <unraid-header> mounts so the
           header does not pop in on page load. #header is already
           display:flex; align-items:center, so the logo lands left + vertically
           centered where the mounted logo sits. The mount engine calls
           replaceChildren(), so this markup is discarded when the component
           upgrades. */
        #header.unraid-consolidated-header .unraid-header-boot-logo {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 0.8rem;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo svg {
            display: block;
            width: 14rem;
            max-width: 100%;
            max-height: 3rem;
            height: auto;
        }
        /* Invisible stub that reproduces the mounted version pill so the boot
           logo sits at the same top offset as the mounted logo+version group
           instead of jumping up on mount. Only at sm+, where the mounted layout
           groups them; below sm the mounted logo is centered, matching the
           stub-less centered boot logo. */
        #header.unraid-consolidated-header .unraid-header-boot-version {
            display: none;
        }
        @media (min-width: 30rem) {
            #header.unraid-consolidated-header .unraid-header-boot-logo svg {
                width: 16rem;
            }
        }
        @media (min-width: 640px) {
            #header.unraid-consolidated-header .unraid-header-boot-version {
                display: block;
                height: 1.4rem;
            }
        }
        /* Below sm the mounted host is stretched and the logo is centered in the
           space beneath the uptime strip. Fill the host and center the boot logo
           with the same top offset so it lands where the mounted logo does
           instead of pinning to the top. */
        @media (max-width: 639.98px) {
            #header.unraid-consolidated-header .unraid-header-boot-logo {
                height: 100%;
                box-sizing: border-box;
                justify-content: center;
                padding-top: 2rem;
            }
        }
    </style>
    <unraid-header
        server="<?= $headerServerState->getServerStateJsonForHtmlAttr() ?>"
        show-array-usage="<?= $headerShowArrayUsage ?>"
        header-logo-style="<?= $headerLogoStyle ?>"
    ><span class="unraid-header-boot-logo"><a href="https://unraid.net" target="_blank" rel="noopener" aria-label="Unraid"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 222.36 39.04" aria-hidden="true"><defs><linearGradient id="unraid-header-boot-logo-gradient" x1="47.53" y1="79.1" x2="170.71" y2="-44.08" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#e32929"/><stop offset="1" stop-color="#ff8d30"/></linearGradient></defs><path fill="url(#unraid-header-boot-logo-gradient)" d="M146.7,29.47H135l-3,9h-6.49L138.93,0h8l13.41,38.49h-7.09L142.62,6.93l-5.83,16.88h8ZM29.69,0V25.4c0,8.91-5.77,13.64-14.9,13.64S0,34.31,0,25.4V0H6.54V25.4c0,5.17,3.19,7.92,8.25,7.92s8.36-2.75,8.36-7.92V0ZM50.86,12v26.5H44.31V0h6.11l17,26.5V0H74V38.49H67.9ZM171.29,0h6.54V38.49h-6.54Zm51.07,24.69c0,9-5.88,13.8-15.17,13.8H192.67V0H207.3c9.18,0,15.06,4.78,15.06,13.8ZM215.82,13.8c0-5.28-3.3-8.14-8.52-8.14h-8.08V32.77h8c5.33,0,8.63-2.8,8.63-8.08ZM108.31,23.92c4.34-1.6,6.93-5.28,6.93-11.55C115.24,3.68,110.18,0,102.48,0H88.84V38.49h6.55V5.66h6.87c3.8,0,6.21,1.82,6.21,6.71s-2.41,6.76-6.21,6.76H98.88l9.21,19.36h7.53Z"/></svg></a><span class="unraid-header-boot-version" aria-hidden="true"></span></span></unraid-header>
<?php else: ?>
    <unraid-header-os-version></unraid-header-os-version>
    <? if ($display['usage'] && $themeHelper->isSidebarTheme()): ?>
        <span id='array-usage-sidenav'></span>
    <? endif; ?>
    <?include "$docroot/plugins/dynamix.my.servers/include/myservers2.php"?>
<?php endif; ?>
</div>
