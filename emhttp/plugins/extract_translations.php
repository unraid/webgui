#!/usr/bin/php
<?php
/**
 * Translation String Extractor
 * 
 * This script scans through the plugins folder looking for translation strings
 * in two formats:
 * 1. _(text)_ - outputs to translations.po
 * 2. _('text') or _("text") - outputs to translations_function.po
 */

// Configuration
$baseDir = __DIR__;
$outputFile1 = $baseDir . '/translations.po';
$outputFile2 = $baseDir . '/translations_function.po';
$fileExtensions = ['js', 'html', 'htm', 'php', 'page'];

// Pattern 1: Match _(text)_ - handles escaped characters and nested content
$pattern1 = '/_\(((?:[^)]|\\.)*?)\)_/s';

// Pattern 2: Match _('text') or _("text") - function call pattern
$pattern2 = '/_\((["\'])((?:(?!\1).)*)\1\)/s';

echo "Translation String Extractor\n";
echo "============================\n\n";
echo "Base directory: $baseDir\n";
echo "Output files:\n";
echo "  - $outputFile1 (for _(text)_ pattern)\n";
echo "  - $outputFile2 (for _('text') pattern)\n\n";

// Store found strings with their locations
$translations1 = [];
$translations2 = [];
$fileCount1 = 0;
$fileCount2 = 0;

/**
 * Recursively scan directory for files
 */
function scanDirectory($dir, $extensions, $pattern1, $pattern2, &$translations1, &$translations2, &$fileCount1, &$fileCount2) {
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            // Recursively scan subdirectories
            scanDirectory($path, $extensions, $pattern1, $pattern2, $translations1, $translations2, $fileCount1, $fileCount2);
        } elseif (is_file($path)) {
            // Check if file has one of the target extensions
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array($ext, $extensions)) {
                processFile($path, $pattern1, $pattern2, $translations1, $translations2, $fileCount1, $fileCount2);
            }
        }
    }
}

/**
 * Process a single file for both patterns
 */
function processFile($filePath, $pattern1, $pattern2, &$translations1, &$translations2, &$fileCount1, &$fileCount2) {
    // Skip the extract_translations.php script itself
    if (basename($filePath) === 'extract_translations.php') {
        return;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "Warning: Could not read file: $filePath\n";
        return;
    }
    
    $found1 = processPattern1($filePath, $content, $pattern1, $translations1);
    $found2 = processPattern2($filePath, $content, $pattern2, $translations2);
    
    if ($found1 > 0) {
        $fileCount1++;
        echo "Found $found1 _(text)_ string(s) in: " . basename($filePath) . "\n";
    }
    
    if ($found2 > 0) {
        $fileCount2++;
        echo "Found $found2 _('text') string(s) in: " . basename($filePath) . "\n";
    }
}

/**
 * Process Pattern 1: _(text)_
 */
function processPattern1($filePath, $content, $pattern, &$translations) {
    $matches = [];
    $count = preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
    $foundCount = 0;
    
    if ($count > 0) {
        foreach ($matches[1] as $match) {
            $text = trim($match[0]);
            $offset = $match[1];
            
            // Calculate line number from offset
            $beforeMatch = substr($content, 0, $offset);
            $lineNumber = substr_count($beforeMatch, "\n") + 1;
            
            // Remove surrounding quotes if present
            if ((substr($text, 0, 1) === '"' && substr($text, -1) === '"') ||
                (substr($text, 0, 1) === "'" && substr($text, -1) === "'")) {
                $text = substr($text, 1, -1);
            }
            
            // Filter out obvious regex patterns and false positives
            // Skip if it's mostly regex special characters or looks like a regex pattern
            $specialChars = preg_match_all('/[\/\^\$\*\+\?\{\}\[\]\\\|]/', $text, $temp);
            $totalChars = strlen($text);
            
            // If more than 30% of the string is regex special chars, it's likely a regex pattern
            if ($totalChars > 0 && ($specialChars / $totalChars) > 0.3) {
                continue;
            }
            
            // Skip patterns that start and end with / (regex delimiters)
            if (strlen($text) > 2 && substr($text, 0, 1) === '/' && substr($text, -1) === '/') {
                continue;
            }
            
            // Store the translation with its location and line number
            if (!isset($translations[$text])) {
                $translations[$text] = [];
            }
            $translations[$text][] = $filePath . ':' . $lineNumber;
            $foundCount++;
        }
    }
    
    return $foundCount;
}

/**
 * Process Pattern 2: _('text') or _("text")
 */
