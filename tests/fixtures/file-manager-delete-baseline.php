<?php
// Pre-follow-up delete command from PR #2787, commit
// 3b8086ee19cc94f1ad507e17af58026d14645161. Test data only; do not include.
function delete_command($sources, $action) {
  $paths = [];
  foreach ($sources as $name) {
    $name = rtrim($name, '/');
    if ($name !== truepath($name) || !preg_match('#^/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#', $name)) return '';
    $parent = resolve_rsync_path(dirname($name));
    if (!$parent) return '';
    $path = $parent.'/'.basename($name);
    if (!preg_match('#^/(?:mnt/[^/]+/[^/]+|boot/[^/]+)(?:/|$)#', $path) || @lstat($path) === false) return '';
    if ($action == 6 && !is_link($path) && is_dir($path)) return '';
    $paths[] = escapeshellarg($path);
  }
  if (!$paths) return '';
  // File actions never recurse, even if the selected entry changes to a folder.
  return 'find '.implode(' ', $paths).($action == 6 ? ' -maxdepth 0' : '').' -name "*" -print -delete';
}
