Menu="UserPreferences"
Type="xmenu"
Title="Notification Settings"
Icon="icon-notifications"
Tag="phone-square"
---
<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2012, Andrew Hamer-Adams, http://www.pixeleyes.co.nz.
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
$events = explode('|', $notify['events'] ?? '');
$disabled = $notify['system'] ? '' : 'disabled';
?>
<script>
function prepareNotify(form) {
  form.entity.value = form.normal1.checked | form.warning1.checked | form.alert1.checked;
  form.normal.value = form.normal1.checked*1 + form.normal2.checked*2 + form.normal3.checked*4;
  form.warning.value = form.warning1.checked*1 + form.warning2.checked*2 + form.warning3.checked*4;
  form.alert.value = form.alert1.checked*1 + form.alert2.checked*2 + form.alert3.checked*4;
  form.unraid.value = form.unraid1.checked*1 + form.unraid2.checked*2 + form.unraid3.checked*4;
  form.plugin.value = form.plugin1.checked*1 + form.plugin2.checked*2 + form.plugin3.checked*4;
  form.docker_notify.value = form.docker_notify1.checked*1 + form.docker_notify2.checked*2 + form.docker_notify3.checked*4;
  form.language_notify.value = form.language_notify1.checked*1 + form.language_notify2.checked*2 + form.language_notify3.checked*4;
  form.report.value = form.report1.checked*1 + form.report2.checked*2 + form.report3.checked*4;
  form.normal1.disabled = true;
  form.normal2.disabled = true;
  form.normal3.disabled = true;
  form.warning1.disabled = true;
  form.warning2.disabled = true;
  form.warning3.disabled = true;
  form.alert1.disabled = true;
  form.alert2.disabled = true;
  form.alert3.disabled = true;
  form.unraid1.disabled = true;
  form.unraid2.disabled = true;
  form.unraid3.disabled = true;
  form.plugin1.disabled = true;
  form.plugin2.disabled = true;
  form.plugin3.disabled = true;
  form.docker_notify1.disabled = true;
  form.docker_notify2.disabled = true;
  form.docker_notify3.disabled = true;
  form.language_notify1.disabled = true;
  form.language_notify2.disabled = true;
  form.language_notify3.disabled = true;
  form.report1.disabled = true;
  form.report2.disabled = true;
  form.report3.disabled = true;
}
function prepareSystem(index) {
  if (index==0) $('.checkbox').attr('disabled','disabled'); else $('.checkbox').removeAttr('disabled');
}
function prepareTitle() {
  var title = '_(Available notifications)_:';
  $('#unraidTitle,#pluginTitle,#dockerTitle,#languageTitle,#reportTitle').html('&nbsp;');
  if ($('.unraid').is(':visible')) {$('#unraidTitle').html(title); return;}
  if ($('.plugin').is(':visible')) {$('#pluginTitle').html(title); return;}
  if ($('.docker').is(':visible')) {$('#dockerTitle').html(title); return;}
  if ($('.language').is(':visible')) {$('#languageTitle').html(title); return;}
  if ($('.report').is(':visible')) {$('#reportTitle').html(title); return;}
}
function prepareUnraid(value) {
  if (value=='') $('.unraid').hide(); else $('.unraid').show();
  prepareTitle();
}
function preparePlugin(value) {
  if (value=='') $('.plugin').hide(); else $('.plugin').show();
  prepareTitle();
}
function prepareDocker(value) {
  if (value=='') $('.docker').hide(); else $('.docker').show();
  prepareTitle();
}
function prepareLanguage(value) {
  if (value=='') $('.language').hide(); else $('.language').show();
  prepareTitle();
}
function prepareReport(value) {
  if (value=='') $('.report').hide(); else $('.report').show();
  prepareTitle();
}
$(function(){
  prepareUnraid(document.notify_settings.unraidos.value);
  preparePlugin(document.notify_settings.version.value);
  prepareDocker(document.notify_settings.docker_update.value);
  prepareLanguage(document.notify_settings.language_update.value);
  prepareReport(document.notify_settings.status.value);
});
</script>
<form markdown="1" name="notify_settings" method="POST" action="/update.php" target="progressFrame" onsubmit="prepareNotify(this)">
<input type="hidden" name="#file" value="dynamix/dynamix.cfg">
<input type="hidden" name="#section" value="notify">
<input type="hidden" name="#command" value="/webGui/scripts/notify">
<input type="hidden" name="#arg[1]" value="cron-init">
<input type="hidden" name="entity">
<input type="hidden" name="normal">
<input type="hidden" name="warning">
<input type="hidden" name="alert">
<input type="hidden" name="unraid">
<input type="hidden" name="plugin">
<input type="hidden" name="docker_notify">
<input type="hidden" name="language_notify">
<input type="hidden" name="report">
_(Notifications display)_:
: <select class="a" name="display">
  <?=mk_option($notify['display'], "0", _("Detailed"))?>
  <?=mk_option($notify['display'], "1", _("Summarized"))?>
  </select>

