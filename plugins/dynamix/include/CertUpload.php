<?PHP
/* Copyright 2005-2020, Lime Technology
 * Copyright 2012-2020, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$text = $_POST['text'] ?? '';

file_safeput_contents('/boot/config/ssl/certs/certificate_bundle.pem.new', $text);

//validate certificate_bundle.pem.new is for *.unraid.net before moving it over to certificate_bundle.pem
if (preg_match('/[0-9a-f]{40}\.unraid\.net$/', exec('openssl x509 -in /boot/config/ssl/certs/certificate_bundle.pem.new -subject -noout 2>&1'))) {
  rename('/boot/config/ssl/certs/certificate_bundle.pem.new', '/boot/config/ssl/certs/certificate_bundle.pem');
} else {
  unlink('/boot/config/ssl/certs/certificate_bundle.pem.new');
}
?>
