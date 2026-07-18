<?PHP
/* Copyright 2005-2026, Lime Technology
 * Copyright 2012-2023, Bergware International.
 * License: GPLv2 only
 */

/**
 * Read one plugin attribute without the path-keyed request cache used by the
 * web UI. CLI checks call this before and after a downloaded file replacement.
 */
function plugin_attribute_uncached($method, $arg) {
  if (!$arg || !is_file($arg)) return false;
  $xml = @simplexml_load_file($arg, null, LIBXML_NOCDATA);
  if (!$xml) return false;
  $attributes = (array)$xml->attributes();
  if ($method == 'attributes') return json_encode($attributes['@attributes'] ?? []);
  return isset($attributes['@attributes'][$method])
    ? (string)$attributes['@attributes'][$method]
    : false;
}
