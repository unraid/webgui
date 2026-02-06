#!/bin/bash

# Boot Parameters Management Script
# Handles reading, writing, and managing syslinux.cfg boot parameters

set -e

# Configuration (can be overridden via environment for testing)
SYSLINUX_CFG="${SYSLINUX_CFG:-/boot/syslinux/syslinux.cfg}"
GRUB_CFG="${GRUB_CFG:-/boot/grub/grub.cfg}"
BACKUP_DIR="${BACKUP_DIR:-/boot/syslinux/backups}"
GRUB_BACKUP_DIR="${GRUB_BACKUP_DIR:-/boot/grub/backups}"
COMMENTS_FILE="${COMMENTS_FILE:-/boot/config/custom_params_comments.json}"
MAX_BACKUPS=10

# Error handling
error_exit() {
    echo "Error: $1" >&2
    exit 1
}

# Validate environment
if [[ -z "$OPERATION" ]]; then
    error_exit "No operation specified"
fi

detect_bootloader() {
    if [[ -f "$GRUB_CFG" ]]; then
        echo "grub"
        return 0
    fi
    if [[ -f "$SYSLINUX_CFG" ]]; then
        echo "syslinux"
        return 0
    fi
    error_exit "No supported bootloader config found"
}

BOOTLOADER_TYPE="${BOOTLOADER_TYPE:-$(detect_bootloader)}"

#############################################
# JSON Escaping Function
#############################################

# Escape special characters for safe JSON output (pure Bash, no dependencies)
# Prevents JSON injection when custom parameters contain quotes, backslashes, etc.
escape_json_string() {
    local str="$1"
    # Escape backslashes first (MUST be first to avoid double-escaping!)
    str="${str//\\/\\\\}"
    # Escape double quotes
    str="${str//\"/\\\"}"
    # Escape newlines (shouldn't exist in our use case, but be defensive)
    str="${str//$'\n'/\\n}"
    # Escape tabs
    str="${str//$'\t'/\\t}"
    # Escape carriage returns
    str="${str//$'\r'/\\r}"
    echo "$str"
}

#############################################
# JSON Comment Management Functions
#############################################

# Load comments from JSON file
# Returns the custom_params_comments object from the JSON file
load_comments() {
    if [[ -f "$COMMENTS_FILE" ]]; then
        local content=$(cat "$COMMENTS_FILE")

        # Handle legacy empty array format by converting to empty object
        if [[ "$content" == "[]" ]] || [[ "$content" == "[ ]" ]]; then
            echo "{}"
            return
        fi

        # Extract only the custom_params_comments field using grep and sed
        # Pattern: "custom_params_comments":{...} or "custom_params_comments":{}
        local comments_only=$(echo "$content" | grep -o '"custom_params_comments":{[^}]*}' | sed 's/"custom_params_comments"://')

        # If extraction failed or returned empty, return empty object
        if [[ -z "$comments_only" ]]; then
            echo "{}"
        else
            echo "$comments_only"
        fi
    else
        echo "{}"
    fi
}

# Writes custom parameter comments to JSON file while preserving hardware_tracking data
# Input: custom_params_comments JSON object
save_comments() {
    local comments_json="$1"

    # Ensure comments are stored as object, not array
    if [[ "$comments_json" == "[]" ]] || [[ "$comments_json" == "[ ]" ]]; then
        comments_json="{}"
    fi

    # Preserve existing hardware_tracking field if file exists
    local hw_tracking=""
    if [[ -f "$COMMENTS_FILE" ]]; then
        local existing_content=$(cat "$COMMENTS_FILE")
        # Extract hardware_tracking field using grep and sed
        # Pattern: "hardware_tracking":{...}
        local extracted_hw=$(echo "$existing_content" | grep -o '"hardware_tracking":{[^}]*}')
        if [[ -n "$extracted_hw" ]]; then
            hw_tracking=",$extracted_hw"  # Keep "hardware_tracking":{...} format with leading comma
        fi
    fi

    # Build complete JSON structure with both fields
    local complete_json="{\"custom_params_comments\":${comments_json}${hw_tracking}}"

    # Create directory if it doesn't exist
    mkdir -p "$(dirname "$COMMENTS_FILE")" 2>/dev/null || true

    # Write complete JSON to file
    if echo "$complete_json" > "$COMMENTS_FILE" 2>/dev/null; then
        true  # Success
    else
        # Write failed - try with explicit permissions
        echo "$complete_json" > "$COMMENTS_FILE" 2>&1 || error_exit "Failed to write comments file: $COMMENTS_FILE (Permission denied)"
    fi

    # Ensure file has correct permissions for future writes
    chmod 644 "$COMMENTS_FILE" 2>/dev/null || true
}

#############################################
# Hardware Tracking Functions
#############################################

# Get current hardware IDs using dmidecode
# Returns JSON object with CPU, motherboard, and BIOS identifiers
get_hardware_ids() {
    local cpu=$(dmidecode -s processor-version 2>/dev/null | head -n1 | tr -d '\n')
    local motherboard=$(dmidecode -s baseboard-serial-number 2>/dev/null | head -n1 | tr -d '\n')
    local bios=$(dmidecode -s bios-version 2>/dev/null | head -n1 | tr -d '\n')

    # Handle empty values (some systems may not provide all info)
    [[ -z "$cpu" ]] && cpu="unknown"
    [[ -z "$motherboard" ]] && motherboard="unknown"
    [[ -z "$bios" ]] && bios="unknown"

    # Escape quotes for JSON safety
    cpu=$(echo "$cpu" | sed 's/"/\\"/g')
    motherboard=$(echo "$motherboard" | sed 's/"/\\"/g')
    bios=$(echo "$bios" | sed 's/"/\\"/g')

    cat <<EOF
{"cpu":"$cpu","motherboard":"$motherboard","bios":"$bios"}
EOF
}

# Load hardware tracking data from JSON file
# Returns hardware_tracking object or empty object if not found
load_hardware_tracking() {
    if [[ -f "$COMMENTS_FILE" ]]; then
        local content=$(cat "$COMMENTS_FILE")
        # Extract hardware_tracking object using grep and sed
        # Pattern: "hardware_tracking":{...}
        echo "$content" | grep -o '"hardware_tracking":{[^}]*}' | sed 's/"hardware_tracking"://' || echo "{}"
    else
        echo "{}"
    fi
}