:notifications_display_help:

_(Display position)_:
: <select name="position" class="a">
  <?=mk_option($notify['position'], "top-left", _("top-left"))?>
  <?=mk_option($notify['position'], "top-right", _("top-right"))?>
  <?=mk_option($notify['position'], "bottom-left", _("bottom-left"))?>
  <?=mk_option($notify['position'], "bottom-right", _("bottom-right"))?>
  <?=mk_option($notify['position'], "center", _("center"))?>
  </select>

:notifications_display_position_help:

_(Auto-close)_ (_(seconds)_):
: <input type="number" name="life" class="a" min="0" max="60" value="<?=$notify['life']?>"> _(a value of zero means no automatic closure)_

:notifications_auto_close_help:

_(Date format)_:
: <select name="date" class="a">
  <?=mk_option($notify['date'], "d-m-Y", _("DD-MM-YYYY"))?>
  <?=mk_option($notify['date'], "m-d-Y", _("MM-DD-YYYY"))?>
  <?=mk_option($notify['date'], "Y-m-d", _("YYYY-MM-DD"))?>
  </select>

:notifications_date_format_help:

_(Time format)_:
: <select name="time" class="a">
  <?=mk_option($notify['time'], "h:i A", _("12 hours"))?>
  <?=mk_option($notify['time'], "H:i", _("24 hours"))?>
  </select>

:notifications_time_format_help:

_(Store notifications to flash)_:
: <select name="path" class="a">
  <?=mk_option($notify['path'], "/tmp/notifications", _("No"))?>
  <?=mk_option($notify['path'], "/boot/config/plugins/dynamix/notifications", _("Yes"))?>
  </select>

:notifications_store_flash_help:

_(System notifications)_:
: <select name="system" class="a" onchange="prepareSystem(this.selectedIndex)">
  <?=mk_option($notify['system'], "", _("Disabled"))?>
  <?=mk_option($notify['system'], "*/1 * * * *", _("Enabled"))?>
  </select>

:notifications_system_help:

_(Unraid OS update notification)_:
: <select name="unraidos" class="a" onchange="prepareUnraid(this.value)">
  <?=mk_option($notify['unraidos'], "", _("Never check"))?>
  <?=mk_option($notify['unraidos'], "11 */6 * * *", _("Check four times a day"))?>
  <?=mk_option($notify['unraidos'], "11 0,12 * * *", _("Check twice a day"))?>
  <?=mk_option($notify['unraidos'], "11 0 * * *", _("Check once a day"))?>
  <?=mk_option($notify['unraidos'], "11 0 * * 1", _("Check once a week"))?>
  <?=mk_option($notify['unraidos'], "11 0 1 * *", _("Check once a month"))?>
  </select>

:notifications_os_update_help:

_(Plugins update notification)_:
: <select name="version" class="a" onchange="preparePlugin(this.value)">
  <?=mk_option($notify['version'], "", _("Never check"))?>
  <?=mk_option($notify['version'], "10 */6 * * *", _("Check four times a day"))?>
  <?=mk_option($notify['version'], "10 0,12 * * *", _("Check twice a day"))?>
  <?=mk_option($notify['version'], "10 0 * * *", _("Check once a day"))?>
  <?=mk_option($notify['version'], "10 0 * * 1", _("Check once a week"))?>
  <?=mk_option($notify['version'], "10 0 1 * *", _("Check once a month"))?>
  </select>

