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
job_max_runtime_seconds=200

# deploy this script to and run on remote host
if [[ $ssh_host ]]; then
  scp "$0" "$ssh_user@$ssh_host:/tmp/test-file-manager.sh" || { echo "Error: Failed to copy to remote host" 1>&2; exit 1; }
  ssh -t "$ssh_user@$ssh_host" "bash /tmp/test-file-manager.sh" || { echo "Error: Failed to run test on remote host" 1>&2; exit 1; }
  exit
fi

# functions

on_trap() {
  stop_file_manager
  clean_up_created_files
  exit 1
}
trap on_trap EXIT INT TERM

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
    sleep 1
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
# returns 0 on success (exit code 0), 1 on failure or timeout
run_action() {
  local json=$1
  local rc

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
  local start=$SECONDS empty_count=0
  timeout "$job_max_runtime_seconds" tail -n +1 -f "$fm_debug_nchan_file" | while IFS= read -r line; do
    printf '%s\n' "$line" >>"$collected"
    echo "  nchan: $line"
    if [[ $line == '[]' || $line == '{}' ]]; then
      (( empty_count++ ))
      [[ $empty_count -ge 100 ]] && { echo "  nchan: 100 consecutive empty lines, stopping"; break; }
    else
      empty_count=0
      [[ $line == *'"done":1'* ]] && break
    fi
  done
  local rc=${PIPESTATUS[0]}

  if [[ $rc -eq 124 ]]; then
    echo "Error: timeout after ${job_max_runtime_seconds}s"
    cancel_action
    return 2
  fi

  # parse collected nchan output for assertions
  # outer JSON: {"status":"{\"action\":N,\"text\":[\"...\"]}", "error":"..."}
  # status is a JSON-encoded string, so needs fromjson
  while IFS= read -r line; do
    [[ $line ]] || continue
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

# run compress action
# args: format archive_name source
run_compress() {
  local format=$1 archive_name=$2 source=$3
  local archive="$dst_path/$archive_name"
  local json rc sz

  echo -e "\n=== compress $format ==="

  # cleanup any leftovers from previous runs
  if [[ -f "$archive" ]]; then
    rm -f "$archive" || { echo "Error: failed to remove $archive" 1>&2; exit 1; }
    echo "  removed leftover archive $archive"
  fi

  # build JSON and run action
  # shellcheck disable=SC2016
  json_template='{
    "action":16,
    "title":"Compress",
    "source": $source,
    "target": $target,
    "H":"",
    "sparse":"",
    "overwrite":1,
    "zfs":"",
    "format": $format,
    "archive_name": $archive_name
  }'
  jq_args=(
    --arg source "$source"
    --arg target "$dst_path"
    --arg format "$format"
    --arg archive_name "$archive_name"
  )
  json=$(jq -n "${jq_args[@]}" "$json_template" 2>&1)
  jq_status=$?
  if [[ "$jq_status" -ne 0 ]]; then
    echo "Error: jq error $json"
    exit 1
  fi
  echo "JSON: $json" | sed 's/^/  /'
  run_action "$json"
  rc=$?
  check "compress $format: action run" $rc

  # verify archive has been created
  rc=1 && [[ -f $archive ]] && rc=0
  check "compress $format: archive diff (multiple files)" $rc

  # verify no .tmp files are left in the destination directory (cleanup logic should remove them)
  rc=0
  for f in "$archive"*.tmp; do
    [[ -e $f ]] && { rc=1; break; }
  done
  check "compress $format: .tmp cleanup" $rc

  # verify archive is non-empty
  sz=$(stat -c%s "$archive")
  rc=1 && [[ $sz -gt 0 ]] && rc=0
  check "compress $format: archive size (${sz}B)" $rc

}

