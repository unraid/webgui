#!/usr/bin/env php
<?php
/**
 * Remove duplicate msgid entries from a .po file, keeping the first occurrence.
 *
 * Usage:
 *     php cleanup_po_duplicates.php input.po [output.po]
 */

function cleanupPoDuplicates($inputFile, $outputFile = null) {
    if (!file_exists($inputFile)) {
        $scriptDir = __DIR__;
        $currentDir = getcwd();
        return [false, "Input file not found: $inputFile\n" .
                      "Script directory: $scriptDir\n" .
                      "Current directory: $currentDir\n" .
                      "Please check that the file exists at the specified path."];
    }
    
    if ($outputFile === null) {
        // Generate output filename in same directory as input file
        $outputFile = $inputFile . '.cleaned';
    }
    
    $content = file_get_contents($inputFile);
    if ($content === false) {
        return [false, "Failed to read file: $inputFile"];
    }
    
    // Ensure content is treated as UTF-8
    if (!mb_check_encoding($content, 'UTF-8')) {
        // Try to convert to UTF-8
        $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
    }
    
    // Normalize line endings to \n
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    
    // Split into entries (separated by blank lines)
    $entries = [];
    $currentEntry = [];
    $seenMsgids = [];
    $duplicateCount = 0;
    $totalEntries = 0;
    
    $lines = explode("\n", $content);
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);
        
        // Check if this is a blank line (entry separator)
        if ($trimmed === '') {
            if (!empty($currentEntry)) {
                // Process the entry
                $msgid = extractMsgid($currentEntry);
                
                if ($msgid === null) {
                    // No msgid (header or comment-only), always keep
                    $entries[] = $currentEntry;
                } elseif ($msgid === '') {
                    // Empty msgid (header), always keep - don't track in seenMsgids
                    $entries[] = $currentEntry;
                } elseif (isset($seenMsgids[$msgid])) {
                    // Duplicate - skip it
                    $duplicateCount++;
                } else {
                    // First occurrence - keep it
                    $seenMsgids[$msgid] = true;
                    $entries[] = $currentEntry;
                    $totalEntries++;
                }
                $currentEntry = [];
            }
            // Keep blank lines between entries
            if (!empty($entries) && end($entries) !== []) {
                $entries[] = [];
            }
        } else {
            $currentEntry[] = $line;
        }
    }
    
    // Don't forget the last entry
    if (!empty($currentEntry)) {
        $msgid = extractMsgid($currentEntry);
        if ($msgid === null || $msgid === '') {
            // No msgid or empty msgid (header), always keep
            $entries[] = $currentEntry;
        } elseif (isset($seenMsgids[$msgid])) {
            // Duplicate - skip it
            $duplicateCount++;
        } else {
            // First occurrence - keep it
            $seenMsgids[$msgid] = true;
            $entries[] = $currentEntry;
            $totalEntries++;
        }
    }
    
    // Rebuild the file
    $output = [];
    foreach ($entries as $entry) {
        if (empty($entry)) {
            $output[] = '';
        } else {
            $output = array_merge($output, $entry);
        }
    }
    
    $outputContent = implode("\n", $output);
    // Ensure file ends with newline if original did
    if (substr($content, -1) === "\n" && substr($outputContent, -1) !== "\n") {
        $outputContent .= "\n";
    }
    
    if (file_put_contents($outputFile, $outputContent) === false) {
        return [false, "Failed to write output file: $outputFile"];
    }
    
    return [true, [
        'output' => $outputFile,
        'total_entries' => $totalEntries,
        'duplicates_removed' => $duplicateCount,
        'unique_entries' => count($seenMsgids)
    ]];
}

function extractMsgid($entryLines) {
    $msgid = null;
    $inMsgid = false;
    $msgidValue = '';
    
    foreach ($entryLines as $line) {
        $trimmed = trim($line);
        
        // Match msgid line - handle both single-line and start of multi-line
        // Use non-greedy match and handle escaped quotes properly
        if (preg_match('/^msgid\s+"(.*)"\s*$/', $trimmed, $matches)) {
            $msgidValue = $matches[1];
            $inMsgid = true;
        } elseif ($inMsgid && preg_match('/^"(.*)"\s*$/', $trimmed, $matches)) {
            // Multi-line msgid continuation
            $msgidValue .= $matches[1];
        } elseif ($inMsgid) {
            // Check if we've reached the end of msgid (msgstr, msgid_plural, or blank line)
            if (preg_match('/^msg(str|id_plural)/', $trimmed) || empty($trimmed)) {
                // End of msgid
                break;
            }
        }
    }
    
    if ($msgidValue === '') {
        return null;
    }
    
    // Unescape the msgid value (handles \", \\, \n, etc.)
    // This converts escaped sequences like \" to ", \\ to \, \n to newline, etc.
    $msgidValue = stripcslashes($msgidValue);
    
    return $msgidValue;
}

// Helper function to resolve file path
function resolveFilePath($filename, $preferScriptDir = false) {
    // If filename already contains a path separator, use as-is (but try to resolve it)
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        // Try to resolve to real path if it exists
        $resolved = @realpath($filename);
        return $resolved !== false ? $resolved : $filename;
    }
    
    // Get script directory and current working directory
    $scriptDir = __DIR__;
    $currentDir = getcwd();
    
    // Build list of locations to check
    $locations = [];
    if ($preferScriptDir) {
        // For input files: check script directory first (where files are when running ./scriptname)
        $locations[] = $scriptDir;
        if ($currentDir !== $scriptDir) {
            $locations[] = $currentDir;
        }
    } else {
        // For output files: check current directory first, then script directory
        $locations[] = $currentDir;
        if ($scriptDir !== $currentDir) {
            $locations[] = $scriptDir;
        }
    }
    
    // Check each location
    foreach ($locations as $dir) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        // Try realpath first (resolves symlinks and normalizes)
        $resolved = @realpath($fullPath);
        if ($resolved !== false) {
            return $resolved;
        }
        // Also check if file exists directly (in case realpath fails)
        if (file_exists($fullPath)) {
            return $fullPath;
        }
    }
    
    // If not found, return path using preferred directory
    $preferredDir = $preferScriptDir ? $scriptDir : $currentDir;
    return $preferredDir . DIRECTORY_SEPARATOR . $filename;
}

function main($argv) {
    if (count($argv) < 2) {
        fwrite(STDERR, "Usage: php cleanup_po_duplicates.php input.po [output.po]\n");
        exit(1);
    }
    
    $inputFile = $argv[1];
    $outputFile = $argv[2] ?? null;
    
    // Resolve paths - for input file, prefer script directory
    $inputFile = resolveFilePath($inputFile, true);
    // For output file, prefer current directory (but resolve if provided)
    if ($outputFile !== null) {
        $outputFile = resolveFilePath($outputFile, false);
    }
    
    echo "Processing $inputFile...\n";
    list($success, $result) = cleanupPoDuplicates($inputFile, $outputFile);
    
    if (!$success) {
        fwrite(STDERR, "Error: $result\n");
        exit(1);
    }
    
    echo "✓ Cleaned file written to: {$result['output']}\n";
    echo "  Total entries processed: {$result['total_entries']}\n";
    echo "  Duplicates removed: {$result['duplicates_removed']}\n";
    echo "  Unique entries: {$result['unique_entries']}\n";
}

main($argv);
