<?PHP
$cmd  = isset($_POST['cmd']) ? $_POST['cmd'] : 'load';
$path = $_POST['path'];
$file = rawurldecode($_POST['filename']);
$temp = "/var/tmp";
$safepaths = ['/boot/config/plugins/dynamix'];
$safeexts = ['.png'];

switch ($cmd) {
case 'load':
  if (isset($_POST['filedata'])) {
    exec("rm -f $temp/*.png");
    $result = file_put_contents("$temp/".basename($file), base64_decode(str_replace(['data:image/png;base64,',' '],['','+'],$_POST['filedata'])));
  }
  break;
case 'save':
  foreach ($safepaths as $safepath) {
    if (strpos(dirname("$path/{$_POST['output']}"), $safepath) === 0 && in_array(substr(basename($_POST['output']), -4), $safeexts)) {
      exec("mkdir -p ".escapeshellarg(realpath($path)));
      $result = @rename("$temp/".basename($file), "$path/{$_POST['output']}");
      break;
    }
  }
  break;
case 'delete':
  foreach ($safepaths as $safepath) {
    if (strpos(realpath("$path/$file"), $safepath) === 0 && in_array(substr(realpath("$path/$file"), -4), $safeexts)) {
      exec("rm -f ".escapeshellarg(realpath("$path/$file")));
      $result = true;
      break;
    }
  }
  break;
}
echo ($result ? 'OK 200' : 'Internal Error 500');
?>
