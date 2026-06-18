<?PHP
// start/stop service
exec("/etc/rc.d/rc.ptpd ".escapeshellarg($_POST['cmd']));
?>
