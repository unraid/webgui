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
fm_debug_nchan_collected="/var/tmp/file.manager.nchan.collected.debug"
ssh_user=root
ssh_host=$1
test_path=/mnt/disk1/fm_test
src_path="$test_path/src"
dst_path="$test_path/dst"
single_filename=$'utf8_файл\nspecial&chars\$file.txt'
job_timeout=${JOB_TIMEOUT:-200}

# deploy this script to and run on remote host
if [[ $ssh_host ]]; then
  scp "$0" "$ssh_user@$ssh_host:/tmp/test-file-manager.sh" || { echo "Error: Failed to copy to remote host" 1>&2; exit 1; }
  ssh -t "$ssh_user@$ssh_host" "bash /tmp/test-file-manager.sh" || { echo "Error: Failed to run test on remote host" 1>&2; exit 1; }
  exit
fi

# functions

on_exit() {
  local rc=$?
  echo -e "\n=== exit ==="
  stop_file_manager
  clean_up_created_files
  exit $rc
}
on_signal() {
  echo -e "\n=== signal ==="
  stop_file_manager
  clean_up_created_files
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
    (( pass++ ))
  else
    echo "[FAIL] $label"
    (( fail++ ))
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
  local collected=$fm_debug_nchan_collected
  printf "" >"$collected"

  # write JSON to job file to trigger file_manager action
  echo "$json" >"$fm_job_json_file"

  # follow nchan output in foreground; break on "done":1 closes stdin -> tail exits via SIGPIPE
  # timeout acts as safety net in case file_manager hangs
  local start=$SECONDS
  timeout "$job_timeout" tail -n +1 -f "$fm_debug_nchan_file" | while IFS= read -r line; do
    printf '%s\n' "$line" >>"$collected"
    echo "  nchan: $line"
    [[ $line == *'"done":1'* ]] && break
  done
  local rc=${PIPESTATUS[0]}

  if [[ $rc -eq 124 ]]; then
    echo "Error: timeout after ${job_timeout}s"
    cancel_action
    return 2
  fi

  # parse collected nchan output for assertions
  # outer JSON: {"status":"{\"action\":N,\"text\":[\"...\"]}", "error":"..."}
  # status is a JSON-encoded string, so needs fromjson
  while IFS= read -r line; do
    [[ $line ]] || continue
    # fail on empty publishes - file_manager should suppress them
    if [[ $line == '[]' || $line == '{}' ]]; then
      echo "  [FAIL] nchan: received empty publish '$line' - should be suppressed"
      fail=$((fail + 1))
      continue
    fi
    local action status_text progress_details err_msg
    action=$(echo "$line" | jq -r '.status | fromjson | .action // empty' 2>/dev/null)
    status_text=$(echo "$line" | jq -r '.status | fromjson | .text[0] // empty' 2>/dev/null)
    progress_details=$(echo "$line" | jq -r '.status | fromjson | .text[1] // empty' 2>/dev/null)
    err_msg=$(echo "$line" | jq -r '.error // empty' 2>/dev/null)
    [[ $action ]] && echo "  nchan action: $action"
    if [[ $action && ! $status_text ]]; then
      echo "  [FAIL] nchan: action=$action but text[0] is missing (mandatory)"
      fail=$((fail + 1))
    fi
    [[ $status_text ]] && echo "  nchan text[0]: $status_text"
    [[ $progress_details ]] && echo "  nchan text[1]: $progress_details"
    [[ $err_msg ]] && echo "  nchan error: $err_msg"
    # TODO: assert against expected patterns
  done <"$collected"

  # rc -1 = FM_EXITCODE_FILE not written for this op type (multi-file extract) => N/A, not failure
  # rc  0 = explicit success
  # rc >0 = explicit failure
  rc=-1
  if [[ -f $fm_exitcode_file ]]; then
    rc=$(cat "$fm_exitcode_file")
  elif [[ -f $fm_exitcode_file.debug ]]; then
    rc=$(cat "$fm_exitcode_file.debug")
  fi
  local stderr
  stderr=$(cat "$fm_error_file" 2>/dev/null)
  [[ ! $stderr ]] && stderr=$(cat "$fm_error_file.debug" 2>/dev/null)
  echo "  exit code: $rc, waited: $((SECONDS - start))s, stderr: $stderr"
  [[ $rc -eq 0 || $rc -eq -1 ]]
}

# test compress action and overwrite behavior
# args: format output_file input_file
test_compress() {
  local format=$1 output_file=$2 input_file=$3
  local rc sz cond
  local archive_name target

  echo -e "\n=== compress $format ==="

  archive_name=$(basename -- "$output_file")
  target=$(dirname -- "$output_file")

  # cleanup any leftovers from previous runs
  if [[ -f "$output_file" ]]; then
    rm -f "$output_file" || { echo "Error: failed to remove $output_file" 1>&2; exit 1; }
    echo "  removed leftover archive $output_file"
  fi

  run_action 16 "$input_file" "$target" 1 "$format" "$archive_name"
  rc=$?
  check "compress $format: action run" $rc

  rc=1 && [[ -f $output_file ]] && rc=0
  check "compress $format: archive creation" $rc

  # verify no .tmp files are left in the destination directory (cleanup logic should remove them)
  rc=0
  for f in "$output_file"*.tmp; do
    [[ -e $f ]] && { rc=1; break; }
  done
  check "compress $format: .tmp cleanup" $rc

  sz=$(stat -c%s "$output_file" 2>/dev/null || echo 0)
  rc=1 && [[ $sz -gt 0 ]] && rc=0
  check "compress $format: archive size (${sz}B)" $rc

  # overwrite sub-test: existing archive must be refused when overwrite=0
  run_action 16 "$input_file" "$target" 0 "$format" "$archive_name"
  cond=1; [[ $(stat -c%s "$output_file" 2>/dev/null || echo 0) -eq $sz ]] && cond=0
  check "compress $format overwrite=0: archive unchanged" $cond
}

