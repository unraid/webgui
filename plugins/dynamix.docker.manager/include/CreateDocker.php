<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2015-2018, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2018, Bergware International.
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
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/plugins/dynamix.docker.manager/include/Helpers.php";
require_once "$docroot/webGui/include/Helpers.php";

libxml_use_internal_errors(false); # Enable xml errors

$var = parse_ini_file('state/var.ini');
$cfg = parse_ini_file('boot/config/docker.cfg');
ignore_user_abort(true);

$DockerClient = new DockerClient();
$DockerUpdate = new DockerUpdate();
$DockerTemplates = new DockerTemplates();

#   ███████╗██╗   ██╗███╗   ██╗ ██████╗████████╗██╗ ██████╗ ███╗   ██╗███████╗
#   ██╔════╝██║   ██║████╗  ██║██╔════╝╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
#   █████╗  ██║   ██║██╔██╗ ██║██║        ██║   ██║██║   ██║██╔██╗ ██║███████╗
#   ██╔══╝  ██║   ██║██║╚██╗██║██║        ██║   ██║██║   ██║██║╚██╗██║╚════██║
#   ██║     ╚██████╔╝██║ ╚████║╚██████╗   ██║   ██║╚██████╔╝██║ ╚████║███████║
#   ╚═╝      ╚═════╝ ╚═╝  ╚═══╝ ╚═════╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

$custom = DockerUtil::custom();
$subnet = DockerUtil::network($custom);
$cpus   = cpu_list();

function cpu_pinning() {
  global $xml,$cpus;
  $vcpu = explode(',',$xml['CPUset'] ?? '');
  $total = count($cpus);
  $loop = floor(($total-1)/16)+1;
  for ($c = 0; $c < $loop; $c++) {
    $row1 = $row2 = [];
    $max = ($c == $loop-1 ? ($total%16?:16) : 16);
    for ($n = 0; $n < $max; $n++) {
      unset($cpu1,$cpu2);
      list($cpu1, $cpu2) = preg_split('/[,-]/',$cpus[$c*16+$n]);
      $check1 = in_array($cpu1, $vcpu) ? ' checked':'';
      $check2 = $cpu2 ? (in_array($cpu2, $vcpu) ? ' checked':''):'';
      $row1[] = "<label id='cpu$cpu1' class='checkbox'>$cpu1<input type='checkbox' id='box$cpu1'$check1><span class='checkmark'></span></label>";
      if ($cpu2) $row2[] = "<label id='cpu$cpu2' class='checkbox'>$cpu2<input type='checkbox' id='box$cpu2'$check2><span class='checkmark'></span></label>";
    }
    if ($c) echo '<hr>';
    echo "<span class='cpu'>CPU:</span>".implode($row1);
    if ($row2) echo "<br><span class='cpu'>HT:</span>".implode($row2);
  }
}

#    ██████╗ ██████╗ ██████╗ ███████╗
#   ██╔════╝██╔═══██╗██╔══██╗██╔════╝
#   ██║     ██║   ██║██║  ██║█████╗
#   ██║     ██║   ██║██║  ██║██╔══╝
#   ╚██████╗╚██████╔╝██████╔╝███████╗
#    ╚═════╝ ╚═════╝ ╚═════╝ ╚══════╝

##########################
##   CREATE CONTAINER   ##
##########################

if (isset($_POST['contName'])) {
  $postXML = postToXML($_POST, true);
  $dry_run = $_POST['dryRun']=='true' ? true : false;
  $existing = $_POST['existingContainer'] ?? false;
  $create_paths = $dry_run ? false : true;
  // Get the command line
  list($cmd, $Name, $Repository) = xmlToCommand($postXML, $create_paths);
  readfile("$docroot/plugins/dynamix.docker.manager/log.htm");
  @flush();
  // Saving the generated configuration file.
  $userTmplDir = $dockerManPaths['templates-user'];
  if (!is_dir($userTmplDir)) mkdir($userTmplDir, 0777, true);
  if ($Name) {
    $filename = sprintf('%s/my-%s.xml', $userTmplDir, $Name);
    file_put_contents($filename, $postXML);
  }
  // Run dry
  if ($dry_run) {
    echo "<h2>XML</h2>";
    echo "<pre>".htmlspecialchars($postXML)."</pre>";
    echo "<h2>COMMAND:</h2>";
    echo "<pre>".htmlspecialchars($cmd)."</pre>";
    echo "<div style='text-align:center'><button type='button' onclick='window.location=window.location.pathname+window.location.hash+\"?xmlTemplate=edit:${filename}\"'>Back</button>";
    echo "<button type='button' onclick='done()'>Done</button></div><br>";
    goto END;
  }
  // Will only pull image if it's absent
  if (!$DockerClient->doesImageExist($Repository)) {
    // Pull image
    if (!pullImage($Name, $Repository)) {
      echo '<div style="text-align:center"><button type="button" onclick="done()">Done</button></div><br>';
      goto END;
    }
  }
  $startContainer = true;
  // Remove existing container
  if ($DockerClient->doesContainerExist($Name)) {
    // attempt graceful stop of container first
    $oldContainerInfo = $DockerClient->getContainerDetails($Name);
    if (!empty($oldContainerInfo) && !empty($oldContainerInfo['State']) && !empty($oldContainerInfo['State']['Running'])) {
      // attempt graceful stop of container first
      stopContainer($Name);
    }
    // force kill container if still running after 10 seconds
    removeContainer($Name);
  }
  // Remove old container if renamed
  if ($existing && $DockerClient->doesContainerExist($existing)) {
    // determine if the container is still running
    $oldContainerInfo = $DockerClient->getContainerDetails($existing);
    if (!empty($oldContainerInfo) && !empty($oldContainerInfo['State']) && !empty($oldContainerInfo['State']['Running'])) {
      // attempt graceful stop of container first
      stopContainer($existing);
    } else {
      // old container was stopped already, ensure newly created container doesn't start up automatically
      $startContainer = false;
    }
    // force kill container if still running after 10 seconds
    removeContainer($existing,1);
    // remove old template
    if (strtolower($filename) != strtolower("$userTmplDir/my-$existing.xml")) {
      @unlink("$userTmplDir/my-$existing.xml");
    }
  }
  if ($startContainer) $cmd = str_replace('/docker create ', '/docker run -d ', $cmd);
  execCommand($cmd);
 echo '<div style="text-align:center"><button type="button" onclick="done()">Done</button></div><br>';
  goto END;
}

