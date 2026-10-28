<?php

function read_wireless_state(string $path): array
{
    if (!is_readable($path)) return [];

    $state = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (!is_array($state)) return [];
    if (($state['FORMAT'] ?? '') !== 'base64url-v1') return $state;

    foreach ($state as $key => $value) {
        if ($key === 'FORMAT') continue;
        if (!is_string($value)) return [];

        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding) $value .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode($value, true);
        if ($decoded === false) return [];
        $state[$key] = $decoded;
    }

    unset($state['FORMAT']);
    return $state;
}
