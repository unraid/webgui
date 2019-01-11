<?PHP
exec("git -C /boot status --porcelain", $output);
echo implode("<br>", $output);
?>
