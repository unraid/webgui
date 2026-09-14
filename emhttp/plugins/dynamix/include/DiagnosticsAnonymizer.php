<?php

function diagnostics_anonymize_ip_text($text)
{
  $result = preg_replace_callback(
    '/(?<![0-9A-Fa-f:.])([0-9A-Fa-f:.]+)(?![0-9A-Fa-f:.])/',
    function ($match) {
      $token = $match[1];
      $ip = $token;
      $suffix = '';
      $trailing = '';

      // Do not treat sentence punctuation as part of an address.
      while (!filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '') {
        $last = substr($ip, -1);
        if ($last !== '.' && $last !== ':') break;
        $trailing = $last . $trailing;
        $ip = substr($ip, 0, -1);
      }

      // Support an unbracketed address followed by a port.
      if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        if (
          preg_match('/^(.+):([0-9]{1,5})$/', $ip, $parts) &&
          filter_var($parts[1], FILTER_VALIDATE_IP)
        ) {
          $ip = $parts[1];
          $suffix = ':' . $parts[2];
        }
      }

      if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $octets = explode('.', $ip);
        $is_public = filter_var(
          $ip,
          FILTER_VALIDATE_IP,
          FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($is_public === false) return $token;
        return $octets[0] . '.XXX.XXX.' . $octets[3] . $suffix . $trailing;
      }

      if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return $token;

      $groups = unpack('n8', inet_pton($ip));
      // Keep the first 32 bits and last 16 bits so an ISP /56 prefix is not exposed.
      return sprintf(
        '%x:%x:XXXX:XXXX:XXXX:XXXX:XXXX:%x%s',
        $groups[1],
        $groups[2],
        $groups[8],
        $suffix . $trailing
      );
    },
    $text
  );

  return $result;
}

function diagnostics_anonymize_tailscale_text($text)
{
  return preg_replace(
    '/(?<![A-Za-z0-9.-])(?:[A-Za-z0-9-]+\.)+ts\.net(?![A-Za-z0-9-])/i',
    'removed.ts.net',
    $text
  );
}

function diagnostics_anonymize_storage_text($text, $shareMap, $pools)
{
  if (!$shareMap) return $text;

  $poolPattern = implode('|', array_map(function ($pool) {
    return preg_quote($pool, '/');
  }, $pools));
  $mountPattern = $poolPattern ? "user|user0|$poolPattern" : 'user|user0';

  foreach ($shareMap as $orig => $anon) {
    if ($orig === $anon) continue;
    $origEsc = preg_quote($orig, '/');
    $text = preg_replace_callback(
      "/(\\/mnt\\/(?:$mountPattern)\\/)" . $origEsc . "(\\b)/",
      function ($match) use ($anon) {
        return $match[1] . $anon . $match[2];
      },
      $text
    );
    if ($text === null) return null;

    if ($poolPattern) {
      $text = preg_replace_callback(
        "/(^|[\\s\\/])($poolPattern)\\/" . $origEsc . "(?=\\/|[\\s]|$)/m",
        function ($match) use ($anon) {
          return $match[1] . $match[2] . '/' . $anon;
        },
        $text
      );
      if ($text === null) return null;
    }
  }

  return $text;
}

function diagnostics_anonymize_named_text($text, $nameMap)
{
  foreach ($nameMap as $orig => $anon) {
    if ($orig === '') continue;
    $origEsc = preg_quote($orig, '/');
    $text = preg_replace_callback(
      "/(?<![A-Za-z0-9_-])" . $origEsc . "(?![A-Za-z0-9_-])/",
      function ($match) use ($anon) {
        return $anon;
      },
      $text
    );
    if ($text === null) return null;
  }

  return $text;
}