# Writes hardware tracking data to JSON file while preserving custom parameter comments
save_hardware_tracking() {
    local hw_json="$1"

    # Load existing file if it exists
    if [[ -f "$COMMENTS_FILE" ]]; then
        local existing_content=$(cat "$COMMENTS_FILE")

        # Remove existing hardware_tracking field (if present) using sed
        # Pattern: ,"hardware_tracking":{...} or "hardware_tracking":{...},
        local cleaned_json=$(echo "$existing_content" | sed 's/,"hardware_tracking":{[^}]*}//g' | sed 's/"hardware_tracking":{[^}]*},//g')

        # Remove trailing } to prepare for adding new field
        local temp_json="${cleaned_json%\}}"

        # Add hardware_tracking field
        local merged_json="${temp_json},\"hardware_tracking\":$hw_json}"
    else
        # No existing file, create fresh structure
        local merged_json="{\"custom_params_comments\":{},\"hardware_tracking\":$hw_json}"
    fi

    # Write merged JSON directly to file (already has full structure)
    # Create directory if it doesn't exist
    mkdir -p "$(dirname "$COMMENTS_FILE")" 2>/dev/null || true

    if echo "$merged_json" > "$COMMENTS_FILE" 2>/dev/null; then
        :  # Success
    else
        # Write failed - try with explicit permissions
        echo "$merged_json" > "$COMMENTS_FILE" 2>&1 || error_exit "Failed to write hardware tracking: $COMMENTS_FILE (Permission denied)"
    fi

    # Ensure file has correct permissions for future writes
    chmod 644 "$COMMENTS_FILE" 2>/dev/null || true
}

# Check if hardware has changed
# Returns JSON with changed flag and list of what changed
check_hardware_change() {
    local current_hw=$(get_hardware_ids)
    local stored_hw=$(load_hardware_tracking)


    # First run: no stored hardware
    if [[ "$stored_hw" == "{}" ]]; then
        save_hardware_tracking "$current_hw"
        echo '{"changed":false,"first_run":true}'
        return 0
    fi

    # Extract individual values from JSON using grep/sed
    local current_cpu=$(echo "$current_hw" | grep -o '"cpu":"[^"]*"' | cut -d'"' -f4)
    local current_mb=$(echo "$current_hw" | grep -o '"motherboard":"[^"]*"' | cut -d'"' -f4)
    local current_bios=$(echo "$current_hw" | grep -o '"bios":"[^"]*"' | cut -d'"' -f4)

    local stored_cpu=$(echo "$stored_hw" | grep -o '"cpu":"[^"]*"' | cut -d'"' -f4)
    local stored_mb=$(echo "$stored_hw" | grep -o '"motherboard":"[^"]*"' | cut -d'"' -f4)
    local stored_bios=$(echo "$stored_hw" | grep -o '"bios":"[^"]*"' | cut -d'"' -f4)

    # Compare and build list of changes
    local changes=""
    [[ "$current_cpu" != "$stored_cpu" ]] && changes="${changes}CPU,"
    [[ "$current_mb" != "$stored_mb" ]] && changes="${changes}Motherboard,"
    [[ "$current_bios" != "$stored_bios" ]] && changes="${changes}BIOS,"

    # Remove trailing comma
    changes="${changes%,}"

    if [[ -n "$changes" ]]; then
        echo "{\"changed\":true,\"what_changed\":\"$changes\"}"
    else
        echo "{\"changed\":false}"
    fi
}

# Update stored hardware IDs to current system
update_hardware_tracking() {
    local current_hw=$(get_hardware_ids)
    save_hardware_tracking "$current_hw"
    echo '{"success":true}'
}

#############################################
# Parse append line from a specific label
#############################################
parse_append_line() {
    local label="$1"
    local cfg_file="${2:-$SYSLINUX_CFG}"

    # Extract append line for specific label
    # Matches from "label $label" to the next "label" or end of file
    awk -v label="$label" '
        /^label / {
            if ($0 ~ "^label " label "$") {
                in_section=1
            } else {
                in_section=0
            }
        }
        in_section && /^  append/ {
            sub(/^  append /, "")
            print
            exit
        }
    ' "$cfg_file"
}

#############################################
# Parse GRUB linux args from a specific menuentry
#############################################
parse_grub_linux_args() {
    local label="$1"
    local cfg_file="${2:-$GRUB_CFG}"

    awk -v label="$label" '
        $0 ~ /^menuentry / {
            in_section = (index($0, "\"" label "\"") > 0 || index($0, "\x27" label "\x27") > 0)
        }
        in_section && match($0, /^[ \t]*(linux|linuxefi)[ \t]+([^ \t]+)[ \t]*(.*)$/, m) {
            kernel = m[2]
            args = m[3]
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", args)
            if (args != "") {
                print args
            } else {
                print ""
            }
            exit
        }
    ' "$cfg_file"
}

#############################################
# Extract individual parameters from append line
#############################################
extract_param_value() {
    local append_line="$1"
    local param_name="$2"

    # Extract parameter value (handles both param=value and standalone params)
    echo "$append_line" | grep -o "${param_name}=[^ ]*" | cut -d'=' -f2- || echo ""
}

check_param_exists() {
    local append_line="$1"
    local param_name="$2"

    # Check if parameter exists in append line
    if echo "$append_line" | grep -qw "$param_name"; then
        echo "1"
    else
        echo "0"
    fi
}

#############################################
# Extract timeout from global configuration
#############################################
extract_timeout() {
    local cfg_file="${1:-$SYSLINUX_CFG}"

    if [[ -f "$cfg_file" ]]; then
        grep "^timeout " "$cfg_file" | awk '{print $2}' | head -n 1
    else
        echo ""
    fi
}

