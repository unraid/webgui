#!/usr/bin/php
<?php
/* Copyright 2005-2024, Lime Technology
 * Copyright 2024, Simon Fairweather
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

 # Command for bash script  /usr/libexec/virtiofsd
 # eval exec /usr/bin/virtiofsd $(/usr/local/emhttp/plugins/dynamix.vm.manager/scripts/virtiofsd.php "$@")

 $file = "/etc/libvirt/virtiofsd.opt";
 $whole_cmd = '';
 $long_options = ["fd:","print-capabilities","syslog","daemonize","rlimit-nofile","thread-pool-size:","socket-path:","socket-group:","help","version","source:"];
 $short_options = "o:dfV"; 
 $argoptions = getopt($short_options,$long_options);
 
 array_shift($argv);
 foreach ($argv as $i=>$arg) {
     if (strpos($arg, "--fd") !== false) unset($argv[$i]) ;
     if ($arg == "-o") { unset($argv[$i]);unset($argv[$i+1]);		}
 }
 # Check if options file exists. Each option should be on a new line.
 if (is_file($file)) $options = explode("\n",file_get_contents($file)) ; else $options =  ['--syslog','--inode-file-handles=mandatory','--announce-submounts'];
 if (isset($argoptions['fd'])) {
    $options[] = "--fd=".$argoptions['fd'];
}
 
 if (isset($argoptions['o'])) {
     $virtiofsoptions = explode(',',$argoptions["o"]);
     foreach ($virtiofsoptions as $opt) {
         $optsplit = explode('=',$opt);
         switch ($optsplit[0]) {
             case "source":
                 $options[] = "--shared-dir={$optsplit[1]}";
                 break;
             case "cache":
                 $options[] = "--cache=never";
                 break;
             case "xattr":
                 $options[] = "--xattr";
                 break;
             case "sandbox":
                 $options[] = "--sandbox={$optsplit[1]}";
                 break;
         }
     }
 }
 
 $newargs = array_merge($options,$argv);
 foreach($newargs as $arg) {
     if ($arg != "") $whole_cmd.= escapeshellarg($arg).' ';
 }
 echo trim($whole_cmd);
 ?>