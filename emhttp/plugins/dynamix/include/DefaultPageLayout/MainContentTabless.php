<?php
/**
 * Non-tabbed content template for the Unraid web interface.
 * Renders all pages in sequence without tabs, using original per-page logic.
 */
?>
<div id="displaybox">
    <div class="content">
        <? foreach ($pages as $page): ?>
            <? annotate($page['file']); ?>

            <? if (isset($page['Title'])): ?>
                <div class="title">
                    <? $title = processTitle($page['Title']); ?>
                    <?= tab_title($title, $page['root'], _var($page, 'Tag', false)) ?>
                    <span class="right inline-flex flex-row items-center gap-1"></span>
                </div>
            <? endif; ?>

            <?= generatePanels($page, $path, $defaultIcon, $docroot, true) ?>

            <? 
            $evalContent = '?>'.generateContent($page);
            $evalFile = $page['file'];
            if ( filter_var($page['Eval']??false, FILTER_VALIDATE_BOOLEAN) ) {
                eval($evalContent);
            } else {
                include "$docroot/webGui/include/DefaultPageLayout/evalContent.php";
            }
            ?>
        <? endforeach; ?>
    </div>
</div>
