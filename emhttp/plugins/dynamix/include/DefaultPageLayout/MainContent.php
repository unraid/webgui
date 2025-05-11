<?php
/**
 * Main content delegator for the Unraid web interface.
 * Includes the correct template based on tabbed state.
 * 
 * Even if DisplaySettings is not enabled for tabs, pages with Tabs="true" will use tabs
 * and pages with Tabs="false" will not use tabs.
 */
$display['tabs'] = isset($myPage['Tabs'])
    ? (strtolower($myPage['Tabs']) == 'true' ? 0 : 1)
    : $display['tabs'];
$tabbed = $display['tabs'] == 0 && count($pages) > 1;
$contentInclude = $tabbed ? 'MainContentTabbed.php' : 'MainContentTabless.php';

$defaultIcon = "<i class=\"icon-app PanelIcon\"></i>";

/**
 * Safely processes page titles by replacing PHP variables with their values
 * 
 * @param string $rawTitle The unprocessed title that may contain variable references
 * @return string Processed title with variables substituted
 */
function processTitle($rawTitle) {
    // Safely replace any variables in the title without eval
    $title = htmlspecialchars((string)$rawTitle);
    return preg_replace_callback(
        '/\$([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/',
        function($matches) {
            return isset($GLOBALS[$matches[1]]) ? 
                   htmlspecialchars((string)$GLOBALS[$matches[1]]) : 
                   '$'.$matches[1];
        },
        $title
    );
}

function process_icon($icon, $docroot, $root) {
    global $defaultIcon;
    if (substr($icon, -4) == '.png') {
        if (file_exists("$docroot/$root/images/$icon")) {
            return "<img src=\"/$root/images/$icon\" class=\"PanelImg\">";
        } elseif (file_exists("$docroot/$root/$icon")) {
            return "<img src=\"/$root/$icon\" class=\"PanelImg\">";
        }
        return $defaultIcon;
    } elseif (substr($icon, 0, 5) == 'icon-') {
        return "<i class=\"$icon PanelIcon\"></i>";
    } elseif ($icon[0] != '<') {
        if (substr($icon, 0, 3) != 'fa-') {
            $icon = "fa-$icon";
        }
        return "<i class=\"fa $icon PanelIcon\"></i>";
    }
    return $icon;
}

/**
 * Generates a Panel DOM element for menu items
 * 
 * @param array $pg Page data array containing name, Title, Icon
 * @param string $path Current path
 * @param string $defaultIcon Default icon to use if none specified
 * @param string $docroot Document root path
 * @param bool $useTabCookie Whether to add tab cookie onclick handler
 * @return string HTML for the Panel element
 */
function generatePanel($pg, $path, $defaultIcon, $docroot, $useTabCookie = false) {
    // Process title using our safe variable substitution function
    $panelTitle = processTitle($pg['Title']);
    $icon = _var($pg, 'Icon', $defaultIcon);
    $icon = process_icon($icon, $docroot, $pg['root']);

    $onclick = $useTabCookie ? ' onclick="$.cookie(\'one\',\'tab1\')"' : '';

    return sprintf(
        '<div class="Panel">
            <a href="/%s/%s"%s>
                <span>%s</span>
                <div class="PanelText">%s</div>
            </a>
        </div>',
        $path,
        $pg['name'],
        $onclick,
        $icon,
        _($panelTitle)
    );
}

/**
 * Generates all panels for a menu page
 * 
 * @param array $page Page data array containing Type and name
 * @param string $path Current path
 * @param string $defaultIcon Default icon to use if none specified
 * @param string $docroot Document root path
 * @param bool $useTabCookie Whether to add tab cookie onclick handler
 * @return string HTML for all panels or empty string if not a menu page
 */
function generatePanels($page, $path, $defaultIcon, $docroot, $useTabCookie = false) {
    if (!isset($page['Type']) || $page['Type'] != 'menu') {
        return '';
    }

    $output = '';
    $pgs = find_pages($page['name']);
    foreach ($pgs as $pg) {
        $output .= generatePanel($pg, $path, $defaultIcon, $docroot, $useTabCookie);
    }
    return '<div class="Panels">'.$output.'</div>';
}

/**
 * Generates the content for a page
 * 
 * @param array $page Page data array containing text and Markdown flag
 * @return string Parsed text ready for eval
 * 
 * Usage example:
 * <? eval('?>'.generateContent($page)); ?>
 */
function generateContent($page) {
    if (empty($page['Markdown']) || $page['Markdown'] == 'true') {
        return Markdown(parse_text($page['text']));
    }
    return parse_text($page['text']);
}
?>

<?php require_once __DIR__ . "/$contentInclude"; ?>

<?
/**
 * Legacy carryover. Ideally wouldn't be needed.
 */
unset($pages, $page, $pgs, $pg, $icon, $nchan, $running, $start, $stop, $row, $script, $opt, $nchan_run);
?>