##########################
##   UPDATE CONTAINER   ##
##########################

if ($_GET['updateContainer']){
  readfile("$docroot/plugins/dynamix.docker.manager/log.htm");
  @flush();
  foreach ($_GET['ct'] as $value) {
    $tmpl = $DockerTemplates->getUserTemplate(urldecode($value));
    if (!$tmpl) {
      echo "<script>addLog('<p>Configuration not found. Was this container created using this plugin?</p>');</script>";
      @flush();
      continue;
    }
    $xml = file_get_contents($tmpl);
    list($cmd, $Name, $Repository) = xmlToCommand($tmpl);
    $Registry = getXmlVal($xml, "Registry");
    $oldImageID = $DockerClient->getImageID($Repository);
    // Pull image
    if (!pullImage($Name, $Repository)) continue;
    $oldContainerInfo = $DockerClient->getContainerDetails($Name);
    // determine if the container is still running
    if (!empty($oldContainerInfo) && !empty($oldContainerInfo['State']) && !empty($oldContainerInfo['State']['Running'])) {
      // since container was already running, put it back it to a running state after update
      $cmd = str_replace('/plugins/dynamix.docker.manager/scripts/docker create ', '/plugins/dynamix.docker.manager/scripts/docker run -d ', $cmd);
      // attempt graceful stop of container first
      stopContainer($Name);
    }
    // force kill container if still running after 10 seconds
    if ( ! $_GET['communityApplications'] ) {
      removeContainer($Name);
    }
    execCommand($cmd);
    $DockerClient->flushCaches();
    $newImageID = $DockerClient->getImageID($Repository);
    if ($oldImageID && $oldImageID != $newImageID) {
      // remove old orphan image since it's no longer used by this container
      removeImage($oldImageID);
    }
  }
  echo '<div style="text-align:center"><button type="button" onclick="window.parent.jQuery(\'#iframe-popup\').dialog(\'close\')">Done</button></div><br>';
  goto END;
}

#########################
##   REMOVE TEMPLATE   ##
#########################

if ($_GET['rmTemplate']) {
  unlink($_GET['rmTemplate']);
}

#########################
##    LOAD TEMPLATE    ##
#########################

