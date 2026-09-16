#!/bin/bash
set -eo pipefail

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
tmpdir=$(mktemp -d "${TMPDIR:-/tmp}/mover-hardlink-test.XXXXXX")

cleanup_test() {
  find "$tmpdir" -depth -type f -exec rm -f -- {} \; 2>/dev/null || true
  find "$tmpdir" -depth -type d -exec rmdir -- {} \; 2>/dev/null || true
}
trap cleanup_test EXIT

mkdir -p "$tmpdir/bin" "$tmpdir/tmp" \
  "$tmpdir/source/first" "$tmpdir/source/second" "$tmpdir/source/third"

cat > "$tmpdir/bin/stat" <<'EOF'
#!/bin/bash
set -euo pipefail

format="$2"
path="$4"
if [[ -n ${STAT_FAIL_PATH:-} && $path == "$STAT_FAIL_PATH" ]]; then
  exit 1
fi
if [[ $(uname -s) == Darwin ]]; then
  case "$format" in
    %s) /usr/bin/stat -f '%z' -- "$path" ;;
    %d:%i) /usr/bin/stat -f '%d:%i' -- "$path" ;;
    %D:%i\ %s) /usr/bin/stat -f '%d:%i %z' -- "$path" ;;
    *) exit 1 ;;
  esac
else
  /usr/bin/stat -c "$format" -- "$path"
fi
EOF

cat > "$tmpdir/bin/find" <<'EOF'
#!/bin/bash
set -euo pipefail

root="$1"
shift
args=()
has_printf=0
while [[ $# -gt 0 ]]; do
  if [[ $1 == -printf ]]; then
    has_printf=1
    shift 2
  else
    args+=("$1")
    shift
  fi
done

if [[ $has_printf -eq 1 ]]; then
  /usr/bin/find "$root" "${args[@]}" -exec stat -c '%D:%i %s' -- {} \;
else
  if /usr/bin/find "$root" "${args[@]}"; then
    find_status=0
  else
    find_status=$?
  fi
  [[ ${FIND_FAIL_AFTER_OUTPUT:-0} -eq 1 ]] && exit 1
  exit "$find_status"
fi
EOF

cat > "$tmpdir/bin/move" <<'EOF'
#!/bin/bash
set -euo pipefail

while IFS= read -r path; do
  [[ -n $path ]] || continue
  printf '%s\t%s\n' "$$" "$path" >> "$MOVE_LOG"
  if [[ -f "$path" ]]; then
    rm -f -- "$path"
  fi
done
EOF

chmod +x "$tmpdir/bin/stat" "$tmpdir/bin/find" "$tmpdir/bin/move"

printf 'hardlink-data' > "$tmpdir/source/first/shared.iso"
ln "$tmpdir/source/first/shared.iso" "$tmpdir/source/second/shared.iso"
printf 'single-data' > "$tmpdir/source/third/single.iso"

export PATH="$tmpdir/bin:$PATH"
export TMPDIR="$tmpdir/tmp"
export MOVE_HELPER="$tmpdir/bin/move"
export MOVE_LOG="$tmpdir/move.log"

# Load the mover functions without invoking its command-line dispatcher.
# shellcheck disable=SC1090
source <(sed '/^# display usage and then exit/,$d' "$script_dir/sbin/mover")

share_path="$tmpdir/source"
expected_size=$(( $(file_size "$tmpdir/source/first/shared.iso") + $(file_size "$tmpdir/source/third/single.iso") ))
actual_size=$(tree_size "$share_path")
[[ $actual_size -eq $expected_size ]] || {
  echo "tree_size counted hardlinked bytes more than once" >&2
  exit 1
}

# The functions loaded above consume these variables.
# shellcheck disable=SC2034
SHARE='test'
# shellcheck disable=SC2034
TOTAL=$actual_size
REMAIN=$actual_size
# shellcheck disable=SC2034
SHARE_TOTALS["$SHARE"]=$actual_size
# shellcheck disable=SC2034
SHARE_REMAINS["$SHARE"]=$actual_size
# shellcheck disable=SC2034
MOVER_PROGRESS=enabled
# shellcheck disable=SC2034
MOVERSTATUS="$tmpdir/mover.ini"
# shellcheck disable=SC2034
START_TIME=$(date +%s)
move "$share_path"

group_ids=$(awk -F '\t' -v first="$tmpdir/source/first/shared.iso" \
  -v second="$tmpdir/source/second/shared.iso" \
  '$2 == first || $2 == second {print $1}' "$MOVE_LOG" | sort -u)
[[ $(printf '%s\n' "$group_ids" | sed '/^$/d' | wc -l | tr -d ' ') -eq 1 ]] || {
  echo "hardlink aliases were not sent to one move invocation" >&2
  exit 1
}

single_id=$(awk -F '\t' -v single="$tmpdir/source/third/single.iso" '$2 == single {print $1}' "$MOVE_LOG")
[[ -n $single_id && "$single_id" != "$group_ids" ]] || {
  echo "a normal file did not keep its single-file move invocation" >&2
  exit 1
}

[[ $REMAIN -eq 0 && ${SHARE_REMAIN:-0} -eq 0 ]] || {
  echo "hardlink progress was not decremented once for the group" >&2
  exit 1
}

stat_failure_root="$tmpdir/source/stat-failure"
stat_failure_path="$stat_failure_root/uninspectable.iso"
stat_valid_path="$stat_failure_root/valid.iso"
mkdir -p "$stat_failure_root"
printf 'uninspectable-data' > "$stat_failure_path"
printf 'valid-data' > "$stat_valid_path"
: > "$MOVE_LOG"
export STAT_FAIL_PATH="$stat_failure_path"
# shellcheck disable=SC2034
MOVER_PROGRESS=disabled
# shellcheck disable=SC2034
SHARE='stat-failure'
move "$stat_failure_root"
unset STAT_FAIL_PATH

if grep -F -- "$stat_failure_path" "$MOVE_LOG" >/dev/null; then
  echo "a path with a failed hardlink identity lookup was moved" >&2
  exit 1
fi
grep -F -- "$stat_valid_path" "$MOVE_LOG" >/dev/null || {
  echo "a valid path was skipped after another identity lookup failed" >&2
  exit 1
}
[[ -e "$stat_failure_path" ]] || {
  echo "a path with a failed hardlink identity lookup was removed" >&2
  exit 1
}

partial_find_root="$tmpdir/source/partial-find"
partial_find_path="$partial_find_root/partial.iso"
mkdir -p "$partial_find_root"
printf 'partial-find-data' > "$partial_find_path"
: > "$MOVE_LOG"
export FIND_FAIL_AFTER_OUTPUT=1
# shellcheck disable=SC2034
MOVER_PROGRESS=disabled
# shellcheck disable=SC2034
SHARE='partial-find'
move "$partial_find_root"
unset FIND_FAIL_AFTER_OUTPUT

grep -F -- "$partial_find_path" "$MOVE_LOG" >/dev/null || {
  echo "a usable partial find result was not processed" >&2
  exit 1
}
[[ ! -e "$partial_find_path" ]] || {
  echo "a file from a usable partial find result was not moved" >&2
  exit 1
}

echo "Mover hardlink regression test passed."
