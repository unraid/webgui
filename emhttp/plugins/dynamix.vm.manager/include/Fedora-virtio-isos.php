<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2015-2021, Derek Macias, Eric Schultz, Jon Panozzo.
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
require_once "$docroot/plugins/dynamix/include/Helpers.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

// add translations
$_SERVER['REQUEST_URI'] = 'vms';
require_once "$docroot/webGui/include/Translations.php";

// get Fedora download archive
$fedora  = '/var/tmp/fedora-virtio-isos';
$archive = 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio';
exec("wget -T10 -qO- $archive|grep -Po '\"\\Kvirtio-win-[^/]+'",$isos,$code);

if ($code==0 && count($isos)>1) {
  // delete obsolete entries
  foreach ($virtio_isos as $iso => $data) if (!in_array($iso,$isos)) unset($virtio_isos[$iso]);
  // add new entries
  foreach ($isos as $iso) {
    if (isset($virtio_isos[$iso])) continue;
    $file = explode('-',$iso);
    if (count($file)==4) array_pop($file);
    $file = implode('-',$file);
    $virtio_isos[$iso]['name'] = "$iso.iso";
    $virtio_isos[$iso]['url'] = "$archive/$iso/$file.iso";
    $virtio_isos[$iso]['size'] = 600*1024*1024; // assume 600 MB - adjusted once file is downloaded
    $virtio_isos[$iso]['md5'] = ''; // unused md5 - created once file is downloaded
  }
  // sort with newest version first
  uksort($virtio_isos,function($a,$b){return strnatcmp($b,$a);});
  // save obtained information to keep '$virtio_isos' up-to-date
  file_put_contents($fedora,serialize($virtio_isos));
} else @unlink($fedora);

$iso_dir = $domain_cfg['MEDIADIR'];
if (empty($iso_dir) || !is_dir($iso_dir)) {
  $iso_dir = '/mnt/user/isos/';
} else {
  $iso_dir = str_replace('//', '/', $iso_dir.'/');
}
$strMatch = '';
if (!empty($domain_cfg['MEDIADIR']) && !empty($domain_cfg['VIRTIOISO']) && dirname($domain_cfg['VIRTIOISO'])!='.' && is_file($domain_cfg['VIRTIOISO'])) $strMatch = 'manual';
foreach ($virtio_isos as $key => $value) {
  if (($domain_cfg['VIRTIOISO'] == $iso_dir.$value['name']) && is_file($iso_dir.$value['name'])) $strMatch = $value['name'];
  echo mk_option($strMatch, $value['name'], $value['name']);
 }
echo mk_option($strMatch, 'manual', _('Manual'));
?>