:notifications_plugins_update_help:

_(Docker update notification)_:
: <select name="docker_update" class="a" onchange="prepareDocker(this.value)">
  <?=mk_option($notify['docker_update'], "", _("Never check"))?>
  <?=mk_option($notify['docker_update'], "10 */6 * * *", _("Check four times a day"))?>
  <?=mk_option($notify['docker_update'], "10 0,12 * * *", _("Check twice a day"))?>
  <?=mk_option($notify['docker_update'], "10 0 * * *", _("Check once a day"))?>
  <?=mk_option($notify['docker_update'], "10 0 * * 1", _("Check once a week"))?>
  <?=mk_option($notify['docker_update'], "10 0 1 * *", _("Check once a month"))?>
  </select>

:notifications_docker_update_help:

_(Language update notification)_:
: <select name="language_update" class="a" onchange="prepareLanguage(this.value)">
  <?=mk_option($notify['language_update'], "", _("Never check"))?>
  <?=mk_option($notify['language_update'], "10 */6 * * *", _("Check four times a day"))?>
  <?=mk_option($notify['language_update'], "10 0,12 * * *", _("Check twice a day"))?>
  <?=mk_option($notify['language_update'], "10 0 * * *", _("Check once a day"))?>
  <?=mk_option($notify['language_update'], "10 0 * * 1", _("Check once a week"))?>
  <?=mk_option($notify['language_update'], "10 0 1 * *", _("Check once a month"))?>
  </select>

_(Array status notification)_:
: <select name="status" class="a" onchange="prepareReport(this.value)">
  <?=mk_option($notify['status'], "", _("Never send"))?>
  <?=mk_option($notify['status'], "20 * * * *", _("Send every hour"))?>
  <?=mk_option($notify['status'], "20 */2 * * *", _("Send every two hours"))?>
  <?=mk_option($notify['status'], "20 */6 * * *", _("Send four times a day"))?>
  <?=mk_option($notify['status'], "20 */8 * * *", _("Send three times a day"))?>
  <?=mk_option($notify['status'], "20 0,12 * * *", _("Send twice a day"))?>
  <?=mk_option($notify['status'], "20 0 * * *", _("Send once a day"))?>
  <?=mk_option($notify['status'], "20 0 * * 1", _("Send once a week"))?>
  <?=mk_option($notify['status'], "20 0 1 * *", _("Send once a month"))?>
  </select>

:notifications_array_status_help:

<span id="unraidTitle" class="unraid" style="display:none">&nbsp;</span>
: <span class="unraid" style="display:none"><span class="a">_(Unraid OS update)_</span>
  <input type="checkbox" name="unraid1"<?=($notify['unraid'] & 1)==1 ? ' checked' : ''?>>_(Browser)_ &nbsp;
  <input type="checkbox" name="unraid2"<?=($notify['unraid'] & 2)==2 ? ' checked' : ''?>>_(Email)_ &nbsp;
  <input type="checkbox" name="unraid3"<?=($notify['unraid'] & 4)==4 ? ' checked' : ''?>>_(Agents)_ &nbsp;</span>

<span id="pluginTitle" class="plugin" style="display:none">&nbsp;</span>
: <span class="plugin" style="display:none"><span class="a">_(Plugins update)_</span>
  <input type="checkbox" name="plugin1"<?=($notify['plugin'] & 1)==1 ? ' checked' : ''?>>_(Browser)_ &nbsp;
  <input type="checkbox" name="plugin2"<?=($notify['plugin'] & 2)==2 ? ' checked' : ''?>>_(Email)_ &nbsp;
  <input type="checkbox" name="plugin3"<?=($notify['plugin'] & 4)==4 ? ' checked' : ''?>>_(Agents)_ &nbsp;</span>

