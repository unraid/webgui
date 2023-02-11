<?PHP
/* Copyright 2005-2022, Lime Technology
 * Copyright 2012-2022, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// add translations
$_SERVER['REQUEST_URI'] = 'plugins';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

$valid = ['/var/tmp/','/tmp/plugins/'];
$good  = false;
?>
<!DOCTYPE HTML>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
</head>
<body style="margin:14px 10px">
<?
if ($file = realpath(unscript($_GET['file']??''))) {
  foreach ($valid as $check) if (strncmp($file,$check,strlen($check))===0) $good = true;
  if ($good && pathinfo($file)['extension']=='txt') echo Markdown(file_get_contents($file));
} else {
  echo Markdown("*"._('No release notes available')."!*");
}
?>
<br><div style="text-align:center"><input type="button" value="<?=_('Done')?>" onclick="top.Shadowbox.close()"></div>
</body>
</html>
