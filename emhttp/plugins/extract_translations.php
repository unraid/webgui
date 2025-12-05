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
 * 4. ngettext('singular', 'plural', $n) - for plural forms
 */

// Configuration
$baseDir = __DIR__;
//$baseDir = "/Volumes/GitHub/community.applications/source/community.applications/usr/local/emhttp/plugins/community.applications";
$outputFileJS = $baseDir . '/translations_js.po';
$outputFilePHP = $baseDir . '/translations_php.po';
$fileExtensions = ['js', 'html', 'htm', 'php', 'page', '']; // '' for files with no extension
$jsExtensions = ['js'];
$phpExtensions = ['html', 'htm', 'php', 'page', '']; // '' for files with no extension

// Map of .page relative path => ['title' => string, 'line' => int]
$pageHeaders = [];

// Pattern 1: Match _(text)_ - handles escaped characters and nested content
$pattern1 = '/_\(((?:[^)]|\\.)*?)\)_/s';

// Pattern 2: Match _('text') or _("text") - function call pattern
$pattern2 = '/_\((["\'])((?:(?!\1).)*)\1\)/s';

// Pattern 3: Match tr('text') or tr("text") - function call pattern
$pattern3 = '/\btr\((["\'])((?:(?!\1).)*)\1\)/s';

// Pattern 4: Match ngettext('singular', 'plural', $n) - plural form pattern
$pattern4 = '/\bngettext\s*\(\s*(["\'])((?:(?!\1).)*)\1\s*,\s*(["\'])((?:(?!\3).)*)\3\s*,\s*[^)]+\)/s';

echo "Translation String Extractor\n";
echo "============================\n\n";
echo "Base directory: $baseDir\n";
echo "Output files:\n";
echo "  - $outputFileJS (for JavaScript files)\n";
echo "  - $outputFilePHP (for PHP/HTML files)\n\n";

// Store found strings with their locations
$translationsJS = [];
$translationsPHP = [];
$pluralsJS = []; // Store plural forms separately
$pluralsPHP = []; // Store plural forms separately
$fileCountJS = 0;
$fileCountPHP = 0;

/**
 * Recursively scan directory for files
 */
function scanDirectory($dir, $extensions, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $pattern4, &$translationsJS, &$translationsPHP, &$pluralsJS, &$pluralsPHP, &$fileCountJS, &$fileCountPHP) {
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            // Recursively scan subdirectories
            scanDirectory($path, $extensions, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $pattern4, $translationsJS, $translationsPHP, $pluralsJS, $pluralsPHP, $fileCountJS, $fileCountPHP);
        } elseif (is_file($path)) {
            // Check if file has one of the target extensions (case-insensitive)
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                processFile($path, $ext, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $pattern4, $translationsJS, $translationsPHP, $pluralsJS, $pluralsPHP, $fileCountJS, $fileCountPHP);
            }
        }
    }
}

/**
 * Process a single file for all patterns
 */
