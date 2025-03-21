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
 */
?>
<?
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Helpers.php";

// add translations
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";

function age($number,$time) {
  return sprintf(_('%s '.($number==1 ? $time : $time.'s').' ago'),$number);
}

function my_age($time) {
  if (!is_numeric($time)) $time = time();
  $age = new DateTime('@'.$time);
  $age = date_create('now')->diff($age);
  if ($age->y > 0) return age($age->y,'year');
  if ($age->m > 0) return age($age->m,'month');
  if ($age->d > 0) return age($age->d,'day');
  if ($age->h > 0) return age($age->h,'hour');
  if ($age->i > 0) return age($age->i,'minute');
  return age($age->s,'second');
}

function validname($name) {
  $path = realpath(dirname($name));
  return in_array(explode('/',$path)[1]??'',['mnt','boot']) ? $path.'/'.basename($name) : '';
}

function escape($name) {return escapeshellarg(validname($name));}
function quoted($name) {return is_array($name) ? implode(' ',array_map('escape',$name)) : escape($name);}

switch ($_POST['mode']) {
case 'upload':
  $file = validname(htmlspecialchars_decode(rawurldecode($_POST['file'])));
  if (!$file) die('stop');
  $local = "/var/tmp/".basename($file).".tmp";
  if ($_POST['start']==0) {
    $my = pathinfo($file); $n = 0;
    while (file_exists($file)) $file = $my['dirname'].'/'.preg_replace('/ \(\d+\)$/','',$my['filename']).' ('.++$n.')'.($my['extension'] ? '.'.$my['extension'] : '');
    file_put_contents($local,$file);
    // create file with proper permissions and owner
    touch($file);
    chgrp($file,'users');
    chown($file,'nobody');
    chmod($file,0666);
  }
  $file = file_get_contents($local);
  if ($_POST['cancel']==1) {
    delete_file($file);
    die('stop');
  }
  if (file_put_contents($file,base64_decode($_POST['data']),FILE_APPEND)===false) {
    delete_file($file);
    die('error');
  }
  die();
case 'calc':
  extract(parse_plugin_cfg('dynamix',true));
  $source = explode("\n",htmlspecialchars_decode(rawurldecode($_POST['source'])));
  [$null,$root,$main,$rest] = my_explode('/',$source[0],4);
  if ($root=='mnt' && in_array($main,['user','user0'])) {
    $disks = parse_ini_file('state/disks.ini',true);
    $tag = implode('|',array_merge(['disk'],pools_filter($disks)));
    $loc = array_filter(explode(',',preg_replace("/($tag)/",',$1',exec("shopt -s dotglob; getfattr --no-dereference --absolute-names --only-values -n system.LOCATIONS ".quoted($source)."/* 2>/dev/null"))));
  } else {
    $loc = [];
    foreach ($source as $path) {
      [$null,$root,$main,$rest] = my_explode('/',$path,4);
      $loc[] = $root=='mnt' ? ($main ?: '---') : ($root=='boot' ? _('flash') : '---');
    }
  }
  natcasesort($loc);
  $awk = "awk 'BEGIN{ORS=\" \"}/Number of files|Total file size/{if(\$5==\"(reg:\")print \$4,\$8;if(\$5==\"(dir:\")print \$4,\$6;if(\$3==\"size:\")print \$4}'";
  [$files,$dirs,$size] = explode(' ',str_replace([',',')'],'',exec("rsync --stats -naI ".quoted($source)." /var/tmp 2>/dev/null|$awk")));
  $dirs = $dirs ?: 0;
  $files -= $dirs;
  $calc   = [];
  $calc[] = _('Name').": ".implode(', ',array_map('basename',$source));
  $calc[] = _('Location').": ".implode(', ',array_unique($loc));
  $calc[] = _('Last modified').': '.my_age(max(array_map('filemtime',$source)));
  $calc[] = _('Total occupied space').": ".my_scale($size,$unit)." $unit";
  $calc[] = sprintf(_("in %s folder".($dirs==1?'':'s')." and %s file".($files==1?'':'s')),my_number($dirs),my_number($files));
  $calc   = '<div style="text-align:left;margin-left:56px">'.implode('<br>',$calc).'</div>';
  die($calc);
case 'home':
  $source = explode("\n",htmlspecialchars_decode(rawurldecode($_POST['source'])));
  $target = htmlspecialchars_decode(rawurldecode($_POST['target']));
  $disks = parse_ini_file('state/disks.ini',true);
  $tag  = implode('|',array_merge(['disk'],pools_filter($disks)));
  $loc1 = implode(',',array_unique(array_filter(explode(',',preg_replace("/($tag)/",',$1',exec("getfattr --no-dereference --absolute-names --only-values -n system.LOCATIONS ".quoted($source)." 2>/dev/null"))))));
  $loc2 = exec("getfattr --no-dereference --absolute-names --only-values -n system.LOCATIONS ".quoted($target)." 2>/dev/null");
  $home = $loc1==$loc2 ? '1' : '0';
  die($home);
case 'jobs':
  $jobs = [];
  $file = '/var/tmp/file.manager.jobs';
  $rows = file_exists($file) ? file($file,FILE_IGNORE_NEW_LINES) : [];
  $job  = 1;
  for ($x = 0; $x < count($rows); $x+=9) {
    $data = parse_ini_string(implode("\n",array_slice($rows,$x,9)));
    $task = $data['task'];
    $source = explode("\r",$data['source']);
    $target = $data['target'];
    $more = count($source) > 1 ? " (".sprintf("and %s more",count($source)-1).") " : "";
    $jobs[] = '<i id="queue_'.$job.'" class="fa fa-fw fa-square-o blue-text job" onclick="selectOne(this.id,false)"></i>'._('Job')." [".sprintf("%'.04d",$job++)."] - $task ".$source[0].$more.($target ? " --> $target" : "");
  }
  $jobs = '<div id="dfm_joblist">'.implode("<br>",$jobs).'</div>';
  die($jobs);
case 'edit':
  $file = validname(rawurldecode($_POST['file']));
  die($file ? file_get_contents($file) : '');
case 'save':
  if ($file = validname(rawurldecode($_POST['file']))) file_put_contents($file,rawurldecode($_POST['data']));
  die();
case 'stop':
  $file = htmlspecialchars_decode(rawurldecode($_POST['file']));
  delete_file("/var/tmp/$file.tmp");
  die();
case 'start':
  $active = '/var/tmp/file.manager.active';
  $jobs   = '/var/tmp/file.manager.jobs';
  $start  = '0';
  if (file_exists($jobs)) {
    exec("sed -n '2,9 p' $jobs > $active");
    exec("sed -i '1,9 d' $jobs");
    $start = filesize($jobs) > 0 ? '2' : '1';
    if ($start=='1') delete_file($jobs);
  }
  die($start);
case 'undo':
  $jobs = '/var/tmp/file.manager.jobs';
  $undo = '0';
  if (file_exists($jobs)) {
    $rows = array_reverse(explode(',',$_POST['row']));
    foreach ($rows as $row) {
      $end = $row + 8;
      exec("sed -i '$row,$end d' $jobs");
    }
    $undo = filesize($jobs) > 0 ? '2' : '1';
    if ($undo=='1') delete_file($jobs);
  }
  die($undo);
case 'read':
  $active = '/var/tmp/file.manager.active';
  $read = file_exists($active) ? json_encode(parse_ini_file($active)) : '';
  die($read);
case 'file':
  $active = '/var/tmp/file.manager.active';
  $jobs   = '/var/tmp/file.manager.jobs';
  $data[] = 'action="'.($_POST['action']??'').'"';
  $data[] = 'title="'.rawurldecode($_POST['title']??'').'"';
  $data[] = 'source="'.htmlspecialchars_decode(rawurldecode($_POST['source']??'')).'"';
  $data[] = 'target="'.rawurldecode($_POST['target']??'').'"';
  $data[] = 'H="'.(empty($_POST['hdlink']) ? '' : 'H').'"';
  $data[] = 'sparse="'.(empty($_POST['sparse']) ? '' : '--sparse').'"';
  $data[] = 'exist="'.(empty($_POST['exist']) ? '--ignore-existing' : '').'"';
  $data[] = 'zfs="'.rawurldecode($_POST['zfs']??'').'"';
  if (isset($_POST['task'])) {
    // add task to queue
    $task = rawurldecode($_POST['task']);
    $data = "task=\"$task\"\n".implode("\n",$data)."\n";
    file_put_contents($jobs,$data,FILE_APPEND);
  } else {
    // start operation
    file_put_contents($active,implode("\n",$data));
  }
  die();
}
?>
