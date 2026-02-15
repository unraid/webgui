<?PHP
/* Copyright 2005-2026, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 */
?>
<?
#
# efibootmgr options
# efibootmgr -c   -d /dev/sdx   -p 2   -L "Unraid Internal Boot"   -l '\EFI\BOOT\BOOTX64.EFI'
#
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'output' => 'Method not allowed']);
  exit;
}

$args = $_POST['args'] ?? [];
if (!is_array($args)) $args = [];

$updateBios = false;
foreach ($args as $idx => $arg) {
  if ($arg === 'updatebios') {
    $updateBios = true;
    unset($args[$idx]);
  }
}
$args = array_values($args);

$mkbootpoolArgs = $args;

$devsById = [];
$varroot = '/var/local/emhttp';
$devsIni = $varroot.'/devs.ini';
$devs = @parse_ini_file($devsIni, true) ?: [];
foreach ($devs as $devKey => $dev) {
  if (!is_array($dev)) continue;
  $id = $dev['id'] ?? '';
  if ($id === '' && is_string($devKey)) $id = $devKey;
  $device = $dev['device'] ?? '';
  if ($id !== '' && $device !== '') $devsById[$id] = $device;
}

$docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$script = $docroot.'/plugins/dynamix/scripts/mkbootpool';

if (!is_file($script)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'output' => 'mkbootpool script not found']);
  exit;
}

$cmd = escapeshellcmd($script);
if (!empty($args)) {
  $cmd .= ' '.implode(' ', array_map('escapeshellarg', $args));
}

$output = [];
$rc = 0;
exec($cmd.' 2>&1', $output, $rc);

if ($rc === 0 && $updateBios) {
  $disksIni = $varroot.'/disks.ini';
  $disks = @parse_ini_file($disksIni, true) ?: [];
  $needsFlashEntry = true;
  $bootEntries = [];
  $bootRc = 0;
  exec('efibootmgr 2>&1', $bootEntries, $bootRc);
  if ($bootRc === 0 && !empty($bootEntries)) {
    foreach ($bootEntries as $line) {
      if (stripos($line, 'Unraid Flash') !== false) {
        $needsFlashEntry = false;
        break;
      }
    }
  }
  $bootDevices = [];
  if (count($mkbootpoolArgs) >= 3) {
    $bootDevices = array_slice($mkbootpoolArgs, 2);
    $bootDevices = array_values(array_filter($bootDevices, function($value) {
      return !in_array($value, ['reboot','update','dryrun'], true);
    }));
  }
  foreach ($bootDevices as $bootDevice) {
    $bootId = $bootDevice;
    $device = $bootDevice;
    if ($device === '' || isset($devsById[$device])) {
      if (isset($devsById[$device])) $device = $devsById[$device];
    }
    if ($device === '') continue;
    $devicePath = '/dev/'.$device;
    $label = 'Unraid Internal Boot - '.$bootId;
    $efiPath = '\\EFI\\BOOT\\BOOTX64.EFI';
    $efiCmd = 'efibootmgr -c -d '.escapeshellarg($devicePath).' -p 2 -L '.escapeshellarg($label).' -l '.escapeshellarg($efiPath);
    $output[] = 'Running: '.$efiCmd;
    $efiOut = [];
    $efiRc = 0;
    exec($efiCmd.' 2>&1', $efiOut, $efiRc);
    if (!empty($efiOut)) $output = array_merge($output, $efiOut);
    if ($efiRc !== 0) $output[] = 'efibootmgr failed for '.escapeshellarg($devicePath).' (rc='.$efiRc.')';
  }

  if ($needsFlashEntry) {
    foreach ($disks as $disk) {
      if (($disk['type'] ?? '') !== 'Flash') continue;
      $device = $disk['device'] ?? '';
      if ($device === '') continue;
      $devicePath = '/dev/'.$device;
      $label = 'Unraid Flash';
      $efiCmd = 'efibootmgr -c -d '.escapeshellarg($devicePath).' -p 1 -L '.escapeshellarg($label);
      $output[] = 'Running: '.$efiCmd;
      $efiOut = [];
      $efiRc = 0;
      exec($efiCmd.' 2>&1', $efiOut, $efiRc);
      if (!empty($efiOut)) $output = array_merge($output, $efiOut);
      if ($efiRc !== 0) $output[] = 'efibootmgr failed for flash (rc='.$efiRc.')';
      break;
    }
  }

  $bootEntries = [];
  $bootRc = 0;
  exec('efibootmgr 2>&1', $bootEntries, $bootRc);
  if ($bootRc === 0 && !empty($bootEntries)) {
    $labelMap = [];
    foreach ($bootEntries as $line) {
      if (preg_match('/^Boot([0-9A-Fa-f]{4})\*?\s+(.+)$/', $line, $matches)) {
        $bootNum = strtoupper($matches[1]);
        $labelText = trim($matches[2]);
        $labelMap[$labelText] = $bootNum;
      }
    }

    $desiredOrder = [];
    foreach ($bootDevices as $bootId) {
      $label = 'Unraid Internal Boot - '.$bootId;
      foreach ($labelMap as $labelText => $bootNum) {
        if (stripos($labelText, $label) !== false) {
          $desiredOrder[] = $bootNum;
          break;
        }
      }
    }
    foreach ($labelMap as $labelText => $bootNum) {
      if (stripos($labelText, 'Unraid Flash') !== false) {
        $desiredOrder[] = $bootNum;
        break;
      }
    }

    $desiredOrder = array_values(array_unique(array_filter($desiredOrder)));
    if (!empty($desiredOrder)) {
      $orderList = implode(',', $desiredOrder);
      $orderCmd = 'efibootmgr -o '.$orderList;
      $output[] = 'Running: '.$orderCmd;
      $orderOut = [];
      $orderRc = 0;
      exec($orderCmd.' 2>&1', $orderOut, $orderRc);
      if (!empty($orderOut)) $output = array_merge($output, $orderOut);
      if ($orderRc !== 0) $output[] = 'efibootmgr failed to set boot order (rc='.$orderRc.')';
    }
  }
}



$body = implode("\n", $output);
if ($body === '') $body = 'No output';

$outputDir = '/boot/config/internal_boot';
@mkdir($outputDir, 0777, true);
@file_put_contents($outputDir.'/output.log', $body."\n");

echo json_encode([
  'ok' => ($rc === 0),
  'code' => $rc,
  'output' => $body
]);
?>
