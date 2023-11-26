<?PHP
/* Copyright 2005-2023, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * Copyright 2014-2021, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
function ports_only($key) {
  return preg_match('/_(ETH|BR|BOND)[0-9]/',$key);
}
$ports = array_filter($_POST,'ports_only',ARRAY_FILTER_USE_KEY);
$purge = [];
foreach ($ports as $port => $val) {
  $port = explode('_',$port,3)[2];
  if (!in_array($port,$purge)) $purge[] = $port;
}
foreach ($purge as $port) {
  switch (substr($port,0,2)) {
  case 'ET':
    $A1 = str_replace('ETH','BR',$port);
    $A2 = str_replace('ETH','BOND',$port);
    break;
  case 'BR':
    $A1 = str_replace('BR','ETH',$port);
    $A2 = str_replace('BR','BOND',$port);
    break;
  case 'BO':
    $A1 = str_replace('BOND','BR',$port);
    $A2 = str_replace('BOND','ETH',$port);
    break;
  }
  unset($keys["DOCKER_AUTO_$A1"], $keys["DOCKER_AUTO_$A2"]);
  unset($keys["DOCKER_DHCP_$A1"], $keys["DOCKER_DHCP6_$A1"], $keys["DOCKER_DHCP_$A2"], $keys["DOCKER_DHCP6_$A2"]);
  unset($keys["DOCKER_SUBNET_$A1"], $keys["DOCKER_SUBNET6_$A1"], $keys["DOCKER_SUBNET_$A2"], $keys["DOCKER_SUBNET6_$A2"]);
  unset($keys["DOCKER_GATEWAY_$A1"], $keys["DOCKER_GATEWAY6_$A1"], $keys["DOCKER_GATEWAY_$A2"], $keys["DOCKER_GATEWAY6_$A2"]);
  unset($keys["DOCKER_RANGE_$A1"], $keys["DOCKER_RANGE6_$A1"], $keys["DOCKER_RANGE_$A2"], $keys["DOCKER_RANGE6_$A2"]);
}
?>