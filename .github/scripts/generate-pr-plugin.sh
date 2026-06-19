#!/bin/bash
set -euo pipefail
IFS=$'\n\t'

# Generate PR plugin file for Unraid
# Usage: ./generate-pr-plugin.sh <version> <pr_number> <commit_sha> <local_tarball> <remote_tarball> <txz_url> [plugin_url]
#
# The tarball payload contains:
#   pr.patch          unified diff of all changed TEXT files (system-relative paths, apply with patch -p1 at /)
#   binary/<path>     whole copies of changed BINARY files (cannot be diffed)
#   binary_files.txt  list of binary paths (system-relative)
#
# Install applies pr.patch with `patch`, so several PR plugins can stack on the
# same file as long as their hunks do not overlap. Binaries fall back to a
# whole-file replace, guarded so two plugins never fight over the same binary.

VERSION=$1
PR_NUMBER=$2
COMMIT_SHA=$3
LOCAL_TARBALL=$4  # Local file for SHA calculation
REMOTE_TARBALL=$5  # Remote filename for download
TXZ_URL=$6
PLUGIN_URL=${7:-""}  # Optional plugin URL for updates

if [ -z "$VERSION" ] || [ -z "$PR_NUMBER" ] || [ -z "$COMMIT_SHA" ] || [ -z "$LOCAL_TARBALL" ] || [ -z "$REMOTE_TARBALL" ] || [ -z "$TXZ_URL" ]; then
    echo "Usage: $0 <version> <pr_number> <commit_sha> <local_tarball> <remote_tarball> <txz_url> [plugin_url]"
    exit 1
fi

# If no plugin URL provided, generate one based on R2 location
if [ -z "$PLUGIN_URL" ]; then
    # Extract base URL from TXZ_URL and use consistent filename
    PLUGIN_URL="${TXZ_URL%.tar.gz}.plg"
fi

# Use consistent filename (no version in filename, version is inside the plugin)
PLUGIN_NAME="webgui-pr-${PR_NUMBER}.plg"
TARBALL_SHA256=$(sha256sum "$LOCAL_TARBALL" | awk '{print $1}')

echo "Generating plugin: $PLUGIN_NAME"
echo "Tarball SHA256: $TARBALL_SHA256"

cat > "$PLUGIN_NAME" << 'EOF'
<?xml version='1.0' standalone='yes'?>
<!DOCTYPE PLUGIN [
  <!ENTITY name "webgui-pr-PR_PLACEHOLDER">
  <!ENTITY version "VERSION_PLACEHOLDER">
  <!ENTITY author "unraid">
  <!ENTITY pluginURL "PLUGIN_URL_PLACEHOLDER">
  <!ENTITY tarball "REMOTE_TARBALL_PLACEHOLDER">
  <!ENTITY sha256 "SHA256_PLACEHOLDER">
  <!ENTITY pr "PR_PLACEHOLDER">
  <!ENTITY commit "COMMIT_PLACEHOLDER">
  <!ENTITY github "https://github.com/unraid/webgui">
]>

<PLUGIN name="&name;-&version;"
        author="&author;"
        version="&version;"
        pluginURL="&pluginURL;"
        min="7.1.0"
        icon="wrench"
        support="&github;/pull/&pr;">

<CHANGES>
##&version;
- Test build for PR #&pr; (commit &commit;)
- Applies the PR's changes as a patch, so multiple PR test plugins can be
  installed together as long as they do not edit the same lines
- Changes are reversed when the plugin is removed
</CHANGES>

<!-- FILE sections run in the listed order -->

