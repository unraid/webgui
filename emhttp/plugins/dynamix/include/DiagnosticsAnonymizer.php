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
        $first = (int)$octets[0];
        $second = (int)$octets[1];
        $is_rfc1918 = $first === 10 || $first === 127 ||
          ($first === 172 && $second >= 16 && $second <= 31) ||
          ($first === 192 && $second === 168);

        if ($is_rfc1918) return $token;
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

  return $result ?? $text;
}
