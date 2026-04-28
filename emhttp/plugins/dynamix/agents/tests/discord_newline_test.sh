#!/bin/bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
agent_xml="${script_dir}/../Discord.xml"

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

stub_dir="${tmp_dir}/bin"
payload="${tmp_dir}/payload.json"
script_path="${tmp_dir}/Discord.sh"

mkdir -p "$stub_dir"

cat > "${stub_dir}/curl" <<'EOF'
#!/bin/bash
out="${DISCORD_TEST_OUT:-/tmp/discord_payload.json}"
for ((i=1; i<=$#; i++)); do
  if [[ "${!i}" == "--data-binary" ]]; then
    j=$((i+1))
    printf '%s' "${!j}" > "$out"
    exit 0
  fi
done
exit 0
EOF
chmod +x "${stub_dir}/curl"

cat > "${stub_dir}/date" <<'EOF'
#!/bin/bash
if [[ "$*" == *"-d "* ]]; then
  printf '%s\n' "1970-01-01T00:00:00.000Z"
  exit 0
fi
exec /bin/date "$@"
EOF
chmod +x "${stub_dir}/date"

awk '
  index($0, "<![CDATA[") { in_block=1; next }
  index($0, "]]>") { in_block=0; next }
  in_block { print }
' "$agent_xml" | sed 's/{0}//' > "$script_path"
chmod +x "$script_path"

run_case() {
  local desc="$1"
  local content="$2"
  local expected="$3"

  rm -f "$payload"
  DISCORD_TEST_OUT="$payload" \
  WEBH_URL="http://example.invalid" \
  DESCRIPTION="$desc" \
  CONTENT="$content" \
  PATH="$stub_dir:$PATH" \
  bash "$script_path" >/dev/null

  actual="$(jq -r '.embeds[0].fields[0].value' "$payload")"
  if [[ "$actual" != "$expected" ]]; then
    echo "Discord newline test failed." >&2
    printf 'Expected:\n%s\n' "$expected" >&2
    printf 'Actual:\n%s\n' "$actual" >&2
    exit 1
  fi
}

run_case "Line1\\nLine2" "Line3\\nLine4" $'Line1\nLine2\n\nLine3\nLine4'
run_case "Line1\\nLine2" "" $'Line1\nLine2'

echo "OK"
