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
// add translations
$_SERVER['REQUEST_URI'] = 'settings';
require_once "$docroot/webGui/include/Translations.php";

if (isset($_POST['listen'])) {
  die(exec("$docroot/webGui/scripts/show_interfaces")?:_('Any'));
}

function port($eth) {
  $sys = "/sys/class/net";
  if (substr($eth,0,4)=='wlan') return $eth;
  $x = preg_replace('/[^0-9]/','',$eth);
  return file_exists("$sys/br{$x}") ? "br${x}" : (file_exists("$sys/bond{$x}") ? "bond{$x}" : "eth{$x}");
}

exec("grep -Po 'nameserver \K\S+' /etc/resolv.conf 2>/dev/null",$ns);
$eth    = $_POST['port'];
$vlan   = $_POST['vlan'];
$port   = port($eth).($vlan ? ".$vlan" : "");
$v6on   = trim(file_get_contents("/proc/sys/net/ipv6/conf/$port/disable_ipv6"))==='0';
$none   = _('None');
$error  = "<span class='red-text'>"._('Missing')."</span>";
$note   = in_array($eth,['eth0','wlan0']) && !$vlan ? $error : $none;
$link   = _(ucfirst(exec("ethtool $eth 2>/dev/null | awk '$1==\"Link\" {print $3;exit}'")) ?: 'Unknown')." ("._(exec("ethtool $eth 2>/dev/null | grep -Pom1 '^\s+Port: \K.*'") ?: ($eth=='wlan0' ? 'wifi' :'not present')).")";
$speed  = _(preg_replace(['/^(\d+)/','/!/'],['$1 ',''],exec("ethtool $eth 2>/dev/null | awk '$1==\"Speed:\" {print $2;exit}'")) ?: 'Unknown');
$ipv4   = array_filter(explode(' ',exec("ip -4 -br addr show $port scope global 2>/dev/null | awk '{\$1=\$2=\"\";print;exit}' | sed -r 's/ metric [0-9]+//g; s/\/[0-9]+//g'")));
$gw4    = exec("ip -4 route show default dev $port 2>/dev/null | awk '{print \$3;exit}'") ?: $note;
$dns4   = array_filter($ns,function($ns){return strpos($ns,':')===false;});
$domain = exec("grep -Pom1 'domain \K.*' /etc/resolv.conf 2>/dev/null") ?: '---';

if ($v6on) {
  $ipv6 = array_filter(explode(' ',exec("ip -6 -br addr show $port scope global -temporary 2>/dev/null | awk '{\$1=\$2=\"\";print;exit}' | sed -r 's/ metric [0-9]+//g; s/\/[0-9]+//g'")));
  $gw6  = exec("ip -6 route show default dev $port 2>/dev/null | awk '{print \$3;exit}'") ?: $note;
  $dns6 = array_filter($ns,function($ns){return strpos($ns,':')!==false;});
}

echo "<table style='text-align:left;font-size:1.2rem'>";
echo "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
echo "<tr><td>"._('Interface link').":</td><td>$link</td></tr>";
echo "<tr><td>"._('Interface speed').":</td><td>$speed</td></tr>";
if ($eth=='wlan0') {
  $ini  = '/boot/config/wireless-networks.cfg';
  $wifi = (array)@parse_ini_file($ini,true);
  $att1 = $att2 = $att3 = '';
  foreach ($wifi as $network => $option) {
    if (isset($option['GROUP']) && $option['GROUP']=='active') {
      $att1 = $network;
      $att2 = $option['ATTR2'];
      $att3 = $option['ATTR3'];
      break;
    }
  }
  if ($att1) echo "<tr><td>"._('Network').":</td><td>$att1</td></tr>";
  if ($att2) echo "<tr><td>"._('Health').":</td><td>$att2</td></tr>";
  if ($att3) echo "<tr><td>"._('Security').":</td><td>$att3</td></tr>";
}
if (count($ipv4)) foreach ($ipv4 as $ip) {
  echo "<tr><td>"._('IPv4 address').":</td><td>$ip</td></tr>";
} else {
  echo "<tr><td>"._('IPv4 address').":</td><td>$note</td></tr>";
}
echo "<tr><td>"._('IPv4 default gateway').":</td><td>$gw4</td></tr>";
if (count($dns4)) foreach ($dns4 as $dns) {
  echo "<tr><td>"._('IPv4 DNS server').":</td><td>$dns</td></tr>";
} else {
  echo "<tr><td>"._('IPv4 DNS server').":</td><td>$error</td></tr>";
}
if ($v6on) {
  if (count($ipv6)) foreach ($ipv6 as $ip) {
    echo "<tr><td>"._('IPv6 address').":</td><td>$ip</td></tr>";
  } else {
    echo "<tr><td>"._('IPv6 address').":</td><td>$note</td></tr>";
  }
  echo "<tr><td>"._('IPv6 default gateway').":</td><td>$gw6</td></tr>";
  if (count($dns6)) foreach ($dns6 as $dns) {
    echo "<tr><td>"._('IPv6 DNS server').":</td><td>$dns</td></tr>";
  } else {
    echo "<tr><td>"._('IPv6 DNS server').":</td><td>$error</td></tr>";
  }
}
echo "<tr><td>"._('Domain name').":</td><td>$domain</td></tr>";
echo "</table>";
?>
