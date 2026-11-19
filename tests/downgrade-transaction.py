#!/usr/bin/env python3
"""Exercise the production transaction with only filesystem roots relocated."""
import fcntl
import json
from pathlib import Path
import subprocess
import tempfile
import unittest


HANDLER = Path(__file__).resolve().parents[1] / "emhttp/plugins/dynamix.plugin.manager/include/Downgrade.php"


class DowngradeTransaction(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="downgrade-transaction-")
        self.root = Path(self.temporary.name).resolve()
        self.boot = self.root / "boot"
        self.previous = self.boot / "previous"
        self.previous.mkdir(parents=True)
        self.docroot = self.root / "emhttp"
        includes = self.docroot / "webGui/include"
        includes.mkdir(parents=True)
        (includes / "Wrappers.php").write_text("<?php function _var($a,$k){return $a[$k]??'';} function unscript($s){return strip_tags($s);}")
        (includes / "Secure.php").write_text("<?php")
        (includes / "Translations.php").write_text("<?php if(!function_exists('_')){function _($s){return $s;}}")
        self.readme = self.docroot / "plugins/unRAIDServer/README.md"
        self.readme.parent.mkdir(parents=True)
        self.readme.write_text("current marker")
        self.lock = self.root / "boot-update.lock"
        self.sessions = self.root / "sessions"
        self.sessions.mkdir()
        source = HANDLER.read_text().replace("/boot", str(self.boot)).replace("/var/run/unraid-boot-update.lock", str(self.lock))
        self.handler = self.root / "handler.php"
        self.handler.write_text(source)
        for name in ["bzimage", "bzroot", "changes.txt", "memtest64.efi", "make_bootable_linux", "license.txt", "syslinux.cfg-"]:
            (self.boot / name).write_text("current " + name)
            (self.previous / name).write_text("previous " + name)
        (self.previous / "changes.txt").write_text("# Version 7.4.0-beta.3.2 2026-11-17\n")
        (self.previous / "unrelated.txt").write_text("unrelated sentinel")

    def tearDown(self):
        self.temporary.cleanup()

    def post(self, fields, session_id):
        code = '''session_id($argv[4]); session_start();
$_SERVER['REQUEST_METHOD']='POST'; $docroot=$argv[1]; $_POST=json_decode($argv[3],true);
register_shutdown_function(function(){fwrite(STDERR,'STATUS:'.(http_response_code()?:200));}); include $argv[2];'''
        result = subprocess.run(
            ["php", "-d", "short_open_tag=1", "-d", "display_errors=stderr",
             "-d", f"session.save_path={self.sessions}", "-r", code,
             str(self.docroot), str(self.handler), json.dumps(fields), session_id],
            capture_output=True, text=True, timeout=10,
        )
        self.assertEqual(result.returncode, 0, result.stderr)
        status = int(result.stderr.rsplit("STATUS:", 1)[1])
        return status, json.loads(result.stdout.strip())

    def call(self, version="7.4.0-beta.3.2", prepare=False):
        status, prepared = self.post({"action": "prepare"}, "downgrade-test")
        self.assertEqual(status, 200)
        if prepare:
            return status, prepared
        return self.post({
            "nonce": prepared["nonce"], "confirm": "yes", "version": version,
        }, "downgrade-test")

    def snapshot(self):
        return {str(p.relative_to(self.root)): p.read_bytes() for p in self.boot.rglob("*") if p.is_file()}

    def test_prepare_never_changes_boot_files(self):
        before = self.snapshot()
        status, response = self.call(prepare=True)
        self.assertEqual(status, 200)
        self.assertEqual(len(response["nonce"]), 64)
        self.assertEqual(self.snapshot(), before)

    def test_busy_lock_preserves_files_and_release_allows_operation(self):
        before = self.snapshot()
        with self.lock.open("w") as owner:
            fcntl.flock(owner, fcntl.LOCK_EX | fcntl.LOCK_NB)
            status, response = self.call()
            self.assertEqual(status, 409)
            self.assertIn("error", response)
            self.assertEqual(self.snapshot(), before)
        status, response = self.call()
        self.assertEqual(status, 200)
        self.assertTrue(response["success"])
        self.assertEqual((self.boot / "bzimage").read_text(), "previous bzimage")
        self.assertIn("7.4.0-beta.3.2", (self.boot / "changes.txt").read_text())
        self.assertFalse((self.previous / "changes.txt").exists())
        backup = list(self.boot.glob("deletemedowngrade.*"))
        self.assertEqual(len(backup), 1)
        self.assertEqual((backup[0] / "changes.txt").read_text(), "current changes.txt")
        self.assertIn("DOWNGRADE TO VERSION", self.readme.read_text())
        for name in ["memtest64.efi", "make_bootable_linux", "license.txt", "syslinux.cfg-"]:
            self.assertEqual((self.boot / name).read_text(), "previous " + name)
            self.assertEqual((backup[0] / name).read_text(), "current " + name)
        self.assertEqual((self.previous / "unrelated.txt").read_text(), "unrelated sentinel")
        self.assertFalse((self.boot / "unrelated.txt").exists())

    def test_changed_target_version_preserves_files(self):
        before = self.snapshot()
        status, response = self.call("7.3.4")
        self.assertEqual(status, 409)
        self.assertIn("error", response)
        self.assertEqual(self.snapshot(), before)

    def test_marker_commit_failure_rolls_back_boot_files(self):
        before = self.snapshot()
        self.readme.unlink()
        self.readme.mkdir()
        (self.readme / "keep").write_text("marker sentinel")
        status, response = self.call()
        self.assertEqual(status, 500)
        self.assertIn("error", response)
        self.assertEqual(self.snapshot(), before)
        self.assertEqual((self.readme / "keep").read_text(), "marker sentinel")
        self.assertEqual(list(self.readme.parent.glob(".downgrade-readme.*")), [])

    def test_plugin_commands_hold_lock_through_registration(self):
        source = (HANDLER.parent.parent / "scripts/plugin").read_text()
        replacements = {
            "/usr/local/emhttp": str(self.docroot),
            "/boot": str(self.boot),
            "/var/log/plugins": str(self.root / "registered"),
            "/tmp/plugins": str(self.root / "downloads"),
            "/etc/unraid-version": str(self.root / "version"),
            "/tmp/inline": str(self.root / "inline"),
            "/var/run/unraid-boot-update.lock": str(self.lock),
        }
        for original, replacement in replacements.items():
            source = source.replace(original, replacement)
        runner = self.root / "plugin"
        runner.write_text(source)
        (self.root / "version").write_text('version="7.4.0"')
        for directory in [self.boot / "config/plugins", self.root / "registered", self.root / "downloads"]:
            directory.mkdir(parents=True, exist_ok=True)
        includes = self.docroot / "webGui/include"
        with (includes / "Wrappers.php").open("a") as output:
            output.write(' function my_logger($message,$logger) {}')
        (includes / "publish.php").write_text("<?php")
        manifest = self.root / "unRAIDServer.plg"
        manifest.write_text('<PLUGIN name="unRAIDServer" version="2"><FILE Run="/bin/sh" Method="install remove"><INLINE>exit 0</INLINE></FILE></PLUGIN>')
        installed = self.boot / "config/plugins/unRAIDServer.plg"
        installed.write_text('<PLUGIN name="unRAIDServer" version="1"/>')
        link = self.root / "registered/unRAIDServer.plg"
        link.symlink_to(installed)
        downloaded = self.root / "downloads/unRAIDServer.plg"
        downloaded.write_bytes(manifest.read_bytes())
        with self.lock.open("w") as owner:
            fcntl.flock(owner, fcntl.LOCK_EX | fcntl.LOCK_NB)
            for method, target in [("install", str(manifest)), ("install", "https://example.invalid/unRAIDServer.plg"), ("update", manifest.name), ("remove", manifest.name), ("check", manifest.name), ("download", manifest.name)]:
                with self.subTest(method=method):
                    result = subprocess.run(["php", str(runner), method, target], capture_output=True, text=True, timeout=10)
                    self.assertEqual(result.returncode, 1, result.stdout + result.stderr)
                    self.assertIn("another OS update operation", result.stdout)
                    self.assertTrue(link.is_symlink())
                    self.assertIn('version="1"', installed.read_text())
                    self.assertTrue(downloaded.exists())
            # Attribute reads remain available while an OS writer owns the lock.
            result = subprocess.run(["php", str(runner), "name", str(manifest)], capture_output=True, text=True, timeout=10)
            self.assertEqual(result.returncode, 0, result.stderr)
        hooks = self.docroot / "plugins/dynamix.plugin.manager/post-hooks"
        hooks.mkdir(parents=True)
        proof = self.root / "hook-proof"
        hook = hooks / "verify-lock"
        hook.write_text('#!/usr/bin/env php\n<?php $h=fopen('+json.dumps(str(self.lock))+',"ce"); if(flock($h,LOCK_EX|LOCK_NB)) exit(1); file_put_contents('+json.dumps(str(proof))+',"locked");')
        hook.chmod(0o700)
        result = subprocess.run(["php", str(runner), "update", manifest.name], capture_output=True, text=True, timeout=10)
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual(proof.read_text(), "locked")
        self.assertTrue(link.is_symlink())
        self.assertIn('version="2"', installed.read_text())
        with self.lock.open("r+") as next_owner:
            fcntl.flock(next_owner, fcntl.LOCK_EX | fcntl.LOCK_NB)


if __name__ == "__main__":
    unittest.main()
