function displayconsole(url) {
  window.open(url, '_blank', 'scrollbars=yes,resizable=yes');
}

function displayWebUI(url) {
  window.open(url, '_blank').focus();
}

function downloadFile(source) {
  var a = document.createElement('a');
  a.setAttribute('href',source);
  a.setAttribute('download',source.split('/').pop());
  a.style.display = 'none';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function ajaxVMDispatch(params, spin){
  if (spin) $('#vm-'+params['uuid']).parent().find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
  $.post("/plugins/dynamix.vm.manager/include/VMajax.php", params, function(data) {
    if (data.error) {
      swal({
        title:_("Execution error"), html:true,
        text:data.error, type:"error",
        confirmButtonText:_('Ok')
      },function(){
        if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      });
    } else {
      if (spin) setTimeout(spin+'()',500); else location=window.location.href;
    }
  },'json');
}
function ajaxVMDispatchconsole(params, spin){
  if (spin) $('#vm-'+params['uuid']).parent().find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
  $.post("/plugins/dynamix.vm.manager/include/VMajax.php", params, function(data) {
    if (data.error) {
      swal({
        title:_("Execution error"), html:true,
        text:data.error, type:"error",
        confirmButtonText:_('Ok')
      },function(){
        if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      });
    } else {
      if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      setTimeout('displayconsole("'+data.vmrcurl+'")',500) ;
    }
  },'json');
}
function ajaxVMDispatchconsoleRV(params, spin){
  if (spin) $('#vm-'+params['uuid']).parent().find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
  $.post("/plugins/dynamix.vm.manager/include/VMajax.php", params, function(data) {
    if (data.error) {
      swal({
        title:_("Execution error"), html:true,
        text:data.error, type:"error",
        confirmButtonText:_('Ok')
      },function(){
        if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      });
    } else {
      if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      downloadFile(data.vvfile) ;
    }
  },'json');
}
function ajaxVMDispatchWebUI(params, spin){
  if (spin) $('#vm-'+params['uuid']).parent().find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
  $.post("/plugins/dynamix.vm.manager/include/VMajax.php", params, function(data) {
    if (data.error) {
      swal({
        title:_("Execution error"), html:true,
        text:data.error, type:"error",
        confirmButtonText:_('Ok')
      },function(){
        if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      });
    } else {
      if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      setTimeout('displayWebUI("'+data.vmrcurl+'")',500) ;
    }
  },'json');
}
function addVMContext(name, uuid, template, state, vmrcurl, vmrcprotocol, log, fstype="QEMU",consolein="web;no",usage=false,webui="",pcierror=false){  
  var opts = [];
  var path = location.pathname;
  var x = path.indexOf("?");
  var consolesplit = consolein.split(";");
  var console = consolesplit[0];
  var rdpopt = consolesplit[1];
  var rundivider = false;
  if (x!=-1) path = path.substring(0,x);
  if (vmrcurl !== "" && state == "running")  {
    if (console == "web" || console == "both") {
      var vmrctext=_("VM Console") + " (" + vmrcprotocol + ")" ;
      opts.push({text:vmrctext, icon:"fa-desktop", action:function(e) {
        e.preventDefault();
        window.open(vmrcurl, '_blank', 'scrollbars=yes,resizable=yes');
      }});
    }
    if (console == "remote" || console == "both") {
      opts.push({text:_("VM remote-viewer")+ " (" + vmrcprotocol + ")" , icon:"fa-desktop", action:function(e) {
        e.preventDefault();
        ajaxVMDispatchconsoleRV({action:"domain-consoleRV", uuid:uuid, vmrcurl:vmrcurl}, "loadlist") ;  
      }});
    }  
    rundivider = true;
  }
  if (state == "running") {
    if (webui != "") {
      opts.push({text:_("Open WebUI") , icon:"fa-globe", action:function(e) {
        e.preventDefault();
        ajaxVMDispatchWebUI({action:"domain-openWebUI", uuid:uuid, vmrcurl:webui}, "loadlist") ;  
      }});
      rundivider = true;
    }
    if (rdpopt == "yes") {
      opts.push({text:_("VM Remote Desktop Protocol(RDP)"), icon:"fa-desktop", action:function(e) {
        e.preventDefault();
        ajaxVMDispatchconsoleRV({action:"domain-consoleRDP", uuid:uuid, vmrcurl:vmrcurl}, "loadlist") ;  
      }});
      rundivider = true;
    }
  }
  if (rundivider) opts.push({divider:true});
  context.settings({right:false,above:false});
  if (state == "running") {
    opts.push({text:_("Stop"), icon:"fa-stop", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-stop", uuid:uuid}, "loadlist");
    }});
    opts.push({text:_("Pause"), icon:"fa-pause", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-pause", uuid:uuid}, "loadlist");
    }});
    opts.push({text:_("Restart"), icon:"fa-refresh", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-restart", uuid:uuid}, "loadlist");
    }});
    opts.push({text:_("Hibernate"), icon:"fa-bed", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-pmsuspend", uuid:uuid}, "loadlist");
    }});
    opts.push({text:_("Force Stop"), icon:"fa-bomb", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch( {action:"domain-destroy", uuid:uuid}, "loadlist");
    }});
    opts.push({divider:true});
   
    opts.push({text:_("Create Snapshot"), icon:"fa-clone", action:function(e) {
      e.preventDefault();
      selectsnapshot(uuid , name, "--generate" , "create",false,state,fstype) ;
    }}); 
  } else if (state == "pmsuspended") {
    opts.push({text:_("Resume"), icon:"fa-play", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-pmwakeup", uuid:uuid}, "loadlist");
    }});
    opts.push({text:_("Force Stop"), icon:"fa-bomb", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-destroy", uuid:uuid}, "loadlist");
    }});
  } else if (state == "paused" || state == "unknown") {
    opts.push({text:_("Resume"), icon:"fa-play", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-resume", uuid:uuid}, "loadlist");
    }});
    opts.push({text:_("Force Stop"), icon:"fa-bomb", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-destroy", uuid:uuid}, "loadlist");
    }});
  } else {
    if (!pcierror) {
      opts.push({text:_("Start"), icon:"fa-play", action:function(e) {
        e.preventDefault();
        ajaxVMDispatch({action:"domain-start", uuid:uuid}, "loadlist");
      }});
      if (vmrcprotocol == "VNC" || vmrcprotocol == "SPICE") { 
        if (console == "web" || console == "both")  {
          opts.push({text:_("Start with console")+ " (" + vmrcprotocol + ")" , icon:"fa-play", action:function(e) {
            e.preventDefault();
            ajaxVMDispatchconsole({action:"domain-start-console", uuid:uuid, vmrcurl:vmrcurl}, "loadlist") ;  
          }});}
        if (console == "remote" || console == "both")  {
          opts.push({text:_("Start with remote-viewer")+ " (" + vmrcprotocol + ")" , icon:"fa-play", action:function(e) {
            e.preventDefault();
            ajaxVMDispatchconsoleRV({action:"domain-start-consoleRV", uuid:uuid, vmrcurl:vmrcurl}, "loadlist") ;  
          }});
        }
      }
    } else {
      opts.push({text:_("Start disabled due to PCI Changes"), icon:"fa fa-minus-circle orb red-orb", action:function(e) {
        e.preventDefault();
      }});
    }
  }
  opts.push({divider:true});
  if (log !== "") {
    opts.push({text:_("Logs"), icon:"fa-navicon", action:function(e){e.preventDefault(); openTerminal('log',name,log);}});
  }
  opts.push({text:_("Edit"), icon:"fa-pencil", href:path+'/UpdateVM?uuid='+uuid});

  if (state == "shutoff") {

    opts.push({text:_("Clone"), icon:"fa-clone", action:function(e) {
      e.preventDefault();
      var clonename = VMClone(uuid,name) ;
    }});

    opts.push({divider:true});
    opts.push({text:_("Create Snapshot"), icon:"fa-clone", action:function(e) {
      e.preventDefault();
      selectsnapshot(uuid , name, "--generate" , "create",false,state,fstype) ;
    }});
    opts.push({text:_("Remove VM"), icon:"fa-minus", action:function(e) {
      e.preventDefault();
      swal({
        title:_("Are you sure?"),
        text:_("Remove definition:")+name,
        type:"warning",
        showCancelButton:true,
        confirmButtonText:_('Proceed'),
        cancelButtonText:_('Cancel')
      },function(){
        $('#vm-'+uuid).find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
        ajaxVMDispatch({action:"domain-undefine",uuid:uuid}, "loadlist");
      });
    }});
    if (template != 'OpenELEC') {
      opts.push({text:_("Remove VM")+" & "+_("Disks"), icon:"fa-trash", action:function(e) {
        e.preventDefault();
        swal({
          title:_("Are you sure?"),
          text:_("Completely REMOVE")+" "+name+" "+_("disk image and definition"),
          type:"warning",
          showCancelButton:true,
          confirmButtonText:_('Proceed'),
          cancelButtonText:_('Cancel')
        },function(){
          $('#vm-'+uuid).find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
          ajaxVMDispatch({action:"domain-delete",uuid:uuid}, "loadlist");
        });
      }});
    }
  }
  if (usage) { context.destroy('#vmusage-'+uuid); context.attach('#vmusage-'+uuid, opts); } else { context.destroy('#vm-'+uuid); context.attach('#vm-'+uuid, opts); }
}
function addVMSnapContext(name, uuid, template, state, snapshotname, method){  
  var opts = [];
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);

  context.settings({right:false,above:false});


    opts.push({text:_("Revert snapshot"), icon:"fa-fast-backward", action:function(e) {
      e.preventDefault();
      selectsnapshot(uuid, name, snapshotname, "revert",true,state,method) ;
     }});
  if (method == "QEMU") {
    opts.push({text:_("Block Commit"), icon:"fa-hdd-o", action:function(e) {
      $('#vm-'+uuid).find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
      e.preventDefault();
      selectblock(uuid, name, snapshotname, "commit",true) ;
    }});
    
    opts.push({text:_("Block Pull"), icon:"fa-hdd-o", action:function(e) {
      $('#vm-'+uuid).find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
      e.preventDefault();
      selectblock(uuid, name, snapshotname, "pull",true) ;
    }});
  }

  opts.push({text:_("Remove snapshot"), icon:"fa-trash", action:function(e) {
    e.preventDefault();
    $('#vm-'+uuid).find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
    selectsnapshot(uuid, name, snapshotname, "remove",true) ;
  }});
  context.destroy('#vmsnap-'+uuid);
  context.attach('#vmsnap-'+uuid, opts);
}
function startAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,vm; vm=kvm[i]; i++) if (vm.state!='running') $('#vm-'+vm.id).parent().find('i').removeClass('fa-square').addClass('fa-refresh fa-spin');
  $.post('/plugins/dynamix.vm.manager/include/VMManager.php',{action:'start'}, function(){loadlist();});
}
function stopAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,vm; vm=kvm[i]; i++) if (vm.state=='running') $('#vm-'+vm.id).parent().find('i').removeClass('fa-play').addClass('fa-refresh fa-spin');
  $.post('/plugins/dynamix.vm.manager/include/VMManager.php',{action:'stop'}, function(){loadlist();});
}
function vncOpen() {
  $.post('/plugins/dynamix.vm.manager/include/vnc.php',{cmd:'open',root:'<?=$docroot?>',file:'<?=$docroot?>/plugins/dynamix.vm.manager/vncconnect.vnc'},function(data) {
    window.location.href = data;
  });
}
function toggle_id(itemID){
   var cookie = $.cookie('vmshow')||'';
   if ((document.getElementById(itemID).style.display == 'none')) {
      slideDownRows($('#'+itemID));
      if (cookie.indexOf(itemID)<0) $.cookie('vmshow',cookie+itemID+','); 
   } else {
      slideUpRows($('#'+itemID));
      if (cookie.indexOf(itemID)>=0) $.cookie('vmshow',cookie.replace(itemID+',',''));
   }
   return false;
}
function showInput(){
  $(this).off('click');
  $(this).siblings('input').each(function(){$(this).show();});
  $(this).siblings('input').focus();
  $(this).hide();
}
function hideInput(){
  $(this).hide();
  $(this).siblings('span').show();
  $(this).siblings('span').click(showInput);
}
function addVM() {
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);
  location = path+"/VMTemplates";
}


