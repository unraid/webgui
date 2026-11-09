#!/bin/bash
# Verify that manage_boot_params.sh write_config applies the correct
# bootloader-specific default timeout when TIMEOUT is not given (as the
# "Reset to default" action does), and writes an explicit TIMEOUT unchanged.
# GRUB uses seconds; syslinux uses deciseconds. Both defaults are 5 seconds.
#
# Requires bash, GNU sed, GNU awk and GNU coreutils (as on Unraid).
set -eo pipefail

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
script="$script_dir/emhttp/plugins/dynamix/scripts/manage_boot_params.sh"
tmpdir=$(mktemp -d "${TMPDIR:-/tmp}/boot-params-timeout-test.XXXXXX")
trap 'rm -rf -- "$tmpdir"' EXIT

failures=0

write_grub_cfg() {
  cat > "$1" <<'EOF'
set default=0
set timeout=10
menuentry "Unraid OS" {
  linux /bzimage
  initrd /bzroot
}
menuentry "Unraid OS GUI Mode" {
  linux /bzimage
  initrd /bzroot,/bzroot-gui
}
EOF
}

write_syslinux_cfg() {
  cat > "$1" <<'EOF'
default menu.c32
menu title Lime Technology, Inc.
prompt 0
timeout 100
label Unraid OS
  menu default
  kernel /bzimage
  append initrd=/bzroot
label Unraid OS GUI Mode
  kernel /bzimage
  append initrd=/bzroot,/bzroot-gui
label Unraid OS Safe Mode (no plugins, no GUI)
  kernel /bzimage
  append initrd=/bzroot unraidsafemode
EOF
}

# run_write <bootloader> <timeout-or-"unset">
run_write() {
  local bootloader="$1" timeout="$2" case_dir
  case_dir=$(mktemp -d "$tmpdir/case.XXXXXX")
  local env_args=(
    OPERATION=write_config
    BOOTLOADER_TYPE="$bootloader"
    GRUB_CFG="$case_dir/grub.cfg"
    SYSLINUX_CFG="$case_dir/syslinux.cfg"
    BACKUP_DIR="$case_dir/backups"
    GRUB_BACKUP_DIR="$case_dir/grub-backups"
    COMMENTS_FILE="$case_dir/comments.json"
    APPLY_TO_UNRAID_OS=1
    APPLY_TO_GUI_MODE=1
  )
  [[ $timeout != unset ]] && env_args+=(TIMEOUT="$timeout")

  if [[ $bootloader == grub ]]; then
    write_grub_cfg "$case_dir/grub.cfg"
  else
    write_syslinux_cfg "$case_dir/syslinux.cfg"
  fi

  env -i PATH="$PATH" "${env_args[@]}" bash "$script" > "$case_dir/out" 2>&1 || {
    echo "script failed:" >&2
    cat "$case_dir/out" >&2
    return 1
  }

  if [[ $bootloader == grub ]]; then
    tr -d '\r' < "$case_dir/grub.cfg" | grep '^set timeout='
  else
    tr -d '\r' < "$case_dir/syslinux.cfg" | grep '^timeout '
  fi
}

check() {
  local name="$1" bootloader="$2" timeout="$3" expected="$4" actual
  actual=$(run_write "$bootloader" "$timeout") || actual="<error>"
  if [[ $actual == "$expected" ]]; then
    echo "ok   - $name: $actual"
  else
    echo "FAIL - $name: expected '$expected', got '$actual'"
    failures=$((failures + 1))
  fi
}

check "grub default (reset)" grub unset "set timeout=5"
check "grub explicit 10s" grub 10 "set timeout=10"
check "grub explicit 0s" grub 0 "set timeout=0"
check "syslinux default (reset)" syslinux unset "timeout 50"
check "syslinux explicit 10s" syslinux 100 "timeout 100"
check "syslinux explicit 0s" syslinux 0 "timeout 0"

if ((failures > 0)); then
  echo "$failures check(s) failed"
  exit 1
fi
echo "all checks passed"
