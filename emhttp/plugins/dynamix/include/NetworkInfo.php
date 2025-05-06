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

// Helper function to normalize bitrate values
function normalizeBitrate($rate) {
  $parts = explode(' ', $rate);
  return intval($parts[0] ?? 0).' '.($parts[1] ?? 'Bit/s');
}

function isPort($eth) {
  $sys = "/sys/class/net";
  if (substr($eth,0,4) == 'wlan') return $eth;
  $x = preg_replace('/[^0-9]/', '', $eth) ?: '0';
  return file_exists("$sys/br{$x}") ? "br{$x}" : (file_exists("$sys/bond{$x}") ? "bond{$x}" : "eth{$x}");
}

exec("grep -Po 'nameserver \K\S+' /etc/resolv.conf 2>/dev/null",$ns);
$eth    = $_POST['port'] ?? '';
$vlan   = $_POST['vlan'] ?? '';
$wlan0  = $eth == 'wlan0';
$port   = isPort($eth).($vlan ? ".$vlan" : "");
$v6on   = trim(file_get_contents("/proc/sys/net/ipv6/conf/$port/disable_ipv6"))==='0';
$none   = _('None');
$error  = "<span class='red-text'>"._('Missing')."</span>";
$note   = in_array($eth,['eth0','wlan0']) && !$vlan ? $error : $none;
$ipv4   = array_filter(explode(' ',exec("ip -4 -br addr show ".escapeshellarg($port)." scope global 2>/dev/null | awk '{\$1=\$2=\"\";print;exit}' | sed -r 's/ metric [0-9]+//g; s/\/[0-9]+//g'")));
$gw4    = exec("ip -4 route show default dev ".escapeshellarg($port)." 2>/dev/null | awk '{print \$3;exit}'") ?: $note;
$dns4   = array_filter($ns,function($ns){return strpos($ns,':') === false;});

if ($v6on) {
  $ipv6 = array_filter(explode(' ',exec("ip -6 -br addr show ".escapeshellarg($port)." scope global -temporary 2>/dev/null | awk '{\$1=\$2=\"\";print;exit}' | sed -r 's/ metric [0-9]+//g; s/\/[0-9]+//g'")));
  $gw6  = exec("ip -6 route show default dev ".escapeshellarg($port)." 2>/dev/null | awk '{print \$3;exit}'") ?: $note;
  $dns6 = array_filter($ns,function($ns){return strpos($ns,':') !== false;});
}

echo "<table style='text-align:left;font-size:1.2rem'>";
echo "<tr><td>&nbsp;</td><td>&nbsp;</td></tr>";
if ($wlan0) {
  exec("iw wlan0 link | awk '/^\s+(SSID|freq|signal|[rt]x bitrate): /{print \$1,\$2,\$3,\$4}'", $speed);
  if (count($speed) == 5) {
    $network = explode(': ', $speed[0])[1];
    $freq    = explode(': ', $speed[1])[1];
    $signal  = explode(': ', $speed[2])[1];
    $rxrate  = explode(': ', $speed[3])[1];
    $txrate  = explode(': ', $speed[4])[1];
    $rxrate  = normalizeBitrate($rxrate);
    $txrate  = normalizeBitrate($txrate);
    $tmp     = '/var/tmp/attr';
    $band    = [];
    $attr    = is_readable($tmp) ? (array)parse_ini_file($tmp,true) : [];
    $freq    = explode(' ', $attr[$network]['ATTR4'] ?: $freq);
    foreach ($freq as $number) {
      $number = intval($number);
      switch (true) {
        case ($number >= 2400 && $number < 2500): $id = '2.4G'; break;
        case ($number >= 5000 && $number < 6000): $id = '5G'; break;
        case ($number >= 6000 && $number < 7000): $id = '6G'; break;
      }
      if (!in_array($id, $band)) $band[] = $id;
    }
    sort($band);
    $band = '('.implode(', ', $band).')';
  } else {
    $network = $signal = $rxrate = $txrate = _('Unknown');
    $band = '';
  }
  echo "<tr><td>"._('Network name').":</td><td>$network $band</td></tr>";
  echo "<tr><td>"._('Signal level').":</td><td>$signal</td></tr>";
  echo "<tr><td>"._('Receive bitrate').":</td><td>$rxrate</td></tr>";
  echo "<tr><td>"._('Transmit bitrate').":</td><td>$txrate</td></tr>";
} else {
  $link  = _(ucfirst(exec("ethtool ".escapeshellarg($eth)." 2>/dev/null | awk '$1==\"Link\" {print $3;exit}'")) ?: 'Unknown')." ("._(exec("ethtool ".escapeshellarg($eth)." 2>/dev/null | grep -Pom1 '^\s+Port: \K.*'") ?: 'not present').")";
  $speed = _(preg_replace(['/^(\d+)/','/!/'],['$1 ',''],exec("ethtool ".escapeshellarg($eth)." 2>/dev/null | awk '$1==\"Speed:\" {print $2;exit}'")) ?: 'Unknown');
  echo "<tr><td>"._('Interface link').":</td><td>$link</td></tr>";
  echo "<tr><td>"._('Interface speed').":</td><td>$speed</td></tr>";
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
echo "</table>";
?>
