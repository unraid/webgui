#!/usr/bin/env php
<?php
declare(strict_types=1);

$docroot = dirname(__DIR__) . '/emhttp';
$subnet = ['bridge' => '', 'host' => ''];
$driver = ['bridge' => 'bridge'];
$var = [];
$dockercfg = ['DOCKER_APP_CONFIG_PATH' => sys_get_temp_dir()];
function _var(&$name, $key = null, $default = '') { return is_null($key) ? ($name ?? $default) : ($name[$key] ?? $default); }
function my_lang($text) { return $text; }
require_once dirname(__DIR__) . '/emhttp/plugins/dynamix.docker.manager/include/Helpers.php';

function assertTrue(bool $condition, string $message): void
{
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

function buildCommand(array $fields): string
{
  $fields += ['Network' => 'bridge', 'ExtraParams' => ''];
  $xml = "<Container><Name>web</Name><Repository>nginx:alpine</Repository><PostArgs>--flag</PostArgs>";
  foreach ($fields as $field => $value) {
    $xml .= "<$field>$value</$field>";
  }
  return xmlToCommand($xml . '</Container>')[0];
}

$cmd = buildCommand(['NetbirdEnabled' => 'true', 'NetbirdUserspaceNetworking' => 'true', 'NetbirdSetupKey' => 'KEY']);
assertTrue(str_contains($cmd, "--entrypoint='/opt/unraid/netbird'"), 'NetBird must override the entrypoint.');
assertTrue(str_contains($cmd, "-v '/usr/local/share/docker/netbird_container_hook':'/opt/unraid/netbird'"), 'NetBird must mount the hook.');
assertTrue(str_contains($cmd, "-l net.unraid.docker.netbird.hostname='web'"), 'The hostname must default to the container name.');
assertTrue(str_contains($cmd, "-e NETBIRD_SETUP_KEY='KEY'"), 'The setup key must reach the hook.');
assertTrue(str_contains($cmd, "-e ORG_POSTARGS='--flag'"), 'Post arguments must be handed to the hook.');
assertTrue(!str_contains($cmd, 'NET_ADMIN') && !str_contains($cmd, '/dev/net/tun'), 'Userspace mode needs no extra privileges.');
assertTrue(str_contains($cmd, "-v '" . realpath(sys_get_temp_dir()) . "/web/.netbird':'/var/lib/netbird' -e NETBIRD_STATE_DIR='/var/lib/netbird'"), 'NetBird state must default to the container appdata folder.');

$cmd = buildCommand(['NetbirdEnabled' => 'true', 'NetbirdStateDir' => '/data/nb']);
assertTrue(str_contains($cmd, "-e NETBIRD_STATE_DIR='/data/nb'") && !str_contains($cmd, '/var/lib/netbird'), 'A user state directory replaces the default mount.');

$cmd = buildCommand(['NetbirdEnabled' => 'true', 'NetbirdUserspaceNetworking' => 'false']);
assertTrue(str_contains($cmd, '--cap-add=NET_ADMIN') && str_contains($cmd, "--device='/dev/net/tun'"), 'Kernel mode needs tun and NET_ADMIN.');

$cmd = buildCommand(['TailscaleEnabled' => 'true', 'NetbirdEnabled' => 'true']);
assertTrue(str_contains($cmd, '/opt/unraid/tailscale') && !str_contains($cmd, '/opt/unraid/netbird'), 'Only one of Tailscale and NetBird may be active.');

$cmd = buildCommand([]);
assertTrue(!str_contains($cmd, '--entrypoint') && !str_contains($cmd, 'ORG_POSTARGS') && str_contains($cmd, '--flag'), 'Containers without a VPN must not change.');

$cmd = buildCommand(['TailscaleEnabled' => 'true']);
assertTrue(str_contains($cmd, "-e ORG_POSTARGS='--flag'"), 'Tailscale must keep handing post arguments to the hook.');


$cmd = buildCommand(['Network' => 'host', 'NetbirdEnabled' => 'true']);
assertTrue(!str_contains($cmd, '/opt/unraid/netbird'), 'NetBird must not be added on the host network.');

$cmd = buildCommand(['ExtraParams' => '--net=host', 'TailscaleEnabled' => 'true']);
assertTrue(!str_contains($cmd, '/opt/unraid/tailscale') && str_contains($cmd, '--flag'), 'Tailscale must not be added when Extra Parameters select the host network.');

echo "NetBird container tests passed.\n";
