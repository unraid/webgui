<?php
/* Copyright 2005-2024, Lime Technology
 * Copyright 2012-2024, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?php
require_once "$docroot/plugins/dynamix/include/ThemeHelper.php";
$themeHelper = new ThemeHelper($display['theme'], $display['width']);
$theme   = $themeHelper->getThemeName(); // keep $theme, $themes1, $themes2 vars for plugin backwards compatibility for the time being
$themes1 = $themeHelper->isTopNavTheme();
$themes2 = $themeHelper->isSidebarTheme();
$themeHelper->updateDockerLogColor($docroot);

$display['font'] = filter_var($_COOKIE['fontSize'] ?? $display['font'] ?? '', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

$header  = $display['header']; // keep $header, $backgnd vars for plugin backwards compatibility for the time being
$backgnd = $display['background'];

$config  = "/boot/config";
$entity  = $notify['entity'] & 1 == 1;
$alerts  = '/tmp/plugins/my_alerts.txt';
$wlan0   = file_exists('/sys/class/net/wlan0');

$safemode = _var($var, 'safeMode') == 'yes';
$banner = "$config/plugins/dynamix/banner.png"; // this cannot use the webGui/banner.png file because it is not in the webGui directory but stored on the boot drive

$notes = '/var/tmp/unRAIDServer.txt';
if (!file_exists($notes)) {
    file_put_contents($notes, shell_exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin changes $docroot/plugins/unRAIDServer/unRAIDServer.plg"));
}

$taskPages = find_pages('Tasks');
$buttonPages = find_pages('Buttons');
$pages = []; // finds subpages
if (!empty($myPage['text'])) {
    $pages[$myPage['name']] = $myPage;
}
if (_var($myPage, 'Type') == 'xmenu') {
    $pages = array_merge($pages, find_pages($myPage['name']));
}

// nchan related actions
$nchan = ['webGui/nchan/notify_poller', 'webGui/nchan/session_check'];
if ($wlan0) {
    $nchan[] = 'webGui/nchan/wlan0';
}
// build nchan scripts from found pages
$allPages = array_merge($taskPages, $buttonPages, $pages);
foreach ($allPages as $page) {
    if (isset($page['Nchan'])) {
        nchan_merge($page['root'], $page['Nchan']);
    }
}
// act on nchan scripts
if (count($pages)) {
    $running = file_exists($nchan_pid) ? file($nchan_pid, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $start   = array_diff($nchan, $running);  // returns any new scripts to be started
    $running = array_merge($start, $running); // update list of current running nchan scripts
    // start nchan scripts which are new or have been terminated but still should be running
    if (count($running)) {
        file_put_contents_atomic($nchan_pid, implode("\n", $running) . "\n");
        foreach ($running as $row) {
            $script = explode(':', $row, 2)[0];
            $output = [];
            exec('pgrep --ns $$ -f ' . escapeshellarg("$docroot/$script"),$output,$retval);
            if ($retval !== 0) { // 0=found; 1=none; 2=error
                exec(escapeshellarg("$docroot/$script") . ' >/dev/null 2>&1 &');
            }
        }
    } else {
        @unlink($nchan_pid);
    }
}

?>
<!DOCTYPE html>
<html <?= $display['rtl'] ?>lang="<?= strtok($locale, '_') ?: 'en' ?>" class="<?= $themeHelper->getThemeHtmlClass() ?>">

<head>
  <title><?= _var($var, 'NAME') ?>/<?= _var($myPage, 'name') ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
  <meta name="format-detection" content="telephone=no">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="same-origin">

  <?php require_once "$docroot/plugins/dynamix/include/DefaultPageLayout/Favicon.php"; ?>

  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/default-fonts.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/default-cases.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/font-awesome.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/context.standalone.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/jquery.sweetalert.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/jquery.ui.css") ?>">

  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/default-color-palette.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/default-base.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/default-dynamix.css") ?>">
  <link type="text/css" rel="stylesheet" href="<?php autov("/webGui/styles/themes/{$theme}.css") ?>">

  <style>
    :root {
      --customer-header-background-image: url(<?= file_exists($banner) ? autov($banner) : autov('/webGui/images/banner.png') ?>);
      <?php if ($header): ?>--customer-header-text-color: #<?= $header ?>;
      <?php endif; ?><?php if ($backgnd): ?>--customer-header-background-color: #<?= $backgnd ?>;
      <?php endif; ?><?php if ($display['font']): ?>--custom-font-size: <?= $display['font'] ?>%;
      <?php endif; ?>
    }

    <?php
    // Generate sidebar icon CSS if using sidebar theme
    if ($themeHelper->isSidebarTheme()) {
        echo generate_sidebar_icon_css($taskPages, $buttonPages);
    }
?>
  </style>

  <noscript>
    <div class="upgrade_notice"><?= _("Your browser has JavaScript disabled") ?></div>
  </noscript>

  <script src="<?php autov('/webGui/javascript/dynamix.js') ?>"></script>
  <script src="<?php autov('/webGui/javascript/translate.' . ($locale ?: 'en_US') . '.js') ?>"></script>

  <?php require_once "$docroot/webGui/include/DefaultPageLayout/HeadInlineJS.php"; ?>
  <?php
    foreach ($buttonPages as $button) {
        annotate($button['file']);
        includePageStylesheets($button);
        $evalContent = '?>' . parse_text($button['text']);
        $evalFile = $button['file'];
        if ( filter_var($button['Eval']??false, FILTER_VALIDATE_BOOLEAN) ) {
            eval($evalContent);
        } else {
            include "$docroot/webGui/include/DefaultPageLayout/evalContent.php";
        }
    }
 

    foreach ($pages as $page) {
        annotate($page['file']);
        includePageStylesheets($page);
    }
?>

  <?php include "$docroot/plugins/dynamix.my.servers/include/myservers1.php" ?>
</head>

<body>
  <?php include "$docroot/webGui/include/DefaultPageLayout/MiscElementsTop.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/Header.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/Navigation/Main.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/MainContent.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/Footer.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/MiscElementsBottom.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/BodyInlineJS.php"; ?>
  <?php include "$docroot/webGui/include/DefaultPageLayout/ToastSetup.php"; ?>
</body>

</html>
