<?php
// This webGUI ships a single consolidated header web component (<unraid-header>)
// that owns the whole header and its responsive layout.
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
        // OS version for the boot version placeholder, so the light-DOM logo+version
        // group matches the mounted logo+version group (mounted shows the same info
        // icon + version) and the logo does not jump on upgrade.
        $headerBootVersion = @parse_ini_file('/etc/unraid-version')['version'] ?? '';
        // Shared Unraid wordmark path (same as the mounted HeaderLogo). Rendered
        // with the gradient by default, or in currentColor when the theme-adaptive
        // logo option is selected, matching the mounted logo.
        $headerBootLogoPath = 'M146.7,29.47H135l-3,9h-6.49L138.93,0h8l13.41,38.49h-7.09L142.62,6.93l-5.83,16.88h8ZM29.69,0V25.4c0,8.91-5.77,13.64-14.9,13.64S0,34.31,0,25.4V0H6.54V25.4c0,5.17,3.19,7.92,8.25,7.92s8.36-2.75,8.36-7.92V0ZM50.86,12v26.5H44.31V0h6.11l17,26.5V0H74V38.49H67.9ZM171.29,0h6.54V38.49h-6.54Zm51.07,24.69c0,9-5.88,13.8-15.17,13.8H192.67V0H207.3c9.18,0,15.06,4.78,15.06,13.8ZM215.82,13.8c0-5.28-3.3-8.14-8.52-8.14h-8.08V32.77h8c5.33,0,8.63-2.8,8.63-8.08ZM108.31,23.92c4.34-1.6,6.93-5.28,6.93-11.55C115.24,3.68,110.18,0,102.48,0H88.84V38.49h6.55V5.66h6.87c3.8,0,6.21,1.82,6.21,6.71s-2.41,6.76-6.21,6.76H98.88l9.21,19.36h7.53Z';
    ?>
    <style>
        /* Match the mounted grid's default tracks before the custom element upgrades.
           Minimum tracks reserve controls and async usage; content can still grow. */
        #header.unraid-consolidated-header .unraid-header-boot {
            display: grid;
            column-gap: 0.75rem;
            align-items: stretch;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-rows: minmax(18px, auto) minmax(48px, 1fr) auto;
        }
        #header.unraid-consolidated-header .unraid-header-boot-meta {
            grid-column: 2;
            grid-row: 1;
            visibility: hidden;
            font-size: 12px;
            line-height: 16px;
        }
        #header.unraid-consolidated-header .unraid-header-boot-name {
            display: none;
        }
        #header.unraid-consolidated-header .unraid-header-boot-nav {
            grid-column: 2;
            grid-row: 2;
            visibility: hidden;
        }
        #header.unraid-consolidated-header .unraid-header-boot-usage {
            grid-column: 2;
            grid-row: 3;
            height: 12px;
            visibility: hidden;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo {
            grid-column: 1;
            grid-row: 1 / -1;
            align-self: center;
            justify-self: start;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            min-width: 0;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo a {
            display: flex;
            min-width: 0;
            max-width: 100%;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo a svg {
            display: block;
            width: 140px;
            max-width: 100%;
            max-height: 30px;
            height: auto;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo-theme {
            color: var(--header-text-primary);
        }
        #header.unraid-consolidated-header .unraid-header-boot-version {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            position: relative;
            top: 5px;
            color: #999;
            font-weight: 600;
            line-height: 1;
            font-size: 12px;
            white-space: nowrap;
        }
        #header.unraid-consolidated-header .unraid-header-boot-version svg {
            width: 12px;
            height: 12px;
            flex-shrink: 0;
        }
        @media (min-width: 480px) {
            #header.unraid-consolidated-header .unraid-header-boot-logo a svg {
                width: 160px;
            }
            #header.unraid-consolidated-header .unraid-header-boot-version {
                font-size: 14px;
            }
        }
        @media (max-width: 639.98px) {
            #header.unraid-consolidated-header .unraid-header-boot {
                grid-template-columns: fit-content(60vw) minmax(0, 1fr);
                grid-template-rows: minmax(18px, auto) minmax(44px, auto) minmax(21px, auto);
                gap: 4px;
            }
            #header.unraid-consolidated-header .unraid-header-boot-meta {
                grid-column: 1 / -1;
            }
            #header.unraid-consolidated-header .unraid-header-boot-logo {
                display: contents;
            }
            #header.unraid-consolidated-header .unraid-header-boot-logo a {
                grid-column: 1;
                grid-row: 2;
                align-self: center;
            }
            #header.unraid-consolidated-header .unraid-header-boot-version {
                grid-column: 1;
                grid-row: 3 / span 2;
                align-self: baseline;
                top: auto;
            }
            #header.unraid-consolidated-header .unraid-header-boot-name {
                display: block;
                grid-column: 2;
                grid-row: 3;
                align-self: baseline;
                visibility: hidden;
                font-size: 14px;
                line-height: 21px;
            }
            #header.unraid-consolidated-header .unraid-header-boot-usage {
                grid-row: 4;
            }
        }
    </style>
    <unraid-header
        server="<?= $headerServerState->getServerStateJsonForHtmlAttr() ?>"
        show-array-usage="<?= $headerShowArrayUsage ?>"
        header-logo-style="<?= $headerLogoStyle ?>"
    ><span class="unraid-header-boot"><span class="unraid-header-boot-meta" aria-hidden="true"><span>&nbsp;</span></span><span class="unraid-header-boot-logo"><a href="https://unraid.net" target="_blank" rel="noopener" aria-label="Unraid"><?php if ($headerLogoStyle === 'theme'): ?><svg class="unraid-header-boot-logo-theme" xmlns="http://www.w3.org/2000/svg" width="222.36" height="39.04" viewBox="0 0 222.36 39.04" aria-hidden="true"><path fill="currentColor" d="<?=$headerBootLogoPath?>"/></svg><?php else: ?><svg xmlns="http://www.w3.org/2000/svg" width="222.36" height="39.04" viewBox="0 0 222.36 39.04" aria-hidden="true"><defs><linearGradient id="unraid-header-boot-logo-gradient" x1="47.53" y1="79.1" x2="170.71" y2="-44.08" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#e32929"/><stop offset="1" stop-color="#ff8d30"/></linearGradient></defs><path fill="url(#unraid-header-boot-logo-gradient)" d="<?=$headerBootLogoPath?>"/></svg><?php endif; ?></a><span class="unraid-header-boot-version" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd"/></svg><?= htmlspecialchars($headerBootVersion, ENT_QUOTES) ?></span></span><span class="unraid-header-boot-nav" aria-hidden="true"></span><span class="unraid-header-boot-name" aria-hidden="true">&nbsp;</span><?php if ($headerShowArrayUsage === 'true'): ?><span class="unraid-header-boot-usage" aria-hidden="true"></span><?php endif; ?></span></unraid-header>
</div>
