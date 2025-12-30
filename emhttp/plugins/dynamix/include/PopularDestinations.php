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
 * Save popular destinations to JSON file
 */
function savePopularDestinations($data) {
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  file_put_contents(POPULAR_DESTINATIONS_FILE, $json);
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
  
  // Normalize path (remove trailing slash)
  $targetPath = rtrim($targetPath, '/');
  
  // Load current data
  $data = loadPopularDestinations();
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
  
  // Save
  $data['destinations'] = $destinations;
  savePopularDestinations($data);
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