<span id="dockerTitle" class="docker" style="display:none">&nbsp;</span>
: <span class="docker" style="display:none"><span class="a">_(Docker update)_</span>
  <input type="checkbox" name="docker_notify1"<?=($notify['docker_notify'] & 1)==1 ? ' checked' : ''?>>_(Browser)_ &nbsp;
  <input type="checkbox" name="docker_notify2"<?=($notify['docker_notify'] & 2)==2 ? ' checked' : ''?>>_(Email)_ &nbsp;
  <input type="checkbox" name="docker_notify3"<?=($notify['docker_notify'] & 4)==4 ? ' checked' : ''?>>_(Agents)_ &nbsp;</span>

<span id="languageTitle" class="language" style="display:none">&nbsp;</span>
: <span class="language" style="display:none"><span class="a">_(Language update)_</span>
  <input type="checkbox" name="language_notify1"<?=($notify['language_notify'] & 1)==1 ? ' checked' : ''?>>_(Browser)_ &nbsp;
  <input type="checkbox" name="language_notify2"<?=($notify['language_notify'] & 2)==2 ? ' checked' : ''?>>_(Email)_ &nbsp;
  <input type="checkbox" name="language_notify3"<?=($notify['language_notify'] & 4)==4 ? ' checked' : ''?>>_(Agents)_ &nbsp;</span>

<span id="reportTitle" class="report" style="display:none">&nbsp;</span>
: <span class="report" style="display:none"><span class="a">_(Array status)_</span>
  <input type="checkbox" name="report1"<?=($notify['report'] & 1)==1 ? ' checked' : ''?>>_(Browser)_ &nbsp;
  <input type="checkbox" name="report2"<?=($notify['report'] & 2)==2 ? ' checked' : ''?>>_(Email)_ &nbsp;
  <input type="checkbox" name="report3"<?=($notify['report'] & 4)==4 ? ' checked' : ''?>>_(Agents)_ &nbsp;</span>

:notifications_agent_selection_help:

_(Notification entity)_:
: <span class="a">_(Notices)_</span>
  <input type="checkbox" class="checkbox" name="normal1"<?=($notify['normal'] & 1)==1 ? " checked $disabled" : $disabled?>>_(Browser)_ &nbsp;
  <input type="checkbox" class="checkbox" name="normal2"<?=($notify['normal'] & 2)==2 ? " checked $disabled" : $disabled?>>_(Email)_ &nbsp;
  <input type="checkbox" class="checkbox" name="normal3"<?=($notify['normal'] & 4)==4 ? " checked $disabled" : $disabled?>>_(Agents)_ &nbsp;

&nbsp;
: <span class="a">_(Warnings)_</span>
  <input type="checkbox" class="checkbox" name="warning1"<?=($notify['warning'] & 1)==1 ? " checked $disabled" : $disabled?>>_(Browser)_ &nbsp;
  <input type="checkbox" class="checkbox" name="warning2"<?=($notify['warning'] & 2)==2 ? " checked $disabled" : $disabled?>>_(Email)_ &nbsp;
  <input type="checkbox" class="checkbox" name="warning3"<?=($notify['warning'] & 4)==4 ? " checked $disabled" : $disabled?>>_(Agents)_ &nbsp;

&nbsp;
: <span class="a">_(Alerts)_</span>
  <input type="checkbox" class="checkbox" name="alert1"<?=($notify['alert'] & 1)==1 ? " checked $disabled" : $disabled?>>_(Browser)_ &nbsp;
  <input type="checkbox" class="checkbox" name="alert2"<?=($notify['alert'] & 2)==2 ? " checked $disabled" : $disabled?>>_(Email)_ &nbsp;
  <input type="checkbox" class="checkbox" name="alert3"<?=($notify['alert'] & 4)==4 ? " checked $disabled" : $disabled?>>_(Agents)_ &nbsp;

:notifications_classification_help:

<input type="submit" name="#default" value="_(Default)_">
: <input type="submit" name="#apply" value="_(Apply)_" disabled><input type="button" value="_(Done)_" onclick="done()">
</form>
