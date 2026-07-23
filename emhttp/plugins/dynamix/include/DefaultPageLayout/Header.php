<?php
// This template is installed only with the consolidated <unraid-header>
// component, which owns the full header and its responsive layout.
$headerClass = trim($display['banner'] . ' unraid-consolidated-header');
?>
<div id="header" class="<?=$headerClass?>">
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
            align-items: center;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo img {
            width: 14rem;
            max-width: 100%;
            max-height: 3rem;
            height: auto;
            object-fit: contain;
        }
    </style>
    <unraid-header
        server="<?= $headerServerState->getServerStateJsonForHtmlAttr() ?>"
        show-array-usage="<?= $headerShowArrayUsage ?>"
        header-logo-style="<?= $headerLogoStyle ?>"
    ><a class="unraid-header-boot-logo" href="https://unraid.net" target="_blank" rel="noopener" aria-label="Unraid"><img src="/webGui/images/UN-logotype-gradient.svg" alt="Unraid" /></a></unraid-header>
</div>
