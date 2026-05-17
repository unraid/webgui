#!/bin/bash
# shellcheck disable=SC2317
# ##################################################################### #
# Integration test for file_manager
#
# Copyright 2026 Marc Gutt, Gutt.IT
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# Steps:
# - enables file_manager debug mode via /var/tmp/file.manager.debug
# - stops file_manager so the next start picks up the debug trigger
# - writes JSON for each action to /var/tmp/file.manager.active (as Control.php would do it)
# - waits for file_manager worker to process it and reads nchan output from debug file
# - verifies output
# - removes debug trigger and debug files on cleanup
# ##################################################################### #

# settings
fm_job_json_file=/var/tmp/file.manager.active
fm_exitcode_file=/var/tmp/file.manager.exitcode
fm_stdout_file=/var/tmp/file.manager.status
fm_error_file=/var/tmp/file.manager.error
fm_file="/usr/local/emhttp/webGui/nchan/file_manager"
fm_debug_nchan_file="/var/tmp/file.manager.nchan.debug"
ssh_user=root
ssh_host=$1
test_path=/mnt/disk1/fm_test
src_path="$test_path/src"
dst_path="$test_path/dst"
special_chars_name=$'utf8_файл\nspecial&chars\*file\$'
job_timeout=${JOB_TIMEOUT:-200}
script_args=("$@")

# deploy this script to and run on remote host
if [[ $ssh_host && $0 != "/tmp/test-file-manager.sh" ]]; then
  scp "$0" "$ssh_user@$ssh_host:/tmp/test-file-manager.sh" || { echo "Error: Failed to copy to remote host" 1>&2; exit 1; }
  # shellcheck disable=SC2086
  ssh -t "$ssh_user@$ssh_host" "bash /tmp/test-file-manager.sh ${script_args[*]}" || { echo "Error: Failed to run test on remote host" 1>&2; exit 1; }
  exit
fi

# functions

on_exit() {
  local rc=$?
  echo -e "\n=== exit ==="
  stop_file_manager
  #clean_up_created_files
  exit $rc
}
on_signal() {
  echo -e "\n=== signal ==="
  stop_file_manager
  #clean_up_created_files
  exit 1
}
trap on_exit EXIT
trap on_signal INT TERM

# check condition and print pass/fail
pass=0
fail=0
check() {
  local label=$1
  local cond=$2
  if [[ $cond -eq 0 ]]; then
    echo "[PASS] $label"
    pass=$((pass + 1))
  else
    echo "[FAIL] $label (did not happen)"
    fail=$((fail + 1))
  fi
}

remove_job_files() {
  local timestamp
  timestamp=$(date +%Y%m%d%H%M%S)
  for f in "$fm_job_json_file" "$fm_exitcode_file" "$fm_stdout_file" "$fm_error_file"; do
    if [[ -f $f ]]; then
      # output first 10 and last 10 lines of each file for debugging before moving
      echo "  removing $f (first and last 10 lines):"
      # return only unique lines if the file contains less then 20 lines to avoid duplicates in output
      if [[ $(wc -l < "$f") -le 20 ]]; then
        sed 's/^/  /' "$f"
      else
        { head -n 10 "$f"; echo "..."; tail -n 10 "$f"; } | sed 's/^/  /'
      fi
      # backup file with timestamp suffix
      mv -v "$f" "/tmp/$(basename -- "$f")-$timestamp.bak" | sed 's/^/  /'
    fi
  done
}

stop_file_manager() {
  echo "  stop file_manager if running..."
  if pgrep -f "php.*$fm_file" >/dev/null; then
    pkill -f "php.*$fm_file"
    echo "  stopped file_manager"
    sleep 0.1
    if pgrep -f "php.*$fm_file" >/dev/null; then
      echo "Error: failed to stop file_manager!" 1>&2
      exit 1
    fi
    remove_job_files
  else
    echo "  file_manager is not running"
  fi
}

# start file_manager in background
run_file_manager() {
  echo "  ensure file_manager is running..."
  if ! pgrep -f "php.*$fm_file" >/dev/null; then
    echo "  starting file_manager..."
    if [[ ! -f "$fm_file" ]]; then
      echo "Error: file_manager executable not found" 1>&2
      exit 1
    fi
    /usr/bin/php -q "$fm_file" & disown
    sleep 0.1
    if ! pgrep -f "php.*$fm_file" >/dev/null; then
      echo "Error: failed to start file_manager" 1>&2
      exit 1
    fi
  else
    echo "  file_manager is already running"
  fi
}

# remove all files created by file manager actions
clean_up_created_files() {
  echo -e "\n=== Clean up files created through file manager actions ==="
  if [[ -d "$dst_path" ]]; then
    rm -rf "$dst_path" || { echo "Error: failed to clean up $dst_path" 1>&2; exit 1; }
    echo "  cleaned up $dst_path"
  else
    echo "  no files to clean up in $dst_path"
  fi
  # remove debug trigger and all debug files
  rm -f /var/tmp/file.manager.debug /var/tmp/file.manager.*.debug
}

