#!/bin/bash
set -euo pipefail
IFS=$'\n\t'

# Generate PR plugin file for Unraid
# Usage: ./generate-pr-plugin.sh <version> <pr_number> <commit_sha> <local_tarball> <remote_tarball> <txz_url> [plugin_url]

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
- This plugin installs modified files from the PR for testing
- Original files are backed up and restored upon removal
</CHANGES>

<!-- FILE sections run in the listed order - Check if this is an update prior to installing -->

<!-- If this is an update - restore originals before installing the update -->
<FILE Run="/bin/bash" Method="install">
<INLINE>
<![CDATA[
echo "===================================="
echo "WebGUI PR Test Plugin Update"
echo "===================================="
echo "Version: VERSION_PLACEHOLDER"
echo ""

BACKUP_DIR="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/backups"
MANIFEST="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/installed_files.txt"

# Ensure required directories exist even on first-time install/update
mkdir -p "/boot/config/plugins/webgui-pr-PR_PLACEHOLDER" "$BACKUP_DIR"

# First restore original files to ensure clean state
if [ -f "$MANIFEST" ]; then
    echo "Step 1: Restoring original files before update..."
    echo "------------------------------------------------"
    
    while IFS='|' read -r system_file backup_file; do
        if [ "$backup_file" == "NEW" ]; then
            # This was a new file from previous version, remove it
            if [ -f "$system_file" ]; then
                echo "Removing PR file: $system_file"
                rm -f "$system_file"
            fi
        else
            # Restore original from backup
            if [ -f "$backup_file" ]; then
                echo "Restoring original: $system_file"
                cp -fp "$backup_file" "$system_file"
            fi
        fi
    done < "$MANIFEST"
    
    echo ""
    echo "✅ Original files restored"
    echo ""
else
    echo "⚠️  No previous manifest found, proceeding with fresh install"
    echo ""
fi

# Clear the old manifest for the new version (dir now guaranteed)
> "$MANIFEST"

echo "Step 2: Update will now proceed with installation of new PR files..."
echo ""

# The update continues by running the install method which will extract new files
]]>
</INLINE>
</FILE>
<!-- Create backup directory -->
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

# Create directories
mkdir -p /boot/config/plugins/webgui-pr-PR_PLACEHOLDER
mkdir -p /boot/config/plugins/webgui-pr-PR_PLACEHOLDER/backups

echo "Created plugin directories"
]]>
</INLINE>
</FILE>

<!-- Download tarball from GitHub -->
<FILE Name="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/REMOTE_TARBALL_PLACEHOLDER">
<URL>TXZ_URL_PLACEHOLDER</URL>
<SHA256>&sha256;</SHA256>
</FILE>

