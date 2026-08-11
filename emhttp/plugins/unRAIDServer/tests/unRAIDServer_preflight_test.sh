#!/bin/bash

set -u

TEST_DIR=$(cd "$(dirname "$0")" && pwd)
PLUGIN_FILE="$TEST_DIR/../unRAIDServer.plg"
TEMP_ROOT=$(mktemp -d "${TMPDIR:-/tmp}/unraid-server-preflight.XXXXXX") || exit 1
trap 'rm -rf "$TEMP_ROOT"' EXIT HUP INT TERM

PREFLIGHT_TEMPLATE="$TEMP_ROOT/preflight.template"
awk '
  /^version="&version;"$/ { capture = 1 }
  capture && /^<!\[CDATA\[$/ { next }
  capture { print }
  capture && /^# OS-618 compatibility preflight end$/ { exit }
' "$PLUGIN_FILE" > "$PREFLIGHT_TEMPLATE"

if ! grep -q '^# OS-618 compatibility preflight start$' "$PREFLIGHT_TEMPLATE" ||
   ! grep -q '^# OS-618 compatibility preflight end$' "$PREFLIGHT_TEMPLATE"; then
  echo "FAIL: The embedded compatibility preflight was not extracted."
  exit 1
fi

FAKE_BLKID="$TEMP_ROOT/blkid"
# Shell variables in these strings belong to the generated fake command.
# shellcheck disable=SC2016
printf '%s\n' \
  '#!/bin/bash' \
  'printf "%s\n" "$*" >> "$BLKID_LOG"' \
  'if [[ -n "${BLKID_RESULT:-}" ]]; then' \
  '  printf "%s\n" "$BLKID_RESULT"' \
  'fi' > "$FAKE_BLKID"
chmod +x "$FAKE_BLKID"

TOTAL_CASES=0
FAILED_CASES=0
CASE_FAILED=0
CASE_NAME=""
LAST_STATUS=0
LAST_OUTPUT=""
LAST_BLKID_CALLS=0
LAST_BLKID_ARGS=""

begin_case() {
  CASE_NAME="$1"
  CASE_FAILED=0
}

fail_case() {
  CASE_FAILED=1
  echo "  $1"
}

assert_status() {
  if [[ "$LAST_STATUS" -ne "$1" ]]; then
    fail_case "Expected status $1, got $LAST_STATUS."
  fi
}

assert_output() {
  if [[ "$LAST_OUTPUT" != "$1" ]]; then
    fail_case "Output did not match."
    printf '    expected: %q\n' "$1"
    printf '    actual:   %q\n' "$LAST_OUTPUT"
  fi
}

assert_output_contains() {
  case "$LAST_OUTPUT" in
    *"$1"*) ;;
    *) fail_case "Output did not contain: $1" ;;
  esac
}

assert_output_excludes() {
  case "$LAST_OUTPUT" in
    *"$1"*) fail_case "Output contained unexpected text: $1" ;;
    *) ;;
  esac
}

assert_blkid_calls() {
  if [[ "$LAST_BLKID_CALLS" -ne "$1" ]]; then
    fail_case "Expected $1 blkid call(s), got $LAST_BLKID_CALLS."
  fi
}

finish_case() {
  TOTAL_CASES=$((TOTAL_CASES + 1))
  if [[ $CASE_FAILED -eq 0 ]]; then
    echo "PASS: $CASE_NAME"
  else
    echo "FAIL: $CASE_NAME"
    FAILED_CASES=$((FAILED_CASES + 1))
  fi
}

