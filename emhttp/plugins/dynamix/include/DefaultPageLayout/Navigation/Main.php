<?
/**
 * Template is used for both top navigation and sidebar navigation.
 *
 * @var $themeHelper ThemeHelper
 * @var $taskPages array
 * @var $buttonPages array
 * @var $display array
 * @var $task string
 */
?>
<div id="menu">
    <? if ($themeHelper->isSidebarTheme()): ?>
        <div id="nav-block">
    <? endif; ?>

    <div class="nav-tile">
        <? foreach ($taskPages as $button): ?>
            <? $pageName = $button['name']; ?>
            <? $pageActive = $task == $pageName ? " active" : ""; ?>
            <div class="nav-item<?= $pageActive ?>">
                <a href="/<?= $pageName ?>" onclick="initab('/<?= $pageName ?>')">
                    <?= _(_var($button, 'Name', $pageName)) ?>
                </a>
            </div>
        <? endforeach; ?>
        <? unset($taskPages); ?>
    </div>

    <div class="nav-tile right">
        <? if (isset($myPage['Lock'])): ?>
            <div class="nav-item LockButton util">
                <a href="#" class="hand" onclick="LockButton();return false;" title="<?= _('Unlock sortable items') ?>">
                    <b class="icon-u-lock system green-text"></b>
                    <span><?= _('Unlock sortable items') ?></span>
                </a>
            </div>
        <? endif; ?>

        <? if ($display['usage']): ?>
            <? my_usage(); ?>
        <? endif; ?>

        <? foreach ($buttonPages as $button): ?>
            <? if (empty($button['Link'])): ?>
                <? $icon = $button['Icon']; ?>
                <? if (substr($icon, -4) == '.png'): ?>
                    <? $icon = "<img src='/{$button['root']}/icons/$icon' class='system'>"; ?>
                <? elseif (substr($icon, 0, 5) == 'icon-'): ?>
                    <? $icon = "<b class='$icon system'></b>"; ?>
                <? else: ?>
                    <? if (substr($icon, 0, 3) != 'fa-'): ?>
                        <? $icon = "fa-$icon"; ?>
                    <? endif; ?>
                    <? $icon = "<b class='fa $icon system'></b>"; ?>
                <? endif; ?>

                <div class="nav-item <?= $button['name'] ?> util">
                    <a
                        href="<?= _var($button, 'Href', '#') ?>"
                        onclick="<?= $button['name'] ?>();return false;"
                        title="<?= _($button['Title']) ?>"
                    >
                        <?= $icon ?>
                        <span><?= _($button['Title']) ?></span>
                    </a>
                </div>
            <? else: ?>
                <div class="<?= $button['Link'] ?>"></div>
            <? endif; ?>
        <? endforeach; ?>
        <? unset($buttonPages, $button); ?>

        <div class="nav-user show">
            <a id="board" href="#" class="hand">
                <b id="bell" class="icon-u-bell system"></b>
            </a>
        </div>

        <? if ($themeHelper->isSidebarTheme()): ?>
            </div>
        <? endif; ?>
    </div>
</div>
