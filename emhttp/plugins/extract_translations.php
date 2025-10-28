#!/usr/bin/php
<?php
/**
 * Translation String Extractor
 * 
 * This script scans through the plugins folder looking for translation strings
 * and organizes them by file type:
 * - JavaScript files (.js) → translations_js.po
 * - PHP/HTML files (.php, .page, .html, .htm) → translations_php.po
 * 
 * Patterns detected:
 * 1. _(text)_
 * 2. _('text') or _("text")
 * 3. tr('text') or tr("text")
 */

// Configuration
$baseDir = __DIR__;
//$baseDir = "/Volumes/GitHub/community.applications/source/community.applications/usr/local/emhttp/plugins/community.applications";
$outputFileJS = $baseDir . '/translations_js.po';
$outputFilePHP = $baseDir . '/translations_php.po';
$fileExtensions = ['js', 'html', 'htm', 'php', 'page', '']; // '' for files with no extension
$jsExtensions = ['js'];
$phpExtensions = ['html', 'htm', 'php', 'page', '']; // '' for files with no extension

// Pattern 1: Match _(text)_ - handles escaped characters and nested content
$pattern1 = '/_\(((?:[^)]|\\.)*?)\)_/s';

// Pattern 2: Match _('text') or _("text") - function call pattern
$pattern2 = '/_\((["\'])((?:(?!\1).)*)\1\)/s';

// Pattern 3: Match tr('text') or tr("text") - function call pattern
$pattern3 = '/\btr\((["\'])((?:(?!\1).)*)\1\)/s';

echo "Translation String Extractor\n";
echo "============================\n\n";
echo "Base directory: $baseDir\n";
echo "Output files:\n";
echo "  - $outputFileJS (for JavaScript files)\n";
echo "  - $outputFilePHP (for PHP/HTML files)\n\n";

// Store found strings with their locations
$translationsJS = [];
$translationsPHP = [];
$fileCountJS = 0;
$fileCountPHP = 0;

/**
 * Recursively scan directory for files
 */
function scanDirectory($dir, $extensions, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, &$translationsJS, &$translationsPHP, &$fileCountJS, &$fileCountPHP) {
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            // Recursively scan subdirectories
            scanDirectory($path, $extensions, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $translationsJS, $translationsPHP, $fileCountJS, $fileCountPHP);
        } elseif (is_file($path)) {
            // Check if file has one of the target extensions (case-insensitive)
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                processFile($path, $ext, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $translationsJS, $translationsPHP, $fileCountJS, $fileCountPHP);
            }
        }
    }
}

/**
 * Process a single file for all patterns
 */
function processFile($filePath, $ext, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, &$translationsJS, &$translationsPHP, &$fileCountJS, &$fileCountPHP) {
    // Skip the extract_translations.php script itself
    if (basename($filePath) === 'extract_translations.php') {
        return;
    }
    
    // Skip dynamix.js and all files in the ace directory
    if (basename($filePath) === 'dynamix.js' && strpos($filePath, 'dynamix/javascript/') !== false) {
        return;
    }
    if (strpos($filePath, '/dynamix/javascript/ace/') !== false) {
        return;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "Warning: Could not read file: $filePath\n";
        return;
    }
    
    // Determine target translation array based on file extension
    $isJS = in_array($ext, $jsExtensions);
    $fileType = $isJS ? 'JS' : 'PHP';
    
    // Process all patterns - route to appropriate translation array
    if ($isJS) {
        $found1 = processPattern1($filePath, $content, $pattern1, $translationsJS);
        $found2 = processPattern2($filePath, $content, $pattern2, $translationsJS);
        $found3 = processPattern3($filePath, $content, $pattern3, $translationsJS);
    } else {
        $found1 = processPattern1($filePath, $content, $pattern1, $translationsPHP);
        $found2 = processPattern2($filePath, $content, $pattern2, $translationsPHP);
        $found3 = processPattern3($filePath, $content, $pattern3, $translationsPHP);
    }
    
    $totalFound = $found1 + $found2 + $found3;
    
    if ($totalFound > 0) {
        if ($isJS) {
            $fileCountJS++;
        } else {
            $fileCountPHP++;
        }
        
        echo "Found $totalFound translation string(s) in [$fileType] " . basename($filePath);
        if ($found1 > 0) echo " (_(text)_: $found1)";
        if ($found2 > 0) echo " (_(): $found2)";
        if ($found3 > 0) echo " (tr(): $found3)";
        echo "\n";
    }
}

