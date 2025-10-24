<?
/**
 * Global elements are elements that are used on all pages.
 * Describe the purpose of each element here.
 * 
 * - upgrade_notice: Display a notice to the user that they need to upgrade their browser.
 * - move_to_end: Move to the end of the page.
 * - back_to_top: Move to the top of the page.
 * - spinner: Display the Unraid loader icon.
 * - rebootNow: Global form to POST to /webGui/include/Boot.php?cmd=reboot to trigger a system reboot.
 * - progressFrame: Display a frame to show the progress of the reboot.
 */
?>

<div class="upgrade_notice" style="display:none"></div>

<a href="#" class="move_to_end" title="<?=_('Move To End')?>"><i class="fa fa-arrow-circle-down"></i></a>
<a href="#" class="back_to_top" title="<?=_('Back To Top')?>"><i class="fa fa-arrow-circle-up"></i></a>

<div class="spinner fixed"></div>

<form name="rebootNow" method="POST" action="/webGui/include/Boot.php"><input type="hidden" name="cmd" value="reboot"></form>

<iframe id="progressFrame" name="progressFrame" frameborder="0"></iframe>