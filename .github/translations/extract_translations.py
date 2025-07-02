#!/usr/bin/env python3
"""
Translation string extraction for Unraid webgui codebase.
Properly handles nested parentheses and complex patterns.
"""

import os
import re
import sys
from pathlib import Path
from collections import defaultdict

def clean_translation_string(string):
    """Clean up a translation string by removing surrounding quotes and extra whitespace."""
    # Remove surrounding quotes (both single and double)
    string = string.strip()
    
    # Remove surrounding double quotes
    if string.startswith('"') and string.endswith('"'):
        string = string[1:-1]
    
    # Remove surrounding single quotes
    if string.startswith("'") and string.endswith("'"):
        string = string[1:-1]
    
    # Clean up any remaining whitespace
    string = string.strip()
    
    return string

def is_likely_translatable(string):
    """Check if a string is likely to be translatable."""
    # Skip obvious code fragments
    if any(code_pattern in string.lower() for code_pattern in [
        'function', 'if', 'else', 'for', 'while', 'foreach', 'return', 'echo', 'print',
        'startsWith', 'endsWith', 'preg_match', 'str_replace', 'array', 'isset',
        'unset', 'require', 'include', 'class', 'public', 'private', 'protected',
        'static', 'const', 'var', 'global', 'local', 'session', 'cookie',
        '$_', '$', '<?', '?>', '<!--', '-->', '&&', '||', '==', '!=', '===', '!==',
        '+=', '-=', '*=', '/=', '%=', '.=', '++', '--', '->', '::', '=>'
    ]):
        return False
    
    # Skip strings that look like variable names or function calls
    if re.match(r'^[a-zA-Z_][a-zA-Z0-9_]*\(', string):  # Function calls
        return False
    
    if re.match(r'^\$[a-zA-Z_][a-zA-Z0-9_]*$', string):  # Simple variables
        return False
    
    if re.match(r'^[a-zA-Z_][a-zA-Z0-9_]*\s*[=<>!+\-*/%]', string):  # Assignment/operation
        return False
    
    # Skip strings that are mostly punctuation or special characters
    if len(re.findall(r'[a-zA-Z]', string)) < len(string) * 0.3:  # Less than 30% letters
        return False
    
    # Skip strings that are too short or too long
    if len(string) < 2 or len(string) > 200:
        return False
    
    # Skip strings that are just numbers or hex
    if re.match(r'^[0-9a-fA-F\s]+$', string) and len(string) < 10:
        return False
    
    # Skip strings that start with common code patterns
    if string.startswith(('$', '@', '<?', '?>', '<!--', '-->', '&&', '||', '==', '!=')):
        return False
    
    return True

def extract_nested_parens(text, start_pos):
    """Extract content within nested parentheses starting from start_pos."""
    if text[start_pos] != '(':
        return None, start_pos
    
    pos = start_pos + 1
    paren_count = 1
    start = pos
    
    while pos < len(text) and paren_count > 0:
        if text[pos] == '(':
            paren_count += 1
        elif text[pos] == ')':
            paren_count -= 1
        pos += 1
    
    if paren_count == 0:
        return text[start:pos-1], pos
    return None, start_pos

def extract_translation_strings(directory):
    """Extract all translation strings from the codebase with proper nested parentheses handling."""
    translation_strings = set()
    file_sources = defaultdict(list)  # Track which files contain each string
    
    # File extensions to search
    extensions = {'.php', '.page', '.js', '.htm', '.html'}
    
    # Directories to skip
    skip_dirs = {'.git', 'node_modules', 'vendor', '__pycache__'}
    
    for root, dirs, files in os.walk(directory):
        # Skip unwanted directories
        dirs[:] = [d for d in dirs if d not in skip_dirs]
        
        for file in files:
            if Path(file).suffix in extensions:
                filepath = os.path.join(root, file)
                try:
                    with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                        content = f.read()
                        
                        # Pattern 1: _('string') or _("string") - most reliable
                        quoted_matches = re.findall(r'_\([\'"]([^\'"]+)[\'"]\)', content)
                        for match in quoted_matches:
                            string = clean_translation_string(match)
                            if string and is_likely_translatable(string):
                                translation_strings.add(string)
                                file_sources[string].append(filepath)
                        
                        # Pattern 2: _(string)_ - template pattern with proper nested parentheses handling
                        pos = 0
                        while True:
                            # Find start of _( pattern
                            start_match = re.search(r'_\(', content[pos:])
                            if not start_match:
                                break
                            
                            start_pos = pos + start_match.start()
                            
                            # Extract content with nested parentheses
                            content_str, end_pos = extract_nested_parens(content, start_pos + 1)
                            
                            if content_str is not None:
                                # Check if it ends with )_ (fixed logic)
                                if end_pos < len(content) and content[end_pos] == '_':
                                    string = clean_translation_string(content_str)
                                    if string and is_likely_translatable(string):
                                        translation_strings.add(string)
                                        file_sources[string].append(filepath)
                            
                            pos = end_pos if content_str is not None else start_pos + 2
                        
                        # Pattern 3: Very specific pattern for unquoted strings that look like real text
                        unquoted_matches = re.findall(r'_\(([A-Za-z][^)]{2,50})\)', content)
                        for match in unquoted_matches:
                            string = clean_translation_string(match)
                            if string and is_likely_translatable(string):
                                translation_strings.add(string)
                                file_sources[string].append(filepath)
                                
                except Exception as e:
                    print(f"Error reading {filepath}: {e}", file=sys.stderr)
    
    return sorted(list(translation_strings)), file_sources

