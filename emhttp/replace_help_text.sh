#!/bin/bash

# Function to get help text for a marker
get_help_text() {
    local marker=$1
    local help_text=""
    local in_section=0
    
    # Read the help text file
    while IFS= read -r line; do
        # Only match markers that are not :end
        if [[ $line =~ ^:[^:]+:$ ]] && [[ $line != ":end" ]]; then
            if [[ $line == "$marker" ]]; then
                in_section=1
                continue
            else
                in_section=0
            fi
        fi
        if [[ $in_section -eq 1 ]]; then
            # Stop if we hit another marker or :end or empty line
            if [[ $line =~ ^:[^:]+:$ ]] || [[ $line == ":end" ]] || [[ -z $line ]]; then
                break
            fi
            # Add the line to help text, prefixing with '> '
            help_text+="> $line\n"
        fi
    done < "languages/en_US/helptext.txt"
    # Remove trailing newline
    help_text=${help_text%\\n}
    echo -e "$help_text"
    return 0
}

# Process all .page files
find . -name "*.page" -type f | while read -r file; do
    echo "Processing $file"
    
    # Create a temporary file
    temp_file=$(mktemp)
    
    # Process the file line by line
    while IFS= read -r line; do
        if [[ $line =~ ^:[^:]+:$ ]] && [[ $line != ":end" ]]; then
            # Extract the marker
            marker=$line
            echo "Found marker: $marker"
            
            # Get help text for the marker
            help_text=$(get_help_text "$marker")
            
            if [ $? -eq 0 ]; then
                # Replace the marker with help text
                echo -e "$help_text" >> "$temp_file"
            else
                # If no help text found, keep the original line
                echo "$line" >> "$temp_file"
            fi
        else
            # Keep non-marker lines as is
            echo "$line" >> "$temp_file"
        fi
    done < "$file"
    
    # Replace original file with modified content
    mv "$temp_file" "$file"
done 