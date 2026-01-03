<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * NOTE: The templates below use Markdown syntax for definition lists.
 * 
 * The <wbr /> (word break opportunity) elements serve two purposes:
 * 1. They trigger Markdown to generate proper <dl><dt><dd> HTML structure
 *    (the Markdown parser requires a "term" before the colon to create definition lists)
 *    Syntax reference: https://www.markdownguide.org/extended-syntax/#definition-lists
 * 2. They allow CSS to hide empty <dt> spacer rows in mobile layouts using dt:has(wbr)
 *    (see @media (max-width: 768px) in default-dynamix.css)
 * 
 * Without <wbr />, the colon-prefixed lines would be rendered as <p> paragraphs instead
 * of definition list items, breaking the intended layout structure. And using &nbsp; 
 * would not allow CSS to target those rows for hiding in mobile views.
 */
?>
<div class="dfm_template">
<div id="dfm_dialogWindow"></div>
<input type="file" id="dfm_upload" value="" onchange="startUpload(this.files)" multiple>

<div markdown="1" id="dfm_templateCreateFolder">
_(New folder name)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="">

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateDeleteFolder">
_(Folder name)_:
: <span id="dfm_source"></span>

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateRenameFolder">
_(Current folder name)_:
: <span id="dfm_source"></span>

<wbr />
: _(rename to)_ ...

_(New folder name)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="">

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateCopyFolder">
_(Source folder)_:
: <span id="dfm_source"></span>

<wbr />
: <span class="flex flex-col gap-4">
  <label for="dfm_sparse" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_sparse" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_sparse">_(Use sparse option)_</span>
  </label>
  <label for="dfm_exist" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_exist" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_exist">_(Overwrite existing files)_</span>
  </label>
  <span class="dfm_text"></span>
</span>

<wbr />
: _(copy to)_ ...

_(Target folder)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="HIDE_FILES_FILTER" data-pickmatch="" data-pickroot="" data-picktop="">
</div>

<div markdown="1" id="dfm_templateMoveFolder">
_(Source folder)_:
: <span id="dfm_source"></span>

<wbr />
: <span class="flex flex-col gap-4">
  <label for="dfm_sparse" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_sparse" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_sparse">_(Use sparse option)_</span>
  </label>
  <label for="dfm_exist" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_exist" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_exist">_(Overwrite existing files)_</span>
  </label>
  <span class="dfm_text"></span>
</span>

<wbr />
: _(move to)_ ...

_(Target folder)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="HIDE_FILES_FILTER" data-pickmatch="" data-pickroot="" data-picktop="">
</div>

<div markdown="1" id="dfm_templateDeleteFile">
_(File name)_:
: <span id="dfm_source"></span>

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateRenameFile">
_(Current file name)_:
: <span id="dfm_source"></span>

<wbr />
: _(rename to)_ ...

_(New file name)_:
: <input type="text" id="dfm_target" autocomplete="off" value="">

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateCopyFile">
_(Source file)_:
: <span id="dfm_source"></span>

<wbr />
: <span class="flex flex-col gap-4">
  <label for="dfm_sparse" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_sparse" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_sparse">_(Use sparse option)_</span>
  </label>
  <label for="dfm_exist" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_exist" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_exist">_(Overwrite existing files)_</span>
  </label>
  <span class="dfm_text"></span>
</span>

<wbr />
: _(copy to)_ ...

_(Target file)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="" data-pickmatch="" data-pickroot="" data-picktop="">
</div>

<div markdown="1" id="dfm_templateMoveFile">
_(Source file)_:
: <span id="dfm_source"></span>

<wbr />
: <span class="flex flex-col gap-4">
  <label for="dfm_sparse" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_sparse" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_sparse">_(Use sparse option)_</span>
  </label>
  <label for="dfm_exist" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_exist" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_exist">_(Overwrite existing files)_</span>
  </label>
  <span class="dfm_text"></span>
</span>

<wbr />
: _(move to)_ ...

_(Target file)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="" data-pickmatch="" data-pickroot="" data-picktop="">
</div>

<div markdown="1" id="dfm_templateDeleteObject">
_(Source)_:
: <select id="dfm_source"></select>

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateRenameObject">
_(Source)_:
: <span id="dfm_source"></span>

<wbr />
: _(rename to)_ ...

_(Target)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="">

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateCopyObject">
_(Source)_:
: <select id="dfm_source"></select>

<wbr />
: <span class="flex flex-col gap-4">
  <label for="dfm_sparse" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_sparse" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_sparse">_(Use sparse option)_</span>
  </label>
  <label for="dfm_exist" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_exist" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_exist">_(Overwrite existing files)_</span>
  </label>
  <span class="dfm_text"></span>
</span>

<wbr />
: _(copy to)_ ...

