<?php
// This evaluates the contents of PHP code.  Has to be "included" because the code is evaluated in the context of the calling page.
// $evalContent is the PHP code to evaluate.
// $evalFile is the file that the code is being evaluated in
// Errors will be logged in the console and the php error log
// The PHP error logged will also include the path of the .page file for easier debugging

$evalSuccess = false;
$evalFile = $evalFile ?? "Unknown";
if ( ! ($evalContent ?? false) ) {
  error_log("No evalContent within $evalFile");
  return;
}

ob_start();
try {
  set_error_handler(function($severity, $message, $file, $line) use ($evalFile) {
    // If error was suppressed with @, error_reporting will be 0
    if (!(error_reporting() & $severity)) {
      return true;
    }
    // Only convert fatal errors to exceptions
    $fatalErrors = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
    if ($severity & $fatalErrors) {
      error_log("PHP Fatal Error (Severity: $severity) in $evalFile: $message");
      throw new ErrorException($message, 0, $severity, $file, $line);
    } else {
      // Log non-fatal errors with evalFile context
      error_log("PHP Error (Severity: $severity) in $evalFile: $message at $file:$line");
    }
    // Let warnings and notices be handled normally
    return false;
  });
  eval($evalContent);
  restore_error_handler();
  $evalSuccess = true;
  ob_end_flush();
} catch (Throwable $e) {
  restore_error_handler();
  $severity = ($e instanceof ErrorException) ? $e->getSeverity() : 'N/A';
  error_log("Error evaluating content in $evalFile (severity: $severity): ".$e->getMessage()."\nStack trace:\n".$e->getTraceAsString());
  ob_clean();
  $errorMessage = "Error evaluating content in $evalFile: ".$e->getMessage();
  echo "<script>console.error(".json_encode($errorMessage).");</script>";
  ob_end_flush();   
}
?>      