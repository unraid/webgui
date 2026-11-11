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
  public function getRegistryAuth($image, $authRegistry = null) {
    $GLOBALS['authLookups'][] = $authRegistry;
    return array_merge([
      'apiUrl' => $GLOBALS['scenario']['origin'].'/v2/',
      'repository' => 'library/', 'imageName' => 'test', 'imageTag' => 'latest',
      'username' => '', 'password' => '',
    ], $authRegistry === null ? ($GLOBALS['scenario']['credentials'] ?? []) : ($GLOBALS['scenario']['destination_credentials'] ?? []));
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
  return $handle->effective ?? $GLOBALS['scenario']['effective'];
}

function curl_errno($handle) { return $handle->error; }

function curl_exec($handle) {
  $GLOBALS['requests'][] = clone $handle;
  if (isset($GLOBALS['scenario']['steps'])) {
    $step = $GLOBALS['scenario']['steps'][count($GLOBALS['requests']) - 1];
    same($step['url'], $handle->url, 'Request must use the expected registry');
    $handle->effective = $step['effective'] ?? $handle->url;
    return $step['reply'];
  }
  if (count($GLOBALS['requests']) === 1) {
    if (isset($GLOBALS['scenario']['initial_reply'])) return $GLOBALS['scenario']['initial_reply'];
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
  $GLOBALS['authLookups'] = [];
  $GLOBALS['scenarioCount'] = ($GLOBALS['scenarioCount'] ?? 0) + 1;
  return (new DockerUpdate())->getRemoteVersionV2('test:latest');
}

$manifest = '/v2/library/test/manifests/latest';
$redirect = "HTTP/1.1 302 Found\r\nLocation: https://registry.example$manifest\r\n"
  ."www-authenticate: Bearer realm=\"https://old-tokens.example/token\",service=\"old\",scope=\"repository:old:pull\"\r\n\r\n";
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
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
    'initial_reply' => $redirect."HTTP/2 401\r\nwww-authenticate: Bearer realm=\"https://tokens.example/token\",service=\"registry\",scope=\"repository:library/test:pull\"\r\n\r\n"],
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
    'auth' => 'basic', 'credentials' => ['username' => 'test-user', 'password' => 'test-password'],
    'initial_reply' => $redirect."HTTP/2 401\r\nwww-authenticate: Basic realm=\"registry\"\r\n\r\n"],
  ['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
    'auth' => 'none', 'initial_reply' => $redirect."HTTP/2 200\r\nDocker-Content-Digest: sha256:test-digest\r\n\r\n"],
];
foreach ($allowed as $scenario) {
  same('sha256:test-digest', run($scenario), 'Approved registry must return its digest');
  $requests = $GLOBALS['requests'];
  $digest = end($requests);
  same($scenario['effective'], $digest->url, 'Digest request must use the approved final URL');
  same(true, $digest->options[CURLOPT_FOLLOWLOCATION], 'Authenticated requests must still support redirects');
  foreach ($requests as $request) {
    same(false, $request->options[CURLOPT_UNRESTRICTED_AUTH], 'Redirects must not forward authentication across origins');
    same(CURLPROTO_HTTPS, $request->options[CURLOPT_REDIR_PROTOCOLS], 'Redirects must stay on HTTPS');
  }
  if (($scenario['auth'] ?? 'bearer') === 'basic') {
    same('test-user:test-password', $digest->options[CURLOPT_USERPWD], 'Same-origin Basic authentication must work');
  } elseif (($scenario['auth'] ?? 'bearer') === 'bearer') {
    same(true, in_array('Authorization: Bearer test-token', $digest->options[CURLOPT_HTTPHEADER], true),
      'Approved digest request must include its Bearer token');
    if (!empty($scenario['credentials']['password'])) {
      same(true, $requests[1]->options[CURLOPT_FOLLOWLOCATION], 'Token request must support same-origin redirects');
    } else {
      same(true, $requests[1]->options[CURLOPT_FOLLOWLOCATION], 'Credentialless token request may follow redirects');
    }
  }
}

