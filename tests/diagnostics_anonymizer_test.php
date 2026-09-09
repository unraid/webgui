#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/emhttp/plugins/dynamix/include/DiagnosticsAnonymizer.php';

function assertSameText(string $expected, string $actual, string $message): void
{
  if ($expected !== $actual) {
    throw new RuntimeException(
      $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
    );
  }
}

assertSameText(
  'network: [IPADDR6:0] => 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:abcd',
  diagnostics_anonymize_ip_text('network: [IPADDR6:0] => 2001:db8:1234:5678:9abc:def0:1234:abcd'),
  'An IPv6 address must not expose more than its first 32 bits and last 16 bits.'
);

assertSameText(
  'server 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:0',
  diagnostics_anonymize_ip_text('server 2001:db8:1234:c::'),
  'A compressed IPv6 address must be anonymized.'
);

assertSameText(
  'uppercase 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:abcd',
  diagnostics_anonymize_ip_text('uppercase 2001:DB8::ABCD'),
  'Uppercase IPv6 addresses must be anonymized.'
);

assertSameText(
  'public 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:0. 198.XXX.XXX.10.',
  diagnostics_anonymize_ip_text('public 2001:db8:1234:c::. 198.51.100.10.'),
  'Sentence punctuation must not prevent IP anonymization.'
);

assertSameText(
  'first addr 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:abcd -> second 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:1234',
  diagnostics_anonymize_ip_text(
    'first addr 2001:db8:1111:2222:3333:4444:5555:abcd -> second 2001:db8:aaaa:bbbb:cccc:dddd:eeee:1234'
  ),
  'Every IPv6 address on a line must be anonymized.'
);

assertSameText(
  'TCP [2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:abcd]:81 route 2001:db8:XXXX:XXXX:XXXX:XXXX:XXXX:abcd/64',
  diagnostics_anonymize_ip_text('TCP [2001:db8:1234:5678:9abc:def0:1234:abcd]:81 route 2001:db8:1234:5678:9abc:def0:1234:abcd/64'),
  'Bracketed IPv6 ports and IPv6 prefixes must remain valid after masking.'
);

assertSameText(
  '198.XXX.XXX.10 00:11:22:33:44:55 14:07:46',
  diagnostics_anonymize_ip_text('198.51.100.10 00:11:22:33:44:55 14:07:46'),
  'IPv4 masking and non-IP values must remain unchanged.'
);

echo "Diagnostics anonymizer tests passed.\n";