#############################################
# Extract GRUB timeout (seconds)
#############################################
extract_grub_timeout() {
    local cfg_file="${1:-$GRUB_CFG}"

    if [[ -f "$cfg_file" ]]; then
        grep "^set timeout=" "$cfg_file" | awk -F'=' '{print $2}' | head -n 1
    else
        echo ""
    fi
}

#############################################
# Read current configuration and output as JSON
#############################################
read_config() {

    local append_line=""
    local timeout=""

    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        if [[ ! -f "$GRUB_CFG" ]]; then
            error_exit "GRUB config file not found: $GRUB_CFG"
        fi
        # Parse linux args from "Unraid OS" menuentry
        append_line=$(parse_grub_linux_args "Unraid OS")
        timeout=$(extract_grub_timeout)
        [[ -z "$timeout" ]] && timeout="5"
        # Convert seconds to deciseconds for UI compatibility
        timeout=$((timeout * 10))
    else
        if [[ ! -f "$SYSLINUX_CFG" ]]; then
            error_exit "Syslinux config file not found: $SYSLINUX_CFG"
        fi
        # Parse append line from "Unraid OS" label
        append_line=$(parse_append_line "Unraid OS")
        timeout=$(extract_timeout)
        [[ -z "$timeout" ]] && timeout="50"
    fi


    # Extract managed parameters
    local nvme_latency=$(extract_param_value "$append_line" "nvme_core.default_ps_max_latency_us")
    local acs_override=$(extract_param_value "$append_line" "pcie_acs_override")
    local vfio_unsafe=$(extract_param_value "$append_line" "vfio_iommu_type1.allow_unsafe_interrupts")
    local acpi_enforce=$(extract_param_value "$append_line" "acpi_enforce_resources")
    local ghes_disable=$(extract_param_value "$append_line" "ghes.disable")
    local usb_autosuspend=$(check_param_exists "$append_line" "usbcore.autosuspend=-1")
    local pcie_aspm=$(extract_param_value "$append_line" "pcie_aspm")
    local pcie_port_pm=$(extract_param_value "$append_line" "pcie_port_pm")

    # Parse PCI options - supports multiple formats:
    # Space-separated: pci=noaer pci=realloc
    # Comma-separated: pci=noaer,realloc
    # Mixed: pci=noaer pci=realloc,assign-busses
    local pci_noaer="0"
    local pci_realloc="0"
    local pci_custom_options=""

    # Extract all pci= parameters from append line
    local pci_params=$(echo "$append_line" | grep -oE 'pci=[^ ]+' || true)

    if [[ -n "$pci_params" ]]; then
        # Process each pci= occurrence
        while IFS= read -r pci_param; do
            [[ -z "$pci_param" ]] && continue

            # Remove 'pci=' prefix
            local options="${pci_param#pci=}"

            # Split by comma and process each option
            IFS=',' read -ra opts <<< "$options"
            for opt in "${opts[@]}"; do
                opt=$(echo "$opt" | xargs)  # Trim whitespace
                case "$opt" in
                    noaer)
                        pci_noaer="1"
                        ;;
                    realloc)
                        pci_realloc="1"
                        ;;
                    *)
                        # PCI option not managed by UI toggles - save for custom params
                        if [[ -z "$pci_custom_options" ]]; then
                            pci_custom_options="$opt"
                        else
                            pci_custom_options="$pci_custom_options,$opt"
                        fi
                        ;;
                esac
            done
        done <<< "$pci_params"
    fi

    # Extract framebuffer-related parameters
    local efifb_off=$(check_param_exists "$append_line" "video=efifb:off")
    local vesafb_off=$(check_param_exists "$append_line" "video=vesafb:off")
    local simplefb_off=$(check_param_exists "$append_line" "video=simplefb:off")
    local sysfb_blacklist=$(check_param_exists "$append_line" "initcall_blacklist=sysfb_init")

    # List of parameters managed by the plugin UI (pci= options are parsed separately)
    local managed_params="nvme_core.default_ps_max_latency_us pcie_acs_override vfio_iommu_type1.allow_unsafe_interrupts acpi_enforce_resources ghes.disable usbcore.autosuspend=-1 pcie_aspm pcie_port_pm pci= video=efifb:off video=vesafb:off video=simplefb:off initcall_blacklist=sysfb_init"

    # Extract custom parameters (everything except initrd and managed params)
    local custom_params=""
    for param in $append_line; do
        # Skip initrd
        if [[ "$param" == initrd=* ]]; then
            continue
        fi

        # Check if it's a managed parameter
        local is_managed=0
        for managed in $managed_params; do
            if [[ "$param" == "$managed"* ]]; then
                is_managed=1
                break
            fi
        done

        # If not managed, add to custom params
        if [[ $is_managed -eq 0 ]]; then
            custom_params="$custom_params $param"
        fi
    done

    custom_params=$(echo "$custom_params" | xargs)  # trim whitespace

    # Add unrecognized PCI options to custom params
    if [[ -n "$pci_custom_options" ]]; then
        if [[ -z "$custom_params" ]]; then
            custom_params="pci=$pci_custom_options"
        else
            custom_params="$custom_params pci=$pci_custom_options"
        fi
    fi

    # Load all comments
    local all_comments=$(load_comments)

    # Output as JSON
    cat <<EOF
{
    "bootloader_type": "$BOOTLOADER_TYPE",
  "nvme_disable": "$([[ "$nvme_latency" == "0" ]] && echo "1" || echo "0")",
  "acs_override": "$acs_override",
  "vfio_unsafe": "$([[ "$vfio_unsafe" == "1" ]] && echo "1" || echo "0")",
  "efifb_off": "$efifb_off",
  "vesafb_off": "$vesafb_off",
  "simplefb_off": "$simplefb_off",
  "sysfb_blacklist": "$sysfb_blacklist",
  "acpi_lax": "$([[ "$acpi_enforce" == "lax" ]] && echo "1" || echo "0")",
  "ghes_disable": "$([[ "$ghes_disable" == "1" ]] && echo "1" || echo "0")",
  "usb_autosuspend": "$usb_autosuspend",
  "pcie_aspm_off": "$([[ "$pcie_aspm" == "off" ]] && echo "1" || echo "0")",
  "pcie_port_pm_off": "$([[ "$pcie_port_pm" == "off" ]] && echo "1" || echo "0")",
  "pci_noaer": "$pci_noaer",
  "pci_realloc": "$pci_realloc",
  "custom_params": "$(escape_json_string "$custom_params")",
  "custom_params_comments": $all_comments,
  "current_config": "$(escape_json_string "$append_line")",
  "current_append_line": "$(escape_json_string "$append_line")",
    "timeout": "$timeout"
}
EOF
}

