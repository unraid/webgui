<?PHP
$docroot ??= ($_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp');
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  header('Content-Type: application/json');
  die(json_encode(['error' => 'POST required']));
}

$token = (string)($_POST['id'] ?? '');
if (!preg_match('/\A[0-9a-f]{64}\z/D',$token)) {
  http_response_code(400);
  header('Content-Type: application/json');
  die(json_encode(['error' => 'invalid plugin id']));
}

foreach (plugin_delete_roots() as $directory) {
  $root = realpath($directory);
  if (!$root) continue;
  foreach (glob($root.'/*.plg',GLOB_NOSORT) ?: [] as $file) {
    if (is_link($file) || realpath($file) !== $file || dirname($file) !== $root) continue;
    if (hash_equals($token,plugin_delete_token($file))) {
      if (@unlink($file)) {
        header('Content-Type: application/json');
        die(json_encode(['success' => true]));
      }
      http_response_code(500);
      header('Content-Type: application/json');
      die(json_encode(['error' => 'unable to delete plugin file']));
    }
  }
}

http_response_code(404);
header('Content-Type: application/json');
die(json_encode(['error' => 'plugin not found']));