# file manager is considered busy if any of the job files exist (job JSON, exit code, stdout, etc.)
is_action_in_progress() {
  [[ -f $fm_job_json_file || -f $fm_exitcode_file || -f $fm_stdout_file || -f $fm_error_file ]]
}

# cancel currently active job (if any) by writing cancel action JSON (file_manager deletes $fm_job_json_file)
cancel_action() {
  echo '{"action":99}' > "$fm_job_json_file"
  sleep 2
  if is_action_in_progress; then
    echo "Error: could not cancel file_manager (job files still exist)!" 1>&2
    fail=$((fail + 1))
    return 2
  fi
}

# write JSON data to file_manager's action file and wait for completion
# args: action source target overwrite [format archive_name]
# returns 0 on success (exit code 0), 1 on failure or timeout
run_action() {
  local action=$1 source=$2 target=$3 overwrite=$4
  local format=${5:-} archive_name=${6:-}
  local json rc

  # build action JSON
  # title is unused by file_manager (not read in any switch case); format/archive_name are harmless empty strings for action 17
  local title
  case $action in
    16) title="Compress" ;;
    17) title="Extract" ;;
    *) title="" ;;
  esac
  local jq_args=(
    --argjson action "$action"
    --arg title "$title"
    --arg source "$source"
    --arg target "$target"
    --argjson overwrite "$overwrite"
    --arg format "$format"
    --arg archive_name "$archive_name"
  )
  # shellcheck disable=SC2016
  if ! json=$(jq -n "${jq_args[@]}" '{"action":$action,"title":$title,"source":$source,"target":$target,"H":"","sparse":"","overwrite":$overwrite,"zfs":"","format":$format,"archive_name":$archive_name}'); then
    echo "Error: jq failed" 1>&2
    exit 1
  fi
  echo "JSON: $json" | sed 's/^/  /'

  # ensure file_manager is running
  run_file_manager

  # abort if another job is running
  if is_action_in_progress; then
    echo "Error: can not start new action as file_manager is busy (job files still exist)!" 1>&2
    fail=$((fail + 1))
    return 2
  fi

  echo "  run action"

  printf "" >"$fm_debug_nchan_file"

  # write JSON to job file to trigger file_manager action
  echo "$json" >"$fm_job_json_file"

  # follow nchan output in foreground; break on "done":1 or "done":2 closes stdin -> tail exits via SIGPIPE
  # timeout acts as safety net in case file_manager hangs
  local start=$SECONDS
  timeout "$job_timeout" tail -n +1 -f "$fm_debug_nchan_file" | while IFS= read -r line; do
    echo "  nchan: $line"
    [[ $line == *'"done":1'* || $line == *'"done":2'* ]] && break
  done
  local rc=${PIPESTATUS[0]}

  if [[ $rc -eq 124 ]]; then
    echo "Error: timeout after ${job_timeout}s"
    cancel_action
    return 2
  fi

  # rc -1 = FM_EXITCODE_FILE not written for this op type (multi-file extract) => N/A, not failure
  # rc  0 = explicit success
  # rc >0 = explicit failure
  rc=-1
  if [[ -f $fm_exitcode_file ]]; then
    rc=$(cat "$fm_exitcode_file")
  elif [[ -f $fm_exitcode_file.debug ]]; then
    rc=$(cat "$fm_exitcode_file.debug")
  fi
  # treat non-empty stderr as failure
  local stderr
  stderr=$(cat "$fm_error_file" 2>/dev/null)
  [[ ! $stderr ]] && stderr=$(cat "$fm_error_file.debug" 2>/dev/null)
  if [[ $stderr ]]; then
    echo "  stderr: $stderr"
    rc=99
  fi
  # treat any nchan publish with a non-empty .error as failure
  local nchan_err
  nchan_err=$(jq -r 'select((.error // "") != "") | .error' "$fm_debug_nchan_file" | head -1)
  if [[ $nchan_err ]]; then
    echo "  nchan error: $nchan_err"
    rc=99
  fi
  echo "  exit code: $rc, waited: $((SECONDS - start))s"
  [[ $rc -eq 0 || $rc -eq -1 ]]
}

