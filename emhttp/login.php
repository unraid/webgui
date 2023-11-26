<?php
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";

// add translations
extract(parse_plugin_cfg('dynamix',true));

$login_locale = $display['locale'];
require_once "$docroot/webGui/include/Translations.php";

$var = parse_ini_file('state/var.ini');
$error = '';

if ($_SERVER['REQUEST_URI'] == '/logout') {
    // User Logout
    if (isset($_COOKIE[session_name()])) {
        session_start();
        unset($_SESSION['unraid_login']);
        unset($_SESSION['unraid_user']);
        // delete session file
        session_destroy();
        // delete the session cookie
        $params = session_get_cookie_params();
        setcookie(session_name(), '', 0, '/', $params['domain'], $params['secure'], isset($params['httponly']));
        syslog(LOG_INFO, "Successful logout user {$_SERVER['USER']} from {$_SERVER['REMOTE_ADDR']}");
    }
    $error = _('Successfully logged out');
}

$result = exec( "/usr/bin/passwd --status root");
if (($result === false) || (substr($result, 0, 6) !== "root P"))
  include "$docroot/webGui/include/.set-password.php";
else
  include "$docroot/webGui/include/.login.php";
?>