<!-- If this is an update: reverse the previously applied patch / restore binaries first -->
<FILE Run="/bin/bash" Method="install">
<INLINE>
<![CDATA[
echo "===================================="
echo "WebGUI PR Test Plugin Update"
echo "===================================="
echo "Version: VERSION_PLACEHOLDER"
echo ""

PLUGIN_DIR="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER"
BACKUP_DIR="$PLUGIN_DIR/backups"
MANIFEST="$PLUGIN_DIR/installed_files.txt"
PATCH_APPLIED="$PLUGIN_DIR/applied.patch"
PAYLOAD="$PLUGIN_DIR/payload"

mkdir -p "$PLUGIN_DIR" "$BACKUP_DIR"

# Roll back THIS plugin's previous text changes by restoring the shipped
# originals, then re-applying the other still-installed PR plugins so their
# changes survive. (Deterministic: does not depend on reverse-patch context.)
if [ -f "$PLUGIN_DIR/text_files.txt" ]; then
    echo "Rolling back previous text changes..."
    while IFS= read -r sys; do
        [ -n "$sys" ] || continue
        SYS="/$sys"
        if [ -f "$PLUGIN_DIR/orig/$sys" ]; then
            mkdir -p "$(dirname "$SYS")"; cp -f "$PLUGIN_DIR/orig/$sys" "$SYS"
        else
            rm -f "$SYS"   # file was newly added by this PR
        fi
    done < "$PLUGIN_DIR/text_files.txt"
    for other in /boot/config/plugins/webgui-pr-*; do
        [ -d "$other" ] || continue
        [ "$other" == "$PLUGIN_DIR" ] && continue
        [ -f "$other/applied.patch" ] || continue
        # Non-blocking: all patches passed dry-run at install time, so failure here
        # is a rare edge case worth surfacing rather than swallowing.
        if ! patch -p1 -d / --forward --batch < "$other/applied.patch" >/dev/null 2>&1; then
            echo "⚠️  Warning: could not re-apply $(basename "$other")'s changes; reinstall it or reboot to restore them"
            logger -t webgui-pr "re-apply of $(basename "$other") patch failed during $(basename "$PLUGIN_DIR") rollback"
        fi
    done
fi
rm -f "$PATCH_APPLIED"
rm -rf "$PLUGIN_DIR/orig"
: > "$PLUGIN_DIR/text_files.txt"

# Restore any previously installed binary files
if [ -f "$MANIFEST" ]; then
    echo "Restoring binary files from previous version..."
    while IFS='|' read -r system_file backup_file; do
        [ -n "$system_file" ] || continue
        if [ "$backup_file" == "NEW" ]; then
            [ -e "$system_file" ] && { echo "Removing PR file: $system_file"; rm -f "$system_file"; }
        elif [ -f "$backup_file" ]; then
            echo "Restoring original: $system_file"
            cp -fp "$backup_file" "$system_file"
        fi
    done < "$MANIFEST"
fi

: > "$MANIFEST"
rm -rf "$PAYLOAD"
echo "Ready for fresh install of PR files."
]]>
</INLINE>
</FILE>

<!-- Create plugin directories -->
<FILE Run="/bin/bash" Method="install">
<INLINE>
<![CDATA[
echo "===================================="
echo "WebGUI PR Test Plugin Installation"
echo "===================================="
echo "Version: VERSION_PLACEHOLDER"
echo "PR: #PR_PLACEHOLDER"
echo "Commit: COMMIT_PLACEHOLDER"
echo ""
mkdir -p /boot/config/plugins/webgui-pr-PR_PLACEHOLDER/backups
echo "Created plugin directories"
]]>
</INLINE>
</FILE>

<!-- Download tarball from GitHub/R2 -->
<FILE Name="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/REMOTE_TARBALL_PLACEHOLDER">
<URL>TXZ_URL_PLACEHOLDER</URL>
<SHA256>&sha256;</SHA256>
</FILE>