# returns 0 if test section should run: no filter set, or filter contains keyword
should_run() { [[ ${#script_args[@]} -le 1 || "${script_args[*]:1}" == *"$1"* ]]; }

# Generic per-line nchan assertion: empty array publishes must be suppressed by file_manager
# args: line
# returns 1 if line should be skipped (empty publish), 0 if line should be processed
check_nchan_line() {
  local line=$1
  if [[ $line == '[]' || $line == '{}' ]]; then
    echo "  [FAIL] nchan: received empty publish '$line' - should be suppressed"
    fail=$((fail + 1))
    return 1
  fi
  return 0
}

# test compress action and overwrite behavior
# args: format output_file input_file [overwrite_test]
test_compress() {
  local format=$1 output_file=$2 input_file=$3
  local overwrite_test=${4:-}
  local rc sz cond
  local archive_name target

  should_run arc || return 0

  echo -e "\n=== compress $input_file in $output_file ${overwrite_test:+with overwrite test} ==="

  archive_name=$(basename -- "$output_file")
  target=$(dirname -- "$output_file")

  # cleanup any leftovers from previous runs
  if [[ -f "$output_file" ]]; then
    rm -f "$output_file" || { echo "Error: failed to remove $output_file" 1>&2; exit 1; }
    echo "  removed leftover archive $output_file"
  fi

  run_action 16 "$input_file" "$target" 1 "$format" "$archive_name"
  rc=$?
  # check nchan output: no empty publishes, empty text at most once, % non-decreasing, text[0] = "Compressing..."
  local empty_text_count=0 last_pct=-1 text0 text1 err_msg pct text_len speed_unit
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    text1=$(echo "$line" | jq -r '.status.text[1] // empty')
    err_msg=$(echo "$line" | jq -r '.error // empty')
    [[ $err_msg ]] && echo "  nchan error: $err_msg"
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan compress $format: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$(( empty_text_count + 1 ))
      fi
      continue
    fi
    [[ $text0 == "Done" ]] && continue
    if [[ $text1 =~ Completed:\ ([0-9]+)% ]]; then
      pct=${BASH_REMATCH[1]}
      if [[ $pct -lt $last_pct ]]; then
        echo "  [FAIL] nchan compress $format: % decreased (${last_pct}% -> ${pct}%)"
        fail=$((fail + 1))
      fi
      last_pct=$pct
      if [[ $text0 != "Compressing"* ]]; then
        echo "  [FAIL] nchan compress $format: text[0] does not start with 'Compressing': '$text0'"
        fail=$((fail + 1))
      fi
    fi
    if [[ $text1 =~ Speed:\ ([0-9.]+)(B|KB|MB|GB|TB)/s ]]; then
      speed_unit=${BASH_REMATCH[2]}
      if [[ $speed_unit == B || $speed_unit == KB ]]; then
        echo "  [FAIL] nchan compress $format: speed below 1 MB/s: ${BASH_REMATCH[1]}${speed_unit}/s"
        fail=$((fail + 1))
      fi
    fi
  done <"$fm_debug_nchan_file"
  cond=1; [[ $empty_text_count -le 1 ]] && cond=0
  check "nchan compress $format: empty status must appear at most once ($empty_text_count)" $cond
  check "compress $format: action run must succeed" "$rc"

  rc=1 && [[ -f $output_file ]] && rc=0
  check "compress $format: archive creation must succeed" "$rc"

  # verify no .tmp files are left in the destination directory (cleanup logic should remove them)
  rc=0
  for f in "$output_file"*.tmp; do
    [[ -e $f ]] && { rc=1; break; }
  done
  check "compress $format: .tmp cleanup must succeed" "$rc"

  sz=$(stat -c%s "$output_file" 2>/dev/null || echo 0)
  rc=1 && [[ $sz -gt 0 ]] && rc=0
  check "compress $format: archive size (${sz}B) must be greater than 0" "$rc"

  # overwrite sub-test: existing archive must be refused when overwrite=0
  [[ ! $overwrite_test ]] && return 0
  ! run_action 16 "$input_file" "$target" 0 "$format" "$archive_name" && rc=0 || rc=1
  check "compress $format overwrite=0: action run must fail" "$rc"
  rc=1 && [[ $(stat -c%s "$output_file" 2>/dev/null || echo 0) -eq $sz ]] && rc=0
  check "compress $format overwrite=0: archive must be unchanged" "$rc"
}

# test extract action and overwrite behavior
# args: format archive dest [overwrite_test] [source_dir]
test_extract() {
  local format=$1 archive=$2 dest=$3
  local overwrite_test=${4:-}
  local source_dir=${5:-$src_path}

  should_run arc || return 0

  echo -e "\n=== extract $archive in $dest ${overwrite_test:+with overwrite test} ==="

  if [[ ! -f $archive ]]; then
    echo "Error: source archive $archive does not exist!" 1>&2
    return 1
  fi

  # cleanup any leftovers from previous runs
  if [[ -d "$dest" ]]; then
    rm -rf "${dest:?}" || { echo "Error: failed to remove $dest" 1>&2; exit 1; }
    echo "  removed leftover $dest"
  fi
  mkdir "$dest" || { echo "Error: failed to create destination directory $dest" 1>&2; exit 1; }

  run_action 17 "$archive" "$dest" 0; rc=$?

  # check nchan output: no empty publishes, empty text at most once, % non-decreasing, text[0] = "Extracting..."
  local empty_text_count=0 last_pct=-1 text0 text1 err_msg pct text_len speed_unit
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    text1=$(echo "$line" | jq -r '.status.text[1] // empty')
    err_msg=$(echo "$line" | jq -r '.error // empty')
    [[ $err_msg ]] && echo "  nchan error: $err_msg"
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan extract $format: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    [[ $text0 == "Done" ]] && continue
    if [[ $text1 =~ Completed:\ ([0-9]+)% ]]; then
      pct=${BASH_REMATCH[1]}
      if [[ $pct -lt $last_pct ]]; then
        echo "  [FAIL] nchan extract $format: % decreased (${last_pct}% -> ${pct}%)"
        fail=$((fail + 1))
      fi
      last_pct=$pct
      if [[ $text0 != "Extracting"* ]]; then
        echo "  [FAIL] nchan extract $format: text[0] does not start with 'Extracting': '$text0'"
        fail=$((fail + 1))
      fi
    fi
    if [[ $text1 =~ Speed:\ ([0-9.]+)(B|KB|MB|GB|TB)/s ]]; then
      speed_unit=${BASH_REMATCH[2]}
      if [[ $speed_unit == B || $speed_unit == KB ]]; then
        echo "  [FAIL] nchan extract $format: speed below 1 MB/s: ${BASH_REMATCH[1]}${speed_unit}/s"
        fail=$((fail + 1))
      fi
    fi
  done <"$fm_debug_nchan_file"
  check "nchan extract $format: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "extract $format: action run must succeed" "$rc"

  local extracted_file_count extracted_file
  extracted_file_count=$(find "$dest" -type f -printf '.' | wc -c)
  if [[ $extracted_file_count -eq 1 ]]; then
    extracted_file=$(find "$dest" -type f)
    diff <(find_metadata "$source_dir/$(basename -- "$extracted_file")") <(find_metadata "$extracted_file") | head -20 | sed 's/^/  /'
    rc=${PIPESTATUS[0]}
  else
    extracted_subdir=$(find "$dest" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' -quit)
    local meta_fmt='%y %#m %T@ %s %n %P\t%l\n'
    [[ $format == zip ]] && meta_fmt='%y %#m %T@ %s %P\t%l\n'
    diff <(find_metadata "$source_dir" "$meta_fmt") <(find_metadata "$dest/$extracted_subdir" "$meta_fmt") | head -20 | sed 's/^/  /'
    rc=${PIPESTATUS[0]}
  fi
  check "extract $format: metadata must match" "$rc"

  # overwrite sub-test: reuse already-extracted destination as baseline
  [[ ! $overwrite_test ]] && return 0
  local dst_file cond
  dst_file=$(find "$dest" -type f -name "*.txt" -print -quit)
  echo "MODIFIED" >"$dst_file"

  # single file extractions fail with overwrite=0, except of zip
  if [[ $extracted_file_count -eq 1 && $format != "zip" ]]; then
    ! run_action 17 "$archive" "$dest" 0 && rc=0 || rc=1
    check "extract $format overwrite=0: action must fail" "$rc"
  # multi-file extractions succeed with overwrite=0 (existing files are skipped silently)
  else
    run_action 17 "$archive" "$dest" 0 && rc=0 || rc=1
    check "extract $format overwrite=0: action must succeed" "$rc"
  fi

  # existing file must be unchanged in any case
  rc=1 && [[ $(cat "$dst_file") == "MODIFIED" ]] && rc=0
  check "extract $format overwrite=0: file must be unchanged" "$rc"

  run_action 17 "$archive" "$dest" 1; rc=$?
  check "extract $format overwrite=1: action run must succeed" "$rc"
  rc=1 && [[ $(cat "$dst_file") != "MODIFIED" ]] && rc=0
  check "extract $format overwrite=1: file must be overwritten" "$rc"
}

# test create folder action (action 0)
# args: label parent_dir folder_name
test_create_folder() {
  local label=$1 parent=$2 name=$3
  local rc

  should_run mk || return 0

  echo -e "\n=== create folder: $label ==="

  # cleanup any leftovers
  if [[ -d "$parent/$name" ]]; then
    rm -rf "${parent:?}/$name" || { echo "Error: failed to remove $parent/$name" 1>&2; exit 1; }
    echo "  removed leftover $parent/$name"
  fi

  run_action 0 "$parent" "$name" 0; rc=$?

  # check nchan output: no empty publishes, text[0] must be "Creating..." or "Done"
  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan create folder $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    if [[ $text0 != "Creating"* && $text0 != "Done" ]]; then
      echo "  [FAIL] nchan create folder $label: unexpected text[0]: '$text0'"
      fail=$((fail + 1))
    fi
  done <"$fm_debug_nchan_file"
  check "nchan create folder $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "create folder $label: action run must succeed" "$rc"

  rc=1 && [[ -d "$parent/$name" ]] && rc=0
  check "create folder $label: folder must exist" "$rc"
}

# test delete action - internal implementation
# args: label path action (1=delete folder, 6=delete file)
test_delete() {
  local label=$1 path=$2 action=$3
  local rc

  should_run del || return 0

  if [[ $action != 1 && $action != 6 ]]; then
    echo "Error: test_delete: invalid action $action (must be 1 or 6)" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  echo -e "\n=== delete $label (action $action) ==="

  if [[ ! -e "$path" ]]; then
    echo "Error: path to delete does not exist: $path" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  run_action "$action" "$path" "" 0; rc=$?

  # check nchan output: no empty publishes, at most 1 empty text (initial stale-clear)
  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan delete $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
    fi
  done <"$fm_debug_nchan_file"
  check "nchan delete $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "delete $label: action run must succeed" "$rc"

  rc=1 && [[ ! -e "$path" ]] && rc=0
  check "delete $label: path must no longer exist" "$rc"
}

# wrappers with fixed action IDs
test_delete_file()   { test_delete "$1" "$2" 6; }
test_delete_folder() { test_delete "$1" "$2" 1; }

# test rename action - internal implementation
# args: label path new_name action (2=rename folder, 7=rename file)
test_rename() {
  local label=$1 path=$2 new_name=$3 action=$4
  local rc new_path pre

  should_run mv || return 0

  if [[ $action != 2 && $action != 7 ]]; then
    echo "Error: test_rename: invalid action $action (must be 2 or 7)" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  echo -e "\n=== rename $label (action $action) ==="

  if [[ ! -e "$path" ]]; then
    echo "Error: path to rename does not exist: $path" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  new_path=$(dirname -- "$path")/$new_name

  # cleanup any leftover renamed target from previous runs
  if [[ -e "$new_path" ]]; then
    rm -rf "${new_path:?}" || { echo "Error: failed to remove $new_path" 1>&2; exit 1; }
    echo "  removed leftover $new_path"
  fi

  # fingerprint before rename to verify integrity at destination (source won't exist after)
  pre=$(find_metadata "$path")

  run_action "$action" "$path" "$new_name" 0; rc=$?

  # check nchan output: text[0] must be "Renaming..." or "Done"
  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan rename $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    if [[ $text0 != "Renaming"* && $text0 != "Done" ]]; then
      echo "  [FAIL] nchan rename $label: unexpected text[0]: '$text0'"
      fail=$((fail + 1))
    fi
  done <"$fm_debug_nchan_file"
  check "nchan rename $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "rename $label: action run must succeed" "$rc"

  rc=1 && [[ ! -e "$path" ]] && rc=0
  check "rename $label: source path must no longer exist" "$rc"

  rc=1 && [[ -e "$new_path" ]] && rc=0
  check "rename $label: target path must exist" "$rc"

  diff <(echo "$pre") <(find_metadata "$new_path") | head -20 | sed 's/^/  /'
  rc=${PIPESTATUS[0]}
  check "rename $label: fingerprint must match" "$rc"
}

# wrappers with fixed action IDs
test_rename_file()   { test_rename "$1" "$2" "$3" 7; }
test_rename_folder() { test_rename "$1" "$2" "$3" 2; }

# test chmod action (action 12)
# args: label path mode
test_chmod() {
  local label=$1 path=$2 mode=$3
  local rc

  should_run chmod || return 0

  echo -e "\n=== chmod $label ==="

  if [[ ! -e "$path" ]]; then
    echo "Error: path to chmod does not exist: $path" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  run_action 12 "$path" "$mode" 0; rc=$?

  # check nchan output: text[0] must be "Updating..." or "Done"
  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan chmod $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    if [[ $text0 != "Updating"* && $text0 != "Done" ]]; then
      echo "  [FAIL] nchan chmod $label: unexpected text[0]: '$text0'"
      fail=$((fail + 1))
    fi
  done <"$fm_debug_nchan_file"
  check "nchan chmod $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "chmod $label: action run must succeed" "$rc"

  local actual_mode
  actual_mode=$(stat -c '%a' "$path")
  rc=1 && [[ $actual_mode == "${mode#0}" || $actual_mode == "$mode" ]] && rc=0
  check "chmod $label: mode must be $mode (got $actual_mode)" "$rc"
}

# outputs sorted find metadata for a file or directory - used for diff-based integrity checks
# fields: type, perms, mtime, size, [link count,] relative path, symlink target
# pass custom fmt as second arg to override fields (e.g. omit %n when hardlinks are not preserved by zip)
find_metadata() {
  local fmt=${2:-%y %#m %T@ %s %n %P\t%l\n}
  find "$1" -printf "$fmt" | sort
}

# test copy action (3=copy folder, 8=copy file)
# args: label source dest_dir action
test_copy() {
  local label=$1 source=$2 dest_dir=$3 action=$4
  local rc dest_item

  should_run cp || return 0

  echo -e "\n=== copy $label (action $action) ==="

  if [[ ! -e "$source" ]]; then
    echo "Error: source does not exist: $source" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  dest_item="$dest_dir/$(basename -- "$source")"

  # cleanup leftover from previous run
  if [[ -e "$dest_item" ]]; then
    rm -rf "${dest_item:?}" || { echo "Error: failed to remove $dest_item" 1>&2; exit 1; }
    echo "  removed leftover $dest_item"
  fi

  run_action "$action" "$source" "$dest_dir" 0; rc=$?

  # check nchan output: text[0] must be "Copying..." or "Done"
  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan copy $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    if [[ $text0 != "Copying"* && $text0 != "Done" ]]; then
      echo "  [FAIL] nchan copy $label: unexpected text[0]: '$text0'"
      fail=$((fail + 1))
    fi
  done <"$fm_debug_nchan_file"
  check "nchan copy $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "copy $label: action run must succeed" "$rc"

  rc=1 && [[ -e "$source" ]] && rc=0
  check "copy $label: source must still exist" "$rc"

  rc=1 && [[ -e "$dest_item" ]] && rc=0
  check "copy $label: destination must exist" "$rc"

  diff <(find_metadata "$source") <(find_metadata "$dest_item") | head -20 | sed 's/^/  /'
  rc=${PIPESTATUS[0]}
  check "copy $label: fingerprint must match" "$rc"
}

test_copy_file()   { test_copy "$1" "$2" "$3" 8; }
test_copy_folder() { test_copy "$1" "$2" "$3" 3; }

# test move action (4=move folder, 9=move file)
# args: label source dest_dir action [force_copy_delete]
test_move() {
  local label=$1 source=$2 dest_dir=$3 action=$4
  local force_copy_delete=${5:-}
  local rc dest_item pre
  local force_file=/var/tmp/file.manager.force-copy-delete.debug

  should_run mv || return 0

  if [[ $action != 4 && $action != 9 ]]; then
    echo "Error: test_move: invalid action $action (must be 4 or 9)" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  local path_label=$label
  [[ $force_copy_delete ]] && path_label="$label (copy-delete)"

  echo -e "\n=== move $path_label (action $action) ==="

  if [[ ! -e "$source" ]]; then
    echo "Error: source does not exist: $source" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  dest_item="$dest_dir/$(basename -- "$source")"

  # cleanup leftover from previous run
  if [[ -e "$dest_item" ]]; then
    rm -rf "${dest_item:?}" || { echo "Error: failed to remove $dest_item" 1>&2; exit 1; }
    echo "  removed leftover $dest_item"
  fi

  # ensure destination directory exists (rsync-rename requires target to be an existing dir)
  [[ ! -d "$dest_dir" ]] && mkdir -p "$dest_dir"

  # fingerprint source before move to verify integrity at destination
  pre=$(find_metadata "$source")

  # force rsync copy-delete path if requested (requires FM_DEBUG_MODE active)
  [[ $force_copy_delete ]] && touch "$force_file"

  run_action "$action" "$source" "$dest_dir" 0; rc=$?

  [[ $force_copy_delete ]] && rm -f "$force_file"

  # check nchan output: text[0] must be "Moving..." or "Done"
  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan move $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    if [[ $text0 != "Moving"* && $text0 != "Done" ]]; then
      echo "  [FAIL] nchan move $label: unexpected text[0]: '$text0'"
      fail=$((fail + 1))
    fi
  done <"$fm_debug_nchan_file"
  check "nchan move $path_label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "move $path_label: action run must succeed" "$rc"

  rc=1 && [[ ! -e "$source" ]] && rc=0
  [[ $rc -ne 0 ]] && echo "  source still exists: $(ls -b -- "$source" 2>&1)"
  check "move $path_label: source must no longer exist" "$rc"

  rc=1 && [[ -e "$dest_item" ]] && rc=0
  [[ $rc -ne 0 ]] && echo "  dest missing: $dest_item ; dest_dir contents: $(ls -la -- "$dest_dir" 2>&1)"
  check "move $path_label: destination must exist" "$rc"

  diff <(echo "$pre") <(find_metadata "$dest_item") | head -20 | sed 's/^/  /'
  rc=${PIPESTATUS[0]}
  check "move $path_label: fingerprint must match after move" "$rc"

  # clean up debug cmd file if present
  rm -f /var/tmp/fm_debug_cmd.txt
}

test_move_file()   { test_move "$1" "$2" "$3" 9 "${4:-}"; }
test_move_folder() { test_move "$1" "$2" "$3" 4 "${4:-}"; }

echo -e "\n=== Start file manager integration test ==="

# verify file_manager has no active job before starting test
is_action_in_progress && { echo "Error: file_manager is busy with existing job files!" 1>&2; exit 1; }

# enable file_manager debug mode: activates nchan debug logging + renames job files instead of deleting
touch /var/tmp/file.manager.debug
printf "" >"$fm_debug_nchan_file"

# stop running file_manager so the next start picks up the debug trigger
stop_file_manager

# ===========================
# create source files once
# ===========================
echo " create test files..."

# create directories
[[ ! -d "$test_path" ]] && ! mkdir -m 777 "$test_path" && { echo "Error: failed to create $test_path" 1>&2; exit 1; }
[[ ! -d "$src_path" ]] && ! mkdir -m 777 "$src_path" && { echo "Error: failed to create $src_path" 1>&2; exit 1; }
[[ ! -d "$dst_path" ]] && ! mkdir -m 777 "$dst_path" && { echo "Error: failed to create $dst_path" 1>&2; exit 1; }
[[ ! -d "$src_path/small_files" ]] && ! mkdir -m 777 "$src_path/small_files" && { echo "Error: failed to create $src_path/small_files" 1>&2; exit 1; }

# random data (compressible only slightly)
[[ ! -f "$src_path/small_files/urandom10MB.bin" ]] && dd if=/dev/urandom bs=1M count=10 of="$src_path/small_files/urandom10MB.bin"
[[ ! -f "$src_path/urandom100MB.bin" ]] && dd if=/dev/urandom bs=1M count=100 of="$src_path/urandom100MB.bin"
[[ ! -f "$src_path/urandom1000MB.bin" ]] && dd if=/dev/urandom bs=1M count=1000 of="$src_path/urandom1000MB.bin"

# zeros (highly compressible)
[[ ! -f "$src_path/small_files/zero10MB.bin" ]] && dd if=/dev/zero  bs=1M count=10 of="$src_path/small_files/zero10MB.bin"
[[ ! -f "$src_path/zero100MB.bin" ]] && dd if=/dev/zero  bs=1M count=100 of="$src_path/zero100MB.bin"
[[ ! -f "$src_path/zero1000MB.bin" ]] && dd if=/dev/zero  bs=1M count=1000 of="$src_path/zero1000MB.bin"

# create partially compressible file: random data with zero blocks interspersed
if [[ ! -f "$src_path/mix1000MB.bin" ]]; then
  for _ in {1..50}; do
    cat "$src_path/urandom10MB.bin" "$src_path/zero10MB.bin"
  done >"$src_path/mix1000MB.bin"
fi

# create huge amount of empty directories
[[ ! -d "$src_path/empty_dirs" ]] && ! mkdir "$src_path/empty_dirs" && { echo "Error: failed to create $src_path/empty_dirs" 1>&2; exit 1; }
for i in {1..1000}; do
  [[ ! -d "$src_path/empty_dirs/$i" ]] && ! mkdir "$src_path/empty_dirs/$i" && { echo "Error: failed to create $src_path/empty_dirs/$i" 1>&2; exit 1; }
done

# empty text file
[[ ! -f "$src_path/empty.txt" ]] && touch "$src_path/empty.txt"

# tiny text file
[[ ! -f "$src_path/hello.txt" ]] && echo "hello world" >"$src_path/hello.txt"

# tiny text file in subdirectory
[[ ! -f "$src_path/small_files/subfile.txt" ]] && echo "hello subfile" >"$src_path/small_files/subfile.txt"

# create file with utf-8 chars in name
[[ ! -f "$src_path/utf8_файл.txt" ]] && echo "utf-8 filename" >"$src_path/utf8_файл.txt"

# create file with newline and tabulator in name
[[ ! -f "$src_path/$'newline\ntab\tfile.txt'" ]] && echo "newline in filename" >"$src_path/$'newline\ntab\tfile.txt'"

# create file with shell-related special chars in name
[[ ! -f "$src_path/shell.\${specific}.special&chars\$file|name.txt" ]] && echo "special chars in filename" >"$src_path/shell.\${specific}.special&chars\$file|name.txt"

# create file with utf-8, newline and special chars in name
[[ ! -f "$src_path/$special_chars_name.txt" ]] && echo "utf-8, newline and special chars in filename" >"$src_path/$special_chars_name.txt"

# create directory with content for delete test
[[ ! -d "$src_path/delete_dir" ]] && mkdir "$src_path/delete_dir"
[[ ! -d "$src_path/delete_dir/subdir" ]] && mkdir "$src_path/delete_dir/subdir"
[[ ! -f "$src_path/delete_dir/file.txt" ]] && echo "file in dir" >"$src_path/delete_dir/file.txt"
[[ ! -f "$src_path/delete_dir/subdir/file.txt" ]] && echo "file in subdir" >"$src_path/delete_dir/subdir/file.txt"

# create file and directory for rename test (renamed targets are left in place; re-created by guards on next run)
[[ ! -f "$src_path/$special_chars_name-rename.txt" ]] && echo "file for rename test" >"$src_path/$special_chars_name-rename.txt"
[[ ! -d "$src_path/$special_chars_name-rename-dir" ]] && mkdir "$src_path/$special_chars_name-rename-dir"
[[ ! -f "$src_path/$special_chars_name-rename-dir/content.txt" ]] && echo "content in rename dir" >"$src_path/$special_chars_name-rename-dir/content.txt"
# create file for chmod test
[[ ! -f "$src_path/$special_chars_name-chmod.txt" ]] && echo "file for chmod test" >"$src_path/$special_chars_name-chmod.txt"

# create files and directories for copy test
[[ ! -f "$src_path/$special_chars_name-copy.txt" ]] && echo "file for copy test" >"$src_path/$special_chars_name-copy.txt"

# create files for move tests (small dedicated files for file-move tests; folder-move tests use $src_path directly)
[[ ! -f "$src_path/$special_chars_name-move-rr.txt" ]] && echo "file for move test (rsync-rename)" >"$src_path/$special_chars_name-move-rr.txt"
[[ ! -f "$src_path/$special_chars_name-move-cd.txt" ]] && echo "file for move test (copy-delete)" >"$src_path/$special_chars_name-move-cd.txt"

# create hidden files
[[ ! -f "$src_path/.hiddenfile" ]] && echo "hidden file" >"$src_path/.hiddenfile"
[[ ! -d "$src_path/.hiddendir" ]] && mkdir "$src_path/.hiddendir"
[[ ! -f "$src_path/.hiddendir/.hiddenfile" ]] && echo "hidden file in hidden directory" >"$src_path/.hiddendir/.hiddenfile"

# create file with .tmp extension to test that it doesn't interfere with a cleanup logic
[[ ! -f "$src_path/not_fm_related_temp_file.tmp" ]] && echo "this temporary file should not be deleted by file manager" >"$src_path/not_fm_related_temp_file.tmp"

# create hidden file to test that it doesn't interfere with a cleanup logic
[[ ! -f "$src_path/.not_fm_related_hidden_temp_file.tmp" ]] && echo "this hidden file should not be deleted by file manager" >"$src_path/.not_fm_related_hidden_temp_file.tmp"

# create file and hardlink it
[[ ! -f "$src_path/hardlink1.bin" ]] && dd if=/dev/urandom bs=1M count=5 of="$src_path/hardlink1.bin"
[[ ! -f "$src_path/hardlink2.bin" ]] && ln "$src_path/hardlink1.bin" "$src_path/hardlink2.bin"

# create file and symlink targeting it
[[ ! -f "$src_path/symlink1.bin" ]] && dd if=/dev/urandom bs=1M count=5 of="$src_path/symlink1.bin"
[[ ! -L "$src_path/symlink2.bin" ]] && ln -s "symlink1.bin" "$src_path/symlink2.bin"

# create broken symlink
[[ ! -L "$src_path/broken_symlink" ]] && ln -s "nonexistent_target.bin" "$src_path/broken_symlink"

echo "  test files have been created in $src_path"

# ===========================
# compress tests
# ===========================
archive_multi_formats=(tar.bz2 tar.lz4 tar.gz tar.xz tar.zst zip)
archive_single_formats=(bz2 gz lz4 xz zst)

for fmt in "${archive_multi_formats[@]}"; do
  test_compress "$fmt" "$dst_path/archive.$fmt" "$src_path"
done
for fmt in "${archive_multi_formats[@]}"; do
  test_compress "$fmt" "$dst_path/archive.overwrite.$fmt" "$src_path/small_files" 1
done
for fmt in "${archive_single_formats[@]}"; do
  test_compress "$fmt" "$dst_path/$special_chars_name.txt.$fmt" "$src_path/$special_chars_name.txt" 1
done

# ===========================
# extract tests
# ===========================
for fmt in "${archive_multi_formats[@]}"; do
  test_extract "$fmt" "$dst_path/archive.$fmt" "$dst_path/extract_$fmt/"
done
for fmt in "${archive_multi_formats[@]}"; do
  test_extract "$fmt" "$dst_path/archive.overwrite.$fmt" "$dst_path/extract_overwrite_$fmt/" 1 "$src_path/small_files"
done
for fmt in "${archive_single_formats[@]}"; do
  test_extract "$fmt" "$dst_path/$special_chars_name.txt.$fmt" "$dst_path/extract_$fmt/" 1
done

# ===========================
# create folder tests
# ===========================
test_create_folder "special name" "$dst_path" "$special_chars_name-dir"

# ===========================
# delete tests
# ===========================
test_delete_file "special name" "$src_path/$special_chars_name.txt"
test_delete_folder "with content" "$src_path/delete_dir"

# ===========================
# rename tests
# ===========================
test_rename_file "special name" "$src_path/$special_chars_name-rename.txt" "$special_chars_name-renamed.txt"
test_rename_folder "special name" "$src_path/$special_chars_name-rename-dir" "$special_chars_name-renamed-dir"

# ===========================
# chmod tests
# ===========================
test_chmod "special name to 0755" "$src_path/$special_chars_name-chmod.txt" "0755"
test_chmod "special name to 0644" "$src_path/$special_chars_name-chmod.txt" "0644"

# ===========================
# copy tests
# ===========================
test_copy_file   "special name" "$src_path/$special_chars_name-copy.txt" "$dst_path"
test_copy_folder "special name" "$src_path" "$dst_path"

# ===========================
# move tests
# ===========================
test_move_file   "special name rsync-rename" "$src_path/$special_chars_name-move-rr.txt" "$dst_path"
test_move_file   "special name copy-delete"  "$src_path/$special_chars_name-move-cd.txt" "$dst_path" 1
test_move_folder "special name rsync-rename" "$src_path" "$dst_path"
test_move_folder "special name copy-delete"  "$dst_path/src" "$test_path" 1

# ===========================
# summary
# ===========================
echo -e "\n=== summary: $pass passed, $fail failed ==="
[[ $fail -eq 0 ]]
