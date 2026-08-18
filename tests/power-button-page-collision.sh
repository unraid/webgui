#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CORE_PAGE="$ROOT_DIR/emhttp/plugins/dynamix/PowerModeButton.page"
LEGACY_PAGE="$ROOT_DIR/emhttp/plugins/dynamix/PowerButton.page"
PLUGIN_PAGE="$ROOT_DIR/emhttp/plugins/dynamix.system.buttons/PowerButton.page"

[[ -f "$CORE_PAGE" ]] || { echo "missing built-in Power Button page: $CORE_PAGE" >&2; exit 1; }
[[ ! -e "$LEGACY_PAGE" ]] || { echo "legacy page name still exists: $LEGACY_PAGE" >&2; exit 1; }

core_name="$(basename "$CORE_PAGE" .page)"
plugin_name="$(basename "$PLUGIN_PAGE" .page)"
[[ "$core_name" == "PowerModeButton" ]] || { echo "unexpected built-in page name: $core_name" >&2; exit 1; }
[[ "$plugin_name" == "PowerButton" ]] || { echo "unexpected plugin page name: $plugin_name" >&2; exit 1; }
[[ "$core_name" != "$plugin_name" ]] || { echo "built-in and plugin page names collide" >&2; exit 1; }

grep -q '^Menu="PowerMode:2"$' "$CORE_PAGE"
grep -q '^Title="Power Button"$' "$CORE_PAGE"

echo "power-button page collision test passed"
