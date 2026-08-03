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
        /* Light-DOM fallback: paint the logo + version before <unraid-header>
           mounts so the header does not pop in on page load. The mount engine
           calls replaceChildren(), so this markup is discarded on upgrade.

           This mirrors the mounted `.unraid-header-shell` GRID rather than
           pinning the logo at measured pixel offsets. Hard-coded offsets cannot
           work: the logo's position depends on the meta row's height, which
           varies by theme (sidebar themes add the array-usage bar), by the root
           font size, and by the breakpoint. Reproducing the grid — including an
           invisible meta row with the same content boxes — lets the browser
           compute the same position the mounted component will, so the two agree
           by construction. Sizes use the same rem values as the mounted
           component so both scale together with the root font size. */
        #header.unraid-consolidated-header .unraid-header-boot {
            display: grid;
            column-gap: 0.75rem;
            align-items: stretch;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-rows: auto minmax(max-content, 1fr) auto;
            height: 100%;
        }
        /* Invisible stand-in for the mounted meta row (the uptime / registration
           line). It only has to occupy the same height, since that is what
           pushes the logo down. Deliberately does NOT reserve space for the
           array-usage bar: that renders only once array data resolves
           (`v-if="hasData"` in ArrayUsage.vue), so at boot the mounted meta row
           is this single line even on sidebar themes.

           Sizes here are px, not rem, and intentionally so: the webGUI light DOM
           roots at 62.5% (1rem = 10px) while the mounted component's Tailwind
           scale is px-based (text-xs -> 12px, gap-y-2 -> 8px). Matching the
           mounted pixel sizes is what keeps the two in step. */
        #header.unraid-consolidated-header .unraid-header-boot-meta {
            grid-column: 2;
            grid-row: 1;
            align-self: start;
            justify-self: end;
            visibility: hidden;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            font-size: 12px;
            line-height: 16px;
        }
        #header.unraid-consolidated-header .unraid-header-boot-logo {
            grid-column: 1;
            grid-row: 1 / -1;
            align-self: center;
            justify-self: start;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            /* Layout gap matches the mounted logo block (gap-y-2 -> 8px). The
               extra visual space under the logo comes from the version's
               relative offset below, exactly as in the mounted component, so the
               block's LAYOUT height matches and centering agrees. */
            gap: 8px;
            min-width: 0;
        }
        /* Scope to the logo link's svg only — a bare `.unraid-header-boot-logo svg`
           descendant selector also matches the version info icon below and would
           stretch it to the logo width. */
        #header.unraid-consolidated-header .unraid-header-boot-logo a svg {
            display: block;
            width: 14rem;
            max-width: 100%;
            max-height: 3rem;
            height: auto;
        }
        /* Theme-adaptive logo: render the wordmark in the header text color like
           the mounted HeaderLogo, instead of the gradient. */
        #header.unraid-consolidated-header .unraid-header-boot-logo-theme {
            color: var(--header-text-primary);
        }
        /* Boot version placeholder mirroring the mounted HeaderVersion (info icon
           + version text) so the group height matches and there is no size flash.
           Uses the same rem sizing as the mounted text-xs / xs:text-sm so both
           scale together with the root font size. */
        #header.unraid-consolidated-header .unraid-header-boot-version {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            /* Mirrors the mounted `.uh-version` relative offset (0.5rem against
               the 10px light-DOM root -> 5px): shifts the version down visually
               without adding layout height, so the boot block's height still
               matches the mounted block. */
            position: relative;
            top: 5px;
            /* Neutral placeholder gray. The real per-theme secondary color
               (--header-text-secondary: #999 on white/black, #606e7f on
               gray/azure) only exists in the mounted .unapi scope, so a fixed
               mid-gray reads fine on both light and dark header backgrounds and
               makes the swap-in a gentle shift instead of a dark->gray flash. */
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
        @media (min-width: 30rem) {
            #header.unraid-consolidated-header .unraid-header-boot-logo a svg {
                width: 16rem;
            }
            #header.unraid-consolidated-header .unraid-header-boot-version {
                font-size: 14px;
            }
        }
        /* Below sm the mounted meta row spans the full width as a top strip and
           the logo block moves to the middle grid row — mirror both so the logo
           lands in the same band. */
        @media (max-width: 639.98px) {
            #header.unraid-consolidated-header .unraid-header-boot-meta {
                grid-column: 1 / -1;
                justify-self: stretch;
                align-items: flex-start;
            }
            #header.unraid-consolidated-header .unraid-header-boot-logo {
                grid-row: 2;
            }
        }
    </style>
    <unraid-header
        server="<?= $headerServerState->getServerStateJsonForHtmlAttr() ?>"
        show-array-usage="<?= $headerShowArrayUsage ?>"
        header-logo-style="<?= $headerLogoStyle ?>"
    ><span class="unraid-header-boot"><span class="unraid-header-boot-meta" aria-hidden="true"><span>&nbsp;</span></span><span class="unraid-header-boot-logo"><a href="https://unraid.net" target="_blank" rel="noopener" aria-label="Unraid"><?php if ($headerLogoStyle === 'theme'): ?><svg class="unraid-header-boot-logo-theme" xmlns="http://www.w3.org/2000/svg" width="140" height="24.6" viewBox="0 0 222.36 39.04" aria-hidden="true"><path fill="currentColor" d="<?=$headerBootLogoPath?>"/></svg><?php else: ?><svg xmlns="http://www.w3.org/2000/svg" width="140" height="24.6" viewBox="0 0 222.36 39.04" aria-hidden="true"><defs><linearGradient id="unraid-header-boot-logo-gradient" x1="47.53" y1="79.1" x2="170.71" y2="-44.08" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#e32929"/><stop offset="1" stop-color="#ff8d30"/></linearGradient></defs><path fill="url(#unraid-header-boot-logo-gradient)" d="<?=$headerBootLogoPath?>"/></svg><?php endif; ?></a><span class="unraid-header-boot-version" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd"/></svg><?= htmlspecialchars($headerBootVersion, ENT_QUOTES) ?></span></span></span></unraid-header>
</div>