#############################################
# Validate custom parameter for Syslinux bootloader
# Returns 0 if valid, exits with error if invalid
# Bootloader-specific validation for Syslinux parameters. Future GRUB support will use separate validation function.
#############################################
validate_custom_param() {
    local param="$1"

    # Check 1: Character whitelist validation (defense in depth - matches frontend validation)
    # Only allow characters that appear in valid kernel boot parameters
    # Whitelist: alphanumeric, underscore, hyphen, dot, comma, equals, colon, slash, at-sign
    if ! [[ "$param" =~ ^[a-zA-Z0-9_.,=:/@-]+$ ]]; then
        error_exit "Invalid custom parameter: '$param' contains disallowed characters. Only letters, numbers, and _ - . , = : / @ are allowed."
    fi

    # Check 2: No spaces (multiple parameters) - redundant with whitelist but kept for clarity
    if [[ "$param" =~ [[:space:]] ]]; then
        error_exit "Invalid custom parameter: contains spaces. Only one parameter per entry allowed."
    fi

    # Check 3: Reserved directives (case-insensitive)
    local lower_param=$(echo "$param" | tr '[:upper:]' '[:lower:]')
    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        if [[ "$lower_param" =~ ^(linux|linuxefi|initrd|menuentry|set)($|=) ]]; then
            error_exit "Invalid custom parameter: '$param' is a reserved GRUB directive"
        fi
    else
        if [[ "$lower_param" =~ ^(append|initrd|label|kernel|menu|default|timeout|unraidsafemode)($|=) ]]; then
            error_exit "Invalid custom parameter: '$param' is a reserved syslinux directive"
        fi
    fi

    return 0
}

#############################################
# Build new append line from environment variables
#############################################
# CANONICAL PARAMETER ORDER:
# This order MUST match the frontend buildProposedParams() function exactly
# to prevent spurious diffs when config is read back after writing.
#
# 1. VM Passthrough (pcie_acs_override, vfio_iommu_type1)
# 2. Framebuffers (video=efifb, video=vesafb, video=simplefb, initcall_blacklist=sysfb_init)
# 3. Hardware Compatibility (acpi_enforce_resources, ghes.disable, pci= merged)
# 4. Power Management (usbcore.autosuspend, nvme_core, pcie_aspm, pcie_port_pm)
# 5. Custom Parameters (in array order, excluding pci=)
get_unraiduuid_param() {
    local append_line=""
    if [[ "$BOOTLOADER_TYPE" != "grub" ]]; then
        echo ""
        return 0
    fi
    append_line=$(parse_grub_linux_args "Unraid OS")
    echo "$append_line" | grep -oE 'unraiduuid=[0-9]+' | head -n 1 || true
}

build_append_line() {
    local params="initrd=/bzroot"

    # 1. VM Passthrough
    [[ -n "${ACS_OVERRIDE}" ]] && params="$params pcie_acs_override=${ACS_OVERRIDE}"
    [[ "${VFIO_UNSAFE}" == "1" ]] && params="$params vfio_iommu_type1.allow_unsafe_interrupts=1"

    # 2. Framebuffers
    [[ "${EFIFB_OFF}" == "1" ]] && params="$params video=efifb:off"
    [[ "${VESAFB_OFF}" == "1" ]] && params="$params video=vesafb:off"
    [[ "${SIMPLEFB_OFF}" == "1" ]] && params="$params video=simplefb:off"
    [[ "${SYSFB_BLACKLIST}" == "1" ]] && params="$params initcall_blacklist=sysfb_init"

    # 3. Hardware Compatibility
    [[ "${ACPI_LAX}" == "1" ]] && params="$params acpi_enforce_resources=lax"
    [[ "${GHES_DISABLE}" == "1" ]] && params="$params ghes.disable=1"

    # Merge PCI options from UI toggles and custom parameters
    local pci_options=""

    # Add toggle-controlled options
    [[ "${PCI_NOAER}" == "1" ]] && pci_options="noaer"
    [[ "${PCI_REALLOC}" == "1" ]] && {
        if [[ -z "$pci_options" ]]; then
            pci_options="realloc"
        else
            pci_options="$pci_options,realloc"
        fi
    }

    # Extract pci= options from CUSTOM_PARAMS
    if [[ -n "${CUSTOM_PARAMS}" ]]; then
        local custom_pci=$(echo "${CUSTOM_PARAMS}" | grep -oE 'pci=[^ ]+' || true)
        if [[ -n "$custom_pci" ]]; then
            # Remove 'pci=' prefix
            local custom_opts="${custom_pci#pci=}"
            if [[ -z "$pci_options" ]]; then
                pci_options="$custom_opts"
            else
                pci_options="$pci_options,$custom_opts"
            fi
        fi
    fi

    # Remove duplicate PCI options while preserving order
    if [[ -n "$pci_options" ]]; then
        # Use awk to deduplicate while preserving order
        pci_options=$(echo "$pci_options" | tr ',' '\n' | awk '!seen[tolower($0)]++' | tr '\n' ',' | sed 's/,$//')
        params="$params pci=$pci_options"
    fi

    # 4. Power Management
    [[ "${USB_AUTOSUSPEND}" == "1" ]] && params="$params usbcore.autosuspend=-1"
    [[ "${NVME_DISABLE}" == "1" ]] && params="$params nvme_core.default_ps_max_latency_us=0"
    [[ "${PCIE_ASPM_OFF}" == "1" ]] && params="$params pcie_aspm=off"
    [[ "${PCIE_PORT_PM_OFF}" == "1" ]] && params="$params pcie_port_pm=off"

    # 5. Custom Parameters (PCI options already merged above)
    if [[ -n "${CUSTOM_PARAMS}" ]]; then
        local filtered_custom=$(echo "${CUSTOM_PARAMS}" | sed 's/\bpci=[^ ]*//g' | xargs)
        if [[ -n "$filtered_custom" ]]; then
            # Validate each custom parameter
            for param in $filtered_custom; do
                validate_custom_param "$param"
            done
            params="$params $filtered_custom"
        fi
    fi

    # 6. System-generated parameters (preserve from current config)
    local unraiduuid_param=$(get_unraiduuid_param)
    if [[ -n "$unraiduuid_param" ]] && ! echo "$params" | grep -qw "$unraiduuid_param"; then
        params="$params $unraiduuid_param"
    fi

    echo "$params"
}

