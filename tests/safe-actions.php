#!/usr/bin/env php
<?php
declare(strict_types=1);

function source(string $path): string
{
  $contents = file_get_contents($path);
  if ($contents === false) throw new RuntimeException("Unable to read $path");
  return $contents;
}

function mustContain(string $needle, string $haystack, string $message): void
{
  if (!str_contains($haystack,$needle)) throw new RuntimeException($message);
}

function mustNotContain(string $needle, string $haystack, string $message): void
{
  if (str_contains($haystack,$needle)) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$showPlugins = source("$root/emhttp/plugins/dynamix.plugin.manager/include/ShowPlugins.php");
mustContain('REQUEST_METHOD',$showPlugins,'Pending marker writes must check the request method.');
mustContain('preg_match',$showPlugins,'Pending markers must use a filename grammar.');
mustContain('is_link($target)',$showPlugins,'Pending marker writes must reject symlink targets.');
mustNotContain('file_put_contents("/tmp/plugins/pluginPending/$plugin"',$showPlugins,'Pending marker paths must not use the request value directly.');
mustContain("$.post('/plugins/dynamix.plugin.manager/include/ShowPlugins.php'",source("$root/emhttp/plugins/dynamix.plugin.manager/Plugins.page"),'Pending operations must use POST.');

$events = source("$root/emhttp/plugins/dynamix.docker.manager/include/Events.php");
mustContain('$requestMethod !==',$events,'Docker lifecycle events must use POST.');
mustNotContain('$_REQUEST',$events,'Docker lifecycle events must not read the merged request bag.');

$docker = source("$root/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php");
mustContain("isset(\$_POST['updateContainer'])",$docker,'Container updates must use POST.');
mustContain("\$_POST['confirmed'] ?? ''",$docker,'Batch updates must require explicit confirmation.');
mustNotContain("isset(\$_GET['updateContainer'])",$docker,'Container updates must not retain the GET trigger.');

$downgrade = source("$root/emhttp/plugins/dynamix.plugin.manager/include/Downgrade.php");
mustContain('downgrade_nonce',$downgrade,'Downgrade must use a session-bound nonce.');
mustContain('movedCurrent',$downgrade,'Downgrade must track files for rollback.');
mustNotContain("_var(\$_GET,'version')",$downgrade,'Downgrade must not take its version from GET.');

$vmAjax = source("$root/emhttp/plugins/dynamix.vm.manager/include/VMajax.php");
mustContain('$requestMethod !==',$vmAjax,'VM mutations must use POST.');
mustContain('vm_domain_xml_allowed',$vmAjax,'VM XML must pass the host-resource allowlist.');
mustContain('vm_template_source_path',$vmAjax,'VM template imports must use a bounded source path.');
mustContain('get_disk_stats($domName)',$vmAjax,'Disk resize must bind the disk to server-side VM metadata.');
mustNotContain('json_decode(file_get_contents($name))',$vmAjax,'VM template imports must not read an arbitrary caller path.');
mustNotContain('$_REQUEST',$vmAjax,'VM actions must not read the merged request bag.');
mustNotContain("'vm-removal']",$vmAjax,'VM removal must not be treated as read-only.');

$vmPage = source("$root/emhttp/plugins/dynamix.vm.manager/include/VMMachines.php");
mustContain("<form method='post' action=''>",$vmPage,'VM disk resize must submit with POST.');
mustNotContain("name='disk'",$vmPage,'VM disk resize must not trust a caller-supplied disk path.');
mustNotContain("name='oldcap'",$vmPage,'VM disk resize must not trust a caller-supplied old capacity.');

$control = source("$root/emhttp/plugins/dynamix/include/Control.php");
mustContain('uploadId',$control,'Uploads must use an opaque upload identifier.');
mustContain('upload_session_key',$control,'Uploads must bind state to the session.');
mustContain('lstat($file)',$control,'Uploads must revalidate the target inode.');
mustContain('chmod($file,0600)',$control,'Incomplete uploads must start private.');
mustNotContain('/var/tmp/$file.tmp',$control,'Uploads must not use basename-derived temporary state.');

$plugin = source("$root/emhttp/plugins/dynamix.plugin.manager/scripts/plugin");
mustContain('--max-redirect=0',$plugin,'Plugin downloads must reject redirects.');
mustContain('download_signed_manifest',$plugin,'Remote manifests must be signature checked.');
mustContain('external file requires a SHA256 value',$plugin,'External plugin files must require SHA-256 metadata.');
mustNotContain('md5_file(',$plugin,'External plugin files must not fall back to MD5.');

$api = source("$root/emhttp/plugins/dynamix.plugin.manager/scripts/PluginAPI.php");
mustContain('CURLOPT_FOLLOWLOCATION => false',$api,'Plugin API downloads must not follow redirects.');
mustContain('download_signed_manifest',$api,'Plugin API manifests must be signature checked.');

echo "Safe action regression checks passed.\n";
