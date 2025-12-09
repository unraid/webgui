<?PHP
/* Copyright 2025-, Lime Technology
 * Copyright 2025-, Simon Fairweather.
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

#VFSETTINGS=0000:04:11.5|8086:1520|0|62:00:04:11:05:01 0000:04:10.5|8086:1520|1|62:00:04:10:05:01
#VFS=0000:04:00.1|8086:1521|3 0000:04:00.0|8086:1521|2

$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/webGui/include/Secure.php";
require_once "$docroot/webGui/include/Wrappers.php";

$sriov = '/boot/config/sriov.cfg';
$sriovvfs = '/boot/config/sriovvfs.cfg';

$type = _var($_POST,'type'); 
$pciid = _var($_POST,'pciid');
$vd = _var($_POST,'vd');

if (isset($pciid) && isset($vd)) {
  $newelement_check = $pciid.'|'.$vd.'|';

  switch($type) {
      case "sriov":
        $old  = is_file($sriov) ? rtrim(file_get_contents($sriov)) : '';
        $newexplode = preg_split('/\s+/', str_replace("VFS=","",$old), -1, PREG_SPLIT_NO_EMPTY);
        $new = $old;
        $numvfs= _var($_POST,'numvfs');
        $found = false;
        foreach($newexplode as $key => $newelement) {
          if (strpos($newelement,$newelement_check) !== false) {
            $found = true;
            if($numvfs == "0") {
              unset($newexplode[$key]) ;
              break;
            } else {
              $newexplode[$key] = $newelement_check.$numvfs;
              break;
            }
          }
        }
        if (!$found  && $numvfs != "0") $newexplode[] = $newelement_check.$numvfs;
        $new = "VFS=".implode(" ",$newexplode);
        $file = $sriov; 
        break;
      case "sriovsettings":
        $old  = is_file($sriovvfs) ? rtrim(file_get_contents($sriovvfs)) : '';
        $newexplode = preg_split('/\s+/', str_replace("VFSETTINGS=","",$old), -1, PREG_SPLIT_NO_EMPTY);
        $mac= _var($_POST,'mac');
        if ($mac == "") $mac = "00:00:00:00:00:00";
        $vfio= _var($_POST,'vfio');
        if ($vfio == "true") $vfio = 1; else $vfio = 0;
        $found = false;
        foreach($newexplode as $key => $newelement) {
          if (strpos($newelement,$newelement_check) !== false) {
            $found = true;
            if($mac == "00:00:00:00:00:00" && $vfio == 0) {
              unset($newexplode[$key]) ;
              break;
            } else {
              $newexplode[$key] = $newelement_check.$vfio."|".$mac;
              break;
            }
          }
        }
        if (!$found  && ($vfio != 0 || $mac != "00:00:00:00:00:00")) $newexplode[] = $newelement_check.$vfio."|".$mac;
        if ($newexplode) $new = "VFSETTINGS=".implode(" ",$newexplode);else $new = null;
        $file = $sriovvfs; 
        break;
  }
}

$reply = 0;
if ($new != $old) {
  if ($old) copy($file,"$file.bak");
  if ($new) file_put_contents($file,$new); else @unlink($file);
  $reply = 1;
}

echo $reply;
?>
