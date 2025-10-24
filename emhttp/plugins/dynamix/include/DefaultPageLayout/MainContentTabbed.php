<?
/**
 * Tabbed content template for the Unraid web interface.
 * Accessible, modern, and decoupled from non-tabbed logic.
 */
?>

<div id="displaybox">
    <nav class="tabs" role="tablist" aria-label="Page Tabs">
        <div class="tabs-container">
            <? $i = 0; ?>
            <? foreach ($pages as $page): ?>
                <? if (!isset($page['Title'])) continue; ?>
                <? $title = processTitle($page['Title']); ?>
                <? $tabId = "tab" . ($i+1); ?>
                <button
                    role="tab"
                    id="<?= $tabId ?>"
                    aria-controls="<?= $tabId ?>-panel"
                    tabindex="<?= $i === 0 ? '0' : '-1' ?>"
                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    <? if ( isset($page['Focus']) ): ?>
                        data-focus="<?= htmlspecialchars($page['Focus'], ENT_QUOTES, 'UTF-8') ?>"
                    <? endif; ?>
                >
                    <?= tab_title($title, $page['root'], _var($page, 'Tag', false)) ?>
                </button>
            <? $i++; ?>
            <? endforeach; ?>
        </div>
    </nav>

    <?
        $i = 0;
        $skipIndexIncrement = false; // used if the page has no title but has text
    ?>
    <? foreach ($pages as $page): ?>
        <? 
            if (!isset($page['Title'])) {
                /**
                 * If the page has no title but has text, we need to eval the content to ensure the page is "rendered".
                 * For instance Share.page is a parent page that has no title but has text aka content.
                 * The content consists of some PHP and JS code that needs to be evaluated to ensure it's rendered.
                 * We'll use the $skipIndexIncrement to skip the index increment. To ensure the tabs are properly rendered.
                 */
                if (isset($page['text'])) {
                    $skipIndexIncrement = true;
                    annotate($page['file']);
                    $evalContent = '?>'.generateContent($page);
                    $evalFile = $page['file'];
                    if ( filter_var($page['Eval']??false, FILTER_VALIDATE_BOOLEAN) ) {
                        eval($evalContent);
                    } else {    
                        include "$docroot/webGui/include/DefaultPageLayout/evalContent.php";
                    }
                }
                continue;
            }
            $title = processTitle($page['Title']);
            $tabId = "tab" . ($i+1);
            annotate($page['file']);
        ?>
        <section
            id="<?= $tabId ?>-panel"
            role="tabpanel"
            aria-labelledby="<?= $tabId ?>"
            class="tab-content content"
            tabindex="0"
        >
            <?= generatePanels($page, $path, $defaultIcon, $docroot) ?>

            <? 
            $evalContent = '?>'.generateContent($page);
            $evalFile = $page['file'];
            if ( filter_var($page['Eval']??false, FILTER_VALIDATE_BOOLEAN) ) {
                eval($evalContent);
            } else {
                include "$docroot/webGui/include/DefaultPageLayout/evalContent.php";
            }
            ?>
        </section>
        <?
            if ($skipIndexIncrement) {
                $skipIndexIncrement = false;
            } else {
                $i++;
            }
        ?>
    <? endforeach; ?>
</div>

<script>
const tabs = document.querySelectorAll('.tabs [role="tab"]');
const panels = document.querySelectorAll('[role="tabpanel"]');

// Hide all panels by default (avoid flash)
panels.forEach(panel => panel.style.display = 'none');

// Figure out which cookie to use (matches settab logic)
let cookieName = 'tab';
<?
// Emulate settab's switch logic for cookie name
switch ($myPage['name']) {
    case 'Main':
        echo "cookieName = 'tab';\n";
        break;
    case 'Cache':
    case 'Data':
    case 'Device':
    case 'Flash':
    case 'Parity':
        echo "cookieName = 'one';\n";
        break;
    default:
        echo "cookieName = 'one';\n";
        break;
}
?>

// On load: select correct tab from cookie, or default to first
let activeIdx = 0;
const cookieVal = $.cookie(cookieName);
if (cookieVal) {
    const idx = Array.from(tabs).findIndex(tab => tab.id === cookieVal);
    if (idx !== -1) activeIdx = idx;
} else {
    // If no cookie exists, clear both cookies to match the origial initab function behavior
    $.removeCookie('one');
    $.removeCookie('tab');
}

tabs.forEach((tab, i) => {
    if (i === activeIdx) {
        tab.setAttribute('aria-selected', 'true');
        tab.setAttribute('tabindex', '0');
        panels[i].style.display = 'block';
    } else {
        tab.setAttribute('aria-selected', 'false');
        tab.setAttribute('tabindex', '-1');
        panels[i].style.display = 'none';
    }
});

// On tab click: update cookie and show correct panel
// Also update ARIA
// No content flash

tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => {
            tabs.forEach((t, j) => {
            t.setAttribute('aria-selected', j === i ? 'true' : 'false');
            t.setAttribute('tabindex', j === i ? '0' : '-1');
            panels[j].style.display = j === i ? 'block' : 'none';
        });
        $.cookie(cookieName, tab.id);
        tab.focus();
        // call the focus function if it exists
        if (tab.getAttribute('data-focus') ) {
            const focusFnName = tab.getAttribute('data-focus');
            if (typeof window[focusFnName] === 'function') {
                window[focusFnName]();
            } else {
                console.error('Focus function not found: ' + focusFnName);
            }
        }
    });
    tab.addEventListener('keydown', e => {
        let idx = Array.prototype.indexOf.call(tabs, document.activeElement);
        if (e.key === 'ArrowRight') {
            tabs[(idx+1)%tabs.length].focus();
        } else if (e.key === 'ArrowLeft') {
            tabs[(idx-1+tabs.length)%tabs.length].focus();
        }
    });
});
</script>