# run extract action
# args: archive_path dest_dir expected_file overwrite
run_extract() {
  local format=$1 archive_path=$2 dst_path=$3 overwrite=${4:-0}
  local json rc

  echo -e "\n=== extract $archive_path ==="

  # verify source archive exists
  if [[ ! -f $archive_path ]]; then
    echo "Error: source archive $archive_path does not exist!" 1>&2
    return 1
  fi

  # cleanup any leftovers from previous runs
  if [[ -d "$dst_path" ]]; then
    rm -rf "${dst_path:?}/"* || { echo "Error: failed to clean up $dst_path" 1>&2; exit 1; }
    echo "  removed leftover files in $dst_path"
  fi

  # create destination directory
  mkdir "$dst_path" || { echo "Error: failed to create destination directory $dst_path" 1>&2; exit 1; }

  # build JSON and run action
  # shellcheck disable=SC2016
  json_template='{
    "action":17,
    "title":"Extract",
    "source": $archive_path,
    "target": $dst_path,
    "H":"",
    "sparse":"",
    "overwrite": $overwrite,
    "zfs":""
  }'
  jq_args=(
    --arg archive_path "$archive_path"
    --arg dst_path "$dst_path"
    --argjson overwrite "$overwrite"
  )
  json=$(jq -n "${jq_args[@]}" "$json_template" 2>&1)
  jq_status=$?
  if [[ "$jq_status" -ne 0 ]]; then
    echo "Error: jq error $json"
    exit 1
  fi
  echo "  JSON: $json"
  run_action "$json"
  rc=$?
  check "$format: action run" $rc

  # verify diff
  rc=1 && diff -r "$src_path/$single_filename" "$dst_path/$single_filename" >/dev/null 2>&1 && rc=0
  check "$format: archive diff ($single_filename)" $rc

}

# test overwrite=0 respects existing files
test_extract_no_overwrite() {
  local label="extract zip no-overwrite"
  local archive="$dst_path/test.zip"
  local dest="$dst_path/extract_no_overwrite"

  echo ""
  echo "--- $label ---"

  local pre=0; [[ -f $archive ]] || pre=1; check "$label: archive existence" $pre
  mkdir -p "$dest"

  # Create sentinel: write known content that should NOT be overwritten
  echo "original" > "$dest/random.bin"
  local orig_size
  orig_size=$(stat -c%s "$dest/random.bin")

  local json
  json=$(printf '{"action":17,"title":"Extract","source":"%s","target":"%s","H":"","sparse":"","overwrite":0,"zfs":""}' \
    "$archive" "$dest")

  run_action "$json"

  # File size should still match sentinel (not overwritten)
  local new_size
  new_size=$(stat -c%s "$dest/random.bin")
  [[ $new_size -eq $orig_size ]]; check "$label: overwrite check" $?
}

# Build a small zip/tar.gz for extract tests using a fresh compress
build_test_archives() {
  echo ""
  echo "=== Building test archives ==="

  for format in zip tar.gz tar.zst; do
    local name="test.${format##*.}"
    # use format as extension for tar variants
    [[ $format == zip ]] && name="test.zip"
    [[ $format == tar.gz ]]  && name="test.tar.gz"
    [[ $format == tar.zst ]] && name="test.tar.zst"

    local archive="$dst_path/$name"
    rm -f "$archive"

    local json
    json=$(printf '{"action":16,"title":"Compress","source":"%s","target":"%s","H":"","sparse":"","overwrite":1,"zfs":"","format":"%s","archive_name":"%s"}' \
      "$src_path" \
      "$dst_path" "$format" "$name")

    run_action "$json" >/dev/null 2>&1
    if [[ -f $archive ]]; then
      echo "  built: $archive ($(stat -c%s "$archive")B)"
    else
      echo "  WARNING: failed to build $archive"
    fi
  done
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
[[ ! -d "$test_path" ]] && ! mkdir "$test_path" && { echo "Error: failed to create $test_path" 1>&2; exit 1; }
[[ ! -d "$src_path" ]] && ! mkdir "$src_path" && { echo "Error: failed to create $src_path" 1>&2; exit 1; }
[[ ! -d "$dst_path" ]] && ! mkdir "$dst_path" && { echo "Error: failed to create $dst_path" 1>&2; exit 1; }

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
for fmt in tar.bz2 tar.gz tar.lz4 tar.xz tar.zst zip; do
  run_compress "$fmt" "archive.$fmt" "$src_path"
done
for fmt in bz2 gz lz4 xz zst; do
  run_compress "$fmt" "archive.$fmt" "$src_path/$single_filename"
done

# extract tests (use archives of compress tests)
for fmt in tar.bz2 tar.gz tar.lz4 tar.xz tar.zst zip; do
  run_extract "$fmt" "$dst_path/archive.$fmt" "$dst_path/extract_$fmt/"
done
for fmt in bz2 gz lz4 xz zst; do
  run_extract "$fmt" "$dst_path/archive.$fmt" "$dst_path/extract_$fmt/"
done

# # overwrite protection
# test_extract_no_overwrite

# ===========================
# summary
# ===========================
echo ""
echo "=== summary: $pass passed, $fail failed ==="
[[ $fail -eq 0 ]]