function processFile($filePath, $ext, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $pattern4, &$translationsJS, &$translationsPHP, &$pluralsJS, &$pluralsPHP, &$fileCountJS, &$fileCountPHP) {
    global $baseDir;
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
    
    // If this is a .page file, extract its Title/Name header for context comments later
    if (strtolower($ext) === 'page') {
        $headerMeta = extractPageHeaderTitle($filePath, $content);
        if (!empty($headerMeta['title'])) {
            $relativePath = getRelativePath($filePath, $baseDir);
            $GLOBALS['pageHeaders'][$relativePath] = $headerMeta;
        }
    }

    // Process all patterns - route to appropriate translation array
    if ($isJS) {
        $found1 = processPattern1($filePath, $content, $pattern1, $translationsJS);
        $found2 = processPattern2($filePath, $content, $pattern2, $translationsJS);
        $found3 = processPattern3($filePath, $content, $pattern3, $translationsJS);
        $found4 = processPattern4($filePath, $content, $pattern4, $pluralsJS);
    } else {
        $found1 = processPattern1($filePath, $content, $pattern1, $translationsPHP);
        $found2 = processPattern2($filePath, $content, $pattern2, $translationsPHP);
        $found3 = processPattern3($filePath, $content, $pattern3, $translationsPHP);
        $found4 = processPattern4($filePath, $content, $pattern4, $pluralsPHP);
    }
    
    $totalFound = $found1 + $found2 + $found3 + $found4;
    
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
        if ($found4 > 0) echo " (ngettext(): $found4)";
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
 * Extract Title or Name from a .page file header (before the '---' separator).
 * Returns ['title' => string, 'line' => int] or ['title' => '', 'line' => 0] if none found.
 */
function extractPageHeaderTitle($filePath, $content) {
    $maxHeaderBytes = 8192; // read a small portion from the start
    $headerSection = substr($content, 0, $maxHeaderBytes);

    // Stop at the first '---' line if present
    $posSeparator = strpos($headerSection, "\n---");
    if ($posSeparator !== false) {
        $headerSection = substr($headerSection, 0, $posSeparator);
    }

    $result = ['title' => '', 'line' => 0];

    // Prefer Title over Name; compute line number using offset
    if (preg_match('/^\s*Title="([^"]*)"/m', $headerSection, $m, PREG_OFFSET_CAPTURE)) {
        $result['title'] = trim($m[1][0]);
        $before = substr($headerSection, 0, $m[0][1]);
        $result['line'] = substr_count($before, "\n") + 1;
        return $result;
    }
    if (preg_match('/^\s*Name="([^"]*)"/m', $headerSection, $m, PREG_OFFSET_CAPTURE)) {
        $result['title'] = trim($m[1][0]);
        $before = substr($headerSection, 0, $m[0][1]);
        $result['line'] = substr_count($before, "\n") + 1;
        return $result;
    }

    return $result;
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
 * Process Pattern 4: ngettext('singular', 'plural', $n)
 */
function processPattern4($filePath, $content, $pattern, &$plurals) {
    global $baseDir;
    $matches = [];
    $count = preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
    $foundCount = 0;
    
    if ($count > 0) {
        // Group 2 contains singular text, group 4 contains plural text
        for ($i = 0; $i < count($matches[2]); $i++) {
            $singular = trim($matches[2][$i][0]);
            $plural = trim($matches[4][$i][0]);
            $offset = $matches[2][$i][1];
            
            // Calculate line number from offset
            $beforeMatch = substr($content, 0, $offset);
            $lineNumber = substr_count($beforeMatch, "\n") + 1;
            
            // Unescape any escaped quotes
            $singular = stripslashes($singular);
            $plural = stripslashes($plural);
            
            // Skip empty strings
            if (empty($singular) || empty($plural)) {
                continue;
            }
            
            // Store the plural translation with its location and line number
            // Use singular as key, store both singular and plural
            if (!isset($plurals[$singular])) {
                $plurals[$singular] = [
                    'plural' => $plural,
                    'locations' => []
                ];
            }
            $relativePath = getRelativePath($filePath, $baseDir);
            $plurals[$singular]['locations'][] = $relativePath . ':' . $lineNumber;
            $foundCount++;
        }
    }
    
    return $foundCount;
}

/**
 * Generate .po file
 */
function generatePoFile($outputFile, $translations, $patternName, $plurals = []) {
    $content = "";
    
    // Add .po file header
    $content .= "# Translation file ($patternName)\n";
    $content .= "# Generated on " . date('Y-m-d H:i:s') . "\n";
    $content .= "#\n";
    $content .= "msgid \"\"\n";
    $content .= "msgstr \"\"\n";
    $content .= "\"Project-Id-Version: Unraid WebGUI\\n\"\n";
    $content .= "\"Report-Msgid-Bugs-To: \\n\"\n";
    $content .= "\"POT-Creation-Date: " . date('Y-m-d H:i:sO') . "\\n\"\n";
    $content .= "\"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n\"\n";
    $content .= "\"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n\"\n";
    $content .= "\"Language-Team: English\\n\"\n";
    $content .= "\"Language: en_US\\n\"\n";
    $content .= "\"MIME-Version: 1.0\\n\"\n";
    $content .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
    $content .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
    $content .= "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";
    
    // Separate standard date/time translations from other translations
    $standardDateTimeTranslations = [];
    $standardDateTimePlurals = [];
    $otherTranslations = [];
    $otherPlurals = [];
    
    // Standard date/time keywords
    $standardKeywords = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December",
        "Jan", "Feb", "Mar", "Apr", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
        "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday",
        "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun",
        "am", "pm", "AM", "PM"
    ];
    
    $standardPluralKeywords = ["hour", "Hour", "minute", "Minute", "second", "Second", 
                                "day", "Day", "year", "Year", "sec", "Sec", "min", "Min"];
    
    // Separate translations
    foreach ($translations as $text => $locations) {
        if (in_array($text, $standardKeywords)) {
            $standardDateTimeTranslations[$text] = $locations;
        } else {
            $otherTranslations[$text] = $locations;
        }
    }
    
    // Separate plurals
    foreach ($plurals as $singular => $data) {
        if (in_array($singular, $standardPluralKeywords)) {
            $standardDateTimePlurals[$singular] = $data;
        } else {
            $otherPlurals[$singular] = $data;
        }
    }
    
    // Add section header for standard date/time translations
    if (!empty($standardDateTimeTranslations) || !empty($standardDateTimePlurals)) {
        $content .= "# ============================================================================\n";
        $content .= "# STANDARD DATE/TIME TRANSLATIONS\n";
        $content .= "# ============================================================================\n\n";
    }
    
    // Define logical order for standard date/time translations
    $orderedStandardTranslations = [
        // Full month names
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December",
        // Short month names
        "Jan", "Feb", "Mar", "Apr", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
        // Full day names
        "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday",
        // Short day names
        "Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat",
        // AM/PM markers
        "am", "pm", "AM", "PM"
    ];
    
    // Add standard date/time translations in logical order
    foreach ($orderedStandardTranslations as $text) {
        if (isset($standardDateTimeTranslations[$text])) {
            $locations = $standardDateTimeTranslations[$text];
            foreach (array_unique($locations) as $location) {
                $content .= "#: " . $location . "\n";
            }
            $escapedText = addcslashes($text, "\\\"\n\r\t");
            $content .= "msgid \"$escapedText\"\n";
            $content .= "msgstr \"\"\n\n";
        }
    }
    
    // Define logical order for plural forms
    $orderedStandardPlurals = [
        // Capitalized time units
        "Year", "Day", "Hour", "Minute", "Second",
        // Lowercase time units
        "year", "day", "hour", "minute", "second",
        // Short forms capitalized
        "Min", "Sec",
        // Short forms lowercase
        "min", "sec"
    ];
    
    // Add standard date/time plurals in logical order
    foreach ($orderedStandardPlurals as $singular) {
        if (isset($standardDateTimePlurals[$singular])) {
            $data = $standardDateTimePlurals[$singular];
            $plural = $data['plural'];
            $locations = $data['locations'];
            
            foreach (array_unique($locations) as $location) {
                $content .= "#: " . $location . "\n";
            }
            
            $escapedSingular = addcslashes($singular, "\\\"\n\r\t");
            $escapedPlural = addcslashes($plural, "\\\"\n\r\t");
            
            $content .= "msgid \"$escapedSingular\"\n";
            $content .= "msgid_plural \"$escapedPlural\"\n";
            $content .= "msgstr[0] \"\"\n";
            $content .= "msgstr[1] \"\"\n\n";
        }
    }
    
    // Add section header for other translations
    if (!empty($otherTranslations) || !empty($otherPlurals)) {
        $content .= "# ============================================================================\n";
        $content .= "# APPLICATION TRANSLATIONS\n";
        $content .= "# ============================================================================\n\n";
    }
    
    // Sort other translations alphabetically
    ksort($otherTranslations);
    
    // Build PAGE TITLES section as actual translatable entries
    $referencedPageFiles = [];
    foreach ($otherTranslations as $locations) {
        foreach (array_unique($locations) as $location) {
            $parts = explode(':', $location, 2);
            $filePart = $parts[0];
            if (substr($filePart, -5) === '.page') {
                $referencedPageFiles[$filePart] = true;
            }
        }
    }
    foreach ($otherPlurals as $data) {
        foreach (array_unique($data['locations']) as $location) {
            $parts = explode(':', $location, 2);
            $filePart = $parts[0];
            if (substr($filePart, -5) === '.page') {
                $referencedPageFiles[$filePart] = true;
            }
        }
    }

    if (!empty($referencedPageFiles)) {
        $content .= "# ============================================================================\n";
        $content .= "# PAGE TITLES\n";
        $content .= "# ============================================================================\n\n";
        ksort($referencedPageFiles);
        // Aggregate by title text to group multiple files using same title
        $pageTitleEntries = [];
        foreach (array_keys($referencedPageFiles) as $pagePath) {
            if (!isset($GLOBALS['pageHeaders'][$pagePath]['title']) || $GLOBALS['pageHeaders'][$pagePath]['title'] === '') continue;
            $title = $GLOBALS['pageHeaders'][$pagePath]['title'];
            $line = isset($GLOBALS['pageHeaders'][$pagePath]['line']) ? (int)$GLOBALS['pageHeaders'][$pagePath]['line'] : 1;
            if (!isset($pageTitleEntries[$title])) $pageTitleEntries[$title] = [];
            $pageTitleEntries[$title][] = $pagePath . ':' . $line;
        }
        ksort($pageTitleEntries);
        foreach ($pageTitleEntries as $title => $locations) {
            foreach (array_unique($locations) as $location) {
                $content .= "#: " . $location . "\n";
            }
            $escaped = addcslashes($title, "\\\"\n\r\t");
            $content .= "msgid \"$escaped\"\n";
            $content .= "msgstr \"\"\n\n";
        }
    }

    // Add each translation
    foreach ($otherTranslations as $text => $locations) {
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
    
    // Add other plural forms
    if (!empty($otherPlurals)) {
        ksort($otherPlurals);
        
        foreach ($otherPlurals as $singular => $data) {
            $plural = $data['plural'];
            $locations = $data['locations'];
            
            // Add reference comments (file locations)
            foreach (array_unique($locations) as $location) {
                $content .= "#: " . $location . "\n";
            }
            
            // Escape the texts for .po format
            $escapedSingular = addcslashes($singular, "\\\"\n\r\t");
            $escapedPlural = addcslashes($plural, "\\\"\n\r\t");
            
            // Add msgid and msgid_plural
            $content .= "msgid \"$escapedSingular\"\n";
            $content .= "msgid_plural \"$escapedPlural\"\n";
            
            // Add empty msgstr[0] and msgstr[1] (to be filled with translations)
            $content .= "msgstr[0] \"\"\n";
            $content .= "msgstr[1] \"\"\n\n";
        }
    }
    
    // Write to file
    if (file_put_contents($outputFile, $content) !== false) {
        return true;
    }
    return false;
}

// Main execution
echo "Scanning for translation strings...\n\n";

scanDirectory($baseDir, $fileExtensions, $jsExtensions, $phpExtensions, $pattern1, $pattern2, $pattern3, $pattern4, $translationsJS, $translationsPHP, $pluralsJS, $pluralsPHP, $fileCountJS, $fileCountPHP);

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

// Add standard date/time translations that should always be included
$standardTranslations = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
    "Jan", "Feb", "Mar", "Apr", "May", "Jun",
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
    "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday",
    "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun",
    "am", "pm", "AM", "PM"
];

$standardPlurals = [
    "hour" => "hours",
    "Hour" => "Hours",
    "minute" => "minutes",
    "Minute" => "Minutes",
    "second" => "seconds",
    "Second" => "Seconds",
    "day" => "days",
    "Day" => "Days",
    "year" => "years",
    "Year" => "Years",
    "sec" => "secs",
    "Sec" => "Secs",
    "min" => "mins",
    "Min" => "Mins"
];

// Add standard translations to PHP translations if not already present
foreach ($standardTranslations as $text) {
    if (!isset($translationsPHP[$text])) {
        $translationsPHP[$text] = ['(standard date/time translation)'];
    }
}

// Add standard plurals to PHP plurals if not already present
foreach ($standardPlurals as $singular => $plural) {
    if (!isset($pluralsPHP[$singular])) {
        $pluralsPHP[$singular] = [
            'plural' => $plural,
            'locations' => ['(standard date/time translation)']
        ];
    }
}

// Generate .po files
if (count($translationsJS) > 0 || count($pluralsJS) > 0) {
    echo "Generating $outputFileJS...\n";
    if (generatePoFile($outputFileJS, $translationsJS, "JavaScript translations", $pluralsJS)) {
        echo "Successfully created: $outputFileJS\n";
        echo "  Regular entries: " . count($translationsJS) . "\n";
        echo "  Plural entries: " . count($pluralsJS) . "\n";
        if (count($translationsJS) > 0) {
            echo "  Sample regular entries:\n";
            $sample = array_slice(array_keys($translationsJS), 0, 3);
            foreach ($sample as $text) {
                echo "    - \"$text\"\n";
            }
        }
        if (count($pluralsJS) > 0) {
            echo "  Sample plural entries:\n";
            $sample = array_slice(array_keys($pluralsJS), 0, 3);
            foreach ($sample as $text) {
                echo "    - \"$text\" / \"" . $pluralsJS[$text]['plural'] . "\"\n";
            }
        }
        echo "\n";
    } else {
        echo "Error: Could not write to $outputFileJS\n";
    }
} else {
    echo "No JavaScript translation strings found.\n\n";
}

if (count($translationsPHP) > 0 || count($pluralsPHP) > 0) {
    echo "Generating $outputFilePHP...\n";
    if (generatePoFile($outputFilePHP, $translationsPHP, "PHP/HTML translations", $pluralsPHP)) {
        echo "Successfully created: $outputFilePHP\n";
        echo "  Regular entries: " . count($translationsPHP) . "\n";
        echo "  Plural entries: " . count($pluralsPHP) . "\n";
        if (count($translationsPHP) > 0) {
            echo "  Sample regular entries:\n";
            $sample = array_slice(array_keys($translationsPHP), 0, 3);
            foreach ($sample as $text) {
                echo "    - \"$text\"\n";
            }
        }
        if (count($pluralsPHP) > 0) {
            echo "  Sample plural entries:\n";
            $sample = array_slice(array_keys($pluralsPHP), 0, 3);
            foreach ($sample as $text) {
                echo "    - \"$text\" / \"" . $pluralsPHP[$text]['plural'] . "\"\n";
            }
        }
    } else {
        echo "Error: Could not write to $outputFilePHP\n";
    }
} else {
    echo "No PHP/HTML translation strings found.\n";
}

echo "\nDone!\n";
?>