if ($_GET['xmlTemplate']) {
  list($xmlType, $xmlTemplate) = explode(':', urldecode($_GET['xmlTemplate']));
  if (is_file($xmlTemplate)) {
    $xml = xmlToVar($xmlTemplate);
    $templateName = $xml['Name'];
    if ($xmlType == 'default') {
      if (!empty($dockercfg['DOCKER_APP_CONFIG_PATH']) && file_exists($dockercfg['DOCKER_APP_CONFIG_PATH'])) {
        // override /config
        foreach ($xml['Config'] as &$arrConfig) {
          if ($arrConfig['Type'] == 'Path' && strtolower($arrConfig['Target']) == '/config') {
            $arrConfig['Default'] = $arrConfig['Value'] = realpath($dockercfg['DOCKER_APP_CONFIG_PATH']).'/'.$xml['Name'];
            if (empty($arrConfig['Display']) || preg_match("/^Host Path\s\d/", $arrConfig['Name'])) {
              $arrConfig['Display'] = 'advanced-hide';
            }
            if (empty($arrConfig['Name']) || preg_match("/^Host Path\s\d/", $arrConfig['Name'])) {
              $arrConfig['Name'] = 'AppData Config Path';
            }
          }
        }
      }
      if (!empty($dockercfg['DOCKER_APP_UNRAID_PATH']) && file_exists($dockercfg['DOCKER_APP_UNRAID_PATH'])) {
        // override /unraid
        $boolFound = false;
        foreach ($xml['Config'] as &$arrConfig) {
          if ($arrConfig['Type'] == 'Path' && strtolower($arrConfig['Target']) == '/unraid') {
            $arrConfig['Default'] = $arrConfig['Value'] = realpath($dockercfg['DOCKER_APP_UNRAID_PATH']);
            $arrConfig['Display'] = 'hidden';
            $arrConfig['Name'] = 'Unraid Share Path';
            $boolFound = true;
          }
        }
        if (!$boolFound) {
          $xml['Config'][] = [
            'Name'        => 'Unraid Share Path',
            'Target'      => '/unraid',
            'Default'     => realpath($dockercfg['DOCKER_APP_UNRAID_PATH']),
            'Value'       => realpath($dockercfg['DOCKER_APP_UNRAID_PATH']),
            'Mode'        => 'rw',
            'Description' => '',
            'Type'        => 'Path',
            'Display'     => 'hidden',
            'Required'    => 'false',
            'Mask'        => 'false'
          ];
        }
      }
    }
    $xml['Description'] = str_replace(['[', ']'], ['<', '>'], $xml['Overview']);
    echo "<script>var Settings=".json_encode($xml).";</script>";
  }
}
echo "<script>var Allocations=".json_encode(getAllocations()).";</script>";
$authoringMode = $dockercfg['DOCKER_AUTHORING_MODE'] == "yes" ? true : false;
$authoring     = $authoringMode ? 'advanced' : 'noshow';
$disableEdit   = $authoringMode ? 'false' : 'true';
$showAdditionalInfo = '';
$bgcolor = strstr('white,azure',$display['theme']) ? '#f2f2f2' : '#1c1c1c';
?>
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/jquery.ui.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/jquery.switchbutton.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/jquery.filetree.css")?>">
<link rel="stylesheet" type="text/css" href="<?autov("/plugins/dynamix.docker.manager/styles/style-{$display['theme']}.css")?>">
<style>
option.list{padding:0 0 0 7px}
optgroup.bold{font-weight:bold;margin-top:5px}
optgroup.title{background-color:#625D5D;color:#f2f2f2;text-align:center;margin-top:10px}
.textTemplate{width:60%}
.fileTree{width:240px;max-height:200px;overflow-y:scroll;overflow-x:hidden;position:absolute;z-index:100;display:none;background:<?=$bgcolor?>}
.show{display:block}
.basic{display:table-row}
.advanced{display:none}
.noshow{display:none}
.required:after{content:" *";color:#E80000}
.inline_help{font-weight:normal}
.switch-wrapper{display:inline-block;position:relative;top:3px;vertical-align:middle;margin-top:-30px}
.switch-button-label.off{color:inherit;}
.selectVariable{width:320px}
.fa.button{color:maroon;font-size:2.4rem;position:relative;top:4px;cursor:pointer}
.spacer{padding:16px 0}
span.cpu,label.checkbox{display:inline-block;width:32px}
button[type=button]{margin:0 20px 0 0}
</style>
<script src="<?autov('/webGui/javascript/jquery.switchbutton.js')?>"></script>
<script src="<?autov('/webGui/javascript/jquery.filetree.js')?>"></script>
<script src="<?autov('/plugins/dynamix.vm.manager/javascript/dynamix.vm.manager.js')?>"></script>
<script type="text/javascript">
  var this_tab = $('input[name$="tabs"]').length;
  $(function() {
    var content= "<div class='switch-wrapper'><input type='checkbox' class='advanced-switch'></div>";
    <?if (!$tabbed):?>
    $("#docker_tabbed").html(content);
    <?else:?>
    var last = $('input[name$="tabs"]').length;
    var elementId = "normalAdvanced";
    $('.tabs').append("<span id='"+elementId+"' class='status vhshift' style='display:none;'>"+content+"&nbsp;</span>");
    if ($('#tab'+this_tab).is(':checked')) {
      $('#'+elementId).show();
    }
    $('#tab'+this_tab).bind({click:function(){$('#'+elementId).show();}});
    for (var x=1; x<=last; x++) if(x != this_tab) $('#tab'+x).bind({click:function(){$('#'+elementId).hide();}});
    <?endif;?>
    $('.advanced-switch').switchButton({labels_placement: "left", on_label: 'Advanced View', off_label: 'Basic View'});
    $('.advanced-switch').change(function () {
      var status = $(this).is(':checked');
      toggleRows('advanced', status, 'basic');
      load_contOverview();
      $("#catSelect").dropdownchecklist("destroy");
      $("#catSelect").dropdownchecklist({emptyText:'Select categories...', maxDropHeight:200, width:300, explicitClose:'...close'});
    });
  });

  var confNum = 0;

  if (!Array.prototype.forEach) {
    Array.prototype.forEach = function(fn, scope) {
      for (var i = 0, len = this.length; i < len; ++i) {
        fn.call(scope, this[i], i, this);
      }
    };
  }

  if (!String.prototype.format) {
    String.prototype.format = function() {
      var args = arguments;
      return this.replace(/{(\d+)}/g, function(match, number) {
        return typeof args[number] != 'undefined' ? args[number] : match;
      });
    };
  }
  if (!String.prototype.replaceAll) {
    String.prototype.replaceAll = function(str1, str2, ignore) {
      return this.replace(new RegExp(str1.replace(/([\/\,\!\\\^\$\{\}\[\]\(\)\.\*\+\?\|\<\>\-\&])/g,"\\$&"),(ignore?"gi":"g")),(typeof(str2)=="string")?str2.replace(/\$/g,"$$$$"):str2);
    };
  }
  // Create config nodes using templateDisplayConfig
  function makeConfig(opts) {
    confNum += 1;
    var newConfig = $("#templateDisplayConfig").html();
    newConfig = newConfig.format(opts.Name,
                                 opts.Target,
                                 opts.Default,
                                 opts.Mode,
                                 opts.Description,
                                 opts.Type,
                                 opts.Display,
                                 opts.Required,
                                 opts.Mask,
                                 escapeQuote(opts.Value),
                                 opts.Buttons,
                                 (opts.Required == "true") ? "required" : ""
                                );
    newConfig = "<div id='ConfigNum"+opts.Number+"' class='config_"+opts.Display+"'' >"+newConfig+"</div>";
    newConfig = $($.parseHTML(newConfig));
    value     = newConfig.find("input[name='confValue[]']");
    if (opts.Type == "Path") {
      value.attr("onclick", "openFileBrowser(this,$(this).val(),'',true,false);");
    } else if (opts.Type == "Device") {
      value.attr("onclick", "openFileBrowser(this,$(this).val()||'/dev','',false,true);")
    } else if (opts.Type == "Variable" && opts.Default.split("|").length > 1) {
      var valueOpts = opts.Default.split("|");
      var newValue = "<select name='confValue[]' class='selectVariable' default='"+valueOpts[0]+"'>";
      for (var i = 0; i < valueOpts.length; i++) {
        newValue += "<option value='"+valueOpts[i]+"' "+(opts.Value == valueOpts[i] ? "selected" : "")+">"+valueOpts[i]+"</option>";
      }
      newValue += "</select>";
      value.replaceWith(newValue);
    } else if (opts.Type == "Port") {
      value.addClass("numbersOnly");
    }
    if (opts.Mask == "true") {
      value.prop("type", "password");
    }
    return newConfig.prop('outerHTML');
  }
	
  function escapeQuote(string) {
    return string.replace(new RegExp('"','g'),"&quot;");
  }
	
  function makeAllocations(container,current) {
    var html = [];
    for (var i=0,ct; ct=container[i]; i++) {
      var highlight = ct.Name.toLowerCase()==current.toLowerCase() ? "font-weight:bold" : "";
      html.push($("#templateAllocations").html().format(highlight,ct.Name,ct.Port));
    }
    return html.join('');
  }

  function getVal(el, name) {
    var el = $(el).find("*[name="+name+"]");
    if (el.length) {
      return ( $(el).attr('type') == 'checkbox' ) ? ($(el).is(':checked') ? "on" : "off") : $(el).val();
    } else {
      return "";
    }
  }

  function addConfigPopup() {
    var title = 'Add Configuration';
    var popup = $( "#dialogAddConfig" );
    var network = $('select[name="contNetwork"]')[0].selectedIndex;

    // Load popup the popup with the template info
    popup.html($("#templatePopupConfig").html());

    // Add switchButton to checkboxes
    popup.find(".switch").switchButton({labels_placement:"right",on_label:'YES',off_label:'NO'});
    popup.find(".switch-button-background").css("margin-top", "6px");

    // Load Mode field if needed and enable field
    toggleMode(popup.find("*[name=Type]:first"),false);

    // Start Dialog section
    popup.dialog({
      title: title,
      resizable: false,
      width: 900,
      modal: true,
      show : {effect: 'fade' , duration: 250},
      hide : {effect: 'fade' , duration: 250},
      buttons: {
        "Add": function() {
          $(this).dialog("close");
          confNum += 1;
          var Opts = Object;
          var Element = this;
          ["Name","Target","Default","Mode","Description","Type","Display","Required","Mask","Value"].forEach(function(e){
            Opts[e] = getVal(Element, e);
          });
          if (! Opts.Name ){
            Opts.Name = makeName(Opts.Type);
          }
          if (! Opts.Description ) {
            Opts.Description = "Container "+Opts.Type+": "+Opts.Target;
          }
          if (Opts.Required == "true") {
            Opts.Buttons  = "<span class='advanced'><button type='button' onclick='editConfigPopup("+confNum+",false)'>Edit</button>";
            Opts.Buttons += "<button type='button' onclick='removeConfig("+confNum+")'>Remove</button></span>";
          } else {
            Opts.Buttons  = "<button type='button' onclick='editConfigPopup("+confNum+",false)'>Edit</button>";
            Opts.Buttons += "<button type='button' onclick='removeConfig("+confNum+")'>Remove</button>";
          }
          Opts.Number = confNum;
          newConf = makeConfig(Opts);
          $("#configLocation").append(newConf);
          reloadTriggers();
          $('input[name="contName"]').trigger('change'); // signal change
        },
        Cancel: function() {
          $(this).dialog("close");
        }
      }
    });
    $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
    $(".ui-dialog .ui-dialog-title").css('text-align','center').css( 'width', "100%");
    $(".ui-dialog .ui-dialog-content").css('padding-top','15px').css('vertical-align','bottom');
    $(".ui-button-text").css('padding','0px 5px');
  }

  function editConfigPopup(num,disabled) {
    var title = 'Edit Configuration';
    var popup = $("#dialogAddConfig");
    var network = $('select[name="contNetwork"]')[0].selectedIndex;

    // Load popup the popup with the template info
    popup.html($("#templatePopupConfig").html());

    // Load existing config info
    var config = $("#ConfigNum"+num);
    config.find("input").each(function(){
      var name = $(this).attr("name").replace("conf", "").replace("[]", "");
      popup.find("*[name='"+name+"']").val($(this).val());
    });

    // Hide passwords if needed
    if (popup.find("*[name='Mask']").val() == "true") {
      popup.find("*[name='Value']").prop("type", "password");
    }

    // Load Mode field if needed
    var mode = config.find("input[name='confMode[]']").val();
    toggleMode(popup.find("*[name=Type]:first"),disabled);
    popup.find("*[name=Mode]:first").val(mode);

    // Add switchButton to checkboxes
    popup.find(".switch").switchButton({labels_placement:"right",on_label:'YES',off_label:'NO'});

    // Start Dialog section
    popup.find(".switch-button-background").css("margin-top", "6px");
    popup.dialog({
      title: title,
      resizable: false,
      width: 900,
      modal: true,
      show : {effect: 'fade' , duration: 250},
      hide : {effect: 'fade' , duration: 250},
      buttons: {
        "Save": function() {
          $(this).dialog("close");
          var Opts = Object;
          var Element = this;
          ["Name","Target","Default","Mode","Description","Type","Display","Required","Mask","Value"].forEach(function(e){
            Opts[e] = getVal(Element, e);
          });
          if (Opts.Display == "always-hide" || Opts.Display == "advanced-hide") {
            Opts.Buttons  = "<span class='advanced'><button type='button' onclick='editConfigPopup("+num+",<?=$disableEdit?>)'>Edit</button>";
            Opts.Buttons += "<button type='button' onclick='removeConfig("+num+")'>Remove</button></span>";
          } else {
            Opts.Buttons  = "<button type='button' onclick='editConfigPopup("+num+",<?=$disableEdit?>)'>Edit</button>";
            Opts.Buttons += "<button type='button' onclick='removeConfig("+num+")'>Remove</button>";
          }
          if (! Opts.Name ){
            Opts.Name = makeName(Opts.Type);
          }
          if (! Opts.Description ) {
            Opts.Description = "Container "+Opts.Type+": "+Opts.Target;
          }
          Opts.Number = num;
          newConf = makeConfig(Opts);
          if (config.hasClass("config_"+Opts.Display)) {
            config.html(newConf);
            config.removeClass("config_always config_always-hide config_advanced config_advanced-hide").addClass("config_"+Opts.Display);
          } else {
            config.remove();
            if (Opts.Display == 'advanced' || Opts.Display == 'advanced-hide') {
              $("#configLocationAdvanced").append(newConf);
            } else {
              $("#configLocation").append(newConf);
            }
          }
         reloadTriggers();
          $('input[name="contName"]').trigger('change'); // signal change
        },
        Cancel: function() {
          $(this).dialog("close");
        }
      }
    });
    $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
    $(".ui-dialog .ui-dialog-title").css('text-align','center').css( 'width', "100%");
    $(".ui-dialog .ui-dialog-content").css('padding-top','15px').css('vertical-align','bottom');
    $(".ui-button-text").css('padding','0px 5px');
    $('.desc_readmore').readmore({maxHeight:10});
  }

  function removeConfig(num) {
    $('#ConfigNum'+num).fadeOut("fast", function() {$(this).remove();});
    $('input[name="contName"]').trigger('change'); // signal change
  }

  function prepareConfig(form) {
    var types = [], values = [], targets = [];
    if ($('select[name="contNetwork"]').val()=='host') {
      $(form).find('input[name="confType[]"]').each(function(){types.push($(this).val());});
      $(form).find('input[name="confValue[]"]').each(function(){values.push($(this));});
      $(form).find('input[name="confTarget[]"]').each(function(){targets.push($(this));});
      for (var i=0; i < types.length; i++) if (types[i]=='Port') $(targets[i]).val($(values[i]).val());
    }
    var vcpu = [];
    $(form).find('input[id^="box"]').each(function(){if ($(this).prop('checked')) vcpu.push($('#'+$(this).prop('id').replace('box','cpu')).text());});
    form.contCPUset.value = vcpu.join(',');
  }

  function makeName(type) {
    i = $("#configLocation input[name^='confType'][value='"+type+"']").length+1;
    return "Host "+type.replace('Variable','Key')+" "+i;
  }

  function toggleMode(el,disabled) {
    var mode       = $(el).parent().siblings('#Mode');
    var valueDiv   = $(el).parent().siblings('#Value');
    var defaultDiv = $(el).parent().siblings('#Default');
    var targetDiv  = $(el).parent().siblings('#Target');

    var value      = valueDiv.find('input[name=Value]');
    var target     = targetDiv.find('input[name=Target]');
    var network    = $('select[name="contNetwork"]')[0].selectedIndex;

    value.unbind();
    target.unbind();

    valueDiv.css('display', '');
    defaultDiv.css('display', '');
    targetDiv.css('display', '');
    mode.html('');

    $(el).prop('disabled',disabled);
    switch ($(el)[0].selectedIndex) {
    case 0: // Path
      mode.html("<dt>Access Mode:</dt><dd><select name='Mode'><option value='rw'>Read/Write</option><option value='rw,slave'>RW/Slave</option><option value='rw,shared'>RW/Shared</option><option value='ro'>Read Only</option><option value='ro,slave'>RO/Slave</option><option value='ro,shared'>RO/Shared</option></select></dd>");
      value.bind("click", function(){openFileBrowser(this,$(this).val(), 'sh', true, false);});
      targetDiv.find('#dt1').text('Container Path:');
      valueDiv.find('#dt2').text('Host Path:');
      break;
    case 1: // Port
      mode.html("<dt>Connection Type:</dt><dd><select name='Mode'><option value='tcp'>TCP</option><option value='udp'>UDP</option></select></dd>");
      value.addClass("numbersOnly");
      if (network==0) {
        if (target.val()) target.prop('disabled',<?=$disableEdit?>); else target.addClass("numbersOnly");
        targetDiv.find('#dt1').text('Container Port:');
        targetDiv.show();
      } else {
        targetDiv.hide();
      }
      if (network==0 || network==1 || network>2) {
        valueDiv.find('#dt2').text('Host Port:');
        valueDiv.show();
      } else {
        valueDiv.hide();
        mode.html('');
      }
      break;
    case 2: // Variable
      targetDiv.find('#dt1').text('Key:');
      valueDiv.find('#dt2').text('Value:');
      break;
    case 3: // Label
      targetDiv.find('#dt1').text('Key:');
      valueDiv.find('#dt2').text('Value:');
      break;
    case 4: // Device
      targetDiv.hide();
      defaultDiv.hide();
      valueDiv.find('#dt2').text('Value:');
      value.bind("click", function(){openFileBrowser(this,$(this).val()||'/dev', '', true, true);});
      break;
    }
    reloadTriggers();
  }

  function loadTemplate(el) {
    var template = $(el).val();
    if (template.length) {
      $('#formTemplate').find("input[name='xmlTemplate']").val(template);
      $('#formTemplate').submit();
    }
  }

  function rmTemplate(tmpl) {
    var name = tmpl.split(/[\/]+/).pop();
    swal({title:"Are you sure?",text:"Remove template: "+name,type:"warning",showCancelButton:true},function(){$("#rmTemplate").val(tmpl);$("#formTemplate").submit();});
  }

  function openFileBrowser(el, root, filter, on_folders, on_files, close_on_select) {
    if (on_folders === undefined) on_folders = true;
    if (on_files   === undefined) on_files = true;
    if (!filter && !on_files) filter = 'HIDE_FILES_FILTER';
    if (!root.trim()) root = "/mnt/user/";
    p = $(el);
    // Skip is fileTree is already open
    if (p.next().hasClass('fileTree')) return null;
    // create a random id
    var r = Math.floor((Math.random()*1000)+1);
    // Add a new span and load fileTree
    p.after("<span id='fileTree"+r+"' class='textarea fileTree'></span>");
    var ft = $('#fileTree'+r);
    ft.fileTree({
      root: root,
      filter: filter,
      allowBrowsing: true
    },
    function(file){if(on_files){p.val(file);p.trigger('change');if(close_on_select){ft.slideUp('fast',function(){ft.remove();});}}},
    function(folder){if(on_folders){p.val(folder);p.trigger('change');if(close_on_select){$(ft).slideUp('fast',function (){$(ft).remove();});}}}
    );
    // Format fileTree according to parent position, height and width
    ft.css({'left':p.position().left,'top':(p.position().top+p.outerHeight()),'width':(p.width())});
    // close if click elsewhere
    $(document).mouseup(function(e){if(!ft.is(e.target) && ft.has(e.target).length === 0){ft.slideUp('fast',function (){$(ft).remove();});}});
    // close if parent changed
    p.bind("keydown", function(){ft.slideUp('fast', function (){$(ft).remove();});});
    // Open fileTree
    ft.slideDown('fast');
  }

  function resetField(el) {
    var target = $(el).prev();
    reset = target.attr("default");
    if (reset.length) {
      target.val(reset);
    }
  }

  function prepareCategory() {
    var values = $.map($('#catSelect option') ,function(option) {
      if ($(option).is(":selected")) {
        return option.value;
      }
    });
    $("input[name='contCategory']").val(values.join(" "));
  }
</script>
<div id="docker_tabbed" style="float:right;margin-top:-47px"></div>
<div id="dialogAddConfig" style="display:none"></div>
<form method="GET" id="formTemplate">
  <input type="hidden" id="xmlTemplate" name="xmlTemplate" value="" />
  <input type="hidden" id="rmTemplate" name="rmTemplate" value="" />
</form>

<div id="canvas">
  <form method="POST" autocomplete="off" onsubmit="prepareConfig(this)">
    <input type="hidden" name="csrf_token" value="<?=$var['csrf_token']?>">
    <input type="hidden" name="contCPUset" value="">
    <table class="settings">
      <? if ($xmlType == 'edit'):
      if ($DockerClient->doesContainerExist($templateName)): echo "<input type='hidden' name='existingContainer' value='${templateName}'>\n"; endif;
      else:?>
      <tr class='TemplateDropDown'>
        <td>Template:</td>
        <td>
          <select id="TemplateSelect" size="1" onchange="loadTemplate(this);">
            <option value="">Select a template</option>
            <?
            $rmadd = '';
            $templates = [];
            $templates['default'] = $DockerTemplates->getTemplates('default');
            $templates['user'] = $DockerTemplates->getTemplates('user');
            foreach ($templates as $section => $template) {
              $title = ucfirst($section)." templates";
              printf("<optgroup class='title bold' label='[ %s ]'>", htmlspecialchars($title));
              foreach ($template as $value){
                $name = str_replace('my-', '', $value['name']);
                $selected = (isset($xmlTemplate) && $value['path']==$xmlTemplate) ? ' selected ' : '';
                if ($selected && $section=='default') $showAdditionalInfo = 'class="advanced"';
                if ($selected && $section=='user') $rmadd = $value['path'];
                printf("<option class='list' value='%s:%s' $selected>%s</option>", htmlspecialchars($section), htmlspecialchars($value['path']), htmlspecialchars($name));
              }
              if (!$template) echo("<option class='list' disabled>&lt;none&gt;</option>");
              printf("</optgroup>");
            }
            ?>
          </select>
          <?if ($rmadd) {
            echo "<i class='fa fa-window-close button' title=\"".htmlspecialchars($rmadd)."\" onclick='rmTemplate(\"".addslashes(htmlspecialchars($rmadd))."\")'></i>";
          }?>
        </td>
      </tr>
      <tr>
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Templates are a quicker way to setting up Docker Containers on your Unraid server.  There are two types of templates:</p>

            <p>
              <b>Default templates</b><br>
              When valid repositories are added to your Docker Repositories page, they will appear in a section on this drop down for you to choose (master categorized by author, then by application template).
              After selecting a default template, the page will populate with new information about the application in the Description field, and will typically provide instructions for how to setup the container.
              Select a default template when it is the first time you are configuring this application.
            </p>

            <p>
              <b>User-defined templates</b><br>
              Once you've added an application to your system through a Default template,
              the settings you specified are saved to your USB flash device to make it easy to rebuild your applications in the event an upgrade were to fail or if another issue occurred.
              To rebuild, simply select the previously loaded application from the User-defined list and all the settings for the container will appear populated from your previous setup.
              Clicking create will redownload the necessary files for the application and should restore you to a working state.
              To delete a User-defined template, select it from the list above and click the red X to the right of it.
            </p>
          </blockquote>
        </td>
      </tr>
      <?endif;?>
      <tr <?=$showAdditionalInfo?>>
        <td>Name:</td>
        <td><input type="text" name="contName" required></td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Give the container a name or leave it as default.</p>
          </blockquote>
        </td>
      </tr>
      <tr id="Overview" class="basic">
        <td>Overview:</td>
        <td><div id="contDescription" class="blue-text textTemplate"></div></td>
      </tr>
      <tr id="Overview" class="advanced">
        <td>Overview:</td>
        <td><textarea name="contOverview" rows="10" class="textTemplate"></textarea></td>
      </tr>
      <tr>
        <td colspan="2">
          <blockquote class="inline_help">
            <p>A description for the application container.  Supports basic HTML mark-up.</p>
          </blockquote>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td>Repository:</td>
        <td><input type="text" name="contRepository" required></td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2">
          <blockquote class="inline_help">
            <p>The repository for the application on the Docker Registry.  Format of authorname/appname.
            Optionally you can add a : after appname and request a specific version for the container image.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td>Categories:</td>
        <td>
          <input type="hidden" name="contCategory">
          <select id="catSelect" size="1" multiple="multiple" style="display:none" onchange="prepareCategory();">
            <optgroup label="Categories">
              <option value="Backup:">Backup</option>
              <option value="Cloud:">Cloud</option>
              <option value="Downloaders:">Downloaders</option>
              <option value="GameServers:">Game Servers</option>
              <option value="HomeAutomation:">HomeAutomation</option>
              <option value="Productivity:">Productivity</option>
              <option value="Tools:">Tools</option>
              <option value="Other:">Other</option>
            </optgroup>
            <optgroup label="MediaApp">
              <option value="MediaApp:Video">MediaApp:Video</option>
              <option value="MediaApp:Music">MediaApp:Music</option>
              <option value="MediaApp:Books">MediaApp:Books</option>
              <option value="MediaApp:Photos">MediaApp:Photos</option>
              <option value="MediaApp:Other">MediaApp:Other</option>
            </optgroup>
            <optgroup label="MediaServer">
              <option value="MediaServer:Video">MediaServer:Video</option>
              <option value="MediaServer:Music">MediaServer:Music</option>
              <option value="MediaServer:Books">MediaServer:Books</option>
              <option value="MediaServer:Photos">MediaServer:Photos</option>
              <option value="MediaServer:Other">MediaServer:Other</option>
            </optgroup>
            <optgroup label="Network">
              <option value="Network:Web">Network:Web</option>
              <option value="Network:DNS">Network:DNS</option>
              <option value="Network:FTP">Network:FTP</option>
              <option value="Network:Proxy">Network:Proxy</option>
              <option value="Network:Voip">Network:Voip</option>
              <option value="Network:Management">Network:Management</option>
              <option value="Network:Other">Network:Other</option>
              <option value="Network:Messenger">Network:Messenger</option>
            </optgroup>
            <optgroup label="Development Status">
              <option value="Status:Stable">Status:Stable</option>
              <option value="Status:Beta">Status:Beta</option>
            </optgroup>
          </select>
        </td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td>Support Thread:</td>
        <td><input type="text" name="contSupport"></td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Link to a support thread on Lime-Technology's forum.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td>Project Page:</td>
        <td><input type="text" name="contProject"></td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Link to the project page (eg: www.plex.tv)</p>
          </blockquote>
        </td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td>Donation Text:</td>
        <td><input type="text" name="contDonateText"></td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Text to appear on Donation Links Within The Apps Tab</p>
          </blockquote>
        </td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td>Donation Link:</td>
        <td><input type="text" name="contDonateLink"></td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Link to the donation page.  If using donation's, both the image and link must be set</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Docker Hub URL:</td>
        <td><input type="text" name="contRegistry"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>The path to the container's repository location on the Docker Hub.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td>Template URL:</td>
        <td><input type="text" name="contTemplateURL"></td>
      </tr>
      <tr class="<?=$authoring;?>">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>This URL is used to keep the template updated.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Icon URL:</td>
        <td><input type="text" name="contIcon"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Link to the icon image for your application (only displayed on dashboard if Show Dashboard apps under Display Settings is set to Icons).</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>WebUI:</td>
        <td><input type="text" name="contWebUI"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>When you click on an application icon from the Docker Containers page, the WebUI option will link to the path in this field.
            Use [IP] to identify the IP of your host and [PORT:####] replacing the #'s for your port.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Extra Parameters:</td>
        <td><input type="text" name="contExtraParams"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>If you wish to append additional commands to your Docker container at run-time, you can specify them here.<br>
            For all possible Docker run-time commands, see here: <a href="https://docs.docker.com/reference/run/" target="_blank">https://docs.docker.com/reference/run/</a></p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Post Arguments:</td>
        <td><input type="text" name="contPostArgs"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>If you wish to append additional arguments AFTER the container definition, you can specify them here.
            The content of this field is container specific.</p>
          </blockquote>
        </td>
      </tr>

      <tr class="advanced">
        <td>CPU Pinning:</td>
        <td><?cpu_pinning()?></td>
      </tr>
      <tr class="advanced">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Checking a CPU core(s) will limit the container to run on the selected cores only. Selecting no cores lets the container run on all available cores (default)</p>
          </blockquote>
        </td>
      </tr>


      <tr <?=$showAdditionalInfo?>>
        <td>Network Type:</td>
        <td>
          <select name="contNetwork" onchange="showSubnet(this.value)">
          <?=mk_option(1,'bridge','Bridge')?>
          <?=mk_option(1,'host','Host')?>
          <?=mk_option(1,'none','None')?>
          <?foreach ($custom as $network):?>
          <?=mk_option(1,$network,"Custom : $network")?>
          <?endforeach;?>
          </select>
        </td>
      </tr>
      <tr class="myIP" style="display:none">
        <td>Fixed IP address (optional):</td>
        <td><input type="text" name="contMyIP"><span id="myIP"></span></td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2">
          <blockquote class="inline_help">
            <p>If the Bridge type is selected, the application’s network access will be restricted to only communicating on the ports specified in the port mappings section.
            If the Host type is selected, the application will be given access to communicate using any port on the host that isn’t already mapped to another in-use application/service.
            Generally speaking, it is recommended to leave this setting to its default value as specified per application template.</p>
            <p>IMPORTANT NOTE:  If adjusting port mappings, do not modify the settings for the Container port as only the Host port can be adjusted.</p>
          </blockquote>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td>Console shell command:</td>
        <td><select name="contShell">
            <?=mk_option(1,'sh','Shell')?>
            <?=mk_option(1,'bash','Bash')?>
            </select>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td class="spacer">Privileged:</td>
        <td class="spacer"><input type="checkbox" name="contPrivileged" class="switch-on-off"></td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2">
          <blockquote class="inline_help">
            <p>For containers that require the use of host-device access directly or need full exposure to host capabilities, this option will need to be selected.
            <br>For more information, see this link: <a href="https://docs.docker.com/engine/reference/run/#runtime-privilege-and-linux-capabilities" target="_blank">https://docs.docker.com/engine/reference/run/#runtime-privilege-and-linux-capabilities</a></p>
          </blockquote>
        </td>
      </tr>
    </table>
    <div id="configLocation"></div>
    <table class="settings">
      <tr>
        <td></td>
        <td id="readmore_toggle" class="readmore_collapsed"><a onclick="toggleReadmore()" style="cursor:pointer"><i class="fa fa-chevron-down"></i> Show more settings ...</a></td>
      </tr>
    </table>
    <div id="configLocationAdvanced" style="display:none"></div><br>
    <table class="settings">
      <tr>
        <td></td>
        <td id="allocations_toggle" class="readmore_collapsed"><a onclick="toggleAllocations()" style="cursor:pointer"><i class="fa fa-chevron-down"></i> Show docker allocations ...</a></td>
      </tr>
    </table>
    <div id="dockerAllocations" style="display:none"></div><br>
    <table class="settings">
      <tr>
        <td></td>
        <td><a href="javascript:addConfigPopup()"><i class="fa fa-plus"></i> Add another Path, Port, Variable, Label or Device</a></td>
      </tr>
    </table>
    <br>
    <table class="settings">
      <tr>
        <td></td>
        <td>
          <input type="submit" value="<?=$xmlType=='edit' ? 'Apply':' Apply '?>"><input type="button" value="Done" onclick="done()">
          <?if ($authoringMode):?><button type="submit" name="dryRun" value="true" onclick="$('*[required]').prop('required', null);">Save</button><?endif;?>
        </td>
      </tr>
    </table>
    <br><br><br>
  </form>
</div>

<?
#        ██╗███████╗    ████████╗███████╗███╗   ███╗██████╗ ██╗      █████╗ ████████╗███████╗███████╗
#        ██║██╔════╝    ╚══██╔══╝██╔════╝████╗ ████║██╔══██╗██║     ██╔══██╗╚══██╔══╝██╔════╝██╔════╝
#        ██║███████╗       ██║   █████╗  ██╔████╔██║██████╔╝██║     ███████║   ██║   █████╗  ███████╗
#   ██   ██║╚════██║       ██║   ██╔══╝  ██║╚██╔╝██║██╔═══╝ ██║     ██╔══██║   ██║   ██╔══╝  ╚════██║
#   ╚█████╔╝███████║       ██║   ███████╗██║ ╚═╝ ██║██║     ███████╗██║  ██║   ██║   ███████╗███████║
#    ╚════╝ ╚══════╝       ╚═╝   ╚══════╝╚═╝     ╚═╝╚═╝     ╚══════╝╚═╝  ╚═╝   ╚═╝   ╚══════╝╚══════╝
?>
<div id="templatePopupConfig" style="display:none">
  <dl>
    <dt>Config Type:</dt>
    <dd>
      <select name="Type" onchange="toggleMode(this,false);">
        <option value="Path">Path</option>
        <option value="Port">Port</option>
        <option value="Variable">Variable</option>
        <option value="Label">Label</option>
        <option value="Device">Device</option>
      </select>
    </dd>
    <dt>Name:</dt>
    <dd><input type="text" name="Name"></dd>
    <div id="Target">
      <dt id="dt1">Target:</dt>
      <dd><input type="text" name="Target"></dd>
    </div>
    <div id="Value">
      <dt id="dt2">Value:</dt>
      <dd><input type="text" name="Value"></dd>
    </div>
    <div id="Default" class="advanced">
      <dt>Default Value:</dt>
      <dd><input type="text" name="Default"></dd>
    </div>
    <div id="Mode"></div>
    <dt>Description:</dt>
    <dd>
      <textarea name="Description" rows="6" style="width:304px;"></textarea>
    </dd>
    <div class="advanced">
      <dt>Display:</dt>
      <dd>
        <select name="Display">
          <option value="always" selected>Always</option>
          <option value="always-hide">Always - Hide Buttons</option>
          <option value="advanced">Advanced</option>
          <option value="advanced-hide">Advanced - Hide Buttons</option>
        </select>
      </dd>
      <dt>Required:</dt>
      <dd>
        <select name="Required">
          <option value="false" selected>No</option>
          <option value="true">Yes</option>
        </select>
      </dd>
      <div id="Mask">
        <dt>Password Mask:</dt>
        <dd>
          <select name="Mask">
            <option value="false" selected>No</option>
            <option value="true">Yes</option>
          </select>
        </dd>
      </div>
    </div>
  </dl>
</div>

<div id="templateDisplayConfig" style="display:none">
  <input type="hidden" name="confName[]" value="{0}">
  <input type="hidden" name="confTarget[]" value="{1}">
  <input type="hidden" name="confDefault[]" value="{2}">
  <input type="hidden" name="confMode[]" value="{3}">
  <input type="hidden" name="confDescription[]" value="{4}">
  <input type="hidden" name="confType[]" value="{5}">
  <input type="hidden" name="confDisplay[]" value="{6}">
  <input type="hidden" name="confRequired[]" value="{7}">
  <input type="hidden" name="confMask[]" value="{8}">
  <table class="settings">
    <tr>
      <td class="{11}" style="vertical-align:top;">{0}:</td>
      <td>
        <input type="text" name="confValue[]" default="{2}" value="{9}" autocomplete="off" {11}>&nbsp;{10}
        <div class="orange-text">{4}</div>
      </td>
    </tr>
  </table>
</div>

<div id="templateAllocations" style="display:none">
<table class='settings'>
  <tr><td></td><td style="{0}"><span style="width:160px;display:inline-block;padding-left:20px">{1}</span>{2}</td></tr>
</table>
</div>

<script>
  var subnet = {};
<?foreach ($subnet as $network => $value):?>
  subnet['<?=$network?>'] = '<?=$value?>';
<?endforeach;?>

  function showSubnet(bridge) {
    if (bridge.match(/^(bridge|host|none)$/i) !== null) {
      $('.myIP').hide();
      $('input[name="contMyIP"]').val('');
    } else {
      $('.myIP').show();
      $('#myIP').html('Subnet: '+subnet[bridge]);
    }
  }
  function reloadTriggers() {
    $(".basic").toggle(!$(".advanced-switch:first").is(":checked"));
    $(".advanced").toggle($(".advanced-switch:first").is(":checked"));
    $(".numbersOnly").keypress(function(e){if(e.which != 45 && e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)){return false;}});
  }
  function toggleReadmore() {
    var readm = $('#readmore_toggle');
    if ( readm.hasClass('readmore_collapsed') ) {
      readm.removeClass('readmore_collapsed').addClass('readmore_expanded');
      $('#configLocationAdvanced').slideDown('fast');
      readm.find('a').html('<i class="fa fa-chevron-up"></i> Hide more settings ...');
    } else {
      $('#configLocationAdvanced').slideUp('fast');
      readm.removeClass('readmore_expanded').addClass('readmore_collapsed');
      readm.find('a').html('<i class="fa fa-chevron-down"></i> Show more settings ...');
    }
  }
  function toggleAllocations() {
    var readm = $('#allocations_toggle');
    if ( readm.hasClass('readmore_collapsed') ) {
      readm.removeClass('readmore_collapsed').addClass('readmore_expanded');
      $('#dockerAllocations').slideDown('fast');
      readm.find('a').html('<i class="fa fa-chevron-up"></i> Hide docker allocations ...');
    } else {
      $('#dockerAllocations').slideUp('fast');
      readm.removeClass('readmore_expanded').addClass('readmore_collapsed');
      readm.find('a').html('<i class="fa fa-chevron-down"></i> Show docker allocations ...');
    }
  }
  function load_contOverview() {
    var new_overview = $("textarea[name='contOverview']").val();
    new_overview = new_overview.replaceAll("[","<").replaceAll("]",">");
    $("#contDescription").html(new_overview);
  }
  $(function() {
    // Load container info on page load
    if (typeof Settings != 'undefined') {
      for (var key in Settings) {
        if (Settings.hasOwnProperty(key)) {
          var target = $('#canvas').find('*[name=cont'+key+']:first');
          if (target.length) {
            var value = Settings[key];
            if (target.attr("type") == 'checkbox') {
              target.prop('checked', (value == 'true'));
            } else if ($(target).prop('nodeName') == 'DIV') {
              target.html(value);
            } else {
              target.val(value);
            }
          }
        }
      }
      load_contOverview();
      // Load the confCategory input into the s1 select
      categories=$("input[name='contCategory']").val().split(" ");
      for (var i = 0; i < categories.length; i++) {
        $("#catSelect option[value='"+categories[i]+"']").prop("selected", true);
      }
      // Remove empty description
      if (!Settings.Description.length) {
        $('#canvas').find('#Overview:first').hide();
      }
      // Load config info
      var network = $('select[name="contNetwork"]')[0].selectedIndex;
      for (var i = 0; i < Settings.Config.length; i++) {
        confNum += 1;
        Opts = Settings.Config[i];
        if (Opts.Display == "always-hide" || Opts.Display == "advanced-hide") {
          Opts.Buttons  = "<span class='advanced'><button type='button' onclick='editConfigPopup("+confNum+",<?=$disableEdit?>)'>Edit</button>";
          Opts.Buttons += "<button type='button' onclick='removeConfig("+confNum+")'>Remove</button></span>";
        } else {
          Opts.Buttons  = "<button type='button' onclick='editConfigPopup("+confNum+",<?=$disableEdit?>)'>Edit</button>";
          Opts.Buttons += "<button type='button' onclick='removeConfig("+confNum+")'>Remove</button>";
        }
        Opts.Number = confNum;
        newConf = makeConfig(Opts);
        if (Opts.Display == 'advanced' || Opts.Display == 'advanced-hide') {
          $("#configLocationAdvanced").append(newConf);
        } else {
          $("#configLocation").append(newConf);
        }
      }
    } else {
      $('#canvas').find('#Overview:first').hide();
    }
    // Show associated subnet with fixed IP (if existing)
    showSubnet($('select[name="contNetwork"]').val());
    // Add list of docker allocations
    $("#dockerAllocations").html(makeAllocations(Allocations,$('input[name="contName"]').val()));
    // Add switchButton
    $('.switch-on-off').each(function(){var checked = $(this).is(":checked");$(this).switchButton({labels_placement: "right", checked:checked});});
    // Add dropdownchecklist to Select Categories
    $("#catSelect").dropdownchecklist({emptyText:'Select categories...', maxDropHeight:200, width:300, explicitClose:'...close'});
    <?if ($authoringMode){
      echo "$('.advanced-switch').prop('checked','true'); $('.advanced-switch').change();";
      echo "$('.advanced-switch').siblings('.switch-button-background').click();";
    }?>
  });
  if ( window.location.href.indexOf("/Apps/") > 0 ) {
    $(".TemplateDropDown").hide();
  }
</script>
<?END:?>
