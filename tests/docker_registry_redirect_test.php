#!/usr/bin/env php
<?php
declare(strict_types=1);

namespace DockerRegistryRedirectTest;

// Load the real update class without DockerClient.php's Unraid startup actions.
$source = file_get_contents(dirname(__DIR__).'/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php');
$start = strpos($source, 'class DockerUpdate{');
$end = strpos($source, 'class DockerClient {', $start);
if ($start === false || $end === false) throw new \RuntimeException('DockerUpdate class not found');
eval('namespace '.__NAMESPACE__.'; '.substr($source, $start, $end - $start));

class DockerUtil {
  public static function parseImageTag($image) { return []; }
}

class DockerClient {
  public function getRegistryAuth($image) {
    return array_merge([
      'apiUrl' => $GLOBALS['scenario']['origin'].'/v2/',
      'repository' => 'library/', 'imageName' => 'test', 'imageTag' => 'latest',
      'username' => '', 'password' => '',
    ], $GLOBALS['scenario']['credentials'] ?? []);
  }
}

// Replace only the transport boundary. Calls exercise the production method.
function getCurlHandle($url, $method = 'GET') {
  return (object)['url' => $url, 'options' => [CURLOPT_FOLLOWLOCATION => true], 'error' => 0];
}

function curl_setopt($handle, $option, $value) {
  $handle->options[$option] = $value;
  return true;
}

function curl_getinfo($handle, $option) {
  if ($option !== CURLINFO_EFFECTIVE_URL) throw new \RuntimeException('Unexpected curl info');
  return $GLOBALS['scenario']['effective'];
}

function curl_errno($handle) { return $handle->error; }

function curl_exec($handle) {
  $GLOBALS['requests'][] = clone $handle;
  if (count($GLOBALS['requests']) === 1) {
    $auth = $GLOBALS['scenario']['auth'] ?? 'bearer';
    if ($auth === 'none') return "HTTP/1.1 200 OK\r\nDocker-Content-Digest: sha256:test-digest\r\n";
    return $auth === 'basic'
      ? "HTTP/1.1 401 Unauthorized\r\nwww-authenticate: Basic realm=\"registry\"\r\n"
      : "HTTP/1.1 401 Unauthorized\r\nwww-authenticate: Bearer realm=\"https://tokens.example/token\",service=\"registry\",scope=\"repository:library/test:pull\"\r\n";
  }
  if (str_starts_with($handle->url, 'https://tokens.example/token?')) return '{"token":"test-token"}';
  if ($GLOBALS['scenario']['digest_error'] ?? false) {
    $handle->error = CURLE_COULDNT_CONNECT;
    return false;
  }
  return "HTTP/1.1 200 OK\r\nDocker-Content-Digest: sha256:test-digest\r\n";
}

function same($expected, $actual, $message) {
  if ($expected !== $actual) throw new \RuntimeException($message.'; actual: '.var_export($actual, true));
}

function run(array $scenario) {
  $GLOBALS['scenario'] = $scenario;
  $GLOBALS['requests'] = [];
  return (new DockerUpdate())->getRemoteVersionV2('test:latest');
}

$manifest = '/v2/library/test/manifests/latest';
$allowed = [
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest],
  ['origin' => 'https://registry.example:5443', 'effective' => 'https://registry.example:5443'.$manifest],
  ['origin' => 'https://registry.example', 'effective' => 'https://REGISTRY.EXAMPLE:443'.$manifest],
  ['origin' => 'https://lscr.io', 'effective' => 'https://ghcr.io'.$manifest],
  ['origin' => 'https://lscr.io', 'effective' => 'https://GHCR.IO:443'.$manifest],
  ['origin' => 'https://registry.example', 'effective' => 'https://cdn.example'.$manifest],
  ['origin' => 'https://registry.example', 'effective' => 'https://cdn.example:5443'.$manifest],
  ['origin' => 'https://registry.example:5443', 'effective' => 'https://cdn.example'.$manifest],
  ['origin' => 'https://registry.example', 'effective' => 'https://cdn.example'.$manifest, 'auth' => 'none'],
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
    'credentials' => ['username' => 'test-user', 'password' => 'test-password']],
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
    'auth' => 'basic', 'credentials' => ['username' => 'test-user', 'password' => 'test-password']],
];
foreach ($allowed as $scenario) {
  same('sha256:test-digest', run($scenario), 'Approved registry must return its digest');
  $requests = $GLOBALS['requests'];
  $digest = end($requests);
  same($scenario['effective'], $digest->url, 'Digest request must use the approved final URL');
  same(($scenario['auth'] ?? 'bearer') === 'none', $digest->options[CURLOPT_FOLLOWLOCATION],
    'Only credentialless digest requests may follow another redirect');
  if (($scenario['auth'] ?? 'bearer') === 'basic') {
    same('test-user:test-password', $digest->options[CURLOPT_USERPWD], 'Same-origin Basic authentication must work');
  } elseif (($scenario['auth'] ?? 'bearer') === 'bearer') {
    same(true, in_array('Authorization: Bearer test-token', $digest->options[CURLOPT_HTTPHEADER], true),
      'Approved digest request must include its Bearer token');
    if (!empty($scenario['credentials']['password'])) {
      same(false, $requests[1]->options[CURLOPT_FOLLOWLOCATION], 'Token request with saved credentials must not follow redirects');
    } else {
      same(true, $requests[1]->options[CURLOPT_FOLLOWLOCATION], 'Credentialless token request may follow redirects');
    }
  }
}

$denied = [
  'https://unapproved.example'.$manifest,
  'https://registry.example.unapproved.example'.$manifest,
  'https://registry.example:444'.$manifest,
  'http://registry.example'.$manifest,
  'https://user:password@registry.example'.$manifest,
  'file:///tmp/manifest',
  '/relative/manifest',
  '',
];
foreach ($denied as $destination) {
  same(null, run(['origin' => 'https://registry.example', 'effective' => $destination,
    'credentials' => ['username' => 'test-user', 'password' => 'test-password']]),
    'Unapproved destination must fail');
  same(1, count($GLOBALS['requests']), 'Unapproved destination must be rejected before any authentication request');
}
foreach ([
  ['origin' => 'https://lscr.io', 'effective' => 'http://ghcr.io'.$manifest],
  ['origin' => 'https://lscr.io', 'effective' => 'https://ghcr.io'.$manifest,
    'credentials' => ['username' => 'test-user', 'password' => 'test-password']],
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example:5443'.$manifest,
    'credentials' => ['username' => 'test-user', 'password' => 'test-password']],
] as $scenario) {
  same(null, run($scenario), 'Saved credentials must not cross origins and public redirects must use HTTPS');
  same(1, count($GLOBALS['requests']), 'Rejected cross-origin route must not send credentials');
}

same(null, run(['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
  'digest_error' => true]), 'Digest transport failure must return null');
echo "Docker registry redirect tests passed.\n";