def create_po_file(translation_strings, file_sources, output_file):
    """Create a clean .po file from the extracted translation strings."""
    
    po_content = [
        '# Translation file for Unraid webgui',
        '# This file was automatically generated',
        '# Contains translatable strings found in the codebase',
        '#',
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: Unraid webgui\\n"',
        '"Report-Msgid-Bugs-To: \\n"',
        '"POT-Creation-Date: 2024-01-01 12:00+0000\\n"',
        '"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"',
        '"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"',
        '"Language-Team: LANGUAGE <LL@li.org>\\n"',
        '"Language: \\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Plural-Forms: nplurals=INTEGER; plural=EXPRESSION;\\n"',
        '',
    ]
    
    for string in translation_strings:
        # Escape quotes and newlines for .po format
        escaped_string = string.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')
        
        # Add source file information as comments
        sources = file_sources.get(string, [])
        if sources:
            po_content.append(f'# Found in: {", ".join(sources[:3])}')  # Show first 3 sources
            if len(sources) > 3:
                po_content.append(f'# ... and {len(sources) - 3} more files')
        
        po_content.extend([
            f'msgid "{escaped_string}"',
            f'msgstr ""',
            ''
        ])
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write('\n'.join(po_content))

def create_summary_report(translation_strings, file_sources, output_file):
    """Create a summary report of the extraction."""
    
    report_content = [
        '# Translation Extraction Summary',
        f'# Total unique strings found: {len(translation_strings)}',
        f'# Generated on: {__import__("datetime").datetime.now().strftime("%Y-%m-%d %H:%M:%S")}',
        '',
        '## String Categories:',
        ''
    ]
    
    # Categorize strings
    categories = {
        'UI Elements': [],
        'Messages': [],
        'Actions': [],
        'Status': [],
        'Errors': [],
        'Other': []
    }
    
    for string in translation_strings:
        lower_string = string.lower()
        if any(word in lower_string for word in ['button', 'label', 'title', 'name', 'field']):
            categories['UI Elements'].append(string)
        elif any(word in lower_string for word in ['error', 'failed', 'invalid', 'missing']):
            categories['Errors'].append(string)
        elif any(word in lower_string for word in ['start', 'stop', 'create', 'delete', 'update', 'install']):
            categories['Actions'].append(string)
        elif any(word in lower_string for word in ['status', 'running', 'stopped', 'connected', 'disconnected']):
            categories['Status'].append(string)
        elif any(word in lower_string for word in ['message', 'info', 'note', 'warning']):
            categories['Messages'].append(string)
        else:
            categories['Other'].append(string)
    
    for category, strings in categories.items():
        report_content.extend([
            f'### {category} ({len(strings)} strings):',
            ''
        ])
        for string in strings[:10]:  # Show first 10 examples
            report_content.append(f'- {string}')
        if len(strings) > 10:
            report_content.append(f'- ... and {len(strings) - 10} more')
        report_content.append('')
    
    # File statistics
    report_content.extend([
        '## File Statistics:',
        ''
    ])
    
    file_counts = defaultdict(int)
    for sources in file_sources.values():
        for source in sources:
            file_counts[source] += 1
    
    top_files = sorted(file_counts.items(), key=lambda x: x[1], reverse=True)[:10]
    for filepath, count in top_files:
        report_content.append(f'- {filepath}: {count} strings')
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write('\n'.join(report_content))

def main():
    if len(sys.argv) != 2:
        print("Usage: python extract_translations.py <source_directory>")
        sys.exit(1)
    
    source_dir = sys.argv[1]
    
    if not os.path.exists(source_dir):
        print(f"Error: Source directory '{source_dir}' does not exist")
        sys.exit(1)
    
    print(f"Extracting translation strings from {source_dir}...")
    translation_strings, file_sources = extract_translation_strings(source_dir)
    
    print(f"Found {len(translation_strings)} unique translation strings")
    
    # Create .po file
    po_file = "unraid-webgui.po"
    print(f"Creating .po file: {po_file}")
    create_po_file(translation_strings, file_sources, po_file)
    
    # Create summary report
    report_file = "translation-extraction-report.txt"
    print(f"Creating summary report: {report_file}")
    create_summary_report(translation_strings, file_sources, report_file)
    
    print("Done!")
    print(f"Files created:")
    print(f"  - {po_file}: .po file with {len(translation_strings)} strings")
    print(f"  - {report_file}: Summary report")

if __name__ == "__main__":
    main() 