/**
 * Convert absolute path to relative path from base directory
 */
function getRelativePath($filePath, $baseDir) {
    // Normalize paths
    $baseDir = rtrim($baseDir, '/') . '/';
    if (strpos($filePath, $baseDir) === 0) {
        return substr($filePath, strlen($baseDir));
    }
    return $filePath;
}

/**
 * Process Pattern 1: _(text)_
 */
function processPattern1($filePath, $content, $pattern, &$translations) {
    global $baseDir;
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
            $relativePath = getRelativePath($filePath, $baseDir);
            $translations[$text][] = $relativePath . ':' . $lineNumber;
            $foundCount++;
        }
    }
    
    return $foundCount;
}

/**
 * Process Pattern 2: _('text') or _("text")
 */
function processPattern2($filePath, $content, $pattern, &$translations) {
    global $baseDir;
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
            $relativePath = getRelativePath($filePath, $baseDir);
            $translations[$text][] = $relativePath . ':' . $lineNumber;
            $foundCount++;
        }
    }
    
    return $foundCount;
}

/**
 * Process Pattern 3: tr('text') or tr("text")
 */
function processPattern3($filePath, $content, $pattern, &$translations) {
    global $baseDir;
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
            $relativePath = getRelativePath($filePath, $baseDir);
            $translations[$text][] = $relativePath . ':' . $lineNumber;
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

scanDirectory($baseDir, $fileExtensions, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $translationsJS, $translationsPHP, $fileCountJS, $fileCountPHP);

echo "\n";
echo "============================\n";
echo "Scan complete!\n\n";

// Report for JavaScript
echo "JavaScript Files:\n";
echo "  Files processed: $fileCountJS\n";
echo "  Unique strings found: " . count($translationsJS) . "\n";
echo "  Total occurrences: " . array_sum(array_map('count', $translationsJS)) . "\n\n";

// Report for PHP/HTML
echo "PHP/HTML Files:\n";
echo "  Files processed: $fileCountPHP\n";
echo "  Unique strings found: " . count($translationsPHP) . "\n";
echo "  Total occurrences: " . array_sum(array_map('count', $translationsPHP)) . "\n\n";

// Generate .po files
if (count($translationsJS) > 0) {
    echo "Generating $outputFileJS...\n";
    if (generatePoFile($outputFileJS, $translationsJS, "JavaScript translations")) {
        echo "Successfully created: $outputFileJS\n";
        echo "  Sample entries:\n";
        $sample = array_slice(array_keys($translationsJS), 0, 5);
        foreach ($sample as $text) {
            echo "    - \"$text\"\n";
        }
        if (count($translationsJS) > 5) {
            echo "    ... and " . (count($translationsJS) - 5) . " more\n";
        }
        echo "\n";
    } else {
        echo "Error: Could not write to $outputFileJS\n";
    }
} else {
    echo "No JavaScript translation strings found.\n\n";
}

if (count($translationsPHP) > 0) {
    echo "Generating $outputFilePHP...\n";
    if (generatePoFile($outputFilePHP, $translationsPHP, "PHP/HTML translations")) {
        echo "Successfully created: $outputFilePHP\n";
        echo "  Sample entries:\n";
        $sample = array_slice(array_keys($translationsPHP), 0, 5);
        foreach ($sample as $text) {
            echo "    - \"$text\"\n";
        }
        if (count($translationsPHP) > 5) {
            echo "    ... and " . (count($translationsPHP) - 5) . " more\n";
        }
    } else {
        echo "Error: Could not write to $outputFilePHP\n";
    }
} else {
    echo "No PHP/HTML translation strings found.\n";
}

echo "\nDone!\n";
?>

