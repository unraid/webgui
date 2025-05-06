<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

// add translations
if (substr($_SERVER['REQUEST_URI'],0,4) != '/VMs') {
	$_SERVER['REQUEST_URI'] = 'vms';
	require_once "$docroot/webGui/include/Translations.php";
}

switch ($themeHelper->getThemeName()) { // $themeHelper set in DefaultPageLayout.php
	case 'gray' : $bgcolor = '#121510'; $border = '#606e7f'; $top = -44; break;
	case 'azure': $bgcolor = '#edeaef'; $border = '#606e7f'; $top = -44; break;
	case 'black': $bgcolor = '#212121'; $border = '#2b2b2b'; $top = -64; break;
	default     : $bgcolor = '#ededed'; $border = '#e3e3e3'; $top = -64; break;
}

$templateslocation = "/boot/config/plugins/dynamix.vm.manager/savedtemplates.json";
if (is_file($templateslocation)){
	$arrAllTemplates["User-templates"] = "";
	$ut = json_decode(file_get_contents($templateslocation),true) ;
	$arrAllTemplates = array_merge($arrAllTemplates, $ut);
}


$strSelectedTemplate = array_keys($arrAllTemplates)[1];
if (isset($_GET['template']) && isset($arrAllTemplates[unscript($_GET['template'])])) {
	$strSelectedTemplate = unscript($_GET['template']);
}

$arrLoad = [
	'name' => '',
	'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
	'autostart' => false,
	'form' => $arrAllTemplates[$strSelectedTemplate]['form'],
	'state' => 'shutoff'
];
$strIconURL = '/plugins/dynamix.vm.manager/templates/images/'.$arrLoad['icon'];

if (isset($_GET['uuid'])) {
	// Edit VM mode
	$res = $lv->domain_get_domain_by_uuid(unscript($_GET['uuid']));

	if ($res === false) {
		echo "<p class='notice'>"._('Invalid VM to edit').".</p><input type='button' value=\""._('Done')."\" onclick='done()'>";
		return;
	}

	$strIconURL = $lv->domain_get_icon_url($res);
	$arrLoad = [
		'name' => $lv->domain_get_name($res),
		'icon' => basename($strIconURL),
		'autostart' => $lv->domain_get_autostart($res),
		'form' => $arrAllTemplates[$strSelectedTemplate]['form'],
		'state' => $lv->domain_get_state($res)
	];

	if (empty($_GET['template'])) {
		// read vm-template attribute
		$strTemplateOS = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@os');
		$strLibreELEC = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@libreelec');
		$strOpenELEC = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@openelec');
		if ($strLibreELEC) $strSelectedTemplate = 'LibreELEC';
		elseif ($strOpenELEC) $strSelectedTemplate = 'OpenELEC';
		elseif ($strTemplateOS) {
			$strSelectedTemplate = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
		} else {
			// legacy VM support for <6.2 but need it going forward too
			foreach ($arrAllTemplates as $strName => $arrTemplate) {
				if (!empty($arrTemplate) && !empty($arrTemplate['os']) && $arrTemplate['os'] == $strTemplateOS) {
					$strSelectedTemplate = $strName;
					break;
				}
			}
		}
		if (empty($strSelectedTemplate) || empty($arrAllTemplates[$strSelectedTemplate])) {
			$strSelectedTemplate = 'Windows 10'; //default to Windows 10
		}
	}
	$arrLoad['form'] = $arrAllTemplates[$strSelectedTemplate]['form'];
}
$usertemplate = 0;
$strSelectedTemplateUT = $strSelectedTemplate;
if (strpos($strSelectedTemplate,"User-") !== false) { 
	$strSelectedTemplateUT = str_replace("User-","",$strSelectedTemplateUT); 
	$usertemplate = 1;
}
?>
<link type="text/css" rel="stylesheet" href="<?autov('/webGui/styles/jquery.filetree.css')?>">
<link type="text/css" rel="stylesheet" href="<?autov('/webGui/styles/jquery.switchbutton.css')?>">
<link type="text/css" rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/styles/dynamix.vm.manager.css')?>">
<link type="text/css" rel="stylesheet" href="<?autov('/plugins/dynamix.vm.manager/styles/edit.css')?>">