# test extract action and overwrite behavior
# args: format archive dest
test_extract() {
  local format=$1 archive=$2 dest=$3
  local rc

  echo -e "\n=== extract $archive ==="

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

  run_action 17 "$archive" "$dest" 0
  rc=$?
  check "$format: action run" $rc

  local extracted_file_count extracted_file
  extracted_file_count=$(find "$dest" -type f -printf '.' | wc -c)
  if [[ $extracted_file_count -eq 1 ]]; then
    extracted_file=$(find "$dest" -type f)
    rc=1 && diff "$src_path/$(basename -- "$extracted_file")" "$extracted_file" && rc=0
  else
    # --no-dereference to avoid following symlinks (source contains broken symlink)
    rc=1 && diff --no-dereference -r "$src_path" "$dest/src" && rc=0
  fi
  check "$format: archive extract diff" $rc

  # overwrite sub-test: reuse already-extracted dest as baseline
  local sentinel_file cond
  sentinel_file=$(find "$dest" -type f -print -quit)
  echo "MODIFIED" >"$sentinel_file"

  run_action 17 "$archive" "$dest" 0
  rc=$?
  check "$format overwrite=0: action run" $rc
  cond=1; [[ $(cat "$sentinel_file") == "MODIFIED" ]] && cond=0
  check "$format overwrite=0: sentinel unchanged" $cond

  run_action 17 "$archive" "$dest" 1
  rc=$?
  check "$format overwrite=1: action run" $rc
  cond=1; [[ $(cat "$sentinel_file") != "MODIFIED" ]] && cond=0
  check "$format overwrite=1: sentinel overwritten" $cond
}



# ===========================
# main
# ===========================
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
echo -e "\n=== Create test files ==="

# create source and destination directories
[[ ! -d "$test_path" ]] && ! mkdir -m 777 "$test_path" && { echo "Error: failed to create $test_path" 1>&2; exit 1; }
[[ ! -d "$src_path" ]] && ! mkdir -m 777 "$src_path" && { echo "Error: failed to create $src_path" 1>&2; exit 1; }
[[ ! -d "$dst_path" ]] && ! mkdir -m 777 "$dst_path" && { echo "Error: failed to create $dst_path" 1>&2; exit 1; }

# random data (compressible only slightly)
[[ ! -f "$src_path/urandom10MB.bin" ]] && dd if=/dev/urandom bs=1M count=10 of="$src_path/urandom10MB.bin"
[[ ! -f "$src_path/urandom100MB.bin" ]] && dd if=/dev/urandom bs=1M count=100 of="$src_path/urandom100MB.bin"
[[ ! -f "$src_path/urandom1000MB.bin" ]] && dd if=/dev/urandom bs=1M count=1000 of="$src_path/urandom1000MB.bin"

# zeros (highly compressible)
[[ ! -f "$src_path/zero10MB.bin" ]] && dd if=/dev/zero  bs=1M count=10 of="$src_path/zero10MB.bin"
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

# tiny text files
[[ ! -f "$src_path/hello.txt" ]] && echo "hello world" >"$src_path/hello.txt"

# create subdirectory with a file
[[ ! -d "$src_path/subdir" ]] && ! mkdir "$src_path/subdir" && { echo "Error: failed to create $src_path/subdir" 1>&2; exit 1; }
[[ ! -f "$src_path/subdir/nested.txt" ]] && echo "nested" >"$src_path/subdir/nested.txt"

# create file with utf-8 chars in name
[[ ! -f "$src_path/utf8_файл.txt" ]] && echo "utf-8 filename" >"$src_path/utf8_файл.txt"

# create file with newline and tabulator in name
[[ ! -f "$src_path/$'newline\ntab\tfile.txt'" ]] && echo "newline in filename" >"$src_path/$'newline\ntab\tfile.txt'"

# create file with shell-related special chars in name
[[ ! -f "$src_path/shell.\${specific}.special&chars\$file|name.txt" ]] && echo "special chars in filename" >"$src_path/shell.\${specific}.special&chars\$file|name.txt"

# create file with utf-8, newline and special chars in name
[[ ! -f "$src_path/$single_filename" ]] && echo "utf-8, newline and special chars in filename" >"$src_path/$single_filename"

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

echo "  created test files in $src_path"

# ===========================
# tests
# ===========================
archive_multi_formats=(tar.bz2 tar.lz4 tar.gz tar.xz tar.zst zip)
archive_multi_formats=(tar.zst zip) # TODO: debug
archive_single_formats=(bz2 gz lz4 xz zst)
archive_single_formats=(zst zip) # TODO: debug
for fmt in "${archive_multi_formats[@]}"; do
  test_compress "$fmt" "$dst_path/archive.$fmt" "$src_path"
done
for fmt in "${archive_single_formats[@]}"; do
  test_compress "$fmt" "$dst_path/$single_filename.$fmt" "$src_path/$single_filename"
done

# extract tests (use archives of compress tests; include overwrite sub-test)
for fmt in "${archive_multi_formats[@]}"; do
  test_extract "$fmt" "$dst_path/archive.$fmt" "$dst_path/extract_$fmt/"
done
for fmt in "${archive_single_formats[@]}"; do
  test_extract "$fmt" "$dst_path/$single_filename.$fmt" "$dst_path/extract_$fmt/"
done

# ===========================
# summary
# ===========================
echo -e "\n=== summary: $pass passed, $fail failed ==="
[[ $fail -eq 0 ]]