<!-- Apply patch + install binaries -->
<FILE Run="/bin/bash" Method="install">
<INLINE>
<![CDATA[
PLUGIN_DIR="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER"
BACKUP_DIR="$PLUGIN_DIR/backups"
MANIFEST="$PLUGIN_DIR/installed_files.txt"
PATCH_APPLIED="$PLUGIN_DIR/applied.patch"
TARBALL="$PLUGIN_DIR/REMOTE_TARBALL_PLACEHOLDER"
PAYLOAD="$PLUGIN_DIR/payload"

echo "Extracting payload..."
rm -rf "$PAYLOAD"
mkdir -p "$PAYLOAD"
tar -xzf "$TARBALL" -C "$PAYLOAD"
: > "$MANIFEST"

# ---- Text changes: apply unified diff -------------------------------------
if [ -s "$PAYLOAD/pr.patch" ]; then
    echo "Checking patch applies cleanly..."
    if ! patch -p1 -d / --dry-run --forward --batch < "$PAYLOAD/pr.patch" > /tmp/pr_patch_check.txt 2>&1; then
        echo ""
        echo "❌ Install aborted: this PR's changes do not apply cleanly."
        echo "   Another installed PR plugin likely edits the same lines."
        echo "------------------------------------------------------------"
        grep -Ei 'FAILED|can.t find file|hunk' /tmp/pr_patch_check.txt || cat /tmp/pr_patch_check.txt
        echo "------------------------------------------------------------"
        echo "Remove the conflicting webgui-pr-* plugin and try again."
        rm -f /tmp/pr_patch_check.txt
        exit 1
    fi
    rm -f /tmp/pr_patch_check.txt
    echo "Applying patch..."
    patch -p1 -d / --forward --batch < "$PAYLOAD/pr.patch"
    cp -f "$PAYLOAD/pr.patch" "$PATCH_APPLIED"
    # Persist the originals + file list so removal can rebuild deterministically
    rm -rf "$PLUGIN_DIR/orig"
    [ -d "$PAYLOAD/orig" ] && cp -a "$PAYLOAD/orig" "$PLUGIN_DIR/orig"
    [ -f "$PAYLOAD/text_files.txt" ] && cp -f "$PAYLOAD/text_files.txt" "$PLUGIN_DIR/text_files.txt"
    echo "✅ Patch applied"
fi

# ---- Binary changes: whole-file replace (cannot be merged) -----------------
if [ -s "$PAYLOAD/binary_files.txt" ]; then
    # Guard: a binary may only be managed by one PR plugin at a time
    while IFS= read -r sys; do
        [ -n "$sys" ] || continue
        for other in /boot/config/plugins/webgui-pr-*; do
            [ -d "$other" ] || continue
            [ "$other" == "$PLUGIN_DIR" ] && continue
            if [ -f "$other/installed_files.txt" ] && grep -q "^/$sys|" "$other/installed_files.txt"; then
                echo "❌ Install aborted: binary /$sys is already managed by $(basename "$other")."
                echo "   Remove that plugin first: plugin remove $(basename "$other").plg"
                exit 1
            fi
        done
    done < "$PAYLOAD/binary_files.txt"

    while IFS= read -r sys; do
        [ -n "$sys" ] || continue
        SYS="/$sys"
        SRC="$PAYLOAD/binary/$sys"
        BK="$BACKUP_DIR/$(echo "$sys" | tr '/' '_')"
        mkdir -p "$(dirname "$SYS")"
        if [ -f "$SYS" ] && [ ! -f "$BK" ]; then cp -p "$SYS" "$BK"; fi
        if [ -f "$BK" ]; then echo "$SYS|$BK" >> "$MANIFEST"; else echo "$SYS|NEW" >> "$MANIFEST"; fi
        cp -f "$SRC" "$SYS"
        echo "Installed binary: $SYS"
    done < "$PAYLOAD/binary_files.txt"
fi

echo ""
echo "✅ Installation complete for PR #PR_PLACEHOLDER"
echo "⚠️  This is a TEST plugin — remove it before applying production updates"
echo "⚠️  A reboot may be required for some changes to take effect"
]]>
</INLINE>
</FILE>

<!-- Add a banner to warn user this plugin is installed -->
<FILE Name="/usr/local/emhttp/plugins/webgui-pr-PR_PLACEHOLDER/Banner-PR_PLACEHOLDER.page">
<INLINE>
<![CDATA[
Menu='Buttons'
Link='nav-user'
---
<script>
  $(function() {
    // Check for updates (non-dismissible)
    caPluginUpdateCheck("webgui-pr-PR_PLACEHOLDER.plg", {noDismiss: true},function(result){
      try {
        let json = JSON.parse(result);
        if ( ! json.version ) {
          addBannerWarning("Note: webgui-pr-PR_PLACEHOLDER has either been merged or removed");
        }
      } catch(e) {}
    });

    // Create banner with uninstall link (nondismissible)
    let bannerMessage = "Modified GUI installed via <b>webgui-pr-PR_PLACEHOLDER</b> plugin. " +
                       "<a onclick='uninstallPRPlugin()' style='cursor: pointer; text-decoration: underline;'>Click here to uninstall</a>";

    // true = warning style, true = non-dismissible
    addBannerWarning(bannerMessage, true, true);

    // Define uninstall function
    window.uninstallPRPlugin = function() {
      swal({
        title: "Uninstall PR Test Plugin?",
        text: "This will reverse all of this PR's changes and remove the test plugin.",
        type: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, uninstall",
        cancelButtonText: "Cancel",
        closeOnConfirm: false,
        showLoaderOnConfirm: true
      }, function(isConfirm) {
        if (isConfirm) {
          openPlugin("plugin remove webgui-pr-PR_PLACEHOLDER.plg", "Removing PR Test Plugin", "", "refresh");
        }
      });
    };
  });
</script>
]]>
</INLINE>
</FILE>

