<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
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
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');

$var   = '/var/local/emhttp/var.ini';
$cfg   = '/boot/config/wireless.cfg';
$ssl   = '/etc/rc.d/rc.ssl.input';
$tmp   = '/var/tmp/attr';
$wifi  = is_readable($cfg) ? (array)parse_ini_file($cfg,true) : [];
$attr  = is_readable($tmp) ? (array)parse_ini_file($tmp,true) : [];
$md5   = md5(json_encode($attr),true);
$cmd   = $_POST['cmd'];
$masks = [
  '255.0.0.0' => '8',        '255.255.0.0' => '16',     '255.255.128.0' => '17',   '255.255.192.0' => '18',
  '255.255.224.0' => '19',   '255.255.240.0' => '20',   '255.255.248.0' => '21',   '255.255.252.0' => '22',
  '255.255.254.0' => '23',   '255.255.255.0' => '24',   '255.255.255.128' => '25', '255.255.255.192' => '26',
  '255.255.255.224' => '27', '255.255.255.240' => '28', '255.255.255.248' => '29', '255.255.255.252' => '30'
];

// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

function scanWifi($port) {
  $wlan = [];
  exec("iw ".escapeshellarg($port)." scan | grep -P '^BSS|signal:|SSID:|Authentication suites:'",$scan);
  $n = -1;
  for ($i=0; $i<count($scan); $i++) {
    if (substr($scan[$i],0,3)=='BSS') {
      $wlan[++$n]['bss'] = substr($scan[$i],4,17);
    } elseif (strpos($scan[$i],'signal:')!==false) {
      $wlan[$n]['signal'] = trim(explode(': ',$scan[$i])[1]);
    } elseif (strpos($scan[$i],'SSID:')!==false) {
      $wlan[$n]['ssid'] = trim(explode(': ',$scan[$i])[1]);
    } elseif (strpos($scan[$i],'Authentication suites:')!==false) {
      $wlan[$n]['security'] = trim(explode(': ',$scan[$i])[1]);
    }
  }
  return array_values(array_intersect_key($wlan,array_unique(array_column($wlan,'ssid'))));
}

function saveWifi() {
  global $cfg, $wifi;
  $text = [];
  foreach ($wifi as $network => $block) {
    $text[] = "[$network]";
    foreach ($block as $key => $value) $text[] = "$key=\"$value\"";
  }
  file_put_contents_atomic($cfg,implode("\n",$text)."\n");
}

function saveAttr() {
  global $tmp, $attr, $md5;
  $text = [];
  if (md5(json_encode($attr),true) === $md5) return;
  foreach ($attr as $network => $block) {
    $text[] = "[$network]";
    foreach ($block as $key => $value) $text[] = "$key=\"$value\"";
  }
  file_put_contents_atomic($tmp,implode("\n",$text)."\n");
}

