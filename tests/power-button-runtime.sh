#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "$ROOT_DIR/etc/rc.d/rc.powerbutton"

TMP_ROOT="$(mktemp -d)"
trap 'rm -rf "$TMP_ROOT"' EXIT

assert_same_file() {
  local expected=$1
  local actual=$2
  [[ -f "$actual" ]] || { echo "missing: $actual" >&2; exit 1; }
  cmp -s "$expected" "$actual" || {
    echo "content mismatch: $actual" >&2
    exit 1
  }
}

run_case() {
  local name=$1
  local source_root=$2
  local source_acpi="$source_root/acpi/unraid_power_handler.sh"
  local source_elogind="$source_root/elogind/logind.conf.d/20-unraid-powerbutton.conf"

  export POWERBUTTON_RUNTIME_ROOT="$TMP_ROOT/$name"
  mkdir -p "$(dirname "$source_acpi")" "$(dirname "$source_elogind")"
  printf '%s\n' "$name acpi" > "$source_acpi"
  printf '%s\n' "$name elogind" > "$source_elogind"

  powerbutton_configure_acpid
  powerbutton_configure_elogind

  assert_same_file "$source_acpi" "$POWERBUTTON_RUNTIME_ROOT/etc/acpi/acpi_handler.sh"
  assert_same_file "$source_elogind" "$POWERBUTTON_RUNTIME_ROOT/etc/elogind/logind.conf.d/20-unraid-powerbutton.conf"
  [[ -x "$POWERBUTTON_RUNTIME_ROOT/etc/acpi/acpi_handler.sh" ]] || exit 1
}

run_case full-release "$TMP_ROOT/full-release/usr/local/etc"
run_case preview-plugin "$TMP_ROOT/preview-plugin/etc"

echo "power-button runtime path tests passed"
