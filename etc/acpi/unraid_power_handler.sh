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
# LimeTech - modified for Unraid OS

CFG=/boot/config/plugins/dynamix/dynamix.cfg

# acpid passes the raw event line, e.g.: "button/power PBTN 00000080 00000000"
set -- $@
group=${1%%/*}
action=${1#*/}

case "$group" in
button)
  case "$action" in
  power)
    behavior=$(grep -Pom1 '^powerbutton="\K[^"]+' "$CFG" 2>/dev/null)
    case "${behavior:-shutdown}" in
    ignore)
      logger -t acpid "Power button pressed - ignored (Power Options setting)"
      ;;
    *)
      logger -t acpid "Power button pressed - initiating clean shutdown"
      /sbin/init 0
      ;;
    esac
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
