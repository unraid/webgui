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
$dockerHelpers = source("$root/emhttp/plugins/dynamix.docker.manager/include/Helpers.php");
mustContain('function docker_env_option',$dockerHelpers,'Container arguments must use a structured environment option.');
mustContain("escapeshellarg(\$name.'='.implode(' ',\$values))",$dockerHelpers,'Container argument values must be shell-escaped as one value.');
$docker = source("$root/emhttp/plugins/dynamix.docker.manager/include/CreateDocker.php");
$updateContainer = source("$root/emhttp/plugins/dynamix.docker.manager/scripts/update_container");
mustContain("docker_env_option('ORG_ENTRYPOINT'",$docker,'Container entrypoints must not be joined into shell syntax.');
mustContain("docker_env_option('ORG_CMD'",$docker,'Container commands must not be joined into shell syntax.');
mustContain("docker_env_option('ORG_ENTRYPOINT'",$updateContainer,'Updated container entrypoints must use the same safe representation.');
mustNotContain('ORG_ENTRYPOINT="',$docker,'Container entrypoints must not be interpolated into a shell assignment.');
mustNotContain('ORG_CMD="',$docker,'Container commands must not be interpolated into a shell assignment.');

$pluginHelpers = source("$root/emhttp/plugins/dynamix.plugin.manager/include/PluginHelpers.php");
mustContain('function plugin_delete_token',$pluginHelpers,'Plugin deletion must use an opaque server-validated token.');
mustContain('JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT',$pluginHelpers,'Plugin values must be encoded for their output context.');
mustContain("DeletePlugin.php",$pluginHelpers,'Plugin deletion must use the dedicated POST endpoint.');
mustNotContain('plugin_rm',$pluginHelpers,'Plugin deletion must not build a shell command from a filename.');
$deletePlugin = source("$root/emhttp/plugins/dynamix.plugin.manager/include/DeletePlugin.php");
mustContain("REQUEST_METHOD'] ?? 'GET') !== 'POST'",$deletePlugin,'Plugin deletion must reject non-POST requests.');
mustContain('hash_equals',$deletePlugin,'Plugin deletion must validate its opaque token.');
mustContain('unlink($file)',$deletePlugin,'Plugin deletion must use a bounded PHP filesystem operation.');
$pluginsError = source("$root/emhttp/plugins/dynamix.plugin.manager/PluginsError.page");
mustContain('htmlspecialchars($plugin_file', $pluginsError,'Plugin error filenames must be HTML-escaped.');

$openSsl = source("$root/emhttp/plugins/dynamix/scripts/open_ssl");
mustContain("'aes-256-gcm'",$openSsl,'Stored values must use an authenticated cipher.');
mustContain('random_bytes(32)',$openSsl,'The encryption key must be randomly generated.');
mustContain("'v2:'",$openSsl,'Encrypted values must carry a versioned authenticated format.');
mustContain("legacy_cipher",$openSsl,'Existing values must have an explicit migration path.');
mustNotContain('dmidecode',$openSsl,'The encryption key must not be derived from machine identity.');
mustNotContain('wlan0/address',$openSsl,'The encryption key must not be derived from a network address.');
$wirelessPhp = source("$root/emhttp/plugins/dynamix/include/Wireless.php");
mustContain('function decode_wifi_value',$wirelessPhp,'Wireless values must be decoded through the authenticated helper.');
$wireless = source("$root/emhttp/plugins/dynamix/scripts/wireless");
mustContain('base64_encode',$wireless,'Generated wireless state must encode values as inert data.');
mustContain('$text  = ["PORT=".$encode',$wireless,'Generated wireless state must write encoded key/value data.');
$rcWireless = source("$root/etc/rc.d/rc.wireless");
mustContain('base64 -d',$rcWireless,'Wireless state must be decoded as data.');
mustContain('Never source it',$rcWireless,'Wireless state must never be sourced as shell code.');
mustNotContain('[[ -r $INI ]] && . $INI',$rcWireless,'Wireless state must not be sourced.');

echo "Safe runtime regression checks passed.\n";
