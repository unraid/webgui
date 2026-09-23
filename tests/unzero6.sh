#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Source the config to get unzero6()
# shellcheck source=/dev/null
. "$ROOT_DIR/etc/rc.d/rc.inet1.conf"

pass_count=0
fail_count=0

assert_eq() {
  local input="$1"
  local expected="$2"
  local output

  output="$(unzero6 "$input" || true)"

  if [[ "$output" == "$expected" ]]; then
    printf "PASS: %s -> %s\n" "$input" "$output"
    pass_count=$((pass_count + 1))
  else
    printf "FAIL: %s -> %s (expected %s)\n" "$input" "$output" "$expected"
    fail_count=$((fail_count + 1))
  fi
}

# Basic normalization
assert_eq "2001:0db8:0001:0000:0000:0000:0000:0001" "2001:db8:1:0:0:0:0:1"
assert_eq "fe80:0000:0000:0000:0202:b3ff:fe1e:8329" "fe80:0:0:0:202:b3ff:fe1e:8329"

# Middle :: handling
assert_eq "2001:db8::1" "2001:db8::1"
assert_eq "2001:0db8:0001::0020" "2001:db8:1::20"
assert_eq "2001:db8:1::20" "2001:db8:1::20"

# Trailing :: handling
assert_eq "2001:db8:11:53::" "2001:db8:11:53::"

# Leading :: handling
assert_eq "::1" "::1"
assert_eq "::" "::"

# No compression
assert_eq "2001:db8:1:2:3:4:5:6" "2001:db8:1:2:3:4:5:6"

# Mixed case hex
assert_eq "2001:DB8:0Aa:0:0:0:0:1" "2001:db8:aa:0:0:0:0:1"

printf "\nSummary: %d passed, %d failed\n" "$pass_count" "$fail_count"
if [[ $fail_count -ne 0 ]]; then
  exit 1
fi