$denied = [
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
foreach (['https://other.example', 'https://registry.example:5443'] as $destination) {
  same('sha256:test-digest', run(['origin' => 'https://registry.example', 'effective' => $destination.$manifest,
    'credentials' => ['username' => 'source-user', 'password' => 'source-password']]),
    'Public destination must work even when the source has saved credentials');
  same(parse_url($destination, PHP_URL_HOST).(parse_url($destination, PHP_URL_PORT) ? ':'.parse_url($destination, PHP_URL_PORT) : ''),
    end($GLOBALS['authLookups']), 'Destination credentials must be selected by host and port');
  same(false, isset($GLOBALS['requests'][1]->options[CURLOPT_USERPWD]), 'Source credentials must not reach the destination token service');
}
same('sha256:test-digest', run(['origin' => 'https://registry.example', 'effective' => 'https://other.example'.$manifest,
  'auth' => 'basic', 'credentials' => ['username' => 'source-user', 'password' => 'source-password'],
  'destination_credentials' => ['username' => 'target-user', 'password' => 'target-password']]),
  'Private destination must use its own configured credentials');
same('target-user:target-password', end($GLOBALS['requests'])->options[CURLOPT_USERPWD], 'Only destination credentials may authenticate the final registry');

$basic = "HTTP/1.1 401 Unauthorized\r\nwww-authenticate: Basic realm=\"registry\"\r\n\r\n";
$sourceURL = 'https://registry.example'.$manifest;
$targetURL = 'https://other.example'.$manifest;
same('sha256:test-digest', run(['origin' => 'https://registry.example', 'effective' => $sourceURL,
  'credentials' => ['username' => 'source-user', 'password' => 'source-password'],
  'destination_credentials' => ['username' => 'target-user', 'password' => 'target-password'],
  'steps' => [
    ['url' => $sourceURL, 'reply' => $basic],
    ['url' => $sourceURL, 'effective' => $targetURL, 'reply' => "HTTP/1.1 302 Found\r\nDocker-Content-Digest: sha256:wrong\r\n\r\n".$basic],
    ['url' => $targetURL, 'reply' => $basic],
    ['url' => $targetURL, 'reply' => "HTTP/1.1 200 OK\r\nDocker-Content-Digest: sha256:test-digest\r\n\r\n"],
  ]]), 'A redirect after authentication must re-authenticate at the destination and ignore intermediate digests');
same('source-user:source-password', $GLOBALS['requests'][1]->options[CURLOPT_USERPWD], 'Source request must use source credentials');
same('target-user:target-password', $GLOBALS['requests'][3]->options[CURLOPT_USERPWD], 'Redirected private registry must use target credentials');

same(null, run(['origin' => 'https://registry.example', 'effective' => $targetURL, 'auth' => 'basic',
  'credentials' => ['username' => 'source-user', 'password' => 'source-password']]),
  'A private destination without its own credentials must not reuse source credentials');
same(1, count($GLOBALS['requests']), 'Missing destination credentials must fail before an authenticated request');

$steps = [];
for ($i=0; $i<5; $i++) {
  $url = $sourceURL.'?hop='.$i;
  if ($i === 0) $url = $sourceURL;
  $steps[] = ['url' => $url, 'reply' => $basic];
  $steps[] = ['url' => $url, 'effective' => $sourceURL.'?hop='.($i+1), 'reply' => $basic];
}
same(null, run(['origin' => 'https://registry.example', 'effective' => $sourceURL,
  'credentials' => ['username' => 'source-user', 'password' => 'source-password'], 'steps' => $steps]),
  'Repeated authentication redirects must stop after the finite retry limit');
same(10, count($GLOBALS['requests']), 'Manifest authentication retries must be bounded');

$tokenURL = 'https://tokens.example/token?service=registry&scope=repository%3Alibrary%2Ftest%3Apull';
$steps = [['url' => $sourceURL, 'reply' => "HTTP/1.1 401 Unauthorized\r\nwww-authenticate: Bearer realm=\"https://tokens.example/token\",service=\"registry\",scope=\"repository:library/test:pull\"\r\n\r\n"]];
for ($i=0; $i<5; $i++) {
  $steps[] = ['url' => $i === 0 ? $tokenURL : 'https://other.example/token/'.$i,
    'effective' => 'https://other.example/token/'.($i+1), 'reply' => '{}'];
}
same(null, run(['origin' => 'https://registry.example', 'effective' => $sourceURL, 'steps' => $steps]),
  'Repeated token-service redirects without a token must stop');
same(6, count($GLOBALS['requests']), 'Token authentication retries must be bounded');

same(null, run(['origin' => 'https://registry.example', 'effective' => 'https://registry.example'.$manifest,
  'digest_error' => true]), 'Digest transport failure must return null');
echo "Docker registry redirect tests passed (".$GLOBALS['scenarioCount']." scenarios).\n";
