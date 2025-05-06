<?php

class UnraidUpdateCancel
{
  private $PLG_FILENAME;
  private $PLG_BOOT;
  private $PLG_VAR;
  private $USR_LOCAL_PLUGIN_UNRAID_PATH;

  public function __construct() {
    $this->PLG_FILENAME = "unRAIDServer.plg";
    $this->PLG_BOOT = "/boot/config/plugins/{$this->PLG_FILENAME}";
    $this->PLG_VAR = "/var/log/plugins/{$this->PLG_FILENAME}";
    $this->USR_LOCAL_PLUGIN_UNRAID_PATH = "/usr/local/emhttp/plugins/unRAIDServer";

    // Handle the cancellation
    $revertResult = $this->revertFiles();
    // Return JSON response for front-end client
    $statusCode = $revertResult['success'] ? 200 : 500;
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($revertResult);
  }

  public function revertFiles() {
    try {
      $command = '/sbin/mount | grep -q "/boot/previous/bz"';
      exec($command, $output, $returnCode);

      if ($returnCode !== 0) {
        return ['success' => true]; // Nothing to revert
      }

      // Clear the results of previous unraidcheck run
      @unlink("/tmp/unraidcheck/result.json");

      // Revert changes made by unRAIDServer.plg
      shell_exec("mv -f /boot/previous/* /boot");
      unlink($this->PLG_BOOT);
      unlink($this->PLG_VAR);
      symlink("{$this->USR_LOCAL_PLUGIN_UNRAID_PATH}/{$this->PLG_FILENAME}", $this->PLG_VAR);

      // Restore README.md by echoing the content into the file
      $readmeFile = "{$this->USR_LOCAL_PLUGIN_UNRAID_PATH}/README.md";
      $readmeContent = "**Unraid OS**\n\n";
      $readmeContent .= "Unraid OS by [Lime Technology, Inc.](https://lime-technology.com).\n";
      file_put_contents($readmeFile, $readmeContent);

      return ['success' => true]; // Upgrade handled successfully
    } catch (\Throwable $th) {
      return [
        'success' => false,
        'message' => $th->getMessage(),
      ];
    }
  }
}

// Self instantiate the class and handle the cancellation
new UnraidUpdateCancel();
