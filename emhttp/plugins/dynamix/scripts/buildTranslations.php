#!/usr/bin/env php
<?php
/**
 * Build translations: Compile unraid.po to {locale}.mo
 * 
 * Takes a country code (locale) as parameter, finds the unraid.po file
 * in /usr/local/emhttp/languages/{locale}/, and compiles it to {locale}.mo
 * which is always stored in /usr/local/emhttp/locale/en_US/LC_MESSAGES/
 *
 * Usage:
 *     php buildTranslations.php <locale>
 *     php buildTranslations.php fr_FR
 *     php buildTranslations.php de_DE
 */
function runCleanupScript($inputFile, $outputFile) {
    // Get the cleanup script path (scripts/cleanup_po_duplicates.php relative to this script)
    $scriptDir = dirname(__DIR__);
    $cleanupScript = "$scriptDir/scripts/cleanup_po_duplicates.php";
    
    if (!file_exists($cleanupScript)) {
        return [false, "Cleanup script not found: $cleanupScript"];
    }
    
    // Run the cleanup script
    $command = 'php ' . escapeshellarg($cleanupScript) . ' ' . 
               escapeshellarg($inputFile) . ' ' . escapeshellarg($outputFile) . ' 2>&1';
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        $error = implode("\n", $output);
        return [false, "Cleanup script failed: $error"];
    }
    
    if (!file_exists($outputFile)) {
        return [false, "Cleanup script did not create output file: $outputFile"];
    }
    
    return [true, "Cleaned duplicates from $inputFile -> $outputFile"];
}

function findMsgfmt() {
    // Common locations for msgfmt
    $paths = [
        '/usr/bin/msgfmt',
        '/usr/local/bin/msgfmt',
        '/opt/local/bin/msgfmt',
        '/opt/homebrew/bin/msgfmt',
    ];
    
    // Check PATH first
    $msgfmt = trim(shell_exec('which msgfmt 2>/dev/null'));
    if (!empty($msgfmt) && is_executable($msgfmt)) {
        return $msgfmt;
    }
    
    // Check common paths
    foreach ($paths as $path) {
        if (file_exists($path) && is_executable($path)) {
            return $path;
        }
    }
    
    return null;
}

function buildTranslations($locale) {
    // Validate locale format (should be like en_US, fr_FR, etc.)
    if (empty($locale) || !preg_match('/^[a-z]{2}_[A-Z]{2}$/i', $locale)) {
        return [false, "Invalid locale format. Expected format: en_US, fr_FR, etc."];
    }
    
    // Source .po file location in languages directory
    $poFile = "/usr/local/emhttp/languages/$locale/unraid.po";
    
    // Output .mo file location (always in en_US directory)
    $outputDir = "/usr/local/emhttp/locale/en_US/LC_MESSAGES";
    $moFile = "$outputDir/$locale.mo";
    
    // Check if source file exists
    if (!file_exists($poFile)) {
        // If .po file doesn't exist, delete .mo file if it exists
        if (file_exists($moFile)) {
            if (unlink($moFile)) {
                return [true, "Source file not found: $poFile. Deleted existing $moFile"];
            } else {
                return [false, "Source file not found: $poFile. Failed to delete existing $moFile"];
            }
        } else {
            return [true, "Source file not found: $poFile. No .mo file to delete."];
        }
    }
    
    // Clean up duplicates first - save cleaned file to /tmp
    $cleanedPoFile = "/tmp/unraid_$locale.po";
    list($cleanupSuccess, $cleanupMessage) = runCleanupScript($poFile, $cleanedPoFile);
    if (!$cleanupSuccess) {
        return [false, $cleanupMessage];
    }
    
    // Create output directory if it doesn't exist
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            return [false, "Failed to create output directory: $outputDir"];
        }
    }
    
    // Find msgfmt
    $msgfmt = findMsgfmt();
    if ($msgfmt === null) {
        return [false, "msgfmt not found. Please install gettext package."];
    }
    
    // Compile cleaned .po to .mo
    $command = escapeshellarg($msgfmt) . ' ' . escapeshellarg($cleanedPoFile) . 
               ' -o ' . escapeshellarg($moFile) . ' 2>&1';
    
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        $error = implode("\n", $output);
        return [false, "msgfmt failed: $error"];
    }
    
    if (!file_exists($moFile)) {
        // Clean up temp file
        @unlink($cleanedPoFile);
        return [false, "Output file was not created: $moFile"];
    }
    
    // Clean up temp file
    @unlink($cleanedPoFile);
    
    $size = filesize($moFile);
    return [true, "Compiled $poFile (cleaned) -> $moFile ($size bytes)"];
}

function main($argv) {
    if (count($argv) < 2) {
        fwrite(STDERR, "Usage: php buildTranslations.php <locale>\n");
        fwrite(STDERR, "Example: php buildTranslations.php fr_FR\n");
        exit(1);
    }
    
    $locale = $argv[1];
    list($success, $message) = buildTranslations($locale);
    
    if ($success) {
        echo "$message\n";
        exit(0);
    } else {
        fwrite(STDERR, "Error: $message\n");
        exit(1);
    }
}

main($argv);

// set the domain to be non-existent to force gettext to drop its cache
setlocale(LC_ALL, 'en_US.UTF-8');
bindtextdomain("undefined", "/usr/local/emhttp/locale/");
bind_textdomain_codeset("undefined", "UTF-8");
textdomain("undefined");
gettext("drop cache");

?>

