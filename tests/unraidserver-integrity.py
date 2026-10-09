#!/usr/bin/env python3
"""Exercise only the archive integrity gate; never run the OS installer."""

import hashlib
from pathlib import Path
import re
import shlex
import shutil
import subprocess
import tempfile
import unittest
import xml.etree.ElementTree as ET


TEMPLATE = (
    Path(__file__).resolve().parents[1]
    / "emhttp/plugins/unRAIDServer/unRAIDServer.plg"
).read_text()
PAYLOAD = b"release archive fixture\n"
DIGEST = hashlib.sha256(PAYLOAD).hexdigest()


def manifest(digest):
    # Same entity declaration used by release generation.
    source = re.sub(r'(<!ENTITY sha256\s+)"[^"]*"', rf'\g<1>"{digest}"', TEMPLATE)
    return ET.fromstring(source)


class ReleaseIntegrityTest(unittest.TestCase):
    def run_gate(self, digest=DIGEST, payload=PAYLOAD, hash_command=None):
        root = manifest(digest)
        script = next(
            file.findtext("INLINE")
            for file in root.findall("FILE")
            if "missing or invalid release SHA256" in (file.findtext("INLINE") or "")
        )
        # Do not execute cleanup, extraction, flash writes, or other install hooks.
        gate = script.split('sha256expect=', 1)[1].split(
            '# check if enough free space on flash', 1
        )[0]
        gate = 'sha256expect=' + gate
        with tempfile.TemporaryDirectory(prefix="unraid-integrity-") as directory:
            archive = Path(directory) / "release.zip"
            if payload is not None:
                archive.write_bytes(payload)
            gate = gate.replace('/tmp/unRAIDServer.zip', shlex.quote(str(archive)))
            command = hash_command or shutil.which("sha256sum")
            self.assertIsNotNone(command, "sha256sum must be installed")
            gate = gate.replace('/usr/bin/sha256sum', shlex.quote(command))
            return subprocess.run(
                ["bash", "-c", gate + '\necho extraction-permitted\n'],
                capture_output=True, text=True, check=False,
            )

    def assert_rejected(self, result, message):
        self.assertNotEqual(result.returncode, 0)
        self.assertIn(message, result.stdout)
        self.assertNotIn("extraction-permitted", result.stdout)

    def test_archive_has_declared_pin_and_no_md5_sidecar(self):
        downloads = [file for file in manifest(DIGEST).findall("FILE") if file.find("URL") is not None]
        self.assertEqual(len(downloads), 1)
        self.assertEqual(downloads[0].get("Name"), "/tmp/unRAIDServer.zip")
        self.assertEqual(downloads[0].findtext("SHA256"), DIGEST)
        self.assertNotIn("&md5;", TEMPLATE)
        self.assertNotIn("/tmp/&name;.md5", TEMPLATE)

    def test_matching_archive_reaches_extraction(self):
        result = self.run_gate()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("extraction-permitted", result.stdout)

    def test_substituted_archive_is_rejected(self):
        self.assert_rejected(self.run_gate(payload=b"substituted archive"), "wrong release SHA256")

    def test_empty_digest_is_rejected(self):
        self.assert_rejected(self.run_gate(digest=""), "missing or invalid release SHA256")

    def test_malformed_digests_are_rejected(self):
        for digest in ("a" * 63, "a" * 65, "g" * 64, "unfilled"):
            with self.subTest(digest=digest):
                self.assert_rejected(self.run_gate(digest=digest), "missing or invalid release SHA256")

    def test_missing_archive_is_rejected(self):
        self.assert_rejected(self.run_gate(payload=None), "cannot calculate release SHA256")

    def test_hash_command_failure_is_rejected(self):
        self.assert_rejected(self.run_gate(hash_command="/bin/false"), "cannot calculate release SHA256")

    def test_integrity_gate_precedes_extraction(self):
        script = next(
            file.findtext("INLINE") for file in manifest(DIGEST).findall("FILE")
            if "wrong release SHA256" in (file.findtext("INLINE") or "")
        )
        self.assertLess(script.index("wrong release SHA256"), script.index("unzip "))


if __name__ == "__main__":
    unittest.main()