switch ($cmd) {
case 'list':
  $title = _('Connect to WiFi network');
  $port  = array_key_first($wifi);
  $carrier = "/sys/class/net/$port/carrier";
  $wlan  = scanWifi($port);
  $echo  = [];
  $index = 0;
  if (count(array_column($wlan,'ssid'))) {
    $up    = file_exists($carrier) && file_get_contents($carrier)==1;
    $alive = $up ? exec("iw ".escapeshellarg($port)." link | grep -Pom1 'SSID: \K.+'") : '';
    $state = $up ? _('Connected') : _('Disconnected');
    $color = $up ? 'blue' : 'red';

    foreach (array_column($wlan,'ssid') as $network) {
      $attr[$network]['ATTR1'] = $wlan[$index]['bss'];
      $attr[$network]['ATTR2'] = $wlan[$index]['signal'];
      $attr[$network]['ATTR3'] = $wlan[$index]['security'];
      $index++;
      if (isset($wifi[$network]['GROUP'])) {
        if ($network == $alive || $wifi[$network]['GROUP'] == 'active') {
          $echo['active'][] = "<dl><dt>$state:</dt>";
          $echo['active'][] = "<dd><span class=\"wifi\">$network</span><i class=\"fa fa-fw fa-wifi hand $color-text\" onclick=\"manage_wifi(encodeURIComponent('$network'),1)\" title=\"$title\"></i><input type=\"button\" class=\"form\" value=\""._('Info')."\" onclick=\"networkInfo('$port')\"></dd>";
        } else {
          $echo['saved'][] = empty($echo['saved']) ? "<dl><dt>"._('My networks').":</dt>" : "<dt>&nbsp;</dt>";
          $echo['saved'][] = "<dd><span class=\"wifi\">$network</span><i class=\"fa fa-wifi hand blue-text\" onclick=\"manage_wifi(encodeURIComponent('$network'),1)\" title=\"$title\"></i></dd>";
        }
      } else {
        $echo['other'][] = empty($echo['other']) ? "<dl><dt>"._('Other networks').":</dt>" : "<dt>&nbsp;</dt>";
        $echo['other'][] = "<dd><span class=\"wifi\">$network</span><i class=\"fa fa-wifi hand grey-text\" onclick=\"manage_wifi(encodeURIComponent('$network'),0)\" title=\"$title\"></i></dd>";
      }
    }
    if (empty($echo['active'])) $echo['active'][] = "<dl><dt>"._('Connected').":</dt><dd>"._('None')."</dd>";
    if (empty($echo['saved'])) $echo['saved'][] = "<dl><dt>"._('My networks').":</dt><dd>"._('None')."</dd>";
    if (empty($echo['other'])) $echo['other'][] = "<dl><dt>"._('Other networks').":</dt><dd>"._('None')."</dd>";
    $echo['active'] = implode($echo['active']);
    $echo['saved'] = implode($echo['saved']);
    $echo['other'] = implode($echo['other']);
    saveAttr();
  }
  echo json_encode($echo);
  break;
case 'join':
  if (is_readable($ssl)) extract(parse_ini_file($ssl));
  $token   = parse_ini_file($var)['csrf_token'];
  $ssid    = rawurldecode($_POST['ssid']);
  $drop    = $_POST['task']==1;
  $manual  = $_POST['task']==3;
  $user    = _var($wifi[$ssid],'USERNAME') && isset($cipher,$key,$iv) ? openssl_decrypt($wifi[$ssid]['USERNAME'],$cipher,$key,0,$iv) : _var($wifi[$ssid],'USERNAME');
  $passwd  = _var($wifi[$ssid],'PASSWORD') && isset($cipher,$key,$iv) ? openssl_decrypt($wifi[$ssid]['PASSWORD'],$cipher,$key,0,$iv) : _var($wifi[$ssid],'PASSWORD');
  $join    = _var($wifi[$ssid],'AUTOJOIN','no');
  $dhcp4   = _var($wifi[$ssid],'DHCP4','yes');
  $dns4    = _var($wifi[$ssid],'DNS4','no');
  $ip4     = _var($wifi[$ssid],'IP4');
  $mask4   = _var($wifi[$ssid],'MASK4','255.255.255.0');
  $gw4     = _var($wifi[$ssid],'GATEWAY4');
  $server4 = _var($wifi[$ssid],'SERVER4');
  $dhcp6   = _var($wifi[$ssid],'DHCP6');
  $dns6    = _var($wifi[$ssid],'DNS6','no');
  $ip6     = _var($wifi[$ssid],'IP6');
  $mask6   = _var($wifi[$ssid],'MASK6','64');
  $gwv6    = _var($wifi[$ssid],'GATEWAY6');
  $server6 = _var($wifi[$ssid],'SERVER6');
  $safe    = _var($wifi[$ssid],'SECURITY');
  $attr1   = $attr[$ssid]['ATTR1'];
  $attr2   = $attr[$ssid]['ATTR2'];
  $attr3   = $attr[$ssid]['ATTR3'];
  $ieee1   = strpos($attr3,'IEEE')!==false;
  $ieee2   = strpos($safe,'IEEE')!==false;
  $hide0   = ($manual || !$ieee2) && !$ieee1 ? 'hide' : '';
  $hide1   = $safe=='OPEN' || !$attr3 ? 'hide' : '';
  $hide2   = $dhcp4=='no' ? '': 'hide';
  $hide3   = $dns4=='no' ? 'hide' : '';
  $hide4   = $dhcp6=='no' ? '' : 'hide';
  $hide5   = $dhcp6=='' ? 'hide' : '';
  $hide6   = $dns6=='no' ? 'hide' : '';
  echo "<form name=\"wifi\" method=\"POST\" action=\"/update.php\" target=\"progressFrame\">";
  echo "<input type=\"hidden\" name=\"#file\" value=\"$cfg\">";
  echo "<input type=\"hidden\" name=\"#include\" value=\"/webGui/include/update.wireless.php\">";
  echo "<input type=\"hidden\" name=\"#command\" value=\"/webGui/scripts/wireless\">";
  echo "<input type=\"hidden\" name=\"#section\" value=\"$ssid\">";
  echo "<input type=\"hidden\" name=\"ATTR1\" value=\"$attr1\">";
  echo "<input type=\"hidden\" name=\"ATTR2\" value=\"$attr2\">";
  echo "<input type=\"hidden\" name=\"ATTR3\" value=\"$attr3\">";
  echo "<input type=\"hidden\" name=\"csrf_token\" value=\"$token\">";
  echo "<table class=\"swal\">";
  echo "<tr><td colspan=\"2\">&nbsp;</td></tr>";
  if ($drop && isset($wifi[$ssid])) {
    echo "<tr><td colspan=\"2\"><center><input type=\"button\" class=\"form\" value=\""._('Forget this network')."\" onclick=\"manage_wifi(encodeURIComponent('$ssid'),2)\"></center></td></tr>";
    echo "<tr><td colspan=\"2\">&nbsp;</td></tr>";
  }
  if ($manual || $safe) {
    echo "<tr><td>"._('Security')."</td><td><select name=\"SECURITY\" onclick=\"showSecurity(this.value)\">";
    echo mk_option($safe, 'OPEN', _('None'));
    echo mk_option($safe, 'WEP', _('WEP (deprecated)'));
    echo mk_option($safe, 'PSK', _('WPA2'));
    echo mk_option($safe, 'PSK SAE', _('WPA2/WPA3'));
    echo mk_option($safe, 'SAE', _('WPA3'));
    echo mk_option($safe, 'IEEE 802.1X', _('WPA2 Enterprise'));
    echo mk_option($safe, 'IEEE 802.1X IEEE 802.1X/SHA-256', _('WPA2/WPA3 Enterprise'));
    echo mk_option($safe, 'IEEE 802.1X/SHA-256', _('WPA3 Enterprise'));
    echo "</select></td></tr>";
  }
  if ($ieee1 || $manual || $safe) echo "<tr id=\"username\" class=\"$hide0\"><td>"._('Username').":</td><td><input type=\"text\" name=\"USERNAME\" class=\"narrow\" autocomplete=\"off\" spellcheck=\"false\" value=\"$user\"></td></tr>";
  if ($attr3 || $manual || $safe) echo "<tr id=\"password\" class=\"$hide1\"><td>"._('Password').":</td><td><input type=\"password\" name=\"PASSWORD\" class=\"narrow\" autocomplete=\"off\" spellcheck=\"false\" value=\"$passwd\"><i id=\"showPass\" class=\"fa fa-eye\" onclick=\"showPassword()\"></i></td></tr>";
  echo "<tr><td colspan=\"2\">&nbsp;</td></tr>";
  echo "<tr><td>"._('IPv4 address assignment').":</td><td><select name=\"DHCP4\" onclick=\"showDHCP(this.value,4)\">";
  echo mk_option($dhcp4, 'yes', _('Automatic'));
  echo mk_option($dhcp4, 'no', _('Static'));
  echo "</select></td></tr>";
  echo "<tr class=\"static4 $hide2\"><td>"._('IPv4 address').":</td><td><input type=\"text\" name=\"IP4\" class=\"narrow\" maxlength=\"15\" autocomplete=\"off\" spellcheck=\"false\" value=\"$ip4\">/<select name=\"MASK4\" class=\"slim\">";
  foreach ($masks as $mask => $prefix) echo mk_option($mask4, $mask, $prefix);
  echo "</select></td></tr>";
  echo "<tr class=\"static4 $hide2\"><td>"._('IPv4 default gateway').":</td><td><input type=\"text\" name=\"GATEWAY4\" class=\"narrow\" maxlength=\"15\" autocomplete=\"off\" spellcheck=\"false\" value=\"$gw4\"></td></tr>";
  echo "<tr class=\"dns4\"><td>"._('IPv4 DNS assignment').":</td><td><select name=\"DNS4\" onclick=\"showDNS(this.value,4)\">";
  echo mk_option($dns4, "no", _("Automatic"));
  echo mk_option($dns4, "yes", _("Static"));
  echo "</select></td></tr>";
  echo "<tr class=\"server4 $hide3\"><td>"._('DNSv4 server').":</td><td><input type=\"text\" name=\"SERVER4\" class=\"narrow\" autocomplete=\"off\" spellcheck=\"false\" value=\"$server4\"></td></tr>";
  echo "<tr><td colspan=\"2\">&nbsp;</td></tr>";
  echo "<tr><td>"._('IPv6 address assignment').":</td><td><select name=\"DHCP6\" onclick=\"showDHCP(this.value,6)\">";
  echo mk_option($dhcp6, '', _('None'));
  echo mk_option($dhcp6, 'yes', _('Automatic'));
  echo mk_option($dhcp6, 'no', _('Static'));
  echo "</select></td></tr>";
  echo "<tr class=\"static6 $hide4\"><td>"._('IPv6 address').":</td><td><input type=\"text\" name=\"IP6\" class=\"narrow\" maxlength=\"39\" autocomplete=\"off\" spellcheck=\"false\" value=\"$ip6\">/<input type=\"number\" min=\"1\" max=\"128\" maxlength=\"3\" name=\"MASK6\" class=\"slim\" value=\"$mask6\"></td></tr>";
  echo "<tr class=\"static6 $hide4\"><td>"._('IPv6 default gateway').":</td><td><input type=\"text\" name=\"GATEWAY6\" class=\"narrow\" maxlength=\"39\" autocomplete=\"off\" spellcheck=\"false\" value=\"$gw6\"></td></tr>";
  echo "<tr class=\"dns6 $hide5\"><td>"._('IPv6 DNS assignment').":</td><td><select name=\"DNS6\" onclick=\"showDNS(this.value,6)\">";
  echo mk_option($dns6, "no", _("Automatic"));
  echo mk_option($dns6, "yes", _("Static"));
  echo "</select></td></tr>";
  echo "<tr class=\"server6 $hide6\"><td>"._('DNSv6 server').":</td><td><input type=\"text\" name=\"SERVER6\" class=\"narrow\" autocomplete=\"off\" spellcheck=\"false\" value=\"$server6\"></td></tr>";
  echo "<tr><td colspan=\"2\">&nbsp;</td></tr>";
  echo "</table>";
  echo "</form>";
  break;
case 'forget':
  $ssid = rawurldecode($_POST['ssid']);
  if ($wifi[$ssid]['GROUP'] == 'active') exec("/etc/rc.d/rc.wireless stop &>/dev/null &");
  unset($wifi[$ssid]);
  saveWifi();
  break;
}
?>
