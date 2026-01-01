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

// add translations
$_SERVER['REQUEST_URI'] = '';
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

function write(&$rows) {
  if ($size = count($rows)) echo '<tbody>',implode(array_map(function($row){echo gzinflate($row);},$rows)),'</tbody>';
  $rows = $size;
}

function validdir($dir) {
  $path = realpath($dir);
  return in_array(explode('/', $path)[1] ?? '', ['mnt','boot']) ? $path : '';
}

function escapeQuote($data) {
  return str_replace('"','&#34;', $data);
}

function add($number, $name, $single='', $plural='s') {
  return $number.' '._($name.($number==1 ? $single : $plural));
}

function age($number, $time) {
  return sprintf(_('%s '.($number==1 ? $time : $time.'s').' ago'),$number);
}

function my_age($time) {
  if (!is_numeric($time)) $time = time();
  $age = new DateTime('@'.$time);
  $age = date_create('now')->diff($age);
  if ($age->y > 0) return age($age->y, 'year');
  if ($age->m > 0) return age($age->m, 'month');
  if ($age->d > 0) return age($age->d, 'day');
  if ($age->h > 0) return age($age->h, 'hour');
  if ($age->i > 0) return age($age->i, 'minute');
  return age($age->s, 'second');
}

function parent_link() {
  global $dir, $path;
  $parent = dirname($dir);
  return $parent == '/' ? false : '<a href="/'.$path.'?dir='.rawurlencode(htmlspecialchars($parent)).'">'._('Parent Directory').'</a>';
}

function my_devs(&$devs,$name,$menu) {
  global $disks, $lock;
  $text = []; $i = 0;
  foreach ($devs as $dev) {
    if ($lock == '---') {
      $text[$i] = '<a class="info" onclick="return false"><i class="lock fa fa-fw fa-hdd-o grey-text"></i></a>&nbsp;---';
    } else {
      switch ($disks[$dev]['luksState']??0) {
        case 0: $text[$i] = '<span class="dfm_device"><a class="info" onclick="return false"><i class="lock fa fa-fw fa-unlock-alt grey-text"></i><span>'._('Not encrypted').'</span></a>'; break;
        case 1: $text[$i] = '<span class="dfm_device"><a class="info" onclick="return false"><i class="lock fa fa-fw fa-unlock-alt green-text"></i><span>'._('Encrypted and unlocked').'</span></a>'; break;
        case 2: $text[$i] = '<span class="dfm_device"><a class="info" onclick="return false"><i class="lock fa fa-fw fa-lock red-text"></i><span>'._('Locked: missing encryption key').'</span></a>'; break;
        case 3: $text[$i] = '<span class="dfm_device"><a class="info" onclick="return false"><i class="lock fa fa-fw fa-lock red-text"></i><span>'._('Locked: wrong encryption key').'</span></a>'; break;
       default: $text[$i] = '<span class="dfm_device"><a class="info" onclick="return false"><i class="lock fa fa-fw fa-lock red-text"></i><span>'._('Locked: unknown error').'</span></a>'; break;
      }
      $root = ($dev == 'flash' ? "/boot/$name" : "/mnt/$dev/$name");
      $text[$i] .= '<span id="device_'.$i.'" class="hand" onclick="'.$menu.'(\''.$root.'\','.$i.')" oncontextmenu="'.$menu.'(\''.$root.'\','.$i.');return false">'.compress($dev,11,0).'</span></span>';
    }
    $i++;
  }
  return implode($text);
}

