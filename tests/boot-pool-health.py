"""Exercise rc.S device discovery without touching disks or running ZFS."""

import pathlib
import subprocess
import unittest


SOURCE = pathlib.Path(__file__).resolve().parents[1] / "etc/rc.d/rc.S"
DISCOVERY = SOURCE.read_text().split("find_device() {", 1)[1].split(
    "# we're going to need this", 1
)[0]
# Replace only absolute executable paths in the extracted function. All device
# discovery, ZFS operations and sleeps run as shell stubs below.
DISCOVERY = DISCOVERY.replace("/sbin/blkid", "fixture_blkid").replace(
    "/sbin/resize_boot", "fixture_resize"
).replace("/sbin/zpool", "fixture_zpool").replace("/bin/sleep", "fixture_sleep")


def discover(states="ONLINE", *, failed_probe=False, fs="zfs_member", import_failed=False):
    script = r'''
TIMEOUT=3 UUID=123 LABEL=UNRAID UNRAIDBOOT=""
DEVICE="" FSTYPE="" ROOT=""
log() { echo "LOG:$*"; }
fixture_blkid() {
  case "$*" in
    *"-s TYPE"*) echo "$FIXTURE_FS" ;;
    *"-t UUID="*) echo '/dev/test1 /dev/test2' ;;
    *) echo /dev/test1 ;;
  esac
}
fixture_resize() { echo "RESIZE:$*"; }
fixture_sleep() { echo "SLEEP:$*"; }
fixture_zpool() {
  case "$1" in
    import) echo "IMPORT:$*"; [[ "$FIXTURE_IMPORT_FAILED" != 1 ]] ;;
    list) echo flash ;;
    status) echo "pool flash is DEGRADED"; return 0 ;;
    get)
      # The existing loop decrements TIMEOUT in the parent shell. Deriving the
      # fixture response from it avoids state lost in command substitutions.
      local states=( $FIXTURE_STATES )
      local index=$((3 - TIMEOUT))
      (( index < ${#states[@]} )) || index=$((${#states[@]} - 1))
      [[ "${states[index]}" == EMPTY ]] || echo "${states[index]}"
      [[ "$FIXTURE_FAILED_PROBE" != 1 ]]
      ;;
    export) echo "EXPORT:$*" ;;
    online) echo "EXPAND:$*" ;;
    *) echo "UNEXPECTED:$*"; return 99 ;;
  esac
}
'''
    script += "\nfind_device() {" + DISCOVERY
    script += '\nfind_device; result=$?; echo "RESULT:$result TIMEOUT:$TIMEOUT FSTYPE:$FSTYPE"\n'
    import os

    env = dict(os.environ, FIXTURE_STATES=states, FIXTURE_FS=fs,
               FIXTURE_FAILED_PROBE=str(int(failed_probe)),
               FIXTURE_IMPORT_FAILED=str(int(import_failed)))
    return subprocess.run(["/bin/bash"], input=script, text=True, env=env,
                          capture_output=True, timeout=5, check=True).stdout


class BootPoolHealth(unittest.TestCase):
    def test_online_returns_without_retry_and_expands(self):
        out = discover()
        self.assertIn("LOG:pool is healthy", out)
        self.assertIn("EXPAND:online -e flash /dev/test1 /dev/test2", out)
        self.assertNotIn("EXPORT:", out)
        self.assertNotIn("SLEEP:", out)

    def test_unhealthy_or_unknown_retries_then_boots_degraded(self):
        for state in ("DEGRADED", "FAULTED", "UNAVAIL", "EMPTY", "unexpected"):
            with self.subTest(state=state):
                out = discover(state)
                self.assertNotIn("LOG:pool is healthy", out)
                self.assertNotIn("EXPAND:", out)
                self.assertEqual(out.count("EXPORT:"), 2)
                self.assertEqual(out.count("IMPORT:"), 3)
                self.assertIn("LOG:pool is degraded", out)
                self.assertIn("RESULT:0 TIMEOUT:1", out)

    def test_failed_probe_cannot_authorize_healthy_return(self):
        out = discover("ONLINE", failed_probe=True)
        self.assertNotIn("LOG:pool is healthy", out)
        self.assertEqual(out.count("EXPORT:"), 2)
        self.assertIn("LOG:pool is degraded", out)

    def test_late_member_retries_then_becomes_healthy(self):
        out = discover("DEGRADED ONLINE")
        self.assertEqual(out.count("EXPORT:"), 1)
        self.assertEqual(out.count("IMPORT:"), 2)
        self.assertIn("LOG:pool is healthy", out)
        self.assertIn("EXPAND:", out)
        self.assertNotIn("LOG:pool is degraded", out)

    def test_non_zfs_path_does_not_call_zpool(self):
        out = discover(fs="vfat")
        self.assertIn("LOG:found /dev/test1", out)
        self.assertNotIn("IMPORT:", out)
        self.assertNotIn("SLEEP:", out)

    def test_import_failure_is_preserved(self):
        out = discover(import_failed=True)
        self.assertIn("LOG:zpool import failed", out)
        self.assertIn("RESULT:1", out)
        self.assertNotIn("EXPORT:", out)
        self.assertNotIn("EXPAND:", out)


if __name__ == "__main__":
    unittest.main()
