#!/usr/bin/env python3

import os
import re
import glob

def read_helptext_sections(helptext_file):
    sections = {}
    current_section = None
    current_content = []
    
    with open(helptext_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.rstrip()
            if re.match(r'^:[^:]+:$', line):
                if current_section:
                    sections[current_section] = '\n'.join(current_content)
                current_section = line
                current_content = []
            elif line == ':end':
                if current_section:
                    sections[current_section] = '\n'.join(current_content)
                current_section = None
                current_content = []
            elif current_section:
                current_content.append(line)
    
    if current_section:
        sections[current_section] = '\n'.join(current_content)
    
    return sections

def process_file(file_path, helptext_sections, output_dir):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Find all help text markers
    markers = re.findall(r'^:[^:]+:$', content, re.MULTILINE)
    
    # Replace each marker with its help text
    for marker in markers:
        if marker in helptext_sections:
            help_text = helptext_sections[marker]
            # Add '> ' prefix to each line
            help_text = '\n'.join('> ' + line for line in help_text.split('\n'))
            content = content.replace(marker, help_text)
    
    # Write to output directory
    rel_path = os.path.relpath(file_path, '/Users/andrewzawadzki/Documents/webgui/emhttp')
    output_path = os.path.join(output_dir, rel_path)
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(content)

def main():
    base_dir = '/Users/andrewzawadzki/Documents/webgui/emhttp'
    helptext_file = os.path.join(base_dir, 'languages/en_US/helptext.txt')
    output_dir = '/tmp/emhttp_preview/modified'
    
    # Read all help text sections
    helptext_sections = read_helptext_sections(helptext_file)
    
    # Find all .page and .php files in plugins directory
    page_files = glob.glob(os.path.join(base_dir, 'plugins/**/*.page'), recursive=True)
    php_files = glob.glob(os.path.join(base_dir, 'plugins/**/*.php'), recursive=True)
    all_files = page_files + php_files
    
    # Process each file
    for file_path in all_files:
        process_file(file_path, helptext_sections, output_dir)
        print(f"Processed: {file_path}")

if __name__ == '__main__':
    main() 