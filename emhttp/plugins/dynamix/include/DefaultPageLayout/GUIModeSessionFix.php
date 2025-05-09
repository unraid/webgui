<?php
/**
 * This file is used to fix the session for the Unraid web interface when booted in GUI mode.
 * This can be deleted if GUI mode authentication is enabled.
 */

function is_localhost()
{
    // Use the peer IP, not the Host header which can be spoofed
    return $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';
}
function is_good_session()
{
    return isset($_SESSION) && isset($_SESSION['unraid_user']) && isset($_SESSION['unraid_login']);
}
if (is_localhost() && !is_good_session()) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    session_start();
    $_SESSION['unraid_login'] = time();
    $_SESSION['unraid_user'] = 'root';
    session_write_close();
    my_logger("Unraid GUI-boot: created root session for localhost request.");
}
?>