# Arguments: version, var.ini mode, var.ini content, mount mode, block state, blkid result.
run_preflight() {
  local target_version="$1"
  local var_mode="$2"
  local var_content="$3"
  local mount_mode="$4"
  local block_state="$5"
  local blkid_result="$6"
  local case_dir
  local run_script
  local var_ini
  local mounts_file
  local sys_block_root
  local blkid_log
  local block_name=""

  case_dir=$(mktemp -d "$TEMP_ROOT/case.XXXXXX") || exit 1
  run_script="$case_dir/preflight.sh"
  var_ini="$case_dir/var.ini"
  mounts_file="$case_dir/mounts"
  sys_block_root="$case_dir/sys-class-block"
  blkid_log="$case_dir/blkid.log"
  mkdir -p "$sys_block_root"
  : > "$mounts_file"
  : > "$blkid_log"

  sed "s/^version=\"&version;\"$/version=\"$target_version\"/" \
    "$PREFLIGHT_TEMPLATE" > "$run_script"

  if [[ "$var_mode" == "present" ]]; then
    printf '%b' "$var_content" > "$var_ini"
  fi

  case "$mount_mode" in
    none)
      ;;
    multiple)
      printf '%s\n' \
        '/dev/sda /boot vfat rw 0 0' \
        '/dev/sdb /boot vfat rw 0 0' > "$mounts_file"
      mkdir -p "$sys_block_root/sda" "$sys_block_root/sdb"
      ;;
    *)
      printf '%s /boot vfat rw 0 0\n' "$mount_mode" > "$mounts_file"
      case "$mount_mode" in
        /dev/*) block_name=${mount_mode#/dev/} ;;
      esac
      case "$block_state" in
        whole)
          mkdir -p "$sys_block_root/$block_name"
          ;;
        partition)
          mkdir -p "$sys_block_root/$block_name"
          : > "$sys_block_root/$block_name/partition"
          ;;
        missing)
          ;;
      esac
      ;;
  esac

  if LAST_OUTPUT=$(UNRAID_PREFLIGHT_VAR_INI="$var_ini" \
    UNRAID_PREFLIGHT_MOUNTS_FILE="$mounts_file" \
    UNRAID_PREFLIGHT_SYS_BLOCK_ROOT="$sys_block_root" \
    UNRAID_PREFLIGHT_BLKID_CMD="$FAKE_BLKID" \
    BLKID_LOG="$blkid_log" \
    BLKID_RESULT="$blkid_result" \
    bash "$run_script" 2>&1); then
    LAST_STATUS=0
  else
    LAST_STATUS=$?
  fi

  LAST_BLKID_CALLS=$(wc -l < "$blkid_log" | tr -d ' ')
  LAST_BLKID_ARGS=$(head -n 1 "$blkid_log")
}

TPM_BLOCK='*** Installing an Unraid version below 7.3.0-beta.0.1 is blocked for TPM-based license keys.'
BOOT_WARNING='*** Warning: The boot device is not eligible for features introduced in Unraid 7.3. The installation will continue.'
PARTITION_BLOCK='*** Installing Unraid 7.3.0-beta.0.1 or later is blocked because the boot device has no partition table.'
REISER_OUTPUT=$'***\n*** ReiserFS filesystem(s) detected:\n/dev/md1\n*** Upgrading to this Unraid version is blocked while ReiserFS is in use.\n*** Please migrate all ReiserFS disks to a supported filesystem and retry.\n***'

begin_case "a target immediately before the boundary uses the older-version checks"
run_preflight '7.3.0-beta.0' present 'bootEligible="yes"\n' /dev/sda1 partition ''
assert_status 0
assert_output ''
assert_blkid_calls 0
finish_case

begin_case "the exact boundary uses the newer-version checks"
run_preflight '7.3.0-beta.0.1' present 'tpmGUID="01-test"\nbootEligible="no"\n' /dev/sda1 partition ''
assert_status 0
assert_output ''
assert_blkid_calls 1
if [[ "$LAST_BLKID_ARGS" != '-c /dev/null -t TYPE=reiserfs -o device' ]]; then
  fail_case "The blkid arguments changed: $LAST_BLKID_ARGS"
fi
finish_case

begin_case "a target after the boundary uses the newer-version checks"
run_preflight '7.3.0' present 'tpmGUID="01-test"\nbootEligible="no"\n' /dev/sda1 partition ''
assert_status 0
assert_output ''
assert_blkid_calls 1
finish_case

begin_case "a TPM license blocks an older target"
run_preflight '7.2.9' present 'tpmGUID="01-test-guid"\nbootEligible="yes"\n' /dev/sda1 partition ''
assert_status 1
assert_output "$TPM_BLOCK"
assert_blkid_calls 0
finish_case

begin_case "the TPM prefix must start at the first character"
run_preflight '7.2.9' present 'tpmGUID="x01-test-guid"\nbootEligible="yes"\n' /dev/sda1 partition ''
assert_status 0
assert_output ''
assert_blkid_calls 0
finish_case

for eligibility in no YES; do
  begin_case "bootEligible=$eligibility warns and continues"
  run_preflight '7.2.9' present "bootEligible=\"$eligibility\"\n" /dev/sda1 partition ''
  assert_status 0
  assert_output "$BOOT_WARNING"
  assert_blkid_calls 0
  finish_case
done

for eligibility in empty yes missing_key missing_file; do
  begin_case "bootEligible $eligibility is silent"
  case "$eligibility" in
    empty) run_preflight '7.2.9' present 'bootEligible=""\n' /dev/sda1 partition '' ;;
    yes) run_preflight '7.2.9' present 'bootEligible="yes"\n' /dev/sda1 partition '' ;;
    missing_key) run_preflight '7.2.9' present 'tpmGUID=""\n' /dev/sda1 partition '' ;;
    missing_file) run_preflight '7.2.9' missing '' /dev/sda1 partition '' ;;
  esac
  assert_status 0
  assert_output ''
  assert_blkid_calls 0
  finish_case
done

for fixture in \
  '/dev/sda1 partition' \
  '/dev/nvme0n1p1 partition' \
  '/dev/mmcblk0p1 partition'; do
  fixture_device=${fixture% *}
  fixture_state=${fixture#* }
  begin_case "$fixture_device is identified as a partition through sysfs"
  run_preflight '7.3.0-beta.0.1' missing '' "$fixture_device" "$fixture_state" ''
  assert_status 0
  assert_output ''
  assert_blkid_calls 1
  finish_case
done

for fixture in \
  '/dev/sda whole' \
  '/dev/nvme0n1 whole' \
  '/dev/mmcblk0 whole'; do
  fixture_device=${fixture% *}
  fixture_state=${fixture#* }
  begin_case "$fixture_device is identified as a whole device through sysfs"
  run_preflight '7.3.0-beta.0.1' missing '' "$fixture_device" "$fixture_state" ''
  assert_status 1
  assert_output_contains "$PARTITION_BLOCK"
  assert_output_contains '*** Recreate the Unraid boot device with a partition table and retry.'
  assert_blkid_calls 1
  finish_case
done

begin_case "a missing sysfs entry does not cause a false block"
run_preflight '7.3.0-beta.0.1' missing '' /dev/sda missing ''
assert_status 0
assert_output ''
finish_case

begin_case "multiple boot mount records do not cause a false block"
run_preflight '7.3.0-beta.0.1' missing '' multiple missing ''
assert_status 0
assert_output ''
finish_case

begin_case "a non-device boot source does not cause a false block"
run_preflight '7.3.0-beta.0.1' missing '' 'UUID=test-guid' missing ''
assert_status 0
assert_output ''
finish_case

begin_case "ReiserFS preserves its message and blocks before the partitionless check"
run_preflight '7.3.0-beta.0.1' missing '' /dev/sda whole /dev/md1
assert_status 1
assert_output "$REISER_OUTPUT"
assert_output_excludes "$PARTITION_BLOCK"
assert_blkid_calls 1
finish_case

begin_case "a TPM block takes priority over a boot eligibility warning"
run_preflight '7.2.9' present 'tpmGUID="01-test-guid"\nbootEligible="no"\n' /dev/sda1 partition /dev/md1
assert_status 1
assert_output "$TPM_BLOCK"
assert_output_excludes "$BOOT_WARNING"
assert_blkid_calls 0
finish_case

if [[ $FAILED_CASES -ne 0 ]]; then
  echo "$FAILED_CASES of $TOTAL_CASES preflight cases failed."
  exit 1
fi

echo "All $TOTAL_CASES preflight cases passed."
