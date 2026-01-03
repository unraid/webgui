<?PHP
/* Copyright 2005-2025, Lime Technology
 * Copyright 2012-2025, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
// Popular Destinations Management for File Manager
// Uses frequency-based scoring with decay

define('POPULAR_DESTINATIONS_FILE', '/boot/config/filemanager.json');
define('SCORE_INCREMENT', 10);
define('SCORE_DECAY', 1);
define('MAX_ENTRIES', 50);

/**
 * Load popular destinations from JSON file
 * Note: This is for read-only operations. For updates, use the atomic
 * read-modify-write operation in updatePopularDestinations().
 */
function loadPopularDestinations() {
  if (!file_exists(POPULAR_DESTINATIONS_FILE)) {
    return ['destinations' => []];
  }
  
  $json = file_get_contents(POPULAR_DESTINATIONS_FILE);
  $data = json_decode($json, true);
  
  if (!is_array($data) || !isset($data['destinations'])) {
    return ['destinations' => []];
  }
  
  return $data;
}

/**
 * Update popular destinations when a job is started
 * @param string $targetPath The destination path used in copy/move operation
 */
function updatePopularDestinations($targetPath) {
  // Skip empty paths or paths that are just /mnt or /boot
  if (empty($targetPath) || $targetPath == '/mnt' || $targetPath == '/boot') {
    return;
  }
  
  // Block path traversal attempts
  if (strpos($targetPath, '..') !== false) {
    exec('logger -t webGUI "Security: Blocked path traversal attempt in popular destinations: ' . escapeshellarg($targetPath) . '"');
    return;
  }
  
  // Normalize path (remove trailing slash)
  $targetPath = rtrim($targetPath, '/');
  
  // Open file for read+write, create if doesn't exist
  $fp = fopen(POPULAR_DESTINATIONS_FILE, 'c+');
  if ($fp === false) {
    exec('logger -t webGUI "Error: Cannot open popular destinations file: ' . POPULAR_DESTINATIONS_FILE . '"');
    return;
  }
  
  // Acquire exclusive lock for entire read-modify-write cycle
  if (!flock($fp, LOCK_EX)) {
    exec('logger -t webGUI "Error: Cannot lock popular destinations file: ' . POPULAR_DESTINATIONS_FILE . '"');
    fclose($fp);
    return;
  }
  
  // Read current data
  $json = stream_get_contents($fp);
  if ($json === false || $json === '') {
    $data = ['destinations' => []];
  } else {
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['destinations'])) {
      $data = ['destinations' => []];
    }
  }
  
  $destinations = $data['destinations'];
  
  // Find target path first (before decay)
  $found = false;
  $targetIndex = -1;
  foreach ($destinations as $index => $dest) {
    if ($dest['path'] === $targetPath) {
      $found = true;
      $targetIndex = $index;
      break;
    }
  }
  
  // Decay all scores by 1 (except the target path which we'll increment)
  foreach ($destinations as $index => &$dest) {
    if ($index !== $targetIndex) {
      $dest['score'] -= SCORE_DECAY;
    } else {
      // Target path: increment instead of decaying
      $dest['score'] += SCORE_INCREMENT;
    }
  }
  unset($dest);
  
  // If path not found, add it
  if (!$found) {
    $destinations[] = [
      'path' => $targetPath,
      'score' => SCORE_INCREMENT
    ];
  }
  
  // Remove entries with score <= 0
  $destinations = array_filter($destinations, function($dest) {
    return $dest['score'] > 0;
  });
  
  // Sort by score descending
  usort($destinations, function($a, $b) {
    return $b['score'] - $a['score'];
  });
  
  // Keep only MAX_ENTRIES
  if (count($destinations) > MAX_ENTRIES) {
    $destinations = array_slice($destinations, 0, MAX_ENTRIES);
  }
  
  // Re-index array
  $destinations = array_values($destinations);
  
  // Write back atomically (still holding the lock)
  $data['destinations'] = $destinations;
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  
  // Truncate and write from beginning
  if (ftruncate($fp, 0) === false || fseek($fp, 0) === -1) {
    exec('logger -t webGUI "Error: Cannot truncate popular destinations file: ' . POPULAR_DESTINATIONS_FILE . '"');
    flock($fp, LOCK_UN);
    fclose($fp);
    return;
  }
  
  $result = fwrite($fp, $json);
  if ($result === false) {
    exec('logger -t webGUI "Error: Failed to write popular destinations file: ' . POPULAR_DESTINATIONS_FILE . '"');
  }
  
  // Release lock and close file
  flock($fp, LOCK_UN);
  fclose($fp);
}

/**
 * Get top N popular destinations
 * @param int $limit Maximum number of destinations to return (default 5)
 * @return array Array of destination paths
 */
function getPopularDestinations($limit = 5) {
  $data = loadPopularDestinations();
  $destinations = $data['destinations'];
  
  // Sort by score descending (should already be sorted, but just in case)
  usort($destinations, function($a, $b) {
    return $b['score'] - $a['score'];
  });
  
  // Return top N paths
  $result = array_slice($destinations, 0, $limit);
  
  return array_map(function($dest) {
    return $dest['path'];
  }, $result);
}
?>
