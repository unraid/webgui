#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PAGE="$ROOT_DIR/emhttp/plugins/dynamix/PowerModeCpu.page"
STYLE="$ROOT_DIR/emhttp/plugins/dynamix/sheets/PowerModeCpu.css"
OLD_STYLE="$ROOT_DIR/emhttp/plugins/dynamix/sheets/PowerMode.css"

[[ -f "$PAGE" ]] || { echo "missing Power Mode page: $PAGE" >&2; exit 1; }
[[ -f "$STYLE" ]] || { echo "missing Power Mode page stylesheet: $STYLE" >&2; exit 1; }
[[ ! -e "$OLD_STYLE" ]] || { echo "old Power Mode stylesheet still exists: $OLD_STYLE" >&2; exit 1; }

page_name="$(basename "$PAGE" .page)"
style_name="$(basename "$STYLE" .css)"
[[ "$page_name" == "$style_name" ]] || {
  echo "page and stylesheet names do not match: $page_name != $style_name" >&2
  exit 1
}

grep -q '^div#vm {' "$STYLE"
grep -q '    display: none;' "$STYLE"

echo "Power Mode CPU stylesheet test passed"
