<?php
// Function to recursively find JS files in a directory
function findJsFiles($directory) {
  if (!is_dir($directory)) {
    return [];
  }
  
  $jsFiles = [];
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
  );
  
  try {
    foreach ($iterator as $file) {
      if ($file->isFile() && $file->getExtension() === 'js') {
        $path = $file->getPathname();
        $baseDir = '/usr/local/emhttp';
        if (strpos($path, $baseDir) === 0) {
          $path = substr($path, strlen($baseDir));
        }
        $jsFiles[] = $path;
      }
    }
  } catch (Exception $e) {
    my_logger("Error scanning for JS files: " . $e->getMessage());
    return [];
  }
  
  return $jsFiles;
}

// Function to get JS files with caching
function getCachedJSFiles(string $directory, int $cacheLifetime = 300): array {
  $cacheDir   = sys_get_temp_dir();
  $cacheFile  = "$cacheDir/js_files_cache_" . md5($directory) . ".json";
  $lockFile   = "$cacheFile.lock";

  // Check if cache exists and is still valid
  $useCache = false;
  if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $useCache = true;
  }

  if ($useCache) {
    $data = @file_get_contents($cacheFile);
    if ($data === false) {
      my_logger("Warning: Unable to read JS cache at $cacheFile");
      $useCache = false;
    } else {
      $jsFiles = json_decode($data, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        my_logger("Warning: Corrupt JSON cache at $cacheFile");
        $useCache = false;
      } else {
        return $jsFiles;
      }
    }
  }

  // Acquire lock to rebuild cache safely
  $lockFp = @fopen($lockFile, 'w');
  if ($lockFp && flock($lockFp, LOCK_EX | LOCK_NB)) {
    try {
      $jsFiles = findJsFiles($directory);
      if (file_put_contents($cacheFile, json_encode($jsFiles)) === false) {
        my_logger("Warning: Could not write JS cache to $cacheFile");
      }
      flock($lockFp, LOCK_UN);
      fclose($lockFp);
      @unlink($lockFile);
      return $jsFiles;
    } catch (Exception $e) {
      my_logger("Error rebuilding JS cache: " . $e->getMessage());
      flock($lockFp, LOCK_UN);
      fclose($lockFp);
      @unlink($lockFile);
      return findJsFiles($directory);
    }
  }

  // Fallback: unable to lock, generate without caching
  if ($lockFp) {
    fclose($lockFp);
  }
  return findJsFiles($directory);
} 