<span class="status advancedview_panel" style="margin-top:<?=$top?>px;"><input type="checkbox" class="inlineview"><input type="checkbox" class="advancedview"></span>
<div class="domain">
	<form id="vmform" method="POST">
	<input type="hidden" name="domain[type]" value="kvm" />
	<input type="hidden" name="template[name]" value="<?=htmlspecialchars($strSelectedTemplateUT)?>" />
	<input type="hidden" name="template[iconold]" value="<?=htmlspecialchars($arrLoad['icon'])?>" />

	<table>
		<tr>
			<td>_(Icon)_:</td>
			<td class="template_img_parent">
				<input type="hidden" name="template[icon]" id="template_icon" value="<?=htmlspecialchars($arrLoad['icon'])?>" />
				<img id="template_img" src="<?=htmlspecialchars($strIconURL)?>" width="48" height="48" title="_(Change Icon)_..."/>
				<div id="template_img_chooser_outer">
					<div id="template_img_chooser">
					<?
					$arrImagePaths = [
						"$docroot/plugins/dynamix.vm.manager/templates/images/*.png" => '/plugins/dynamix.vm.manager/templates/images/',
						"$docroot/boot/config/plugins/dynamix.vm.manager/templates/images/*.png" => '/boot/config/plugins/dynamix.vm.manager/templates/images/'
					];
					foreach ($arrImagePaths as $strGlob => $strIconURLBase) {
						foreach (glob($strGlob) as $png_file) {
							echo '<div class="template_img_chooser_inner"><img src="'.$strIconURLBase.basename($png_file).'" basename="'.basename($png_file).'"><p>'.basename($png_file,'.png').'</p></div>';
						}
					}
					?>
					</div>
				</div>
			</td>
			<td></td>
		</tr>
		<tr>
			<td>_(Autostart)_:</td>
			<td>
				<span class="width"><input type="checkbox" id="domain_autostart" name="domain[autostart]" style="display:none" class="autostart" value="1" <?if ($arrLoad['autostart']) echo 'checked'?>></span>
			</td>
			<td></td>
		</tr>
	</table>

	<blockquote class="inline_help">
		<p>If you want this VM to start with the array, set this to yes.</p>
	</blockquote>

	<div id="form_content"><?eval('?>'.parse_file("$docroot/plugins/dynamix.vm.manager/templates/{$arrLoad['form']}",false))?></div>
	</form>
</div>

<script src="<?autov('/webGui/javascript/jquery.filedrop.js')?>"></script>
<script src="<?autov('/webGui/javascript/jquery.filetree.js')?>" charset="utf-8"></script>
<script src="<?autov('/webGui/javascript/jquery.switchbutton.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/javascript/dynamix.vm.manager.js')?>"></script>
<script>
function isVMAdvancedMode() {
	return true;
}

function isVMXMLMode() {
	return ($.cookie('vmmanager_listview_mode') == 'xml');
}

function isinlineXMLMode() {
	return ($.cookie('vmmanager_inline_mode') == 'show');
}

function hidexml(checked) {
	var form = document.getElementById("vmform"); // Replace "yourFormId" with the actual ID of your form
	var xmlElements = form.getElementsByClassName("xml");
	if (checked == 0) xmldisplay = "none"; else xmldisplay = "";
	// Unhide each element
	for (var i = 0; i < xmlElements.length; i++) {
		xmlElements[i].style.display = xmldisplay; // Setting to empty string will revert to default style
	}
}

$(function(){
	$('.autostart').switchButton({
		on_label: "_(Yes)_",
		off_label: "_(No)_",
		labels_placement: "right"
	});
	$('.autostart').change(function () {
		$('#domain_autostart').prop('checked', $(this).is(':checked'));
	});

	$('.advancedview').switchButton({
		labels_placement: "right",
		on_label: "_(XML View)_",
		off_label: "_(Form View)_",
		checked: isVMXMLMode()
	});
	$('.inlineview').switchButton({
		labels_placement: "right",
		off_label: "_(Hide inline xml)_",
		on_label: "_(Show Inline XML)_",
		checked: isinlineXMLMode()
	});
	$('.advancedview').change(function () {
		toggleRows('xmlview', $(this).is(':checked'), 'formview');
		$.cookie('vmmanager_listview_mode', $(this).is(':checked') ? 'xml' : 'form', { expires: 3650 });
	});
	$('.inlineview').change(function () {
		hidexml($(this).is(':checked'));
		$.cookie('vmmanager_inline_mode', $(this).is(':checked') ? 'show' : 'hide', { expires: 3650 });
	});

	$('#template_img').click(function (){
		var p = $(this).position();
		p.left -= 4;
		p.top -= 4;
		$('#template_img_chooser_outer').css(p);
		$('#template_img_chooser_outer').slideDown();
	});
	$('#template_img_chooser').on('click', 'div', function (){
		$('#template_img').attr('src', $(this).find('img').attr('src'));
		$('#template_icon').val($(this).find('img').attr('basename'));
		$('#template_img_chooser_outer').slideUp();
	});
	$(document).keyup(function(e) {
		if (e.which == 27) $('#template_img_chooser_outer').slideUp();
	});

	$("#vmform table[data-category]").each(function () {
		var category = $(this).data('category');

		updatePrefixLabels(category);
		<?if ($arrLoad['state'] == 'shutoff'):?> bindSectionEvents(category); <?endif;?>
	});

	$("#vmform input[data-pickroot]").fileTreeAttach();

	var $el = $('#form_content');
	var $xmlview = $el.find('.xmlview');
	var $formview = $el.find('.formview');

	if ($xmlview.length || $formview.length) {
		$('.advancedview_panel').fadeIn('fast');
		if (isVMXMLMode()) {
			$('.formview').hide();
			$('.xmlview').filter(function() {
				return (($(this).prop('style').display + '') === '');
			}).show();
		} else {
			$('.xmlview').hide();
			$('.formview').filter(function() {
				return (($(this).prop('style').display + '') === '');
			}).show();
		}
	} else {
		$('.advancedview_panel').fadeOut('fast');
	}
	hidexml(isinlineXMLMode());

	$("#vmform #btnCancel").click(function (){
		done();
	});

	$('#form_content').fadeIn('fast');
});
</script>
