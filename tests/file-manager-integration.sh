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
# - throttles the reads of every job (see "I/O throttling" below) so each job runs long enough to show progress
# - verifies output and the progress which is shown to the user (check_progress)
# - removes debug trigger and debug files on cleanup
# ##################################################################### #

# settings
fm_job_json_file=/var/tmp/file.manager.active
fm_stdout_file=/var/tmp/file.manager.status
fm_error_file=/var/tmp/file.manager.error
fm_file="/usr/local/emhttp/webGui/nchan/file_manager"
fm_debug_nchan_file="/var/tmp/file.manager.nchan.debug"
ssh_user=root
ssh_host=$1
test_path=/mnt/disk1/fm_test
src_path="$test_path/src"
arc_path="$src_path/archives"
files_path="$src_path/files"
dst_path="$test_path/dst"
special_chars_name=$'utf8_файл\nspecial&chars\*file\$'
job_timeout=${TIMEOUT:-200}
fm_nchan_ts_file="/var/tmp/file.manager.nchan.ts.debug"
# Every job reads at most this many bytes per second (20 MiB/s), which is below the speed of any array disk (HDD: 50-250 MB/s,
# NVMe: GB/s). So the duration of a job depends on its size and not on the disks of the person who runs the test.
fm_read_limit=20971520
# jobs which have to read at least 5 seconds worth of data must show progress
progress_min_bytes=$((fm_read_limit * 5))
fm_cgroup=/sys/fs/cgroup/file-manager-test
# settings of the File Manager (section [filemanager]), the speed limit test sets bwlimit and restores the file from the backup
fm_cfg=/boot/config/plugins/dynamix/dynamix.cfg
fm_cfg_backup=/var/tmp/dynamix.cfg.file-manager-test
bwlimit_mb=5
IFS=' ' read -ra archive_multi_formats <<< "${MULTI:-tar.bz2 tar.lz4 tar.gz tar.xz tar.zst zip}"
IFS=' ' read -ra archive_single_formats <<< "${SINGLE:-bz2 gz lz4 xz zst}"
script_args=("$@")

if [[ ! $ssh_host || $ssh_host == "-h" || $ssh_host == "--help" ]]; then
  echo "Usage: $(basename -- "$0") host [filter...]"
  echo ""
  echo "Filters (any combination, space-separated):"
  echo "  compress, cmp, 16     compress tests (action 16)"
  echo "  extract, 17           extract tests (action 17)"
  echo "  invalid               compress tests with invalid or failing sources (action 16)"
  echo "  cancel, 99            cancel tests (cancel of compress and extract)"
  echo "  arc                   compress and extract tests (actions 16, 17)"
  echo "  rename, rn, 2, 7       rename tests (actions 2, 7)"
  echo "  move, mv, 4, 9         move tests (actions 4, 9)"
  echo "  copy, cp, 3, 8        copy tests (actions 3, 8)"
  echo "  chmod, 12             chmod tests (action 12)"
  echo "  chown, 11             chown tests (action 11)"
  echo "  search, find, 15      search tests (action 15)"
  echo "  create, mk, 0         create folder tests (action 0)"
  echo "  delete, del, 1, 6     delete tests (actions 1, 6)"
  echo ""
  echo "ENV overrides:"
  echo "  TIMEOUT=N        seconds to wait for a job to complete (default: 200)"
  echo "  MULTI='...'      space-separated multi-file archive formats"
  echo "                   default: tar.bz2 tar.lz4 tar.gz tar.xz tar.zst zip"
  echo "  SINGLE='...'     space-separated single-file archive formats"
  echo "                   default: bz2 gz lz4 xz zst"
  echo ""
  echo "Every output line starts with the wall clock time and the seconds since the start."
  echo "Every job is throttled to 20 MiB/s (cgroup v2 io.max) after the page cache was dropped, so"
  echo "the progress which is shown to the user can be compared with the real values."
  echo "Requires root and a cgroup v2 with the io controller (Unraid 7). The test files must not be on zfs,"
  echo "because the reads from zfs can not be throttled with a cgroup."
  echo ""
  echo "Examples:"
  echo "  $(basename -- "$0") Tower              # run all tests"
  echo "  $(basename -- "$0") myserver arc mv    # compress/extract and rename/move"
  echo "  $(basename -- "$0") myserver 16        # compress and extract by action ID"
  echo "  MULTI='zip tar.gz' $(basename -- "$0") myserver arc"
  exit 0
fi

# deploy this script to and run on remote host
if [[ $ssh_host && $0 != "/tmp/file-manager-integration.sh" ]]; then
  scp "$0" "$ssh_user@$ssh_host:/tmp/file-manager-integration.sh" || { echo "Error: Failed to copy to remote host" 1>&2; exit 1; }
  # prepend ENV overrides so they are available on the remote host
  local_env=""
  [[ ${TIMEOUT:-} ]] && local_env+="TIMEOUT=$(printf '%q' "$TIMEOUT") "
  [[ ${MULTI:-}   ]] && local_env+="MULTI=$(printf '%q' "$MULTI") "
  [[ ${SINGLE:-}  ]] && local_env+="SINGLE=$(printf '%q' "$SINGLE") "
  # shellcheck disable=SC2086
  ssh -t "$ssh_user@$ssh_host" "${local_env}bash /tmp/file-manager-integration.sh ${script_args[*]}" || { echo "Error: Failed to run test on remote host" 1>&2; exit 1; }
  exit
fi

