function displayconsole(url) {
  window.open(url, '_blank', 'scrollbars=yes,resizable=yes');
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
function addVMContext(name, uuid, template, state, vmrcurl,vmrcprotocol , log){
  var opts = [];
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);
  if (vmrcurl !== "" && state == "running") {
    var vmrctext=_("VM Console") + "(" + vmrcprotocol + ")" ;
    opts.push({text:vmrctext, icon:"fa-desktop", action:function(e) {
      e.preventDefault();
      window.open(vmrcurl, '_blank', 'scrollbars=yes,resizable=yes');
    }});
    opts.push({divider:true});
  }
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
    opts.push({text:_("Start"), icon:"fa-play", action:function(e) {
      e.preventDefault();
      ajaxVMDispatch({action:"domain-start", uuid:uuid}, "loadlist");
    }});
    if (vmrcprotocol == "VNC" || vmrcprotocol == "SPICE")  {
    opts.push({text:_("Start with console")+ "(" + vmrcprotocol + ")" , icon:"fa-play", action:function(e) {
      e.preventDefault();
       ajaxVMDispatchconsole({action:"domain-start-console", uuid:uuid, vmrcurl:vmrcurl}, "loadlist") ;  
    }});
  }}
  opts.push({divider:true});
  if (log !== "") {
    opts.push({text:_("Logs"), icon:"fa-navicon", action:function(e){e.preventDefault(); openTerminal('log',name,log);}});
  }
  opts.push({text:_("Edit"), icon:"fa-pencil", href:path+'/UpdateVM?uuid='+uuid});
  if (state == "shutoff") {
    opts.push({divider:true});
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
  context.attach('#vm-'+uuid, opts);
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
