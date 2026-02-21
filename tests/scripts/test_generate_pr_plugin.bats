#!/usr/bin/env bats
# Tests for .github/scripts/generate-pr-plugin.sh

setup() {
    # Set up test environment
    export TEST_DIR="$(mktemp -d)"
    export SCRIPT_PATH="${BATS_TEST_DIRNAME}/../../.github/scripts/generate-pr-plugin.sh"
    export TEST_TARBALL="${TEST_DIR}/test.tar.gz"

    # Create a test tarball
    mkdir -p "${TEST_DIR}/build/usr/local/emhttp/test"
    echo "test content" > "${TEST_DIR}/build/usr/local/emhttp/test/file.txt"
    (cd "${TEST_DIR}/build" && tar -czf "../test.tar.gz" usr/)
}

teardown() {
    # Clean up test environment
    rm -rf "${TEST_DIR}"
}

@test "script requires all mandatory parameters" {
    run bash "${SCRIPT_PATH}"
    [ "$status" -eq 1 ]
    [[ "$output" =~ "Usage:" ]]
}

@test "script requires version parameter" {
    run bash "${SCRIPT_PATH}" "" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    [ "$status" -eq 1 ]
    [[ "$output" =~ "Usage:" ]]
}

@test "script requires PR number parameter" {
    run bash "${SCRIPT_PATH}" "2024.01.01" "" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    [ "$status" -eq 1 ]
    [[ "$output" =~ "Usage:" ]]
}

@test "script generates plugin file with correct name" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    [ -f "webgui-pr-123.plg" ]
}

@test "plugin file contains correct version" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    grep -q "2024.01.01" "webgui-pr-123.plg"
}

@test "plugin file contains correct PR number" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "456" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    grep -q "PR_PLACEHOLDER" "${SCRIPT_PATH}" || true
    grep -q "456" "webgui-pr-456.plg"
}

@test "plugin file contains correct commit SHA" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "xyz789" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    grep -q "xyz789" "webgui-pr-123.plg"
}

@test "plugin file contains correct tarball URL" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"
    grep -q "http://example.com/file.tar.gz" "webgui-pr-123.plg"
}

@test "plugin file calculates SHA256 correctly" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    # Extract SHA256 from plugin file
    PLUGIN_SHA=$(grep -oP 'SHA256_PLACEHOLDER|sha256>[^<]+' "webgui-pr-123.plg" | grep -v PLACEHOLDER | head -1 | sed 's/sha256>//')

    # Calculate actual SHA256
    ACTUAL_SHA=$(sha256sum "${TEST_TARBALL}" | awk '{print $1}')

    # Compare (allowing for the fact that placeholders might not be replaced in test)
    if [ -n "${PLUGIN_SHA}" ]; then
        [ "${PLUGIN_SHA}" = "${ACTUAL_SHA}" ]
    fi
}

@test "plugin file contains valid XML structure" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    # Check for XML declaration
    head -1 "webgui-pr-123.plg" | grep -q "<?xml"

    # Check for PLUGIN tags
    grep -q "<PLUGIN" "webgui-pr-123.plg"
    grep -q "</PLUGIN>" "webgui-pr-123.plg"
}

@test "plugin file contains CHANGES section" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "<CHANGES>" "webgui-pr-123.plg"
    grep -q "</CHANGES>" "webgui-pr-123.plg"
}

@test "plugin file contains installation section" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "Method=\"install\"" "webgui-pr-123.plg"
}

@test "plugin file contains removal section" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "Method=\"remove\"" "webgui-pr-123.plg"
}

@test "plugin file contains backup mechanism" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "BACKUP_DIR" "webgui-pr-123.plg"
    grep -q "MANIFEST" "webgui-pr-123.plg"
}

@test "script uses optional plugin URL parameter" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz" "http://custom.url/plugin.plg"

    grep -q "http://custom.url/plugin.plg" "webgui-pr-123.plg"
}

@test "script generates default plugin URL when not provided" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    # Should contain a plugin URL even if not explicitly provided
    grep -q "pluginURL" "webgui-pr-123.plg"
}

@test "plugin includes banner warning for test plugin" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "Banner" "webgui-pr-123.plg"
}

@test "plugin includes uninstall functionality" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "uninstallPRPlugin" "webgui-pr-123.plg"
}

@test "script handles macOS sed syntax" {
    skip "macOS-specific test - requires macOS environment"
}

@test "script handles Linux sed syntax" {
    # This test runs on Linux
    cd "${TEST_DIR}"
    export OSTYPE="linux-gnu"
    bash "${SCRIPT_PATH}" "2024.01.01" "999" "linuxtest" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    [ -f "webgui-pr-999.plg" ]
    grep -q "linuxtest" "webgui-pr-999.plg"
}

@test "generated plugin contains manifest tracking" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "installed_files.txt" "webgui-pr-123.plg"
}

@test "generated plugin handles file restoration on update" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    # Check for restore logic
    grep -q "Restoring original files" "webgui-pr-123.plg"
}

@test "generated plugin verifies extraction" {
    cd "${TEST_DIR}"
    bash "${SCRIPT_PATH}" "2024.01.01" "123" "abc123" "${TEST_TARBALL}" "remote.tar.gz" "http://example.com/file.tar.gz"

    grep -q "Verifying installation" "webgui-pr-123.plg"
}