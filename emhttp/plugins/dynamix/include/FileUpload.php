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
  $path = $_POST['path'];
  foreach ($safepaths as $safepath) {
    if (strpos(dirname("$path/{$_POST['output']}"),$safepath)===0 && in_array(substr(basename($_POST['output']),-4),$safeexts)) {
      exec("mkdir -p ".escapeshellarg(realpath($path)));
      $result = @rename($verifiedPNG, "$path/{$_POST['output']}");
      break;
    }
  }
  break;
case 'delete':
  $path = $_POST['path'];
  foreach ($safepaths as $safepath) {
    if (strpos(realpath("$path/$file"), $safepath) === 0 && in_array(substr(realpath("$path/$file"), -4), $safeexts)) {
      exec("rm -f ".escapeshellarg(realpath("$path/$file")));
      $result = true;
      break;
    }
  }
  break;
case 'add':
  $file = basename($file);
  $path = "$docroot/languages/$file";
  $save = "/tmp/lang-$file.zip";
  exec("mkdir -p $path");
  if ($result = file_put_contents($save,base64_decode(preg_replace('/^data:.*;base64,/','',$_POST['filedata'])))) {
    @unlink("$docroot/webGui/javascript/translate.$file.js");
    foreach (glob("$path/*.dot",GLOB_NOSORT) as $dot_file) unlink($dot_file);
    exec("unzip -qqjLo -d $path $save", $dummy, $err);
    @unlink($save);
    if ($err > 1) {
      exec("rm -rf $path");
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
    exec("rm -rf $path");
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
