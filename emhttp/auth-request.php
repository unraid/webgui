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
  '/webGui/images/yellow-on.png'
];
if (in_array(preg_replace(['/\?v=\d+$/','/\?\d+$/'],'',$_SERVER['REQUEST_URI']),$arrWhitelist)) {
  // authorized
  http_response_code(200);
} else {
  // non-authorized
  //error_log(print_r($_SERVER, true));
  http_response_code(401);
}
exit;
