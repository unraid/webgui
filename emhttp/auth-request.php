<?php
// only start the session if a session cookie exists
if (isset($_COOKIE[session_name()])) {
  session_start();
  // authorized?
  if (isset($_SESSION["unraid_login"])) {
    if (time() - $_SESSION['unraid_login'] > 300) {
      $_SESSION['unraid_login'] = time();
    }
    session_write_close();
    http_response_code(200);
    exit;
  }
  session_write_close();
}

function isPathInDocroot(string $realPath, string $docroot): bool {
  return $realPath === $docroot || str_starts_with($realPath, $docroot . '/');
}

function getCanonicalRequestUri(string $docroot): string {
  $requestUri = getRequestUriPath();

  $realRequestPath = realpath($docroot . '/' . ltrim($requestUri, '/'));
  if (!is_string($realRequestPath) || !isPathInDocroot($realRequestPath, $docroot)) {
    return '';
  }

  $canonicalRequestUri = substr($realRequestPath, strlen($docroot));
  return $canonicalRequestUri === '' ? '/' : $canonicalRequestUri;
}

function isWebComponentsRequest(string $requestUri): bool {
  $webComponentsDirectory = '/plugins/dynamix.my.servers/unraid-components';
  return $requestUri === $webComponentsDirectory || str_starts_with($requestUri, $webComponentsDirectory . '/');
}

function getRequestUriPath(): string {
  $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  return is_string($requestUri) ? $requestUri : '/';
}

function getAllowedExternalPublicAssetTargets(): array {
  return [
    '/webGui/images/case-model.png' => '/boot/config/plugins/dynamix/case-model.png',
  ];
}

function isAllowedPublicAssetRequest(string $requestUri, string $docroot, array $arrWhitelist): bool {
  if (!in_array($requestUri, $arrWhitelist, true)) {
    return false;
  }

  $realRequestPath = realpath($docroot . '/' . ltrim($requestUri, '/'));
  if (is_string($realRequestPath) && isPathInDocroot($realRequestPath, $docroot)) {
    return true;
  }

  $allowedExternalTargets = getAllowedExternalPublicAssetTargets();
  return isset($allowedExternalTargets[$requestUri]) &&
    $realRequestPath === $allowedExternalTargets[$requestUri];
}

// Base whitelist of files
$arrWhitelist = [
  '/webGui/styles/clear-sans-bold-italic.eot',
  '/webGui/styles/clear-sans-bold-italic.woff',
  '/webGui/styles/clear-sans-bold-italic.ttf',
  '/webGui/styles/clear-sans-bold-italic.svg',
  '/webGui/styles/clear-sans-bold.eot',
  '/webGui/styles/clear-sans-bold.woff',
  '/webGui/styles/clear-sans-bold.ttf',
  '/webGui/styles/clear-sans-bold.svg',
  '/webGui/styles/clear-sans-italic.eot',
  '/webGui/styles/clear-sans-italic.woff',
  '/webGui/styles/clear-sans-italic.ttf',
  '/webGui/styles/clear-sans-italic.svg',
  '/webGui/styles/clear-sans.eot',
  '/webGui/styles/clear-sans.woff',
  '/webGui/styles/clear-sans.ttf',
  '/webGui/styles/clear-sans.svg',
  '/webGui/styles/default-cases.css',
  '/webGui/styles/font-cases.eot',
  '/webGui/styles/font-cases.woff',
  '/webGui/styles/font-cases.ttf',
  '/webGui/styles/font-cases.svg',
  '/webGui/images/case-model.png',
  '/webGui/images/green-on.png',
  '/webGui/images/red-on.png',
  '/webGui/images/yellow-on.png',
  '/webGui/images/UN-logotype-gradient.svg',
  '/apple-touch-icon.png',
  '/favicon-96x96.png',
  '/favicon.ico',
  '/favicon.svg',
  '/web-app-manifest-192x192.png',
  '/web-app-manifest-512x512.png',
  '/manifest.json'
];

// Use canonical filesystem path checks against the trusted docroot.
$docroot = '/usr/local/emhttp';
$requestUri = getRequestUriPath();
$canonicalRequestUri = getCanonicalRequestUri($docroot);

// Allow explicit public assets with strict target checks.
if (isAllowedPublicAssetRequest($requestUri, $docroot, $arrWhitelist)) {
  http_response_code(200);
  exit;
}

// Allow canonical requests under unraid-components.
if (
  $canonicalRequestUri !== '' &&
  isWebComponentsRequest($canonicalRequestUri)
) {
  // authorized
  http_response_code(200);
} else {
  // non-authorized
  //error_log(print_r($_SERVER, true));
  http_response_code(401);
}
exit;