<!-- Removal script -->
<FILE Run="/bin/bash" Method="remove">
<INLINE>
<![CDATA[
echo "===================================="
echo "WebGUI PR Test Plugin Removal"
echo "===================================="
echo ""

PLUGIN_DIR="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER"
MANIFEST="$PLUGIN_DIR/installed_files.txt"
PATCH_APPLIED="$PLUGIN_DIR/applied.patch"

# Restore this plugin's text files to their shipped originals, then re-apply the
# other still-installed PR plugins so their (non-overlapping) changes survive.
if [ -f "$PLUGIN_DIR/text_files.txt" ]; then
    echo "Restoring text files..."
    while IFS= read -r sys; do
        [ -n "$sys" ] || continue
        SYS="/$sys"
        if [ -f "$PLUGIN_DIR/orig/$sys" ]; then
            mkdir -p "$(dirname "$SYS")"; cp -f "$PLUGIN_DIR/orig/$sys" "$SYS"
        else
            rm -f "$SYS"   # file was newly added by this PR
        fi
    done < "$PLUGIN_DIR/text_files.txt"
    for other in /boot/config/plugins/webgui-pr-*; do
        [ -d "$other" ] || continue
        [ "$other" == "$PLUGIN_DIR" ] && continue
        [ -f "$other/applied.patch" ] || continue
        # Non-blocking: all patches passed dry-run at install time, so failure here
        # is a rare edge case worth surfacing rather than swallowing.
        if ! patch -p1 -d / --forward --batch < "$other/applied.patch" >/dev/null 2>&1; then
            echo "⚠️  Warning: could not re-apply $(basename "$other")'s changes; reinstall it or reboot to restore them"
            logger -t webgui-pr "re-apply of $(basename "$other") patch failed during $(basename "$PLUGIN_DIR") rollback"
        fi
    done
    echo "✅ Text changes restored"
fi

# Restore binary files
if [ -f "$MANIFEST" ]; then
    echo "Restoring binary files..."
    while IFS='|' read -r system_file backup_file; do
        [ -n "$system_file" ] || continue
        if [ "$backup_file" == "NEW" ]; then
            [ -e "$system_file" ] && { echo "Removing new file: $system_file"; rm -f "$system_file"; }
        elif [ -f "$backup_file" ]; then
            echo "Restoring: $system_file"
            cp -fp "$backup_file" "$system_file"
        else
            echo "⚠️  Missing backup for: $system_file"
        fi
    done < "$MANIFEST"
fi

echo "Cleaning up plugin files..."
rm -rf "/usr/local/emhttp/plugins/webgui-pr-PR_PLACEHOLDER"
rm -rf "$PLUGIN_DIR"

echo ""
echo "✅ Plugin removed successfully"
echo "⚠️  A reboot may be required to fully clear all changes"
]]>
</INLINE>
</FILE>

</PLUGIN>
EOF

# Replace placeholders (compatible with both Linux and macOS)
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS requires backup extension with -i
    sed -i '' "s/VERSION_PLACEHOLDER/${VERSION}/g" "$PLUGIN_NAME"
    sed -i '' "s/REMOTE_TARBALL_PLACEHOLDER/${REMOTE_TARBALL}/g" "$PLUGIN_NAME"
    sed -i '' "s/SHA256_PLACEHOLDER/${TARBALL_SHA256}/g" "$PLUGIN_NAME"
    sed -i '' "s/PR_PLACEHOLDER/${PR_NUMBER}/g" "$PLUGIN_NAME"
    sed -i '' "s/COMMIT_PLACEHOLDER/${COMMIT_SHA}/g" "$PLUGIN_NAME"
    sed -i '' "s|TXZ_URL_PLACEHOLDER|${TXZ_URL}|g" "$PLUGIN_NAME"
    sed -i '' "s|PLUGIN_URL_PLACEHOLDER|${PLUGIN_URL}|g" "$PLUGIN_NAME"
else
    # Linux sed
    sed -i "s/VERSION_PLACEHOLDER/${VERSION}/g" "$PLUGIN_NAME"
    sed -i "s/REMOTE_TARBALL_PLACEHOLDER/${REMOTE_TARBALL}/g" "$PLUGIN_NAME"
    sed -i "s/SHA256_PLACEHOLDER/${TARBALL_SHA256}/g" "$PLUGIN_NAME"
    sed -i "s/PR_PLACEHOLDER/${PR_NUMBER}/g" "$PLUGIN_NAME"
    sed -i "s/COMMIT_PLACEHOLDER/${COMMIT_SHA}/g" "$PLUGIN_NAME"
    sed -i "s|TXZ_URL_PLACEHOLDER|${TXZ_URL}|g" "$PLUGIN_NAME"
    sed -i "s|PLUGIN_URL_PLACEHOLDER|${PLUGIN_URL}|g" "$PLUGIN_NAME"
fi

echo "Plugin generated: $PLUGIN_NAME"
