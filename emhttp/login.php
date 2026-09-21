<?php
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/webGui/include/Wrappers.php";

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

// If issue with license key redirect to Tools/Registration, otherwise go to start page
$start_page = (!empty(_var($var,'regCheck'))) ? 'Tools/Registration' : _var($var,'START_PAGE','Main');

$root_status_output = [];
$root_status_code = -1;

function root_password_setup_allowed($output, $exit_code)
{
    if ($exit_code !== 0 || !is_array($output) || count($output) !== 1 || !is_string($output[0]))
        return false;

    $status_fields = preg_split('/\s+/', trim($output[0]));
    return is_array($status_fields) && ($status_fields[0] ?? null) === 'root' && ($status_fields[1] ?? null) === 'NP';
}

exec("/usr/bin/passwd --status root", $root_status_output, $root_status_code);
$can_set_root_password = root_password_setup_allowed($root_status_output, $root_status_code);

if ($can_set_root_password)
  include "$docroot/webGui/include/.set-password.php";
else
  include "$docroot/webGui/include/.login.php";
?>
