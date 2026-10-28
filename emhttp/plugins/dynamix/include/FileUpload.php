<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
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
$docroot   = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$file      = rawurldecode($_POST['filename']);
$temp      = "/var/tmp";
$tmp       = '/tmp/plugins';
$plugins   = '/var/log/plugins';
$boot      = "/boot/config/plugins";
$safepaths = ["$boot/dynamix"];
$safeexts  = ['.png'];
$result    = false;

function in_safe_path(string $path, string $base): bool {
  $path = rtrim($path, '/').'/';
  $base = rtrim($base, '/').'/';
  return strpos($path, $base) === 0;
}

function remove_tree(string $dir): bool {
  if (!is_dir($dir)) return false;
  $items = scandir($dir);
  if ($items === false) return false;
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $entry = "$dir/$item";
    if (is_dir($entry) && !is_link($entry)) {
      if (!remove_tree($entry)) return false;
    } elseif (!@unlink($entry)) {
      return false;
    }
  }
  return @rmdir($dir);
}

require_once "$docroot/webGui/include/Helpers.php";

switch ($_POST['cmd'] ?? 'load') {
case 'load':
  // uploaded file to temp directory and verify is a png
  if (isset($_POST['filedata'])) {
    $initialUpload = "$temp/".basename($file).".tmp";
    $verifiedPNG   = "$temp/".basename($file);
    if (file_exists($initialUpload)) unlink($initialUpload);
    if (file_exists($verifiedPNG)) unlink($verifiedPNG);
    $result = file_put_contents($initialUpload,base64_decode(str_replace(['data:image/png;base64,',' '],['','+'],$_POST['filedata'])));
    if ($result) {
      $img = @imagecreatefrompng($initialUpload);
      if ($img) {
        $result = imagepng($img,$verifiedPNG);
        imagedestroy($img);
      } else {
        $result = false;
      }
    }
    if (file_exists($initialUpload)) unlink($initialUpload);
  }
  break;
case 'save':
  // move uploaded file ($verifiedPNG) to final destination
  $verifiedPNG = "$temp/".basename($file);
  $path = $_POST['path'] ?? '';
  $outputRaw = $_POST['output'] ?? '';
  $output = basename($outputRaw);
  $outputExt = strtolower(substr($output, -4));
  $isValidFilename = $output !== '' && $output === $outputRaw && preg_match('/^[A-Za-z0-9._$-]+$/', $output);
  foreach ($safepaths as $safepath) {
    $safeBase = realpath($safepath);
    $targetDir = realpath($path);
    if (!$targetDir && $safeBase) {
      $parentDir = realpath(dirname($path));
      if ($parentDir && in_safe_path($parentDir, $safeBase) && @mkdir($path, 0777, true)) {
        $targetDir = realpath($path);
      }
    }
    if ($targetDir && $safeBase && in_safe_path($targetDir, $safeBase) && $isValidFilename && in_array($outputExt, $safeexts, true)) {
      if (is_dir($targetDir)) {
        $result = @rename($verifiedPNG, "$targetDir/$output");
      }
      break;
    }
  }
  break;
case 'delete':
  $path = $_POST['path'] ?? '';
  $file = basename($file);
  $targetFile = realpath("$path/$file");
  $targetExt = $targetFile ? strtolower(substr($targetFile, -4)) : '';
  foreach ($safepaths as $safepath) {
    $safeBase = realpath($safepath);
    if ($targetFile && $safeBase && in_safe_path($targetFile, $safeBase) && in_array($targetExt, $safeexts, true)) {
      @unlink($targetFile);
      $result = true;
      break;
    }
  }
  break;
case 'add':
  $file = basename($file);
  $path = "$docroot/languages/$file";
  $save = "/tmp/lang-$file.zip";
  if (!is_dir($path) && !@mkdir($path, 0777, true)) break;
  if ($result = file_put_contents($save,base64_decode(preg_replace('/^data:.*;base64,/','',$_POST['filedata'])))) {
    @unlink("$docroot/webGui/javascript/translate.$file.js");
    foreach (glob("$path/*.dot",GLOB_NOSORT) as $dot_file) unlink($dot_file);
    $err = 0;
    $zip = new ZipArchive();
    if ($zip->open($save) === true) {
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false) continue;
        if (substr($entry, -1) === '/') continue;
        $content = $zip->getFromIndex($i);
        if ($content === false) {
          $err = 2;
          break;
        }
        $name = strtolower(basename($entry));
        if ($name === '') continue;
        if (!preg_match('/\.(dot|txt|json)$/', $name)) continue;
        if (file_put_contents("$path/$name", $content) === false) {
          $err = 2;
          break;
        }
      }
      $zip->close();
    } else {
      $err = 2;
    }
    @unlink($save);
    if ($err > 1) {
      remove_tree($path);
      $result = false;
      break;
    }
    [$home,$name] = my_explode(' (',urldecode($_POST['name']));
    $name  = rtrim($name,')'); $i = 0;
    $place = "$plugins/lang-$file.xml";
    $child = ['LanguageURL','Language','LanguageLocal','LanguagePack','Author','Name','TemplateURL','Version','Icon','Description','Changes'];
    $value = ['',$name,$home,$file,$_SERVER['HTTP_HOST'],"$name translation",$place,date('Y.m.d',time()),'','',''];
    // create a corresponding XML file
    $xml = new SimpleXMLElement('<Language/>');
    foreach ($child as $key) $xml->addChild($key,$value[$i++]);
    // saved as file (not link)
    $xml->asXML($place);
    // return list of installed language packs
    $installed = [];
    foreach (glob("$docroot/languages/*",GLOB_ONLYDIR) as $dir) $installed[] = basename($dir);
    exit(implode(',',$installed));
  }
  break;
case 'rm':
  $file = basename($file);
  $path = "$docroot/languages/$file";
  if ($result = is_dir($path)) {
    $result = remove_tree($path);
    @unlink("$docroot/webGui/javascript/translate.$file.js");
    @unlink("$boot/lang-$file.xml");
    @unlink("$plugins/lang-$file.xml");
    @unlink("$tmp/lang-$file.xml");
    @unlink("$boot/dynamix/lang-$file.zip");
    // return list of installed language packs
    $installed = [];
    foreach (glob("$docroot/languages/*",GLOB_ONLYDIR) as $dir) $installed[] = basename($dir);
    exit(implode(',',$installed));
  }
  break;
}
exit($result ? 'OK 200' : 'Internal Error 500');
?>