function processPattern2($filePath, $content, $pattern, &$translations) {
    $matches = [];
    $count = preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
    $foundCount = 0;
    
    if ($count > 0) {
        // Group 2 contains the actual text (group 1 is the quote type)
        foreach ($matches[2] as $match) {
            $text = trim($match[0]);
            $offset = $match[1];
            
            // Calculate line number from offset
            $beforeMatch = substr($content, 0, $offset);
            $lineNumber = substr_count($beforeMatch, "\n") + 1;
            
            // Text is already without quotes (captured between quotes)
            // Just unescape any escaped quotes
            $text = stripslashes($text);
            
            // Skip empty strings
            if (empty($text)) {
                continue;
            }
            
            // Store the translation with its location and line number
            if (!isset($translations[$text])) {
                $translations[$text] = [];
            }
            $translations[$text][] = $filePath . ':' . $lineNumber;
            $foundCount++;
        }
    }
    
    return $foundCount;
}

/**
 * Generate .po file
 */
function generatePoFile($outputFile, $translations, $patternName) {
    $content = "";
    
    // Add .po file header
    $content .= "# Translation file ($patternName)\n";
    $content .= "# Generated on " . date('Y-m-d H:i:s') . "\n";
    $content .= "#\n";
    $content .= "msgid \"\"\n";
    $content .= "msgstr \"\"\n";
    $content .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
    $content .= "\"Content-Transfer-Encoding: 8bit\\n\"\n\n";
    
    // Sort translations alphabetically
    ksort($translations);
    
    // Add each translation
    foreach ($translations as $text => $locations) {
        // Add reference comments (file locations)
        foreach (array_unique($locations) as $location) {
            $content .= "#: " . $location . "\n";
        }
        
        // Escape the text for .po format
        $escapedText = addcslashes($text, "\\\"\n\r\t"); // Add backslash to escape quotes and other special characters
        // Add msgid (original text)
        $content .= "msgid \"$escapedText\"\n";
        
        // Add empty msgstr (to be filled with translation)
        $content .= "msgstr \"\"\n\n";
    }
    
    // Write to file
    if (file_put_contents($outputFile, $content) !== false) {
        return true;
    }
    return false;
}

// Main execution
echo "Scanning for translation strings...\n\n";

scanDirectory($baseDir, $fileExtensions, $pattern1, $pattern2, $translations1, $translations2, $fileCount1, $fileCount2);

echo "\n";
echo "============================\n";
echo "Scan complete!\n\n";

// Report for Pattern 1: _(text)_
echo "Pattern _(text)_:\n";
echo "  Files processed: $fileCount1\n";
echo "  Unique strings found: " . count($translations1) . "\n";
echo "  Total occurrences: " . array_sum(array_map('count', $translations1)) . "\n\n";

// Report for Pattern 2: _('text')
echo "Pattern _('text'):\n";
echo "  Files processed: $fileCount2\n";
echo "  Unique strings found: " . count($translations2) . "\n";
echo "  Total occurrences: " . array_sum(array_map('count', $translations2)) . "\n\n";

// Generate .po files
if (count($translations1) > 0) {
    echo "Generating $outputFile1...\n";
    if (generatePoFile($outputFile1, $translations1, "_(text)_ pattern")) {
        echo "Successfully created: $outputFile1\n";
        echo "  Sample entries:\n";
        $sample = array_slice(array_keys($translations1), 0, 5);
        foreach ($sample as $text) {
            echo "    - \"$text\"\n";
        }
        if (count($translations1) > 5) {
            echo "    ... and " . (count($translations1) - 5) . " more\n";
        }
        echo "\n";
    } else {
        echo "Error: Could not write to $outputFile1\n";
    }
} else {
    echo "No _(text)_ translation strings found.\n\n";
}

if (count($translations2) > 0) {
    echo "Generating $outputFile2...\n";
    if (generatePoFile($outputFile2, $translations2, "_('text') pattern")) {
        echo "Successfully created: $outputFile2\n";
        echo "  Sample entries:\n";
        $sample = array_slice(array_keys($translations2), 0, 5);
        foreach ($sample as $text) {
            echo "    - \"$text\"\n";
        }
        if (count($translations2) > 5) {
            echo "    ... and " . (count($translations2) - 5) . " more\n";
        }
    } else {
        echo "Error: Could not write to $outputFile2\n";
    }
} else {
    echo "No _('text') translation strings found.\n";
}

echo "\nDone!\n";
?>

