<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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
switch ($_POST['table']) {
case 't1':
  exec('for group in $(ls /sys/kernel/iommu_groups/ -1|sort -n);do echo "IOMMU group $group";for device in $(ls -1 "/sys/kernel/iommu_groups/$group"/devices/);do echo -n $\'\t\';lspci -ns "$device"|awk \'BEGIN{ORS=" "}{print "["$3"]"}\';lspci -s "$device";done;done',$groups);
  if (empty($groups)) {
    exec('lspci -n|awk \'{print "["$3"]"}\'',$iommu);
    exec('lspci',$lspci);
    $i = 0;
    foreach ($lspci as $line) echo "<tr><td>".$iommu[$i++]."</td><td>$line</td></tr>";
  } else {
    foreach ($groups as $line) {
      if (!$line) continue;
      if ($line[0]=='I') {
        if ($spacer) echo "<tr><td colspan='2' class='thin'></td>"; else $spacer = true;
        echo "</tr><tr><td>$line:</td><td>";
        $append = true;
      } else {
        echo preg_replace("/^\t/",$append?"":"<tr><td></td><td>",$line)."</td></tr>";
        $append = false;
      }
    }
  }
  break;
case 't2':
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list|sort -nu',$pairs);
  $i = 1;
  foreach ($pairs as $line) {
    $line = preg_replace(['/(\d+)[-,](\d+)/','/(\d+)\b/'],['$1 / $2','cpu $1'],$line);
    echo "<tr><td>".(strpos($line,'/')===false?"Single":"Pair ".$i++).":</td><td>$line</td></tr>";
  }
  break;
case 't3':
  exec('lsusb|sort',$lsusb);
  foreach ($lsusb as $line) {
    list($bus,$id) = explode(':', $line, 2);
    echo "<tr><td>$bus:</td><td>".trim($id)."</td></tr>";
  }
  break;
case 't4':
  exec('lsscsi -s',$lsscsi);
  foreach ($lsscsi as $line) {
    if (strpos($line,'/dev/')===false) continue;
    echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$line)."</td></tr>";
  }
  break;
}
?>