function icon_class($ext) {
  switch ($ext) {
  case '3gp': case 'asf': case 'avi': case 'f4v': case 'flv': case 'm4v': case 'mkv': case 'mov': case 'mp4': case 'mpeg': case 'mpg': case 'm2ts': case 'ogm': case 'ogv': case 'vob': case 'webm': case 'wmv':
    return 'fa fa-film';
  case '7z': case 'bz2': case 'gz': case 'rar': case 'tar': case 'xz': case 'zip':
    return 'fa fa-file-archive-o';
  case 'aac': case 'ac3': case 'dsf': case 'flac': case 'm4a': case 'mka': case 'mp2': case 'mp3': case 'oga': case 'ogg': case 'tds': case 'wav': case 'wma':
    return 'fa fa-music';
  case 'ai': case 'eps': case 'fla': case 'psd': case 'swf':
    return 'fa fa-file-image-o';
  case 'avif': case 'bmp': case 'gif': case 'ico': case 'jp2': case 'jpc': case 'jpeg': case 'jpg': case 'jpx': case 'png': case 'svg': case 'tif': case 'tiff': case 'wbmp': case 'webp': case 'xbm':
    return 'fa fa-picture-o';
  case 'bak': case 'swp':
    return 'fa fa-clipboard';
  case 'bat':
    return 'fa fa-terminal';
  case 'bot': case 'cfg': case 'conf': case 'dat': case 'htaccess': case 'htpasswd': case 'ini': case 'log': case 'pl': case 'tmp': case 'toml': case 'top': case 'txt': case 'yaml': case 'yml':
    return 'fa fa-file-text-o';
  case 'c': case 'config': case 'cpp': case 'cs': case 'dtd': case 'exe': case 'ftpquota': case 'gitignore': case 'hbs': case 'json': case 'jsx': case 'lock': case 'map': case 'md': case 'msi': case 'passwd': case 'rs': case 'sh': case 'sql': case 'tpl': case 'ts': case 'tsx': case 'twig':
    return 'fa fa-file-code-o';
  case 'css': case 'less': case 'sass': case 'scss':
    return 'fa fa-css3';
  case 'csv':
    return 'fa fa-file-text-o';
  case 'cue': case 'm3u': case 'm3u8': case 'pls': case 'xspf':
    return 'fa fa-headphones';
  case 'doc': case 'docm': case 'docx': case 'dot': case 'dotm': case 'dotx': case 'odt':
    return 'fa fa-file-word-o';
  case 'eml': case 'msg':
    return 'fa fa-envelope-o';
  case 'eot': case 'fon': case 'otf': case 'ttc': case 'ttf': case 'woff': case 'woff2':
    return 'fa fa-font';
  case 'htm': case 'html': case 'shtml': case 'xhtml':
    return 'fa fa-html5';
  case 'js': case 'php': case 'php4': case 'php5': case 'phps': case 'phtml': case 'py':
    return 'fa fa-code';
  case 'key':
    return 'fa fa-key';
  case 'ods': case 'xla': case 'xls': case 'xlsb': case 'xlsm': case 'xlsx': case 'xlt': case 'xltm': case 'xltx':
    return 'fa fa-file-excel-o';
  case 'pdf':
    return 'fa fa-file-pdf-o';
  case 'pot': case 'potx': case 'ppt': case 'pptm': case 'pptx':
    return 'fa fa-file-powerpoint-o';
  case 'xml': case 'xsl':
    return 'fa fa-file-excel-o';
  default:
    return 'fa fa-file-o';
  }
}

$dir = validdir(htmlspecialchars_decode(rawurldecode($_GET['dir'])));
if (!$dir) {echo '<tbody><tr><td></td><td></td><td colspan="6">',_('Invalid path'),'</td><td></td></tr></tbody>'; exit;}

extract(parse_plugin_cfg('dynamix',true));
$disks  = parse_ini_file('state/disks.ini',true);
$shares = parse_ini_file('state/shares.ini',true);
$path   = unscript($_GET['path']);
$fmt    = "%F {$display['time']}";
$dirs   = $files = [];
$total  = $objs = 0;
[$null,$root,$main,$next,$rest] = my_explode('/', $dir, 5);
$user   = $root=='mnt' && in_array($main, ['user','user0']);
$lock   = $root=='mnt' ? ($main ?: '---') : ($root=='boot' ? _('flash') : '---');
$ishare = $root=='mnt' && (!$main || !$next || ($main=='rootshare' && !$rest));
$folder = $lock=='---' ? _('DEVICE') : ($ishare ? _('SHARE') : _('FOLDER'));

if ($user ) {
  exec("shopt -s dotglob;getfattr --no-dereference --absolute-names -n system.LOCATIONS ".escapeshellarg($dir)."/* 2>/dev/null",$tmp);
  for ($i = 0; $i < count($tmp); $i+=3) $set[basename($tmp[$i])] = explode('"',$tmp[$i+1])[1];
  unset($tmp);
}