_(Target)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="" data-pickmatch="" data-pickroot="" data-picktop="">
</div>

<div markdown="1" id="dfm_templateMoveObject">
_(Source)_:
: <select id="dfm_source"></select>

<wbr />
: <span class="flex flex-col gap-4">
  <label for="dfm_sparse" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_sparse" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_sparse">_(Use sparse option)_</span>
  </label>
  <label for="dfm_exist" class="inline-flex flex-wrap items-center gap-4">
    <input type="checkbox" id="dfm_exist" value="" onchange="this.value=this.checked?'1':''">
    <span class="dfm_exist">_(Overwrite existing files)_</span>
  </label>
  <span class="dfm_text"></span>
</span>

<wbr />
: _(move to)_ ...

_(Target)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value="" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="" data-pickmatch="" data-pickroot="" data-picktop="">
</div>

<div markdown="1" id="dfm_templateChangeOwner">
_(Source)_:
: <select id="dfm_source"></select>

<wbr />
: _(change owner)_ ...

_(New owner)_:
: <select id="dfm_target">
<?foreach ($users as $user) echo mk_option(0,$user['name'],$user['name']);
  echo mk_option(0,'nobody','nobody');
?></select>

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateChangePermission">
_(Source)_:
: <select id="dfm_source"></select>

<wbr />
: _(change permission)_ ...

_(New permission)_:
: <input type="hidden" id="dfm_target" value="">
  <label for="dfm_owner" class="inline-flex flex-wrap items-center gap-4">
    _(Owner)_:
    <select id="dfm_owner" class="dfm">
      <?=mk_option(0,'u-rwx',_('No Access'))?>
      <?=mk_option(0,'u-wx+r',_('Read-only'))?>
      <?=mk_option(0,'u-x+rw',_('Read/Write'))?>
    </select>
  </label>
  <label for="dfm_group" class="inline-flex flex-wrap items-center gap-4">
    _(Group)_:
    <select id="dfm_group" class="dfm">
    <?=mk_option(0,'g-rwx',_('No Access'))?>
    <?=mk_option(0,'g-wx+r',_('Read-only'))?>
    <?=mk_option(0,'g-x+rw',_('Read/Write'))?>
    </select>
  </label>
  <label for="dfm_other" class="inline-flex flex-wrap items-center gap-4">
    _(Other)_:
    <select id="dfm_other" class="dfm">
    <?=mk_option(0,'o-rwx',_('No Access'))?>
    <?=mk_option(0,'o-wx+r',_('Read-only'))?>
    <?=mk_option(0,'o-x+rw',_('Read/Write'))?>
    </select>
  </label>

<wbr />
: <span class="dfm_text"></span>
</div>

<div markdown="1" id="dfm_templateSearch">
_(Source)_:
: <select id="dfm_source"></select>

_(Search pattern)_:
: <input type="text" id="dfm_target" autocomplete="off" spellcheck="false" value=""><span id="dfm_files"></span>

<span class="dfm_loc">&nbsp;</span>
: <span class="dfm_text"></span>

</div>

<div id="dfm_templateEditFile">
<!--!
<style>div#dfm_editor{position:absolute;top:0;bottom:0;left:0;right:0}</style>
<div id="dfm_editor"></div>
<script src="<?autov('/webGui/javascript/ace/ace.js')?>"></script>
<script src="<?autov('/webGui/javascript/ace/ext-modelist.js')?>"></script>
<script>
function getMode(file){
  var modelist = require('ace/ext/modelist');
  return modelist.getModeForPath(file).mode;
}
var source = "{$0}";
var editor = ace.edit('dfm_editor');
editor.session.setMode(getMode(source));
editor.setOptions({
  showPrintMargin:false,
  fontSize:13,
  fontFamily:'bitstream',
  theme:'ace/theme/<?if (in_array($theme,['black','gray'])):?>tomorrow_night<?else:?>tomorrow<?endif;?>'
});
timers.editor = setTimeout(function(){$('div.spinner.fixed').show();},500);
$.post('/webGui/include/Control.php',{mode:'edit',file:encodeURIComponent(source)},function(data){
  clearTimeout(timers.editor);
  $('div.spinner.fixed').hide();
  editor.session.setValue(data);
});
</script>
!-->
</div>

<div id="dfm_templateViewFile">
<!--!
<img id="dfm_viewer" href="{$0}">
<script src="<?autov('/webGui/javascript/EZView.js')?>"></script>
<script>
$('#dfm_viewer').EZView();
$('#dfm_viewer').click();
</script>
!-->
</div>

<div id="dfm_templateJobs">
<!--!
<div id="dfm_jobs"></div>
<script>
$.post('/webGui/include/Control.php',{mode:'jobs'},function(jobs){
  $('#dfm_jobs').html(jobs);
});
</script>
!-->
</div>
</div>
