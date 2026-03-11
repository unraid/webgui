#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
HELPER="$SCRIPT_DIR/../sbin/rclone_config_init"

fail() {
  echo "FAIL: $1" >&2
  exit 1
}

assert_file_content() {
  local file="$1"
  local expected="$2"
  [[ -f "$file" ]] || fail "missing file: $file"
  local got
  got=$(cat "$file")
  [[ "$got" == "$expected" ]] || fail "unexpected content in $file (got '$got', expected '$expected')"
}

assert_symlink_to() {
  local link="$1"
  local target="$2"
  [[ -L "$link" ]] || fail "expected symlink: $link"
  local resolved
  local expected
  resolved=$(readlink -f "$link")
  expected=$(readlink -f "$target")
  [[ "$resolved" == "$expected" ]] || fail "unexpected symlink target for $link (got '$resolved', expected '$expected')"
}

run_case_no_plugin_migrates_root() {
  local tmp
  tmp=$(mktemp -d)
  local cfg="$tmp/boot/config"
  local root_cfg="$tmp/root/.config/rclone/rclone.conf"
  local default_cfg="$cfg/rclone/rclone.conf"
  mkdir -p "$(dirname "$root_cfg")"
  printf "alpha" > "$root_cfg"

  "$HELPER" "$cfg" "$root_cfg" >/dev/null

  assert_file_content "$default_cfg" "alpha"
  assert_symlink_to "$root_cfg" "$default_cfg"
  rm -rf "$tmp"
}

run_case_plugin_present_uses_plugin_path() {
  local tmp
  tmp=$(mktemp -d)
  local cfg="$tmp/boot/config"
  local root_cfg="$tmp/root/.config/rclone/rclone.conf"
  local default_cfg="$cfg/rclone/rclone.conf"
  local plugin_cfg="$cfg/plugins/rclone/.rclone.conf"
  mkdir -p "$(dirname "$root_cfg")" "$cfg/plugins"
  : > "$cfg/plugins/rclone.plg"
  printf "beta" > "$root_cfg"

  "$HELPER" "$cfg" "$root_cfg" >/dev/null

  assert_file_content "$plugin_cfg" "beta"
  assert_symlink_to "$root_cfg" "$plugin_cfg"
  assert_symlink_to "$default_cfg" "$plugin_cfg"
  rm -rf "$tmp"
}

run_case_plugin_added_later_migrates_default() {
  local tmp
  tmp=$(mktemp -d)
  local cfg="$tmp/boot/config"
  local root_cfg="$tmp/root/.config/rclone/rclone.conf"
  local default_cfg="$cfg/rclone/rclone.conf"
  local plugin_cfg="$cfg/plugins/rclone/.rclone.conf"
  mkdir -p "$(dirname "$root_cfg")" "$cfg/rclone" "$cfg/plugins"
  printf "gamma" > "$default_cfg"
  ln -s "$default_cfg" "$root_cfg"
  : > "$cfg/plugins/rclone.plg"

  "$HELPER" "$cfg" "$root_cfg" >/dev/null

  assert_file_content "$plugin_cfg" "gamma"
  assert_symlink_to "$default_cfg" "$plugin_cfg"
  assert_symlink_to "$root_cfg" "$plugin_cfg"
  rm -rf "$tmp"
}

run_case_backup_when_both_exist() {
  local tmp
  tmp=$(mktemp -d)
  local cfg="$tmp/boot/config"
  local root_cfg="$tmp/root/.config/rclone/rclone.conf"
  local default_cfg="$cfg/rclone/rclone.conf"
  local plugin_cfg="$cfg/plugins/rclone/.rclone.conf"
  local backup_dir="$cfg/rclone/backups"
  mkdir -p "$(dirname "$root_cfg")" "$cfg/plugins/rclone" "$cfg/rclone" "$backup_dir" "$cfg/plugins"
  : > "$cfg/plugins/rclone.plg"
  printf "canonical" > "$plugin_cfg"
  printf "stale-default" > "$default_cfg"
  ln -s "$default_cfg" "$root_cfg"

  "$HELPER" "$cfg" "$root_cfg" >/dev/null

  assert_file_content "$plugin_cfg" "canonical"
  assert_symlink_to "$default_cfg" "$plugin_cfg"
  assert_symlink_to "$root_cfg" "$plugin_cfg"
  ls "$backup_dir"/rclone.conf.* >/dev/null 2>&1 || fail "expected backup not found in $backup_dir"
  rm -rf "$tmp"
}

run_case_plugin_removed_recovers_default_path() {
  local tmp
  tmp=$(mktemp -d)
  local cfg="$tmp/boot/config"
  local root_cfg="$tmp/root/.config/rclone/rclone.conf"
  local default_cfg="$cfg/rclone/rclone.conf"
  local plugin_cfg="$cfg/plugins/rclone/.rclone.conf"
  mkdir -p "$(dirname "$root_cfg")" "$cfg/plugins/rclone" "$cfg/plugins"
  : > "$cfg/plugins/rclone.plg"
  printf "delta" > "$root_cfg"

  "$HELPER" "$cfg" "$root_cfg" >/dev/null

  rm -f "$cfg/plugins/rclone.plg" "$plugin_cfg"

  "$HELPER" "$cfg" "$root_cfg" >/dev/null

  [[ -f "$default_cfg" ]] || fail "expected default config to exist after plugin removal"
  [[ ! -L "$default_cfg" ]] || fail "expected default config to be a regular file after plugin removal"
  assert_file_content "$default_cfg" ""
  assert_symlink_to "$root_cfg" "$default_cfg"
  rm -rf "$tmp"
}

run_case_no_plugin_migrates_root
run_case_plugin_present_uses_plugin_path
run_case_plugin_added_later_migrates_default
run_case_backup_when_both_exist
run_case_plugin_removed_recovers_default_path
echo "PASS: rclone_config_init tests"