#############################################
# Build append line for GUI mode entries (excludes framebuffer parameters)
#############################################
# CANONICAL PARAMETER ORDER (same as build_append_line, but framebuffers excluded):
# 1. VM Passthrough (pcie_acs_override, vfio_iommu_type1)
# 2. Framebuffers (EXCLUDED for GUI mode - ensures display works)
# 3. Hardware Compatibility (acpi_enforce_resources, ghes.disable, pci= merged)
# 4. Power Management (usbcore.autosuspend, nvme_core, pcie_aspm, pcie_port_pm)
# 5. Custom Parameters (in array order, excluding pci=)
build_append_line_gui_safe() {
    local params="initrd=/bzroot"

    # 1. VM Passthrough (safe for GUI mode)
    [[ -n "${ACS_OVERRIDE}" ]] && params="$params pcie_acs_override=${ACS_OVERRIDE}"
    [[ "${VFIO_UNSAFE}" == "1" ]] && params="$params vfio_iommu_type1.allow_unsafe_interrupts=1"

    # 2. Framebuffers - EXCLUDED to ensure GUI display works

    # 3. Hardware Compatibility (safe for GUI mode)
    [[ "${ACPI_LAX}" == "1" ]] && params="$params acpi_enforce_resources=lax"
    [[ "${GHES_DISABLE}" == "1" ]] && params="$params ghes.disable=1"

    # Merge PCI options from UI toggles and custom parameters
    local pci_options=""
    [[ "${PCI_NOAER}" == "1" ]] && pci_options="noaer"
    [[ "${PCI_REALLOC}" == "1" ]] && {
        if [[ -z "$pci_options" ]]; then
            pci_options="realloc"
        else
            pci_options="$pci_options,realloc"
        fi
    }
    if [[ -n "${CUSTOM_PARAMS}" ]]; then
        local custom_pci=$(echo "${CUSTOM_PARAMS}" | grep -oE 'pci=[^ ]+' || true)
        if [[ -n "$custom_pci" ]]; then
            local custom_opts="${custom_pci#pci=}"
            if [[ -z "$pci_options" ]]; then
                pci_options="$custom_opts"
            else
                pci_options="$pci_options,$custom_opts"
            fi
        fi
    fi
    if [[ -n "$pci_options" ]]; then
        pci_options=$(echo "$pci_options" | tr ',' '\n' | awk '!seen[tolower($0)]++' | tr '\n' ',' | sed 's/,$//')
        params="$params pci=$pci_options"
    fi

    # 4. Power Management (safe for GUI mode)
    [[ "${USB_AUTOSUSPEND}" == "1" ]] && params="$params usbcore.autosuspend=-1"
    [[ "${NVME_DISABLE}" == "1" ]] && params="$params nvme_core.default_ps_max_latency_us=0"
    [[ "${PCIE_ASPM_OFF}" == "1" ]] && params="$params pcie_aspm=off"
    [[ "${PCIE_PORT_PM_OFF}" == "1" ]] && params="$params pcie_port_pm=off"

    # 5. Custom Parameters (PCI options already merged above)
    if [[ -n "${CUSTOM_PARAMS}" ]]; then
        local filtered_custom=$(echo "${CUSTOM_PARAMS}" | sed 's/\bpci=[^ ]*//g' | xargs)
        if [[ -n "$filtered_custom" ]]; then
            # Validate each custom parameter
            for param in $filtered_custom; do
                validate_custom_param "$param"
            done
            params="$params $filtered_custom"
        fi
    fi

    # 6. System-generated parameters (preserve from current config)
    local unraiduuid_param=$(get_unraiduuid_param)
    if [[ -n "$unraiduuid_param" ]] && ! echo "$params" | grep -qw "$unraiduuid_param"; then
        params="$params $unraiduuid_param"
    fi

    echo "$params"
}

#############################################
# Build kernel args for GRUB (no initrd=)
#############################################
build_kernel_args() {
    echo "$(build_append_line | sed 's/^initrd=\/bzroot[ ]*//')"
}

build_kernel_args_gui_safe() {
    echo "$(build_append_line_gui_safe | sed 's/^initrd=\/bzroot[ ]*//')"
}

#############################################
# Validate syslinux.cfg structure
#############################################
validate_config() {
    local cfg_file="${1:-$SYSLINUX_CFG}"


    # Check file exists
    if [[ ! -f "$cfg_file" ]]; then
        return 1
    fi

    # Check required labels exist
    if ! grep -q "^label Unraid OS$" "$cfg_file"; then
        return 1
    fi

    if ! grep -q "^label Unraid OS GUI Mode$" "$cfg_file"; then
        return 1
    fi

    if ! grep -q "^label Unraid OS Safe Mode" "$cfg_file"; then
        return 1
    fi

    # Check primary Unraid labels and ensure they carry append/initrd lines
    local append_line=$(parse_append_line "Unraid OS" "$cfg_file")

    if [[ -z "$append_line" ]]; then
        return 1
    fi

    if ! echo "$append_line" | grep -q "initrd="; then
        return 1
    fi

    return 0
}

