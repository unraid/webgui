var eventURL = '/plugins/dynamix.docker.manager/include/Events.php';

function addDockerContainerContext(container, image, template, started, paused, update, autostart, webui, tswebui, shell, id, Support, Project, Registry, donateLink, ReadMe) {
  var opts = [];
  context.settings({right:false,above:'auto'});
  if (started && !paused) {
    if (webui !== '' && webui != '#') opts.push({text:_('WebUI'), icon:'fa-globe', action:function(e){e.preventDefault();window.open(webui,'_blank');}});
    if (tswebui !== '' && tswebui != '#') opts.push({text:_('Tailscale WebUI'), icon:'fa-globe', action:function(e){e.preventDefault();window.open(tswebui,'_blank');}});
    opts.push({text:_('Console'), icon:'fa-terminal', action:function(e){e.preventDefault(); openTerminal('docker',container,shell);}});
    opts.push({divider:true});
  }
  if (update==1) {
    opts.push({text:_('Update'), icon:'fa-cloud-download', action:function(e){e.preventDefault(); updateContainer(container);}});
    opts.push({divider:true});
  }
  if (started) {
    if (paused) {
      opts.push({text:_('Resume'), icon:'fa-play', action:function(e){e.preventDefault(); eventControl({action:'resume', container:id}, 'loadlist');}});
    } else {
      opts.push({text:_('Stop'), icon:'fa-stop', action:function(e){e.preventDefault(); eventControl({action:'stop', container:id}, 'loadlist');}});
      opts.push({text:_('Pause'), icon:'fa-pause', action:function(e){e.preventDefault(); eventControl({action:'pause', container:id}, 'loadlist');}});
    }
    opts.push({text:_('Restart'), icon:'fa-refresh', action:function(e){e.preventDefault(); eventControl({action:'restart', container:id}, 'loadlist');}});
  } else {
    opts.push({text:_('Start'), icon:'fa-play', action:function(e){e.preventDefault(); eventControl({action:'start', container:id}, 'loadlist');}});
  }
  opts.push({divider:true});
  opts.push({text:_('Logs'), icon:'fa-navicon', action:function(e){e.preventDefault(); openTerminal('docker',container,'.log');}});
  if (template) {
    opts.push({text:_('Edit'), icon:'fa-wrench', action:function(e){e.preventDefault(); editContainer(container, template);}});
  }
  opts.push({text:_('Remove'), icon:'fa-trash', action:function(e){e.preventDefault(); rmContainer(container, image, id);}});
  if (ReadMe||Project||Support||Registry) {
    opts.push({divider:true});
  }
  if (ReadMe) {
    opts.push({text:_('Read Me First'), icon:'fa-book', href:ReadMe, target:'_blank'});
  }
  if (Project) {
    opts.push({text:_('Project Page'), icon:'fa-life-ring', href:Project, target:'_blank'});
  }
  if (Support) {
    opts.push({text:_('Support'), icon:'fa-question', href:Support, target:'_blank'});
  }
  if (Registry) {
    opts.push({text:_('More Info'),icon:'fa-info-circle', href:Registry, target:'_blank'});
  }
  if (donateLink) {
    opts.push({divider:true});
    opts.push({text:_('Donate'),icon:'fa-external-link', href:donateLink,target:'_blank'});
  }
  context.destroy('#'+id);
  context.attach('#'+id, opts);
}
function addDockerImageContext(image, imageTag) {
  var opts = [];
  opts.push({text:_('Remove'), icon:'fa-trash', action:function(e){e.preventDefault(); rmImage(image, imageTag);}});
  context.attach('#'+image, opts);
}
function popupWithIframe(title, cmd, reload, func) {
  pauseEvents();
  $('#iframe-popup').html('<iframe id="myIframe" frameborder="0" scrolling="yes" width="100%" height="99%"></iframe>');
  $('#iframe-popup').dialog({
    autoOpen:true,
    title:title,
    height: 600,
    width: 900,
    draggable:true,
    resizable:true,
    modal:true,
    open:function(ev, ui){
      $('#myIframe').attr('src', cmd);
    },
    close:function(event, ui){
      if (reload && !$('#myIframe').contents().find('#canvas').length) {
        if (func) setTimeout(func+'()',0); else location = window.location.href;
      } else {
        resumeEvents();
      }
    }
  });
  $('.ui-dialog-titlebar-close').css({'display':'none'});
  $('.ui-dialog-title').css({'text-align':'center','width':'100%','font-size':'1.8rem'});
  $('.ui-dialog-content').css({'padding-top':'15px','vertical-align':'bottom'});
  $('.ui-button-text').css({'padding':'0px 5px'});
}
function execUpContainer(container) {
  var title = _('Updating the container TEST')+': '+container;
  var cmd = '/plugins/dynamix.docker.manager/include/CreateDocker.php?updateContainer=true&ct[]='+encodeURIComponent(container);
  popupWithIframe(title, cmd, true, 'loadlist');
}
function addContainer() {
  var path = location.pathname;
  var x = path.indexOf('?');
  if (x!=-1) path = path.substring(0,x);
  location = path+'/AddContainer';
}
function editContainer(container, template) {
  var path = location.pathname;
  var x = path.indexOf('?');
  if (x!=-1) path = path.substring(0, x);
  location = path+'/UpdateContainer?xmlTemplate=edit:'+template;
}
function updateContainer(container) {
  swal({
    title:_('Are you sure?'),text:_('Update container')+': '+container, type:'warning',html:true,showCancelButton:true,closeOnConfirm:false,confirmButtonText:_('Yes, update it!'),cancelButtonText:_('Cancel')
  },function(){
    openDocker('update_container '+encodeURIComponent(container),_('Updating the container'),'','loadlist');
  });
}
function rmContainer(container, image, id) {
  var body = _('Remove container')+': '+container+'<br><br><label><input id="removeimagechk" type="checkbox" checked style="display:inline;width:unset;height:unset;margin-top:unset;margin-bottom:unset">'+_('also remove image')+'</label>';
  $('input[type=button]').prop('disabled',true);
  swal({
    title:_('Are you sure?'),text:body,type:'warning',html:true,showCancelButton:true,confirmButtonText:_('Yes, delete it!'),cancelButtonText:_('Cancel'),showLoaderOnConfirm:true
  },function(c){
    if (!c) {setTimeout(loadlist); return;}
    $('div.spinner.fixed').show('slow');
    if ($('#removeimagechk').prop('checked')) {
      eventControl({action:'remove_all', container:id, name:container, image:image},'loadlist');
    } else {
      eventControl({action:'remove_container', container:id, name:container},'loadlist');
    }
  });
}
function rmImage(image, imageName) {
  var body = _('Remove image')+': '+$('<textarea />').html(imageName).text();
  $('input[type=button]').prop('disabled',true);
  swal({
    title:_('Are you sure?'),text:body,type:'warning',html:true,showCancelButton:true,confirmButtonText:_('Yes, delete it!'),cancelButtonText:_('Cancel'),showLoaderOnConfirm:true
  },function(c){
    if (!c) {setTimeout(loadlist,0); return;}
    $('div.spinner.fixed').show('slow');
    eventControl({action:'remove_image', image:image},'loadlist');
  });
}
function eventControl(params, spin) {
  if (spin) $('#'+params['container']).parent().find('i').removeClass('fa-play fa-square fa-pause').addClass('fa-refresh fa-spin');
  $.post(eventURL, params, function(data) {
    $('div.spinner.fixed').hide('slow');
    if (data.success === true) {
      if (spin) setTimeout(spin+'()',500); else location=window.location.href;
    } else {
      setTimeout(function(){
        swal({
          title:_('Execution error'),text:data.success,type:'error',html:true,confirmButtonText:_('Ok')
        },function(){
          if (spin) setTimeout(spin+'()',500); else location=window.location.href;
        });
      },100);
    }
  },'json');
}
function startAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,ct; ct=docker[i]; i++) if (ct.state==0) $('#'+ct.id).parent().find('i').removeClass('fa-square').addClass('fa-refresh fa-spin');
  $.post('/plugins/dynamix.docker.manager/include/ContainerManager.php',{action:'start'},function(){loadlist();});
}
function stopAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,ct; ct=docker[i]; i++) if (ct.state==1) $('#'+ct.id).parent().find('i').removeClass('fa-play fa-pause').addClass('fa-refresh fa-spin');
  $.post('/plugins/dynamix.docker.manager/include/ContainerManager.php',{action:'stop'},function(){loadlist();});
}
function pauseAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,ct; ct=docker[i]; i++) if (ct.state==1 && ct.pause==0) $('#'+ct.id).parent().find('i').removeClass('fa-play').addClass('fa-refresh fa-spin');
  $.post('/plugins/dynamix.docker.manager/include/ContainerManager.php',{action:'pause'},function(){loadlist();});
}
function resumeAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,ct; ct=docker[i]; i++) if (ct.state==1 && ct.pause==1) $('#'+ct.id).parent().find('i').removeClass('fa-pause').addClass('fa-refresh fa-spin');
  $.post('/plugins/dynamix.docker.manager/include/ContainerManager.php',{action:'unpause'},function(){loadlist();});
}
function checkAll() {
  $('input[type=button]').prop('disabled',true);
  $('.updatecolumn').html('<span style="color:#267CA8"><i class="fa fa-refresh fa-spin"></i> '+_('checking')+'...</span>');
  $.post('/plugins/dynamix.docker.manager/include/DockerUpdate.php',{},function(){loadlist();});
}
function updateAll() {
  $('input[type=button]').prop('disabled',true);
  var ct = [];
  for (var i=0,d; d=docker[i]; i++) if (d.update==1) ct.push(encodeURIComponent(d.name));
  openDocker('update_container '+ct.join('*'),_('Updating all Containers'),'','loadlist');
}
function rebuildAll() {
  $('input[type=button]').prop('disabled',true);
  $('div.spinner.fixed').show('slow');
  var ct = [];
  for (var i=0,d; d=docker[i]; i++) if (d.update==2) ct.push(encodeURIComponent(d.name));
  $.get('/plugins/dynamix.docker.manager/include/CreateDocker.php',{updateContainer:true,mute:true,ct},function(){loadlist();});
}