$stat = popen("shopt -s dotglob;stat -L -c'%F|%U|%A|%s|%Y|%n' ".escapeshellarg($dir)."/* 2>/dev/null",'r');

while (($row = fgets($stat)) !== false) {
  [$type,$owner,$perm,$size,$time,$name] = explode('|',rtrim($row,"\n"),6);
  $dev  = explode('/', $name, 5);
  $devs = explode(',', $user ? $set[basename($name)] ?? $shares[$dev[3]]['cachePool'] ?? '' : $lock);
  $objs++;
  $text = [];
  if ($type[0] == 'd') {
    $text[] = '<tr><td><i id="check_'.$objs.'" class="fa fa-fw fa-square-o" onclick="selectOne(this.id)"></i></td>';
    $text[] = '<td data=""><i class="fa fa-folder-o"></i></td>';
    $text[] = '<td><a id="name_'.$objs.'" oncontextmenu="folderContextMenu(this.id,\'right\');return false" href="/'.$path.'?dir='.rawurlencode(htmlspecialchars($name)).'">'.htmlspecialchars(basename($name)).'</a></td>';
    $text[] = '<td id="owner_'.$objs.'">'.$owner.'</td>';
    $text[] = '<td id="perm_'.$objs.'">'.$perm.'</td>';
    $text[] = '<td data="0">&lt;'.$folder.'&gt;</td>';
    $text[] = '<td data="'.$time.'"><span class="my_time">'.my_time($time,$fmt).'</span><span class="my_age" style="display:none">'.my_age($time).'</span></td>';
    $text[] = '<td class="loc">'.my_devs($devs,$dev[3]??$dev[2],'deviceFolderContextMenu').'</td>';
    $text[] = '<td><i id="row_'.$objs.'" data="'.escapeQuote($name).'" type="d" class="fa fa-plus-square-o" onclick="folderContextMenu(this.id,\'both\')" oncontextmenu="folderContextMenu(this.id,\'both\');return false">...</i></td></tr>';
    $dirs[] = gzdeflate(implode($text));
  } else {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $tag = count($devs) > 1 ? 'warning' : '';
    $text[] = '<tr><td><i id="check_'.$objs.'" class="fa fa-fw fa-square-o" onclick="selectOne(this.id)"></i></td>';
    $text[] = '<td class="ext" data="'.$ext.'"><i class="'.icon_class($ext).'"></i></td>';
    $text[] = '<td id="name_'.$objs.'" class="'.$tag.'" onclick="fileEdit(this.id)" oncontextmenu="fileContextMenu(this.id,\'right\');return false">'.htmlspecialchars(basename($name)).'</td>';
    $text[] = '<td id="owner_'.$objs.'" class="'.$tag.'">'.$owner.'</td>';
    $text[] = '<td id="perm_'.$objs.'" class="'.$tag.'">'.$perm.'</td>';
    $text[] = '<td data="'.$size.'" class="'.$tag.'">'.my_scale($size,$unit).' '.$unit.'</td>';
    $text[] = '<td data="'.$time.'" class="'.$tag.'"><span class="my_time">'.my_time($time,$fmt).'</span><span class="my_age" style="display:none">'.my_age($time).'</span></td>';
    $text[] = '<td class="loc '.$tag.'">'.my_devs($devs,$dev[3]??$dev[2],'deviceFileContextMenu').'</td>';
    $text[] = '<td><i id="row_'.$objs.'" data="'.escapeQuote($name).'" type="f" class="fa fa-plus-square-o" onclick="fileContextMenu(this.id,\'both\')" oncontextmenu="fileContextMenu(this.id,\'both\');return false">...</i></td></tr>';
    $files[] = gzdeflate(implode($text));
    $total += $size;
  }
}

pclose($stat);

if ($link = parent_link()) echo '<tbody class="tablesorter-infoOnly"><tr><td></td><td><i class="fa fa-folder-open-o"></i></td><td>',$link,'</td><td colspan="6"></td></tr></tbody>';
echo write($dirs),write($files),'<tfoot><tr><td></td><td></td><td colspan="7">',add($objs,'object'),': ',add($dirs,'director','y','ies'),', ',add($files,'file'),' (',my_scale($total,$unit),' ',$unit,' ',_('total'),')</td></tr></tfoot>';
?>