<!-- Backup and install files -->
<FILE Run="/bin/bash" Method="install">
<INLINE>
<![CDATA[
BACKUP_DIR="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/backups"
TARBALL="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/REMOTE_TARBALL_PLACEHOLDER"
MANIFEST="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/installed_files.txt"

echo "Starting file deployment..."
echo "Tarball: $TARBALL"
echo "Backup directory: $BACKUP_DIR"

# Clear manifest
> "$MANIFEST"

# Get file list first
echo "Examining tarball contents..."
tar -tzf "$TARBALL" | head -20
echo ""

# Count total files
FILE_COUNT=$(tar -tzf "$TARBALL" | grep -v '/$' | wc -l)
echo "Total files to process: $FILE_COUNT"
echo ""

# Get file list
tar -tzf "$TARBALL" > /tmp/plugin_files.txt

# Backup original files BEFORE extraction
while IFS= read -r file; do
    # Skip directories
    if [[ "$file" == */ ]]; then
        continue
    fi
    
    # The tarball contains usr/local/emhttp/... (no leading slash)
    # When we extract with -C /, it becomes /usr/local/emhttp/...
    SYSTEM_FILE="/${file}"
    BACKUP_FILE="$BACKUP_DIR/$(echo "$file" | tr '/' '_')"
    
    echo "Processing: $file"
    
    # Only backup if we haven't already backed up this file
    # (preserves original backups across updates)
    if [ -f "$BACKUP_FILE" ]; then
        echo "  → Using existing backup: $BACKUP_FILE"
        echo "$SYSTEM_FILE|$BACKUP_FILE" >> "$MANIFEST"
    elif [ -f "$SYSTEM_FILE" ]; then
        echo "  → Creating backup of original: $SYSTEM_FILE"
        cp -p "$SYSTEM_FILE" "$BACKUP_FILE"
        echo "$SYSTEM_FILE|$BACKUP_FILE" >> "$MANIFEST"
    else
        echo "  → Will create new: $SYSTEM_FILE"
        echo "$SYSTEM_FILE|NEW" >> "$MANIFEST"
    fi
done < /tmp/plugin_files.txt

# Clean up temp file
rm -f /tmp/plugin_files.txt

# Extract the tarball to root with verbose output
# Since tarball contains usr/local/emhttp/..., extracting to / makes it /usr/local/emhttp/...
echo ""
echo "Extracting files to system (verbose mode)..."
echo "----------------------------------------"
tar -xzvf "$TARBALL" -C /
EXTRACT_STATUS=$?
echo "----------------------------------------"
echo "Extraction completed with status: $EXTRACT_STATUS"
echo ""

# Verify extraction
echo "Verifying installation..."
INSTALLED_COUNT=0
while IFS='|' read -r file backup; do
    if [ -f "$file" ]; then
        INSTALLED_COUNT=$((INSTALLED_COUNT + 1))
    fi
done < "$MANIFEST"

echo "Successfully installed $INSTALLED_COUNT files"

echo ""
echo "✅ Installation complete!"
echo ""
echo "Summary:"
echo "--------"
echo "Files deployed: $INSTALLED_COUNT"
echo ""
if [ $INSTALLED_COUNT -gt 0 ]; then
    echo "Modified files:"
    while IFS='|' read -r file backup; do
        if [ -f "$file" ]; then
            if [ "$backup" == "NEW" ]; then
                echo "  [NEW] $file"
            else
                echo "  [MOD] $file"
            fi
        fi
    done < "$MANIFEST"
else
    echo "⚠️  WARNING: No files were installed!"
    echo "Check that the tarball structure matches the expected format."
fi

echo ""
echo "⚠️  This is a TEST plugin for PR #PR_PLACEHOLDER"
echo "⚠️  Remove this plugin before applying production updates"
echo ""
echo "⚠️  NOTE: Under certain circumstances, it might be necessary to reboot the server for the code changes to take effect"
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
        text: "This will restore all original files and remove the test plugin.",
        type: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, uninstall",
        cancelButtonText: "Cancel",
        closeOnConfirm: false,
        showLoaderOnConfirm: true
      }, function(isConfirm) {
        if (isConfirm) {
          // Execute plugin removal using openPlugin (Unraid's standard method)
          // The "refresh" parameter will automatically reload the page when uninstall is completed
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

BACKUP_DIR="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/backups"
MANIFEST="/boot/config/plugins/webgui-pr-PR_PLACEHOLDER/installed_files.txt"

if [ -f "$MANIFEST" ]; then
    echo "Restoring original files..."
    
    while IFS='|' read -r system_file backup_file; do
        if [ "$backup_file" == "NEW" ]; then
            # This was a new file, remove it
            if [ -f "$system_file" ]; then
                echo "Removing new file: $system_file"
                rm -f "$system_file"
            fi
        else
            # Restore from backup
            if [ -f "$backup_file" ]; then
                echo "Restoring: $system_file"
                mv -f "$backup_file" "$system_file"
            fi
        fi
    done < "$MANIFEST"
    
    echo ""
    echo "✅ Original files restored"
else
    echo "⚠️  No manifest found, cannot restore files"
fi

# Clean up
echo "Cleaning up plugin files..."
# Remove the banner
rm -rf "/usr/local/emhttp/plugins/webgui-pr-PR_PLACEHOLDER"
# Remove the plugin directory (which includes the tarball and backups)
rm -rf "/boot/config/plugins/webgui-pr-PR_PLACEHOLDER"
# Note: The .plg file is handled automatically by the plugin system and should not be removed here

echo ""
echo "✅ Plugin removed successfully"
echo ""
echo "⚠️  A reboot of the server might be required under certain circumstances to completely remove all traces of this plugin"
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
