#!/usr/bin/env python3
"""
Extract JavaScript translation strings from .page files (inside <script> blocks) and create a .po file.
"""
import os
import re
from pathlib import Path
from collections import defaultdict

def extract_js_strings_from_script(script_content):
    # Match _('<string>') or _("string") in JS
    pattern = re.compile(r'_\([\'"]([^\'"]+)[\'"]\)')
    return pattern.findall(script_content)

def extract_js_translations_from_page_files(directory):
    js_strings = set()
    file_sources = defaultdict(list)
    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith('.page'):
                path = os.path.join(root, file)
                try:
                    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                        # Find all <script>...</script> blocks
                        for script in re.findall(r'<script[^>]*>([\s\S]*?)</script>', content, re.IGNORECASE):
                            for s in extract_js_strings_from_script(script):
                                s_clean = s.strip()
                                if s_clean:
                                    js_strings.add(s_clean)
                                    file_sources[s_clean].append(path)
                except Exception as e:
                    print(f"Error reading {path}: {e}")
    return sorted(js_strings), file_sources

def write_po_file(strings, file_sources, output_file):
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write('# JavaScript translation strings for Unraid webgui\n')
        f.write('msgid ""\nmsgstr ""\n\n')
        for s in strings:
            sources = file_sources.get(s, [])
            if sources:
                f.write(f'# Found in: {", ".join(sources[:3])}\n')
                if len(sources) > 3:
                    f.write(f'# ... and {len(sources) - 3} more files\n')
            s_escaped = s.replace('"', '\\"')
            f.write(f'msgid "{s_escaped}"\nmsgstr ""\n\n')

def main():
    strings, file_sources = extract_js_translations_from_page_files('.')
    print(f"Found {len(strings)} unique JavaScript translation strings.")
    write_po_file(strings, file_sources, 'unraid-webgui-js.po')
    print("Wrote unraid-webgui-js.po")

if __name__ == '__main__':
    main() 