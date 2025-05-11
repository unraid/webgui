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
$banner = "$config/webGui/banner.png";

$notes = '/var/tmp/unRAIDServer.txt';
if (!file_exists($notes)) {
  file_put_contents($notes, shell_exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin changes $docroot/plugins/unRAIDServer/unRAIDServer.plg"));
}

$taskPages = find_pages('Tasks');
$buttonPages = find_pages('Buttons');
$pages = []; // finds subpages
if (!empty($myPage['text'])) $pages[$myPage['name']] = $myPage;
if (_var($myPage, 'Type') == 'xmenu') $pages = array_merge($pages, find_pages($myPage['name']));

// nchan related actions
$nchan = ['webGui/nchan/notify_poller', 'webGui/nchan/session_check'];
if ($wlan0) $nchan[] = 'webGui/nchan/wlan0';
// build nchan scripts from found pages
$allPages = array_merge($taskPages, $buttonPages, $pages);
foreach ($allPages as $page) {
  if (isset($page['Nchan'])) nchan_merge($page['root'], $page['Nchan']);
}
// act on nchan scripts
if (count($pages)) {
  $running = file_exists($nchan_pid) ? file($nchan_pid, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
  $start   = array_diff($nchan, $running);  // returns any new scripts to be started
  $stop    = array_diff($running, $nchan);  // returns any old scripts to be stopped
  $running = array_merge($start, $running); // update list of current running nchan scripts
  // start nchan scripts which are new
  foreach ($start as $row) {
    $script = explode(':', $row)[0];
    exec("$docroot/$script &>/dev/null &");
  }
  // stop nchan scripts with the :stop option
  foreach ($stop as $row) {
    [$script, $opt] = my_explode(':', $row);
    if ($opt == 'stop') {
      exec("pkill -f $docroot/$script &>/dev/null &");
      array_splice($running, array_search($row, $running), 1);
    }
  }
  if (count($running)) file_put_contents($nchan_pid, implode("\n", $running) . "\n");
  else @unlink($nchan_pid);
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
  <meta name="viewport" content="width=1300">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="same-origin">
  <link type="image/png" rel="shortcut icon" href="/webGui/images/<?= _var($var, 'mdColor', 'red-on') ?>.png">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/default-fonts.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/default-cases.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/font-awesome.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/context.standalone.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/jquery.sweetalert.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/jquery.ui.css") ?>">

  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/default-color-palette.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/default-base.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/default-dynamix.css") ?>">
  <link type="text/css" rel="stylesheet" href="<? autov("/webGui/styles/themes/{$theme}.css") ?>">

  <style>
    :root {
      --customer-header-background-image: url(<?= file_exists($banner) ? autov($banner) : autov('/webGui/images/banner.png') ?>);
      <? if ($header): ?>--customer-header-text-color: #<?= $header ?>;
      <? endif; ?><? if ($backgnd): ?>--customer-header-background-color: #<?= $backgnd ?>;
      <? endif; ?><? if ($display['font']): ?>--custom-font-size: <?= $display['font'] ?>%;
      <? endif; ?>
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

  <script src="<? autov('/webGui/javascript/dynamix.js') ?>"></script>
  <script src="<? autov('/webGui/javascript/translate.' . ($locale ?: 'en_US') . '.js') ?>"></script>

  <? require_once "$docroot/webGui/include/DefaultPageLayout/HeadInlineJS.php"; ?>
  <?php
  foreach ($buttonPages as $button) {
    annotate($button['file']);
    includePageStylesheets($button);
    eval('?>' . parse_text($button['text']));
  }

  foreach ($pages as $page) {
    annotate($page['file']);
    includePageStylesheets($page);
  }
  ?>

  <? include "$docroot/plugins/dynamix.my.servers/include/myservers1.php" ?>
  <? require_once "$docroot/webGui/include/DefaultPageLayout/GUIModeSessionFix.php"; ?>
</head>

<body>
  <? include "$docroot/webGui/include/DefaultPageLayout/Header.php"; ?>
  <? include "$docroot/webGui/include/DefaultPageLayout/Navigation/Main.php"; ?>
  <? include "$docroot/webGui/include/DefaultPageLayout/MainContent.php"; ?>
  <? include "$docroot/webGui/include/DefaultPageLayout/Footer.php"; ?>
  <? include "$docroot/webGui/include/DefaultPageLayout/MiscElements.php"; ?>
  <? include "$docroot/webGui/include/DefaultPageLayout/BodyInlineJS.php"; ?>
  <? include "$docroot/webGui/include/DefaultPageLayout/ToastSetup.php"; ?>
</body>

</html>