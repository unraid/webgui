<?PHP
/* Copyright 2005-2020, Lime Technology
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
$_SERVER['REQUEST_URI'] = 'tools';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

$var = parse_ini_file('state/var.ini');
$keyfile = base64_encode(file_get_contents($var['regFILE']));
?>
<!DOCTYPE html>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=1600">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
<script>
function replaceKey(email, guid, keyfile) {
  if (email.length) {
    var timestamp = <?=time()?>;
    $('#status_panel').slideUp('fast');
    $('#input_form').find('input').prop('disabled', true);
    // Nerds love spinners, Maybe place a spinner image next to the submit button; we'll show it now:
    $('#spinner_image').fadeIn('fast');

    $.post('https://keys.lime-technology.com/account/license/transfer',{timestamp:timestamp,guid:guid,email:email,keyfile:keyfile},function(data) {
        $('#spinner_image').fadeOut('fast');
        var msg = "<p><?=_('A registration replacement key has been created for USB Flash GUID')?> <strong>"+guid+"</strong></p>" +
                  "<p><?=_('An email has been sent to')?> <strong>"+email+"</strong> <?=_('containing your key file URL')?>." +
                  " <?=_('When received, please paste the URL into the *Key file URL* box and')?>" +
                  " <?=_('click <i>Install Key</i>')?>.</p>" +
                  "<p><?=_('If you do not receive an email, please check your spam or junk-email folder')?>.</p>";

        $('#status_panel').hide().html(msg).slideDown('fast');
        $('#input_form').fadeOut('fast');
    }).fail(function(data) {
        $('#input_form').find('input').prop('disabled', false);
        $('#spinner_image').fadeOut('fast');
        var status = data.status;
        var obj = data.responseJSON;
        var msg = "<p><?=_('Sorry, an error occurred')?> <?=_('registering USB Flash GUID')?> <strong>"+guid+"</strong><p>"+"<p><?=_('The error is')?>: "+obj.error+"</p>";

        $('#status_panel').hide().html(msg).slideDown('fast');
    });
  }
}
</script>
</head>
<body>
<div style="margin-top:20px;line-height:30px;margin-left:40px">
<div id="status_panel"></div>
<form markdown="1" id="input_form">

Email address: <input type="text" name="email" maxlength="1024" value="" style="width:33%">

<input type="button" value="Replace Key" onclick="replaceKey(this.form.email.value.trim(), '<?=$var['flashGUID']?>', '<?=$keyfile?>')">

<p>A link to your replacement key will be delivered to this email address.

<p><strong>Note:</strong>
Once a replacement key is generated, your old USB Flash device will be <b>blacklisted</b>.
</form>
</div>
</body>
</html>