#############################################
# Update default boot entry
#############################################
update_default_boot() {
    local target_label="$1"
    local temp_file="$2"


    # If no target label specified, skip this operation
    if [[ -z "$target_label" ]]; then
        return 0
    fi

    # First, remove all "menu default" lines
    awk '!/^  menu default$/' "$temp_file" > "${temp_file}.default"
    mv "${temp_file}.default" "$temp_file"

    # Now add "menu default" to the target label
    awk -v label="$target_label" '
        /^label / {
            current_label = $0
            sub(/^label /, "", current_label)
            in_target = (current_label == label)
        }
        {
            print
            # After printing the label line, check if we need to add menu default
            if (in_target && /^label /) {
                # Check if next line exists and is not menu default
                getline next_line
                if (next_line !~ /^  menu default$/) {
                    print "  menu default"
                    print next_line
                } else {
                    print next_line
                }
                in_target = 0
            }
        }
    ' "$temp_file" > "${temp_file}.default"
    mv "${temp_file}.default" "$temp_file"
}

#############################################
# Write new configuration to syslinux.cfg
#############################################
write_config() {

    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        write_config_grub
        return
    fi

    # Save custom parameter comments if provided
    if [[ -n "${CUSTOM_PARAMS_COMMENTS}" ]]; then
        save_comments "${CUSTOM_PARAMS_COMMENTS}"
    fi

    # Validate timeout (deciseconds) to keep sed safe
    if [[ -n "${TIMEOUT}" && ! "${TIMEOUT}" =~ ^[0-9]+$ ]]; then
        error_exit "Invalid timeout value"
    fi

    # Read framebuffer exclusion flag (default to 0 if not set)
    EXCLUDE_FRAMEBUFFER_FROM_GUI="${EXCLUDE_FRAMEBUFFER_FROM_GUI:-0}"

    local new_append=$(build_append_line)
    local timestamp=$(date +%Y-%b-%d_%H-%M-%S)


    # Create backup directory
    mkdir -p "$BACKUP_DIR"

    # Create timestamped backup
    cp "$SYSLINUX_CFG" "$BACKUP_DIR/syslinux.cfg.bak.$timestamp"

    # Cleanup old backups (keep last MAX_BACKUPS)
    local backup_count=$(ls -1 "$BACKUP_DIR"/syslinux.cfg.bak.* 2>/dev/null | wc -l)
    if [[ $backup_count -gt $MAX_BACKUPS ]]; then
        ls -t "$BACKUP_DIR"/syslinux.cfg.bak.* | tail -n +$((MAX_BACKUPS + 1)) | xargs rm -f
    fi

    # Create temp file on same filesystem as target to ensure atomic move operation (prevents corruption on power loss)
    local temp_file=$(mktemp -p "$(dirname "$SYSLINUX_CFG")")
    cp "$SYSLINUX_CFG" "$temp_file"

    # Update "Unraid OS" label if enabled in UI
    if [[ "${APPLY_TO_UNRAID_OS}" == "1" ]]; then
        awk -v new_append="  append $new_append" '
            /^label Unraid OS$/ {
                in_section=1
            }
            /^label / && !/^label Unraid OS$/ {
                in_section=0
            }
            {
                if (in_section && /^  append/) {
                    print new_append
                } else {
                    print
                }
            }
        ' "$temp_file" > "${temp_file}.1"
        mv "${temp_file}.1" "$temp_file"
    fi

    # Update "Unraid OS GUI Mode" label if enabled in UI
    if [[ "${APPLY_TO_GUI_MODE}" == "1" ]]; then

        # Optionally exclude framebuffer parameters for GUI mode entries
        if [[ "${EXCLUDE_FRAMEBUFFER_FROM_GUI}" == "1" ]]; then
            local gui_append="  append $(build_append_line_gui_safe)"
        else
            local gui_append="  append $new_append"
        fi

        awk -v new_append="$gui_append" '
            /^label Unraid OS GUI Mode$/ {
                in_section=1
            }
            /^label / && !/^label Unraid OS GUI Mode$/ {
                in_section=0
            }
            {
                if (in_section && /^  append/) {
                    print new_append
                } else {
                    print
                }
            }
        ' "$temp_file" > "${temp_file}.2"
        mv "${temp_file}.2" "$temp_file"
    fi

    # Update "Unraid OS Safe Mode" label if enabled in UI
    if [[ "${APPLY_TO_SAFE_MODE}" == "1" ]]; then
        awk -v new_append="  append $new_append" '
            /^label Unraid OS Safe Mode/ {
                in_section=1
            }
            /^label / && !/^label Unraid OS Safe Mode/ {
                in_section=0
            }
            {
                if (in_section && /^  append/) {
                    print new_append
                } else {
                    print
                }
            }
        ' "$temp_file" > "${temp_file}.3"
        mv "${temp_file}.3" "$temp_file"
    fi

    # Update "Unraid OS GUI Safe Mode" label if enabled in UI
    if [[ "${APPLY_TO_GUI_SAFE_MODE}" == "1" ]]; then

        # Optionally exclude framebuffer parameters for GUI mode entries
        if [[ "${EXCLUDE_FRAMEBUFFER_FROM_GUI}" == "1" ]]; then
            local gui_safe_append="  append $(build_append_line_gui_safe)"
        else
            local gui_safe_append="  append $new_append"
        fi

        awk -v new_append="$gui_safe_append" '
            /^label Unraid OS GUI Safe Mode/ {
                in_section=1
            }
            /^label / && !/^label Unraid OS GUI Safe Mode/ {
                in_section=0
            }
            {
                if (in_section && /^  append/) {
                    print new_append
                } else {
                    print
                }
            }
        ' "$temp_file" > "${temp_file}.4"
        mv "${temp_file}.4" "$temp_file"
    fi

    # Update default boot entry if requested
    if [[ -n "${DEFAULT_BOOT_ENTRY}" ]]; then
        update_default_boot "${DEFAULT_BOOT_ENTRY}" "$temp_file"
    fi

    # Update timeout if provided
    if [[ -n "${TIMEOUT}" ]]; then
        sed -i.timeout "s/^timeout .*/timeout ${TIMEOUT}/" "$temp_file"
        rm -f "${temp_file}.timeout"
    fi

    # Validate the modified config
    if validate_config "$temp_file"; then
        # Move temp file to actual config
        mv "$temp_file" "$SYSLINUX_CFG"

        # Output success with full config
        echo "SUCCESS"
        echo "---CONFIG-START---"
        cat "$SYSLINUX_CFG"
        echo "---CONFIG-END---"
    else
        # Validation failed, restore backup
        rm -f "$temp_file"
        cp "$BACKUP_DIR/syslinux.cfg.bak.$timestamp" "$SYSLINUX_CFG"
        error_exit "Configuration validation failed, restored from backup"
    fi
}