# prefix every output line with the wall clock time and the seconds since start, e.g.:
# 10:04:12.832 +809.463s [PASS] compress zip: archive creation must succeed
ts_start=${EPOCHREALTIME//[.,]/}
exec > >(while IFS= read -r ts_line; do
  ts_now=${EPOCHREALTIME//[.,]/}
  ts_elapsed=$(( (ts_now - ts_start) / 1000 ))
  ts_micro=${EPOCHREALTIME#*[.,]}
  printf '%(%H:%M:%S)T.%s +%d.%03ds %s\n' "${EPOCHREALTIME%%[.,]*}" "${ts_micro:0:3}" "$((ts_elapsed / 1000))" "$((ts_elapsed % 1000))" "$ts_line"
done) 2>&1

# functions

on_exit() {
  local rc=$?
  echo -e "\n=== exit ==="
  stop_file_manager
  # leave the cgroup and remove it (the cgroup2-unraid daemon may have done this already) and the io controller (only if this test enabled it)
  if [[ -d $fm_cgroup ]]; then
    echo $$ > /sys/fs/cgroup/cgroup.procs
    [[ -d $fm_cgroup ]] && rmdir "$fm_cgroup"
  fi
  [[ $io_controller_enabled ]] && echo "-io" > /sys/fs/cgroup/cgroup.subtree_control
  # the settings which the speed limit test changed (the backup only exists while the test runs)
  [[ -f $fm_cfg_backup ]] && mv -f "$fm_cfg_backup" "$fm_cfg"
  # the debug trigger must never stay on the server, otherwise the next file_manager (e.g. started by the WebGUI) logs and keeps job
  # files. The debug output of failed tests stays for inspection (clean_up_created_files removes it after successful tests).
  rm -f /var/tmp/file.manager.debug /var/tmp/file.manager.force-copy-delete.debug
  if [[ $rc -eq 0 ]]; then
    clean_up_created_files
  else
    echo "  skipping cleanup (tests failed - files left for inspection in $dst_path)"
  fi
  exit $rc
}
on_signal() {
  echo -e "\n=== signal ==="
  stop_file_manager
  echo "  skipping cleanup (interrupted - files left for inspection in $dst_path)"
  exit 1
}
trap on_exit EXIT
trap on_signal INT TERM

io_controller_enabled=
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
  for f in "$fm_job_json_file" "$fm_stdout_file" "$fm_error_file"; do
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

# file manager is considered busy if any of the job files exist (job JSON, stdout, stderr)
is_action_in_progress() {
  [[ -f $fm_job_json_file || -f $fm_stdout_file || -f $fm_error_file ]]
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

# check what the user sees of the last run_action: the progress which file_manager publishes through nchan
# args: label expected_bytes [expected_total]
#   expected_bytes: bytes the job has to read (size of the source for compress, copy and move, size of the archive for extract)
#   expected_total: the "Total" which is shown (default: expected_bytes; zip extract shows the uncompressed size)
#
# All jobs are throttled to $fm_read_limit, so the real values are known: speed <= ~$fm_read_limit and duration >= expected_bytes / $fm_read_limit.
# Jobs which read at least $progress_min_bytes (= 5 seconds worth of data) must show progress: a first progress line (the speed needs
# a few seconds to be calculated, so it shows "ETA: N/A") and at least two more lines with speed and ETA.
check_progress() {
  local label=$1 expected_bytes=$2 expected_total=${3:-$2}
  local count problems="" cond=0
  # the update interval of file_manager is one second: more than 2.5 seconds without a message looks like a hang
  local max_silence_ms=2500
  # a job must be finished 2 seconds after it showed 100%
  local max_after_100_ms=2000
  local min_lines=3 min_eta_lines=2
  # tolerance of the shown total: +-10%
  local total_tolerance=10

  # 1. one row per nchan message, e.g. for these messages
  #      {"status":{"action":16,"text":[]}}
  #      {"status":{"action":16,"text":["Compressing... files/a.bin","Completed: 8%,  Total: 285MB,  ETA: N/A"]}}
  #      {"status":{"action":16,"text":["Compressing... files/a.bin","Completed: 24%,  Total: 285MB,  Speed: 25MB/s,  ETA: 0:00:08"]}}
  #      {"status":{"action":16,"text":["Done"]},"done":1,"error":""}
  #    and their arrival times (in $fm_nchan_ts_file) the rows are (the first column is the time in milliseconds):
  #      1761646800210  -   -         -  -          0     <- empty status at the start of the job
  #      1761646801260  8   -         -  298844160  0     <- first progress line, no speed yet
  #      1761646803310  24  26214400  8  298844160  0     <- percent, speed (bytes/s), ETA (seconds), total (bytes), done
  #      1761646806400  -   -         -  -          1     <- Done
  #    "-" = not shown. The order of the values in the text differs between rsync and the other actions, the filter does not care.
  local r_ms=() r_pct=() r_speed=() r_eta=() r_total=() r_done=()
  local ts pct speed eta total done_flag
  count=$(wc -l <"$fm_nchan_ts_file")
  while IFS=$'\t' read -r ts pct speed eta total done_flag; do
    r_ms+=("$(( ${ts//[.,]/} / 1000 ))")
    r_pct+=("$pct")
    r_speed+=("$speed")
    r_eta+=("$eta")
    r_total+=("$total")
    r_done+=("$done_flag")
  done < <(paste "$fm_nchan_ts_file" <(head -n "$count" "$fm_debug_nchan_file" | jq -r '
    # "1.52GB" -> bytes (binary units; rsync prints "kB")
    def to_bytes:
      capture("^(?<number>[0-9.]+)(?<unit>[a-zA-Z]*)$")
      | (.unit | ascii_upcase) as $unit
      | (.number | tonumber) * ({"": 1, "B": 1, "KB": 1024, "MB": 1048576, "GB": 1073741824, "TB": 1099511627776}[$unit] // 1)
      | floor;
    (.status.text[1] // "") as $text
    | [
        (($text | capture("Completed: (?<v>[0-9]+)%") | .v) // "-"),
        (($text | capture("Speed: (?<v>[0-9.]+[a-zA-Z]+)/s") | .v | to_bytes) // "-"),
        (($text | capture("ETA: (?<h>[0-9]+):(?<m>[0-9]+):(?<s>[0-9]+)") | (.h | tonumber) * 3600 + (.m | tonumber) * 60 + (.s | tonumber)) // "-"),
        (($text | capture("Total: (?<v>[0-9.]+[a-zA-Z]+)") | .v | to_bytes) // "-"),
        (.done // 0)
      ]
    | @tsv'))

  # 2. the job is finished when the done message arrived
  local i first_ms=${r_ms[0]} done_ms=0
  for i in "${!r_ms[@]}"; do
    [[ ${r_done[i]} == 1 ]] && done_ms=${r_ms[i]}
  done
  if [[ $done_ms -eq 0 ]]; then
    problems+="no done message"$'\n'
    done_ms=${r_ms[-1]}
  fi
  local duration_ms=$(( done_ms - first_ms ))
  # speeds above this are not possible, because the reads are throttled. Zip extract shows the uncompressed bytes,
  # which are more than the bytes which are read from the archive (expected_total / expected_bytes)
  local max_speed=$(( fm_read_limit * 2 * expected_total / (expected_bytes > 0 ? expected_bytes : 1) ))
  # average speed of the whole job, shown speeds below a third of it are no real speeds
  local avg_speed=0
  (( duration_ms > 0 )) && avg_speed=$(( expected_total * 1000 / duration_ms ))

  # 3. check every message
  local progress_lines=0 eta_lines=0 last_pct=0 first_progress_ms=0 ms_100=0 prev_ms=$first_ms
  local when remaining_ms diff_ms
  for i in "${!r_ms[@]}"; do
    printf -v when '+%d.%01ds' $(( (r_ms[i] - first_ms) / 1000 )) $(( (r_ms[i] - first_ms) % 1000 / 100 ))

    # silence: time since the previous message (the last message is the done message)
    if (( r_ms[i] - prev_ms > max_silence_ms )); then
      problems+="no message for $(( (r_ms[i] - prev_ms) / 1000 )).$(( (r_ms[i] - prev_ms) % 1000 / 100 ))s before $when"$'\n'
    fi
    prev_ms=${r_ms[i]}

    # messages without percent are the empty status at the start and the done message
    [[ ${r_pct[i]} == - ]] && continue
    progress_lines=$((progress_lines + 1))
    [[ ${r_eta[i]} != - ]] && eta_lines=$((eta_lines + 1))

    # percent: never above 100, never decreasing
    (( r_pct[i] > 100 )) && problems+="percent above 100 (${r_pct[i]}%) at $when"$'\n'
    (( r_pct[i] < last_pct )) && problems+="percent decreased from $last_pct% to ${r_pct[i]}% at $when"$'\n'
    last_pct=${r_pct[i]}
    (( r_pct[i] >= 100 && ms_100 == 0 )) && ms_100=${r_ms[i]}

    # total: must be the size which was given to the job
    if [[ ${r_total[i]} != - && $expected_total -gt 0 ]]; then
      diff_ms=$(( r_total[i] > expected_total ? r_total[i] - expected_total : expected_total - r_total[i] ))
      (( diff_ms * 100 > expected_total * total_tolerance )) && problems+="total ${r_total[i]} bytes at $when, but the job has $expected_total bytes"$'\n'
    fi

    # the first progress line is the start of the job and not checked for speed and ETA
    if (( first_progress_ms == 0 )); then
      first_progress_ms=${r_ms[i]}
      continue
    fi

    # speed: not faster than the throttle allows and not near zero
    if [[ ${r_speed[i]} != - ]]; then
      (( r_speed[i] > max_speed )) && problems+="speed ${r_speed[i]} bytes/s at $when is above the limit of $max_speed bytes/s"$'\n'
      (( r_speed[i] * 3 < avg_speed )) && problems+="speed ${r_speed[i]} bytes/s at $when is far below the average of $avg_speed bytes/s"$'\n'
    fi

    # ETA: must be about the real remaining time. The speed of a compressor depends on the data (random data: bzip2 11 MB/s, zeros:
    # limited by the read limit), so the ETA is never exact. An ETA which is too long is harmless: the compressible rest of a zip
    # extract (text, zeros) is done within a second, while the ETA was based on the speed of the random data. An ETA which is too
    # short is the one which annoys the user ("ETA 0:00:05" for a job which has 90 seconds left). Tolerance: at most twice as
    # long (plus 5 seconds) and at least half as long (minus 3 seconds).
    if [[ ${r_eta[i]} != - ]]; then
      remaining_ms=$(( done_ms - r_ms[i] ))
      if (( r_eta[i] * 1000 > remaining_ms * 2 + 5000 || r_eta[i] * 1000 * 2 + 3000 < remaining_ms )); then
        problems+="ETA ${r_eta[i]}s at $when, but $(( remaining_ms / 1000 ))s remained"$'\n'
      fi
    fi
  done

  # 4. the job must be finished soon after it showed 100%
  if (( ms_100 > 0 && done_ms - ms_100 > max_after_100_ms )); then
    problems+="job was still running $(( (done_ms - ms_100) / 1000 )).$(( (done_ms - ms_100) % 1000 / 100 ))s after it showed 100%"$'\n'
  fi

  # 5. long jobs must show enough progress
  if (( expected_bytes >= progress_min_bytes )); then
    (( progress_lines < min_lines )) && problems+="only $progress_lines progress lines (at least $min_lines expected)"$'\n'
    (( eta_lines < min_eta_lines )) && problems+="only $eta_lines progress lines with an ETA (at least $min_eta_lines expected)"$'\n'
  fi

  echo "  progress: $progress_lines lines ($eta_lines with ETA), job took $(( duration_ms / 1000 )).$(( duration_ms % 1000 / 100 ))s for $expected_bytes bytes (limit $fm_read_limit bytes/s)"
  if [[ $problems ]]; then
    echo "  progress problems:"$'\n'"${problems%$'\n'}" | sed 's/^/  /'
    cond=1
  fi
  check "$label: progress shown to the user must be plausible" $cond
}

# print the JSON of a job like Control.php writes it to the action file
# args: action source target overwrite [format archive_name]
job_json() {
  local action=$1 source=$2 target=$3 overwrite=$4
  local format=${5:-} archive_name=${6:-}
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
  if ! jq -n "${jq_args[@]}" '{"action":$action,"title":$title,"source":$source,"target":$target,"sparse":"","overwrite":$overwrite,"zfs":"","format":$format,"archive_name":$archive_name}'; then
    echo "Error: jq failed" 1>&2
    exit 1
  fi
}

# write JSON data to file_manager's action file and wait for completion
# args: action source target overwrite [format archive_name]
# returns 0 on success (exit code 0), 1 on failure or timeout
run_action() {
  local action=$1 source=$2 target=$3 overwrite=$4
  local format=${5:-} archive_name=${6:-}
  local json rc

  json=$(job_json "$action" "$source" "$target" "$overwrite" "$format" "$archive_name")
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
  printf "" >"$fm_nchan_ts_file"
  # in debug mode file_manager keeps the stderr of a job as .debug file. Jobs which write none (create folder, ...)
  # would read the one of an earlier job, e.g. the error of a compress job which failed on purpose
  rm -f "$fm_error_file.debug"

  # read from the disk (not from the page cache, like a user who compresses files which were not accessed recently) with the read limit
  sync
  echo 3 > /proc/sys/vm/drop_caches
  echo "$test_device rbps=$fm_read_limit" > "$fm_cgroup/io.max"

  # write JSON to job file to trigger file_manager action
  echo "$json" >"$fm_job_json_file"

  # follow nchan output in foreground; break on "done":1 or "done":2 closes stdin -> tail exits via SIGPIPE
  # timeout acts as safety net in case file_manager hangs
  local start=$SECONDS
  timeout "$job_timeout" tail -n +1 -f "$fm_debug_nchan_file" | while IFS= read -r line; do
    echo "$EPOCHREALTIME" >>"$fm_nchan_ts_file"
    echo "  nchan: $line"
    [[ $line == *'"done":1'* || $line == *'"done":2'* ]] && break
  done
  local rc=${PIPESTATUS[0]}
  echo "$test_device rbps=max" > "$fm_cgroup/io.max"

  if [[ $rc -eq 124 ]]; then
    echo "Error: timeout after ${job_timeout}s"
    cancel_action
    return 2
  fi

  # treat non-empty stderr as failure
  rc=0
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
  echo "  result: $rc, waited: $((SECONDS - start))s"
  [[ $rc -eq 0 ]]
}

# returns 0 if test section should run: no filter set, or any keyword matches a filter arg
# numeric keywords use word-boundary matching to avoid partial matches (e.g. 1 != 16)
should_run() {
  [[ ${#script_args[@]} -le 1 ]] && return 0
  # normalize filter: replace any non-keyword chars (not alphanumeric, dot, dash) with spaces
  # allows both space- and comma-separated filters e.g. "arc,search,mv" or "arc search mv"
  local filter
  filter=$(printf '%s ' "${script_args[@]:1}" | tr -cs '[:alnum:].-' ' ')
  local keyword
  for keyword in "$@"; do
    [[ $filter =~ (^|[[:space:]])$keyword([[:space:]]|$) ]] && return 0
  done
  return 1
}

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

  should_run compress arc cmp 16 || return 0

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
  [[ $rc -eq 0 ]] && check_progress "compress $format" "$(du -sb -- "$input_file" | cut -f1)"
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
  local source_dir=${5:-$files_path}

  should_run extract arc 17 || return 0

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
  # the percentage is based on the bytes which are read from the archive. Only zip shows the uncompressed size as total.
  if [[ $rc -eq 0 ]]; then
    if [[ $format == zip ]]; then
      check_progress "extract $format" "$(stat -c%s -- "$archive")" "$(du -sb -- "$source_dir" | cut -f1)"
    else
      check_progress "extract $format" "$(stat -c%s -- "$archive")"
    fi
  fi

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

  local extracted_file_count extracted_file extracted_subdir
  extracted_file_count=$(find "$dest" -type f -printf '.' | wc -c)
  if [[ $extracted_file_count -eq 1 ]]; then
    extracted_file=$(find "$dest" -type f)
    # single-file formats: file_manager does not restore mtime - compare type and size only
    local single_fmt='%P %y %s\n'
    diff <(find_metadata "$source_dir/$(basename -- "$extracted_file")" "$single_fmt") <(find_metadata "$extracted_file" "$single_fmt") | sed 's/^/  /'
    rc=${PIPESTATUS[0]}
  else
    extracted_subdir=$(find "$dest" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' -quit)
    if [[ $format == zip ]]; then
      # zip: DOS time (2s resolution), no hardlinks
      # %P first so sort is by filename - timestamps differ before sed zeroing, sort must be stable
      # zero last 2 digits of epoch before decimal (100s bucket >> 2s DOS error)
      local zip_fmt='%P %y %#m %T@ %s %l\n'
      local zip_sed='s/[0-9][0-9]\.[0-9]+/00/'
      diff <(find_metadata "$source_dir" "$zip_fmt" | sed -E "$zip_sed") <(find_metadata "$dest/$extracted_subdir" "$zip_fmt" | sed -E "$zip_sed") | sed 's/^/  /'
      rc=${PIPESTATUS[0]}
    else
      diff <(find_metadata "$source_dir") <(find_metadata "$dest/$extracted_subdir") | sed 's/^/  /'
      rc=${PIPESTATUS[0]}
    fi
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

  should_run create mk 0 || return 0

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

  should_run delete del 1 6 || return 0

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

  should_run rename rn 2 7 || return 0

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

  diff <(echo "$pre") <(find_metadata "$new_path") | sed 's/^/  /'
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

  should_run chmod 12 || return 0

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

# build an archive from source; writes to .tmp first, then renames atomically
# args: format source dest_dir archive_name
build_archive() {
  local fmt=$1 source=$2 dest_dir=$3 archive_name=$4
  local output="$dest_dir/$archive_name"
  local tmp="$output.tmp"
  local src_dir src_base rc
  src_dir=$(dirname -- "$source")
  src_base=$(basename -- "$source")
  echo "  building $output ..."
  local tar_opts=(--sparse --xattrs "--xattrs-include=*.*" --acls)
  case $fmt in
    tar.gz)  tar -czvf "$tmp" "${tar_opts[@]}" -C "$src_dir" "$src_base" ;;
    tar.bz2) tar -cjvf "$tmp" "${tar_opts[@]}" -C "$src_dir" "$src_base" ;;
    tar.xz)  tar -cJvf "$tmp" "${tar_opts[@]}" -C "$src_dir" "$src_base" ;;
    tar.zst) tar -cvf - "${tar_opts[@]}" -C "$src_dir" "$src_base" | zstd > "$tmp" ;;
    tar.lz4) tar -cvf - "${tar_opts[@]}" -C "$src_dir" "$src_base" | lz4  > "$tmp" ;;
    zip)     (cd "$src_dir" && LANG=en_US.UTF-8 zip -ry "$tmp" "$src_base") ;;
    gz)      gzip  -vc "$source" > "$tmp" ;;
    bz2)     bzip2 -vc "$source" > "$tmp" ;;
    xz)      xz    -vc "$source" > "$tmp" ;;
    zst)     zstd  -f  "$source" -o "$tmp" ;;
    lz4)     lz4   -f  "$source" "$tmp" ;;
    *)       echo "Error: build_archive: unknown format: $fmt" 1>&2; exit 1 ;;
  esac
  rc=$?
  [[ $rc -ne 0 ]] && { echo "Error: failed to create archive $output" 1>&2; exit 1; }
  mv "$tmp" "$output" || { echo "Error: failed to move $tmp to $output" 1>&2; exit 1; }
}

# outputs sorted find metadata for a file or directory - used for diff-based integrity checks
# fields: relative path, type, perms, mtime, size, link count, symlink target
# %P is first so sort is stable by filename regardless of timestamp differences
# pass custom fmt as second arg to override fields (e.g. omit %n when hardlinks are not preserved by zip)
# additional args from $3 onward are passed to find as extra predicates (e.g. -not "(" -type d -empty ")")
find_metadata() {
  local fmt=${2:-'%P %y %#m %T@ %s %n %l\n'}
  find "$1" "${@:3}" -printf "$fmt" | sort
}

# test chown action (action 11)
# args: label path owner
test_chown() {
  local label=$1 path=$2 owner=$3
  local rc

  should_run chown 11 || return 0

  echo -e "\n=== chown $label ==="

  if [[ ! -e "$path" ]]; then
    echo "Error: path to chown does not exist: $path" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  run_action 11 "$path" "$owner" 0; rc=$?

  local empty_text_count=0 text0 text_len
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ ! $text0 ]]; then
      text_len=$(echo "$line" | jq -r '.status.text | length')
      if [[ $text_len -gt 0 ]]; then
        echo "  [FAIL] nchan chown $label: non-empty text array but text[0] missing"
        fail=$((fail + 1))
      else
        empty_text_count=$((empty_text_count + 1))
      fi
      continue
    fi
    if [[ $text0 != "Updating"* && $text0 != "Done" ]]; then
      echo "  [FAIL] nchan chown $label: unexpected text[0]: '$text0'"
      fail=$((fail + 1))
    fi
  done <"$fm_debug_nchan_file"
  check "nchan chown $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "chown $label: action run must succeed" "$rc"

  local actual_owner
  actual_owner=$(stat -c '%U:%G' "$path")
  rc=1 && [[ $actual_owner == "$owner" ]] && rc=0
  check "chown $label: owner must be $owner (got $actual_owner)" "$rc"
}

# test search action (action 15)
# args: label search_dir pattern
test_search() {
  local label=$1 search_dir=$2 pattern=$3
  local rc

  should_run search find 15 || return 0

  echo -e "\n=== search $label ==="

  if [[ ! -d "$search_dir" ]]; then
    echo "Error: search dir does not exist: $search_dir" 1>&2
    fail=$((fail + 1))
    return 1
  fi

  # count expected results using -print0 so filenames with newlines are counted correctly
  local expected_count
  expected_count=$(find "$search_dir" -iname "$pattern" -print0 | tr -cd '\0' | wc -c)

  run_action 15 "$search_dir" "$pattern" 0; rc=$?

  local empty_text_count=0 text0 done_line=""
  while IFS= read -r line; do
    [[ $line ]] || continue
    check_nchan_line "$line" || continue
    # during search: text[0] = "Searching... N"
    text0=$(echo "$line" | jq -r '.status.text[0] // empty')
    if [[ $text0 ]]; then
      if [[ $text0 != "Searching"* ]]; then
        echo "  [FAIL] nchan search $label: unexpected text[0]: '$text0'"
        fail=$((fail + 1))
      fi
      continue
    fi
    # on done: status has results field (array of {location, path} objects)
    if echo "$line" | jq -e '.status.results != null' >/dev/null 2>&1; then
      done_line=$line
      continue
    fi
    empty_text_count=$((empty_text_count + 1))
  done <"$fm_debug_nchan_file"

  check "nchan search $label: empty status must appear at most once ($empty_text_count)" $(( empty_text_count > 1 ? 1 : 0 ))
  check "search $label: action run must succeed" "$rc"
  rc=1 && [[ $done_line ]] && rc=0
  check "search $label: results must be present in nchan output" "$rc"

  if [[ $done_line ]]; then
    # check result count against local find (detects split filenames caused by newlines in names)
    local actual_count
    actual_count=$(echo "$done_line" | jq '.status.results | length')
    rc=1 && [[ $actual_count -eq $expected_count ]] && rc=0
    check "search $label: result count must be $expected_count (got $actual_count)" "$rc"

    # check all paths are under search_dir (split filenames appear without directory prefix)
    # use jq for the check to avoid false failures on paths that legitimately contain newlines
    local all_valid
    all_valid=$(echo "$done_line" | jq --arg dir "$search_dir/" '[.status.results[].path | startswith($dir)] | all')
    rc=1 && [[ $all_valid == "true" ]] && rc=0
    check "search $label: all result paths must be under $search_dir" "$rc"
    if [[ $rc -ne 0 ]]; then
      echo "$done_line" | jq -r --arg dir "$search_dir/" '.status.results[] | select(.path | startswith($dir) | not) | .path | @json'
    fi
  fi
}

# test copy action (3=copy folder, 8=copy file)
# args: label source dest_dir action
test_copy() {
  local label=$1 source=$2 dest_dir=$3 action=$4
  local rc dest_item

  should_run copy cp 3 8 || return 0

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
  [[ $rc -eq 0 ]] && check_progress "copy $label" "$(du -sb -- "$source" | cut -f1)"

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

  diff <(find_metadata "$source") <(find_metadata "$dest_item") | sed 's/^/  /'
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
  local rc dest_item pre source_bytes
  local force_file=/var/tmp/file.manager.force-copy-delete.debug

  should_run move mv 4 9 || return 0

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
  source_bytes=$(du -sb -- "$source" | cut -f1)

  # force rsync copy-delete path if requested (requires FM_DEBUG_MODE active)
  # FM_DEBUG_FORCE_COPY_DELETE is a PHP define() evaluated at process start, so file_manager
  # must be restarted after creating the force file to pick up the new value
  if [[ $force_copy_delete ]]; then
    touch "$force_file"
    stop_file_manager
  fi

  run_action "$action" "$source" "$dest_dir" 0; rc=$?
  # rsync-rename does not read any data (it renames), so the read limit does not apply and there is no progress to check
  [[ $rc -eq 0 && $force_copy_delete ]] && check_progress "move $path_label" "$source_bytes"

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

  diff <(echo "$pre") <(find_metadata "$dest_item") | sed 's/^/  /'
  rc=${PIPESTATUS[0]}
  check "move $path_label: fingerprint must match after move" "$rc"

  # clean up debug cmd file if present
  rm -f /var/tmp/fm_debug_cmd.txt
}

test_move_file()   { test_move "$1" "$2" "$3" 9 "${4:-}"; }
test_move_folder() { test_move "$1" "$2" "$3" 4 "${4:-}"; }

# print the processes of the running job: everything in the cgroup of this test which is named like a tool of a compress/extract job
# (the shell of the test and the worker, which is php, are not listed)
job_processes() {
  local pids
  pids=$(tr '\n' ',' <"$fm_cgroup/cgroup.procs")
  ps -o pid=,comm= -p "${pids%,}" | grep -E ' (tar|pv|gzip|bzip2|xz|zstd|lz4|zip|unzip|pvzip)$'
}

# test that a compress job fails and publishes no archive if the source is invalid: a source which does not exist, or a folder or
# several files as source of a single-file format (.gz, ...)
# args: label format source archive_name expected_error
test_compress_invalid() {
  local label=$1 format=$2 source=$3 archive_name=$4 expected_error=$5
  local rc error_text

  should_run compress invalid arc cmp 16 || return 0

  echo -e "\n=== compress $format with invalid source: $label ==="

  rm -f "${dst_path:?}/$archive_name"
  run_action 16 "$source" "$dst_path" 1 "$format" "$archive_name" && rc=1 || rc=0
  check "compress $format ($label): action run must fail" "$rc"

  error_text=$(jq -r '.error // empty' "$fm_debug_nchan_file" | head -1)
  rc=1 && [[ $error_text == *"$expected_error"* ]] && rc=0
  check "compress $format ($label): the user must see an error like '$expected_error' (is: '$error_text')" "$rc"

  rc=0 && [[ -e "$dst_path/$archive_name" ]] && rc=1
  check "compress $format ($label): no archive may be published" "$rc"
}

# test that a compress job fails and publishes no archive if a file is changed while tar reads it, because the archive would not
# contain a consistent state of the file
test_compress_changing_source() {
  local format=tar.gz archive_name=changing.tar.gz
  local changing_path="$src_path/changing" writer rc error_text

  should_run compress invalid arc cmp 16 || return 0

  echo -e "\n=== compress $format with a file which changes while it is archived ==="

  rm -rf "${changing_path:?}" "${dst_path:?}/$archive_name"
  mkdir -p "$changing_path"
  head -c 100M "$files_path/urandom150MB.bin" >"$changing_path/changing.bin"

  # the file is read with the read limit (about 5 seconds), so one of the appends happens while tar reads it
  (for _ in {1..30}; do echo "$EPOCHREALTIME" >>"$changing_path/changing.bin"; sleep 1; done) &
  writer=$!
  run_action 16 "$changing_path/changing.bin" "$dst_path" 1 "$format" "$archive_name" && rc=1 || rc=0
  kill "$writer"
  wait "$writer"
  check "compress $format (changing file): action run must fail" "$rc"

  error_text=$(jq -r '.error // empty' "$fm_debug_nchan_file" | head -1)
  rc=1 && [[ $error_text == *"file changed as we read it"* ]] && rc=0
  check "compress $format (changing file): the user must see an error (is: '$error_text')" "$rc"

  rc=0 && [[ -e "$dst_path/$archive_name" ]] && rc=1
  check "compress $format (changing file): no archive may be published" "$rc"

  rm -rf "${changing_path:?}"
}

# test that a cancel stops all processes of the job (tar, zip, xz, ...) and leaves no temporary file in the target
# args: label action source target [format archive_name]
test_cancel() {
  local label=$1 action=$2 source=$3 target=$4
  local format=${5:-} archive_name=${6:-}
  local json rc running leftovers

  should_run cancel arc 16 17 99 || return 0

  echo -e "\n=== cancel $label (action $action) ==="

  rm -rf "${target:?}"
  mkdir -p "$target"
  json=$(job_json "$action" "$source" "$target" 1 "$format" "$archive_name")
  run_file_manager
  if is_action_in_progress; then
    echo "Error: can not start new action as file_manager is busy (job files still exist)!" 1>&2
    fail=$((fail + 1))
    return 2
  fi

  # read from the disk with the read limit (see run_action), so the job is still running when it is canceled
  printf "" >"$fm_debug_nchan_file"
  sync
  echo 3 > /proc/sys/vm/drop_caches
  echo "$test_device rbps=$fm_read_limit" > "$fm_cgroup/io.max"
  echo "$json" >"$fm_job_json_file"
  sleep 3

  # the job must really run, otherwise the cancel would not be tested
  running=$(job_processes)
  rc=1 && [[ $running ]] && rc=0
  check "cancel $label: the job must be running before it is canceled" "$rc"

  # cancel like Control.php does: the cancel JSON keeps the temporary file of the running job (target_tmp)
  echo "  cancel"
  jq -c '.action = 99' "$fm_job_json_file" >"$fm_job_json_file.cancel" && mv "$fm_job_json_file.cancel" "$fm_job_json_file"
  sleep 3
  sed 's/^/  nchan: /' "$fm_debug_nchan_file"

  rc=0
  is_action_in_progress && rc=1
  check "cancel $label: job files must be removed" "$rc"

  running=$(job_processes)
  rc=0
  if [[ $running ]]; then
    rc=1
    echo "  still running: ${running//$'\n'/, }"
    echo "$running" | awk '{print $1}' | xargs kill
  fi
  check "cancel $label: no process of the job may keep running" "$rc"
  # the read limit stays until now, otherwise processes which were not stopped would finish quickly and could not be found
  echo "$test_device rbps=max" > "$fm_cgroup/io.max"

  # hidden temporary file of file_manager (.name_uniqid.tmp) and the temporary file of zip (zi followed by 6 characters)
  leftovers=$(find "$target" -maxdepth 1 \( -name '.*.tmp' -o -name 'zi??????' \))
  rc=0
  [[ $leftovers ]] && { rc=1; echo "  left in the target: ${leftovers//$'\n'/, }"; }
  check "cancel $label: no temporary file may be left in the target" "$rc"

  rm -rf "${target:?}"
}

# create source files; idempotent - safe to call multiple times
create_source_files() {
  # files of an older layout (other file names or sizes) and their reference archives must not be mixed with the current ones
  local layout_marker="$src_path/.layout-3"
  if [[ -d $src_path && ! -f $layout_marker ]]; then
    rm -rf "${src_path:?}" || { echo "Error: failed to remove $src_path" 1>&2; exit 1; }
    echo "  removed test files of an older layout"
  fi

  # create directories
  [[ ! -d "$test_path" ]] && ! mkdir -m 777 "$test_path" && { echo "Error: failed to create $test_path" 1>&2; exit 1; }
  [[ ! -d "$src_path" ]] && ! mkdir -m 777 "$src_path" && { echo "Error: failed to create $src_path" 1>&2; exit 1; }
  [[ ! -d "$dst_path" ]] && ! mkdir -m 777 "$dst_path" && { echo "Error: failed to create $dst_path" 1>&2; exit 1; }
  [[ ! -d "$files_path" ]] && ! mkdir -m 777 "$files_path" && { echo "Error: failed to create $files_path" 1>&2; exit 1; }
  [[ ! -d "$arc_path" ]] && ! mkdir -m 777 "$arc_path" && { echo "Error: failed to create $arc_path" 1>&2; exit 1; }
  [[ ! -d "$files_path/small_files" ]] && ! mkdir -m 777 "$files_path/small_files" && { echo "Error: failed to create $files_path/small_files" 1>&2; exit 1; }

  # The jobs read with 20 MiB/s (see "I/O throttling"), so the sizes decide how long a job runs and how many progress lines it shows:
  # a job which reads 150 MB takes 7.5 seconds. The archive of $files_path is about 190 MB, so extracting it takes 9 seconds.
  # random data: the worst case for compressors, the archive is as big as the source
  [[ ! -f "$files_path/small_files/urandom10MB.bin" ]] && dd if=/dev/urandom bs=1M count=10 of="$files_path/small_files/urandom10MB.bin"
  [[ ! -f "$files_path/urandom150MB.bin" ]] && dd if=/dev/urandom bs=1M count=150 of="$files_path/urandom150MB.bin"

  # text: 9 million random words of a vocabulary of 3000 words with a decreasing probability, like a log or a document. The
  # compression ratio is 1.5 (lz4) to 4 (bzip2) and, like for real text, the archive is much smaller than the source.
  # example: "kdgiqr btqyeyiih kmhhy yakp lgdww nnv uxxdl zlwpl suhph uie ihelzqypn bwttmhswg."
  if [[ ! -f "$files_path/text60MB.txt" ]]; then
    LC_ALL=C awk 'BEGIN {
      srand(7)
      split("abcdefghijklmnopqrstuvwxyz", letters, "")
      # vocabulary: 3000 words of 3-9 random letters
      for (i = 1; i <= 3000; i++) {
        length_of_word = 3 + int(rand() * 7)
        word = ""
        for (j = 0; j < length_of_word; j++) word = word letters[1 + int(rand() * 26)]
        vocabulary[i] = word
      }
      # rand() * rand() prefers the first words of the vocabulary; a sentence has 12 words
      for (k = 0; k < 9000000; k++) printf "%s%s", vocabulary[1 + int(rand() * rand() * 3000)], (k % 12 == 11 ? ".\n" : " ")
    }' >"$files_path/text60MB.txt"
  fi

  # zeros: extremely compressible, the archive is almost empty
  [[ ! -f "$files_path/small_files/zero10MB.bin" ]] && dd if=/dev/zero  bs=1M count=10 of="$files_path/small_files/zero10MB.bin"
  [[ ! -f "$files_path/zero40MB.bin" ]] && dd if=/dev/zero  bs=1M count=40 of="$files_path/zero40MB.bin"

  # create huge amount of empty directories
  [[ ! -d "$files_path/empty_dirs" ]] && ! mkdir "$files_path/empty_dirs" && { echo "Error: failed to create $files_path/empty_dirs" 1>&2; exit 1; }
  for i in {1..1000}; do
    [[ ! -d "$files_path/empty_dirs/$i" ]] && ! mkdir "$files_path/empty_dirs/$i" && { echo "Error: failed to create $files_path/empty_dirs/$i" 1>&2; exit 1; }
  done

  # empty text file
  [[ ! -f "$files_path/empty.txt" ]] && touch "$files_path/empty.txt"

  # tiny text file
  [[ ! -f "$files_path/hello.txt" ]] && echo "hello world" >"$files_path/hello.txt"

  # tiny text file in subdirectory
  [[ ! -f "$files_path/small_files/subfile.txt" ]] && echo "hello subfile" >"$files_path/small_files/subfile.txt"

  # create file with utf-8 chars in name
  [[ ! -f "$files_path/utf8_файл.txt" ]] && echo "utf-8 filename" >"$files_path/utf8_файл.txt"

  # create file with newline and tabulator in name
  [[ ! -f "$files_path/"$'newline\ntab\tfile.txt' ]] && echo "newline in filename" >"$files_path/"$'newline\ntab\tfile.txt'

  # create file with shell-related special chars in name
  [[ ! -f "$files_path/shell.\${specific}.special&chars\$file|name.txt" ]] && echo "special chars in filename" >"$files_path/shell.\${specific}.special&chars\$file|name.txt"

  # create file with utf-8, newline and special chars in name
  [[ ! -f "$files_path/$special_chars_name.txt" ]] && echo "utf-8, newline and special chars in filename" >"$files_path/$special_chars_name.txt"

  # create directory with content for delete test
  [[ ! -d "$files_path/delete_dir" ]] && mkdir "$files_path/delete_dir"
  [[ ! -d "$files_path/delete_dir/subdir" ]] && mkdir "$files_path/delete_dir/subdir"
  [[ ! -f "$files_path/delete_dir/file.txt" ]] && echo "file in dir" >"$files_path/delete_dir/file.txt"
  [[ ! -f "$files_path/delete_dir/subdir/file.txt" ]] && echo "file in subdir" >"$files_path/delete_dir/subdir/file.txt"

  # remove the renamed targets of the rename tests, they are not part of the reference archives of the extract tests
  # (which are reused by the next run)
  rm -rf "${files_path:?}/$special_chars_name-renamed.txt" "${files_path:?}/$special_chars_name-renamed-dir"

  # create file and directory for rename test (the originals are re-created by the guards after the rename tests)
  [[ ! -f "$files_path/$special_chars_name-rename.txt" ]] && echo "file for rename test" >"$files_path/$special_chars_name-rename.txt"
  [[ ! -d "$files_path/$special_chars_name-rename-dir" ]] && mkdir "$files_path/$special_chars_name-rename-dir"
  [[ ! -f "$files_path/$special_chars_name-rename-dir/content.txt" ]] && echo "content in rename dir" >"$files_path/$special_chars_name-rename-dir/content.txt"
  # create file for chmod test
  [[ ! -f "$files_path/$special_chars_name-chmod.txt" ]] && echo "file for chmod test" >"$files_path/$special_chars_name-chmod.txt"
  # create file for chown test
  [[ ! -f "$files_path/$special_chars_name-chown.txt" ]] && echo "file for chown test" >"$files_path/$special_chars_name-chown.txt"

  # create files and directories for copy test
  [[ ! -f "$files_path/$special_chars_name-copy.txt" ]] && echo "file for copy test" >"$files_path/$special_chars_name-copy.txt"

  # create files for move tests (small dedicated files for file-move tests; folder-move tests use $files_path directly)
  [[ ! -f "$files_path/$special_chars_name-move-rr.txt" ]] && echo "file for move test (rsync-rename)" >"$files_path/$special_chars_name-move-rr.txt"
  [[ ! -f "$files_path/$special_chars_name-move-cd.txt" ]] && echo "file for move test (copy-delete)" >"$files_path/$special_chars_name-move-cd.txt"

  # create hidden files
  [[ ! -f "$files_path/.hiddenfile" ]] && echo "hidden file" >"$files_path/.hiddenfile"
  [[ ! -d "$files_path/.hiddendir" ]] && mkdir "$files_path/.hiddendir"
  [[ ! -f "$files_path/.hiddendir/.hiddenfile" ]] && echo "hidden file in hidden directory" >"$files_path/.hiddendir/.hiddenfile"

  # create file with .tmp extension to test that it doesn't interfere with a cleanup logic
  [[ ! -f "$files_path/not_fm_related_temp_file.tmp" ]] && echo "this temporary file should not be deleted by file manager" >"$files_path/not_fm_related_temp_file.tmp"

  # create hidden file to test that it doesn't interfere with a cleanup logic
  [[ ! -f "$files_path/.not_fm_related_hidden_temp_file.tmp" ]] && echo "this hidden file should not be deleted by file manager" >"$files_path/.not_fm_related_hidden_temp_file.tmp"

  # create file and hardlink it; verify both share the same inode (rsync breaks hardlinks - re-link if needed)
  [[ ! -f "$files_path/hardlink1.bin" ]] && dd if=/dev/urandom bs=1M count=5 of="$files_path/hardlink1.bin"
  if [[ -f "$files_path/hardlink2.bin" ]]; then
    inode1=$(stat -c '%i' "$files_path/hardlink1.bin")
    inode2=$(stat -c '%i' "$files_path/hardlink2.bin")
    if [[ $inode1 != "$inode2" ]]; then
      rm "$files_path/hardlink2.bin"
    fi
  fi
  [[ ! -f "$files_path/hardlink2.bin" ]] && ln "$files_path/hardlink1.bin" "$files_path/hardlink2.bin"

  # create file and symlink targeting it
  [[ ! -f "$files_path/symlink1.bin" ]] && dd if=/dev/urandom bs=1M count=5 of="$files_path/symlink1.bin"
  [[ ! -L "$files_path/symlink2.bin" ]] && ln -s "symlink1.bin" "$files_path/symlink2.bin"

  # create broken symlink
  [[ ! -L "$files_path/broken_symlink" ]] && ln -s "nonexistent_target.bin" "$files_path/broken_symlink"

  # normalize all mtimes to a fixed value so extract diffs are reproducible across runs
  find "$files_path" -exec touch -h -t 202001010000 {} +

  # build archives for extract tests
  for fmt in "${archive_multi_formats[@]}"; do
    [[ ! -f "$arc_path/archive.$fmt" ]] && build_archive "$fmt" "$files_path" "$arc_path" "archive.$fmt"
    [[ ! -f "$arc_path/archive.overwrite.$fmt" ]] && build_archive "$fmt" "$files_path/small_files" "$arc_path" "archive.overwrite.$fmt"
  done
  for fmt in "${archive_single_formats[@]}"; do
    [[ ! -f "$arc_path/$special_chars_name.txt.$fmt" ]] && build_archive "$fmt" "$files_path/$special_chars_name.txt" "$arc_path" "$special_chars_name.txt.$fmt"
    [[ ! -f "$arc_path/urandom150MB.bin.$fmt" ]] && build_archive "$fmt" "$files_path/urandom150MB.bin" "$arc_path" "urandom150MB.bin.$fmt"
  done
  find "$arc_path" -exec touch -h -t 202001010000 {} +

  touch "$layout_marker"
  echo "  test files have been created in $src_path"
}

echo -e "\n=== Start file manager integration test ==="

# verify file_manager has no active job before starting test
is_action_in_progress && { echo "Error: file_manager is busy with existing job files!" 1>&2; exit 1; }

# enable file_manager debug mode: activates nchan debug logging + renames job files instead of deleting
touch /var/tmp/file.manager.debug
printf "" >"$fm_debug_nchan_file"

# stop running file_manager so the next start picks up the debug trigger
stop_file_manager

# ===========================
# I/O throttling
# ===========================
# Every job reads with at most $fm_read_limit, no matter how fast the disk of the person who runs the test is. So each job runs
# long enough to show several progress updates and the shown values can be compared with the real ones.
# - cgroup v2 io.max limits the reads of the block device which holds the test files (set in run_action while a job runs)
# - the file_manager worker is started from this shell, so tar, zip, rsync, xz, ... inherit the cgroup and the limit
# - the page cache is dropped before each job, otherwise the files would be read from RAM and not from the disk
# - Unraid removes empty cgroups after a few seconds (cgroup2-unraid), so this shell moves into the cgroup right after creating it
if [[ $(cat /sys/fs/cgroup/cgroup.controllers) != *io* ]]; then
  echo "Error: this test requires cgroup v2 with the io controller (Unraid 7)" 1>&2
  exit 1
fi
# zfs issues its I/O with kernel threads and assigns every bio to the root cgroup (vdev_disk.c: vdev_bio_associate_blkg), so the
# cgroup of the process which reads does not limit it. Additionally the ARC is not emptied by drop_caches.
test_fs=$(findmnt -n -o FSTYPE --target "${test_path%/*}")
if [[ $test_fs == zfs ]]; then
  echo "Error: reads from zfs can not be throttled with a cgroup, use a path on a disk with another filesystem" 1>&2
  exit 1
fi
# io.max only accepts the number of a whole block device. The number which findmnt reports for the mount is the one of a partition
# (e.g. a pool disk) or an anonymous one (btrfs: 0:NN), so the number is taken from the device of the mount source and from its
# disk if it is a partition. A btrfs subvolume is appended to the source (/dev/sdb1[/subvol]). Only the first device of a btrfs
# filesystem with several devices is throttled.
test_source=$(findmnt -n -o SOURCE --target "${test_path%/*}")
test_source=${test_source%%\[*}
test_disk=$(lsblk -dno PKNAME "$test_source")
test_device=$(lsblk -dno MAJ:MIN "/dev/${test_disk:-${test_source##*/}}" | tr -d ' ')
if [[ $(cat /sys/fs/cgroup/cgroup.subtree_control) != *io* ]]; then
  echo "+io" > /sys/fs/cgroup/cgroup.subtree_control
  io_controller_enabled=1
fi
for attempt in {1..5}; do
  mkdir -p "$fm_cgroup" && echo $$ > "$fm_cgroup/cgroup.procs" && break
  [[ $attempt -eq 5 ]] && { echo "Error: failed to move into the cgroup $fm_cgroup" 1>&2; exit 1; }
done
if ! echo "$test_device rbps=max" > "$fm_cgroup/io.max"; then
  echo "Error: io.max does not accept the device $test_device of $test_source, the reads of ${test_path%/*} can not be throttled" 1>&2
  exit 1
fi
echo "  throttling reads of device $test_device ($test_source, $test_fs) to $fm_read_limit bytes/s in cgroup $fm_cgroup (shell is in $(cat /proc/self/cgroup))"

# ===========================
# create source files
# ===========================
echo " create test files..."
create_source_files

# ===========================
# compress tests
# ===========================
for fmt in "${archive_multi_formats[@]}"; do
  test_compress "$fmt" "$dst_path/archive.$fmt" "$files_path"
done
for fmt in "${archive_multi_formats[@]}"; do
  test_compress "$fmt" "$dst_path/archive.overwrite.$fmt" "$files_path/small_files" 1
done
for fmt in "${archive_single_formats[@]}"; do
  test_compress "$fmt" "$dst_path/$special_chars_name.txt.$fmt" "$files_path/$special_chars_name.txt" 1
done
# a larger single file runs long enough to show progress
for fmt in "${archive_single_formats[@]}"; do
  test_compress "$fmt" "$dst_path/urandom150MB.bin.$fmt" "$files_path/urandom150MB.bin"
done

# ===========================
# extract tests
# ===========================
for fmt in "${archive_multi_formats[@]}"; do
  test_extract "$fmt" "$arc_path/archive.$fmt" "$dst_path/extract_$fmt/"
done
for fmt in "${archive_multi_formats[@]}"; do
  test_extract "$fmt" "$arc_path/archive.overwrite.$fmt" "$dst_path/extract_overwrite_$fmt/" 1 "$files_path/small_files"
done
for fmt in "${archive_single_formats[@]}"; do
  test_extract "$fmt" "$arc_path/$special_chars_name.txt.$fmt" "$dst_path/extract_$fmt/" 1
done
for fmt in "${archive_single_formats[@]}"; do
  test_extract "$fmt" "$arc_path/urandom150MB.bin.$fmt" "$dst_path/extract_big_$fmt/"
done

# ===========================
# invalid and failing compress tests
# ===========================
test_compress_invalid "second source does not exist" tar.gz "$files_path/hello.txt"$'\r'"$files_path/does_not_exist.txt" invalid.tar.gz "Cannot stat"
test_compress_invalid "second source does not exist" zip "$files_path/hello.txt"$'\r'"$files_path/does_not_exist.txt" invalid.zip "name not matched"
test_compress_invalid "folder" gz "$files_path/small_files" invalid-folder.gz "Single-file formats require exactly one file"
test_compress_invalid "two files" gz "$files_path/hello.txt"$'\r'"$files_path/empty.txt" invalid-two.gz "Single-file formats require exactly one file"
test_compress_changing_source

# ===========================
# cancel tests
# ===========================
for fmt in tar.bz2 zip; do
  [[ " ${archive_multi_formats[*]} " == *" $fmt "* ]] || continue
  test_cancel "compress $fmt" 16 "$files_path" "$dst_path/cancel" "$fmt" "cancel.$fmt"
  test_cancel "extract $fmt" 17 "$arc_path/archive.$fmt" "$dst_path/cancel/"
done
[[ " ${archive_single_formats[*]} " == *" xz "* ]] && test_cancel "compress xz (single file)" 16 "$files_path/urandom150MB.bin" "$dst_path/cancel" xz cancel.xz

# ===========================
# create folder tests
# ===========================
test_create_folder "special chars" "$dst_path" "$special_chars_name-dir"

# ===========================
# delete tests
# ===========================
test_delete_file "special chars" "$files_path/$special_chars_name.txt"
test_delete_folder "with content" "$files_path/delete_dir"
create_source_files >/dev/null

# ===========================
# rename tests
# ===========================
test_rename_file "special chars" "$files_path/$special_chars_name-rename.txt" "$special_chars_name-renamed.txt"
test_rename_folder "special chars dir" "$files_path/$special_chars_name-rename-dir" "$special_chars_name-renamed-dir"
create_source_files >/dev/null

# ===========================
# chmod tests
# ===========================
test_chmod "special chars to 0755" "$files_path/$special_chars_name-chmod.txt" "0755"
test_chmod "special chars to 0644" "$files_path/$special_chars_name-chmod.txt" "0644"

# ===========================
# chown tests
# ===========================
test_chown "special chars to nobody:users" "$files_path/$special_chars_name-chown.txt" "nobody:users"
test_chown "special chars to root:root"    "$files_path/$special_chars_name-chown.txt" "root:root"

# ===========================
# search tests
# ===========================
test_search "hello.txt in src" "$files_path" "hello.txt"
test_search "*.txt wildcard"   "$files_path" "*.txt"
test_search "no match pattern" "$files_path" "no_such_file_xyz.bin"

# ===========================
# copy tests
# ===========================
test_copy_file   "special chars" "$files_path/$special_chars_name-copy.txt" "$dst_path"
test_copy_folder "files dir" "$files_path" "$dst_path"

# ===========================
# copy and move with a speed limit
# ===========================
# The speed limit of the File Manager settings (bwlimit in MB/s) is passed to rsync as --bwlimit. The worker reads the settings for
# every job. The copy is the first and the move with rsync copy-delete the second rsync call which uses the limit.
if should_run copy cp move mv; then
  echo -e "\n=== speed limit of $bwlimit_mb MB/s for copy and move ==="
  cp -p "$fm_cfg" "$fm_cfg_backup"
  awk '/^\[/{skip = ($0 == "[filemanager]")} !skip' "$fm_cfg_backup" > "$fm_cfg"
  printf '[filemanager]\nbwlimit="%s"\n' "$bwlimit_mb" >> "$fm_cfg"
  bwlimit_source="$files_path/text60MB.txt"
  bwlimit_expected_ms=$(( $(stat -c %s "$bwlimit_source") * 1000 / (bwlimit_mb * 1048576) ))
  for bwlimit_action in copy move; do
    bwlimit_start=${EPOCHREALTIME/./}
    if [[ $bwlimit_action == copy ]]; then
      should_run copy cp || continue
      test_copy_file "speed limit" "$bwlimit_source" "$dst_path"
    else
      should_run move mv || continue
      test_move_file "speed limit copy-delete" "$bwlimit_source" "$dst_path" 1
    fi
    bwlimit_ms=$(( (${EPOCHREALTIME/./} - bwlimit_start) / 1000 ))
    echo "  $bwlimit_action took $((bwlimit_ms / 1000)) s, expected about $((bwlimit_expected_ms / 1000)) s"
    check "$bwlimit_action with a speed limit of $bwlimit_mb MB/s: must take at least 80% of the expected $((bwlimit_expected_ms / 1000)) s (took $((bwlimit_ms / 1000)) s)" $(( bwlimit_ms * 10 >= bwlimit_expected_ms * 8 ? 0 : 1 ))
    check "$bwlimit_action with a speed limit of $bwlimit_mb MB/s: must not take more than twice the expected time plus 10 s (took $((bwlimit_ms / 1000)) s)" $(( bwlimit_ms <= bwlimit_expected_ms * 2 + 10000 ? 0 : 1 ))
  done
  mv -f "$fm_cfg_backup" "$fm_cfg"
  create_source_files >/dev/null # the move removed the source file
fi

# ===========================
# move tests
# ===========================
test_move_file   "special chars rsync-rename" "$files_path/$special_chars_name-move-rr.txt" "$dst_path"
test_move_file   "special chars copy-delete"  "$files_path/$special_chars_name-move-cd.txt" "$dst_path" 1
test_move_folder "files dir rsync-rename" "$files_path" "$dst_path"
[[ ! -d "$files_path" && -d "$dst_path/files" ]] && mv "$dst_path/files" "$src_path/" 2>/dev/null
create_source_files >/dev/null # repair source if broken
test_move_folder "files dir copy-delete"  "$files_path" "$dst_path" 1
[[ ! -d "$files_path" && -d "$dst_path/files" ]] && mv "$dst_path/files" "$src_path/" 2>/dev/null
create_source_files >/dev/null # repair source if broken

# ===========================
# summary
# ===========================
echo -e "\n=== summary: $pass passed, $fail failed ==="
[[ $fail -eq 0 ]]
