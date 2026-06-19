#!/bin/bash
#
# script: unraid_power_handler.sh
#
# Unraid ACPI event handler. Installed over /etc/acpi/acpi_handler.sh at boot
# by /etc/rc.d/rc.acpid, so it reliably overrides the stock Slackware acpid
# handler regardless of package install order.
#
# The physical power button behavior is configurable from the WebUI
# (Settings > Power Options > Power Button). The choice is stored in
# dynamix.cfg and read live on each event, so no acpid reload is needed
# when the setting changes.
#
# Plugins can add or override actions by dropping an executable handler in
# /etc/acpi/powerbutton.d/<action> - see /etc/acpi/powerbutton.d/README.
#
# LimeTech - modified for Unraid OS

CFG=/boot/config/plugins/dynamix/dynamix.cfg
PLUGIN_DIR=/etc/acpi/powerbutton.d

# acpid passes the raw event line, e.g.: "button/power PBTN 00000080 00000000"
set -- $@
group=${1%%/*}
action=${1#*/}

run_powerbutton(){
  local behavior=$1
  case "$behavior" in
  ignore)
    logger -t acpid "Power button: ignored (Power Options setting)"
    ;;
  reboot)
    logger -t acpid "Power button: rebooting"
    /sbin/init 6
    ;;
  sleep)
    # Let the S3 Sleep plugin (if installed) perform array-aware prep,
    # otherwise fall back to a generic suspend-to-RAM.
    if [[ -x $PLUGIN_DIR/sleep ]]; then
      logger -t acpid "Power button: sleeping (plugin handler)"
      "$PLUGIN_DIR/sleep"
    elif grep -qw mem /sys/power/state 2>/dev/null; then
      logger -t acpid "Power button: suspending to RAM"
      sync
      echo -n mem > /sys/power/state
    else
      logger -t acpid "Power button: suspend not supported, ignoring"
    fi
    ;;
  shutdown)
    logger -t acpid "Power button: initiating clean shutdown"
    /sbin/init 0
    ;;
  *)
    # Extension point: plugin-provided action handler.
    if [[ -n $behavior && -x $PLUGIN_DIR/$behavior ]]; then
      logger -t acpid "Power button: running plugin action '$behavior'"
      "$PLUGIN_DIR/$behavior"
    else
      logger -t acpid "Power button: unknown action '$behavior', ignoring"
    fi
    ;;
  esac
}

case "$group" in
button)
  case "$action" in
  power)
    behavior=$(grep -Pom1 '^powerbutton="\K[^"]+' "$CFG" 2>/dev/null)
    run_powerbutton "${behavior:-shutdown}"
    ;;
  *)
    logger -t acpid "ACPI action button/$action is not handled"
    ;;
  esac
  ;;
*)
  logger -t acpid "ACPI event $1 is not handled"
  ;;
esac
