<?PHP
/* Copyright 2005-2023, Lime Technology
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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";
extract(parse_plugin_cfg('dynamix',true));

// add translations
$_SERVER['REQUEST_URI'] = 'tools';
require_once "$docroot/webGui/include/Translations.php";

$var = parse_ini_file('state/var.ini');

if (!empty($_POST['trial'])) {
  file_put_contents_atomic('/boot/config/Trial.key', base64_decode($_POST['trial']));
  exit;
}
?>
<!DOCTYPE html>
<html <?=$display['rtl']?>lang="<?=strtok($locale,'_')?:'en'?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta http-equiv="Content-Security-Policy" content="block-all-mixed-content">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=1300">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="same-origin">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<script src="<?autov('/webGui/javascript/dynamix.js')?>"></script>
</head>
<body>
<div style="margin:20px;">
<div id="status_panel"></div>
<form markdown="1" id="trial_form">

<p><input type="checkbox" id="eula" name="eula"><label for="eula"><?=_('By using this software, you agree with our')?> <a target="_blank" href="/Tools/EULA"><?=_('End-User License Agreement')?></a> <?=_('and')?> <a target="_blank" href="https://unraid.net/policies"><?=_('Privacy Policy')?></a>.</label></p>

<br><br>

<center><input type="button" id="trial_button" value="<?=(strstr($var['regTy'], "expired")?_("Extend"):_("Start"))?> <?=_('Trial')?>" onclick="startTrial()" disabled></center>

</form>
</div>
<script>
function startTrial() {
  var guid = '<?=$var['flashGUID']?>';
  var timestamp = <?=time()?>;
  $('#status_panel').slideUp('fast');
  $('#trial_form').find('input').prop('disabled', true);
  $('#spinner_image').fadeIn('fast');

  $.post('https://keys.lime-technology.com/account/trial',{timestamp:timestamp,guid:guid},function(data) {
    $.post('/webGui/include/TrialRequest.php',{trial:data.trial,csrf_token:'<?=$var['csrf_token']?>'},function(data2) {
      $('#spinner_image,#status_panel').fadeOut('fast');
      parent.swal({title:"<?=_('Trial')?> <?=(strstr($var['regTy'], 'expired')?_('extended'):_('started'))?>",text:"<?=_('Thank you for registering USB Flash GUID')?> "+guid+".",type:'success',html:true,confirmButtonText:"<?=_('Ok')?>"},function(){parent.window.location='/Main';});
    });
  }).fail(function(data) {
      $('#trial_form').find('input').prop('disabled', false);
      $('#spinner_image').fadeOut('fast');
      var status = data.status;
      var obj = data.responseJSON;
      var msg = "<p><?=_('Sorry, an error occurred')?> <?=('registering USB Flash GUID')?> <strong>"+guid+"</strong><p>"+"<p><?=_('The error is')?>: "+obj.error+"</p>";
      $('#status_panel').hide().html(msg).slideDown('fast');
  });
}
$('#eula').change(function() {
  $('#trial_button').prop('disabled', !this.checked);
});
</script>
</body>
</html>