#############################################
# Update GRUB menuentry linux args
#############################################
update_grub_entry() {
    local label="$1"
    local new_args="$2"
    local cfg_file="$3"

    awk -v label="$label" -v new_args="$new_args" '
        $0 ~ /^menuentry / {
            in_section = (index($0, "\"" label "\"") > 0 || index($0, "'" label "'") > 0)
        }
        {
            if (in_section && match($0, /^[ \t]*(linux|linuxefi)[ \t]+([^ \t]+)[ \t]*(.*)$/, m)) {
                match($0, /^[ \t]*/)
                indent = substr($0, RSTART, RLENGTH)
                cmd = m[1]
                kernel = m[2]
                if (new_args != "") {
                    print indent cmd " " kernel " " new_args
                } else {
                    print indent cmd " " kernel
                }
                next
            }
            print
        }
    ' "$cfg_file" > "${cfg_file}.tmp"
    mv "${cfg_file}.tmp" "$cfg_file"
}

#############################################
# Update GRUB default menuentry
#############################################
update_grub_default() {
    local label="$1"
    local cfg_file="$2"

    local index=$(awk -v label="$label" '
        $0 ~ /^menuentry / {
            if (index($0, "\"" label "\"") > 0 || index($0, "'" label "'") > 0) {
                print idx
                exit
            }
            idx++
        }
        BEGIN { idx=0 }
    ' "$cfg_file")

    if [[ -z "$index" ]]; then
        return
    fi

    if grep -q '^set default=' "$cfg_file"; then
        sed -i -E "s/^set default=.*/set default=${index}/" "$cfg_file"
    else
        # Insert default near top
        sed -i "1iset default=${index}" "$cfg_file"
    fi
}

#############################################
# Write new configuration to GRUB config
#############################################
write_config_grub() {

    # Save custom parameter comments if provided
    if [[ -n "${CUSTOM_PARAMS_COMMENTS}" ]]; then
        save_comments "${CUSTOM_PARAMS_COMMENTS}"
    fi

    # Validate timeout (deciseconds)
    if [[ -n "${TIMEOUT}" && ! "${TIMEOUT}" =~ ^[0-9]+$ ]]; then
        error_exit "Invalid timeout value"
    fi

    EXCLUDE_FRAMEBUFFER_FROM_GUI="${EXCLUDE_FRAMEBUFFER_FROM_GUI:-0}"

    local new_args=$(build_kernel_args)
    local timestamp=$(date +%Y-%b-%d_%H-%M-%S)

    mkdir -p "$GRUB_BACKUP_DIR"

    cp "$GRUB_CFG" "$GRUB_BACKUP_DIR/grub.cfg.bak.$timestamp"

    local backup_count=$(ls -1 "$GRUB_BACKUP_DIR"/grub.cfg.bak.* 2>/dev/null | wc -l)
    if [[ $backup_count -gt $MAX_BACKUPS ]]; then
        ls -t "$GRUB_BACKUP_DIR"/grub.cfg.bak.* | tail -n +$((MAX_BACKUPS + 1)) | xargs rm -f
    fi

    local temp_file=$(mktemp -p "$(dirname "$GRUB_CFG")")
    cp "$GRUB_CFG" "$temp_file"

    if [[ "${APPLY_TO_UNRAID_OS}" == "1" ]]; then
        update_grub_entry "Unraid OS" "$new_args" "$temp_file"
    fi

    if [[ "${APPLY_TO_GUI_MODE}" == "1" ]]; then
        local gui_args="$new_args"
        if [[ "${EXCLUDE_FRAMEBUFFER_FROM_GUI}" == "1" ]]; then
            gui_args=$(build_kernel_args_gui_safe)
        fi
        update_grub_entry "Unraid OS GUI Mode" "$gui_args" "$temp_file"
    fi

    if [[ "${APPLY_TO_SAFE_MODE}" == "1" ]]; then
        update_grub_entry "Unraid OS Safe Mode (no plugins, no GUI)" "$new_args" "$temp_file"
    fi

    if [[ "${APPLY_TO_GUI_SAFE_MODE}" == "1" ]]; then
        local gui_safe_args="$new_args"
        if [[ "${EXCLUDE_FRAMEBUFFER_FROM_GUI}" == "1" ]]; then
            gui_safe_args=$(build_kernel_args_gui_safe)
        fi
        update_grub_entry "Unraid OS GUI Safe Mode (no plugins)" "$gui_safe_args" "$temp_file"
    fi

    if [[ -n "${DEFAULT_BOOT_ENTRY}" ]]; then
        update_grub_default "${DEFAULT_BOOT_ENTRY}" "$temp_file"
    fi

    if [[ -n "${TIMEOUT}" ]]; then
        local timeout_seconds=$(((TIMEOUT + 5) / 10))
        if grep -q '^set timeout=' "$temp_file"; then
            sed -i -E "s/^set timeout=.*/set timeout=${timeout_seconds}/" "$temp_file"
        else
            sed -i "1iset timeout=${timeout_seconds}" "$temp_file"
        fi
    fi

    # Basic validation: ensure the config is non-empty and has at least one menuentry
    if [[ ! -s "$temp_file" ]] || ! grep -q '^menuentry ' "$temp_file"; then
        rm -f "$temp_file"
        cp "$GRUB_BACKUP_DIR/grub.cfg.bak.$timestamp" "$GRUB_CFG"
        error_exit "GRUB configuration validation failed, restored from backup"
    fi

    mv "$temp_file" "$GRUB_CFG"

    echo "SUCCESS"
    echo "---CONFIG-START---"
    cat "$GRUB_CFG"
    echo "---CONFIG-END---"
}

#############################################
# Write raw configuration directly
#############################################
write_raw_config() {

    # Read raw config from environment variable
    if [[ -z "${RAW_CONFIG}" ]]; then
        error_exit "No raw configuration provided"
    fi

    local timestamp=$(date +%Y-%b-%d_%H-%M-%S)

    local target_cfg="$SYSLINUX_CFG"
    local target_backup_dir="$BACKUP_DIR"
    local backup_prefix="syslinux.cfg"
    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        target_cfg="$GRUB_CFG"
        target_backup_dir="$GRUB_BACKUP_DIR"
        backup_prefix="grub.cfg"
    fi

    # Create backup directory
    mkdir -p "$target_backup_dir"

    # Create timestamped backup
    cp "$target_cfg" "$target_backup_dir/${backup_prefix}.bak.$timestamp"

    # Cleanup old backups (keep last $MAX_BACKUPS)
    ls -t "$target_backup_dir"/${backup_prefix}.bak.* 2>/dev/null | tail -n +$((MAX_BACKUPS + 1)) | xargs rm -f 2>/dev/null || true

    # Create temp file on same filesystem as target to ensure atomic move operation
    local temp_file=$(mktemp -p "$(dirname "$target_cfg")")

    # Write raw config to temp file
    echo "$RAW_CONFIG" > "$temp_file"

    # Validate the raw config (syslinux only)
    if [[ "$BOOTLOADER_TYPE" == "grub" ]] || validate_config "$temp_file"; then
        # Move temp file to actual config
        mv "$temp_file" "$target_cfg"

        # Output success
        echo "SUCCESS"
        echo "Raw configuration saved successfully"
    else
        # Validation failed, restore backup
        rm -f "$temp_file"
        cp "$target_backup_dir/${backup_prefix}.bak.$timestamp" "$target_cfg"
        error_exit "Raw configuration validation failed. The configuration must contain 'label Unraid OS' and 'initrd=' entries. Restored from backup."
    fi
}

#############################################
# List available backups
#############################################
list_backups() {
    local target_backup_dir="$BACKUP_DIR"
    local backup_prefix="syslinux.cfg"
    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        target_backup_dir="$GRUB_BACKUP_DIR"
        backup_prefix="grub.cfg"
    fi

    if [[ ! -d "$target_backup_dir" ]]; then
        echo "[]"
        return
    fi

    # List backups in JSON format
    echo "["
    local first=1
    for backup in $(ls -t "$target_backup_dir"/${backup_prefix}.bak.* 2>/dev/null); do
        if [[ $first -eq 0 ]]; then
            echo ","
        fi
        first=0

        local filename=$(basename "$backup")
        local timestamp=$(echo "$filename" | sed "s/${backup_prefix}\.bak\.//")
        local size=$(stat -f%z "$backup" 2>/dev/null || stat -c%s "$backup" 2>/dev/null || echo "0")

        cat <<EOF
  {
    "filename": "$filename",
    "timestamp": "$timestamp",
    "size": $size
  }
EOF
    done
    echo "]"
}

#############################################
# Restore from backup
#############################################
restore_backup() {
    local target_cfg="$SYSLINUX_CFG"
    local target_backup_dir="$BACKUP_DIR"
    local backup_prefix="syslinux.cfg"
    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        target_cfg="$GRUB_CFG"
        target_backup_dir="$GRUB_BACKUP_DIR"
        backup_prefix="grub.cfg"
    fi

    # Validate backup filename format (defense-in-depth - prevents path traversal)
    # Expected format: syslinux.cfg.bak.YYYY-MMM-DD_HH-MM-SS
    # Example: syslinux.cfg.bak.2025-Jan-08_14-30-45
    # Regex breakdown:
    #   ^syslinux\.cfg\.bak\.        - Literal prefix (escaped dots)
    #   [0-9]{4}                     - Year (4 digits)
    #   -[A-Za-z]{3}                 - Month (3 letters: Jan, Feb, etc.)
    #   -[0-9]{2}                    - Day (2 digits)
    #   _[0-9]{2}-[0-9]{2}-[0-9]{2}  - Time (HH-MM-SS)
    #   $                            - End of string (prevents path traversal)
    # This matches the PHP validation pattern exactly (line 188 in boot_params_handler.php)
    if [[ ! "$BACKUP_FILENAME" =~ ^${backup_prefix//./\.}\.bak\.[0-9]{4}-[A-Za-z]{3}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}$ ]]; then
        error_exit "Invalid backup filename format: $BACKUP_FILENAME"
    fi

    local backup_file="$target_backup_dir/${BACKUP_FILENAME}"

    # Verify file exists
    if [[ ! -f "$backup_file" ]]; then
        error_exit "Backup file not found: $backup_file"
    fi

    # Validate backup before restoring
    if [[ "$BOOTLOADER_TYPE" != "grub" ]]; then
        if ! validate_config "$backup_file"; then
            error_exit "Backup file validation failed"
        fi
    fi

    # Restore from backup
    cp "$backup_file" "$target_cfg"

    echo "SUCCESS"
    echo "Restored from backup: ${BACKUP_FILENAME}"
}

#############################################
# Delete all backups
#############################################
delete_all_backups() {

    local target_backup_dir="$BACKUP_DIR"
    local backup_prefix="syslinux.cfg"
    if [[ "$BOOTLOADER_TYPE" == "grub" ]]; then
        target_backup_dir="$GRUB_BACKUP_DIR"
        backup_prefix="grub.cfg"
    fi

    # Remove all backup files
    rm -f "${target_backup_dir}"/${backup_prefix}.bak.* 2>/dev/null

    echo "SUCCESS: All backups deleted"
}

#############################################
# Main execution
#############################################
case "$OPERATION" in
    "read_config")
        read_config
        ;;

    "write_config")
        write_config
        ;;

    "list_backups")
        list_backups
        ;;

    "restore_backup")
        restore_backup
        ;;

    "delete_all_backups")
        delete_all_backups
        ;;

    "write_raw_config")
        write_raw_config
        ;;

    "check_hardware")
        check_hardware_change
        ;;

    "update_hardware")
        update_hardware_tracking
        ;;

    *)
        error_exit "Unknown operation: $OPERATION"
        ;;
esac
