#!/usr/bin/php -q
<?PHP
if (!isset($argv[1])) {
  exit(0);
}

# We simply use this script to replace any arguments containing a user share path (e.g. /mnt/user/domains/) with the real backing disk (e.g. /mnt/disk1/domains/).

# arguments will look something like this:
# -drive file=/mnt/cache/domains/win2012r2/win2012r2.qcow2,if=none,id=drive-virtio-disk2,format=qcow2,cache=writeback
# or this in newer versions of qemu/libvirt:
# -blockdev '{"driver":"file","filename":"/mnt/user/domains/win2012r2/win2012r2.qcow2","node-name":"libvirt-2-storage","cache":{"direct":false,"no-flush":false},"auto-read-only":true,"discard":"unmap"}'

function detect_user_share(&$arg) {
  $arg = preg_replace_callback('|(/mnt/user/[^,"]+\.[^,"\s]*)|', function($match) {
    if (is_file($match[0])) {
      // resolve the actual disk or cache backing device for this user share path
      $realdisk = trim(shell_exec("getfattr --absolute-names --only-values -n system.LOCATION ".escapeshellarg($match[0])." 2>/dev/null"));
      if (!empty($realdisk)) {
        $replacement = str_replace('/mnt/user/', "/mnt/$realdisk/", $match[0]);
        if (is_file($replacement)) {
          // the replacement path (e.g. /mnt/disk1/domains/vmname/vdisk1.img) checks out so use it
          return $replacement;
        }
      }
    }
    return $match[0];
  }, $arg);
}

array_shift($argv);
array_walk($argv, 'detect_user_share');

$whole_cmd = '';
foreach ($argv as $arg) {
  $whole_cmd .= escapeshellarg($arg).' ';
}

echo trim($whole_cmd);
?>
