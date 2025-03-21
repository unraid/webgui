<?php
/**
 * This file exists to maintain separation of concerns between UnraidCheck and ReplaceKey.
 * Instead of combining both classes directly, we utilize the unraidcheck script which already
 * handles both operations in a simplified manner.
 * 
 * It's called via the WebguiCheckForUpdate function in composables/services/webgui.ts of the web components.
 * Handles WebguiUnraidCheckExecPayload interface parameters:
 * - altUrl?: string
 * - json?: boolean
 */
class UnraidCheckExec
{
    private const SCRIPT_PATH = '/usr/local/emhttp/plugins/dynamix.plugin.manager/scripts/unraidcheck';
    private const ALLOWED_DOMAIN = 'releases.unraid.net';

    private function setupEnvironment(): void
    {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Content-Security-Policy: default-src \'none\'');

        $params = [
            'json' => 'true',
        ];

        if (isset($_GET['altUrl'])) {
            $url = filter_var($_GET['altUrl'], FILTER_VALIDATE_URL);
            if ($url !== false) {
                $host = parse_url($url, PHP_URL_HOST);
                $scheme = parse_url($url, PHP_URL_SCHEME);

                if ($host && $scheme === 'https' && (
                    $host === self::ALLOWED_DOMAIN ||
                    str_ends_with($host, '.' . self::ALLOWED_DOMAIN)
                )) {
                    $params['altUrl'] = $url;
                }
            }
        }

        putenv('QUERY_STRING=' . http_build_query($params));
    }

    public function execute(): string
    {
        // Validate script with all necessary permissions
        if (!is_file(self::SCRIPT_PATH) || 
            !is_readable(self::SCRIPT_PATH) || 
            !is_executable(self::SCRIPT_PATH)) {
            throw new RuntimeException('Script not found or not executable');
        }

        $this->setupEnvironment();
        $output = [];
        $command = escapeshellcmd(self::SCRIPT_PATH);
        if (exec($command, $output) === false) {
            throw new RuntimeException('Script execution failed');
        }

        return implode("\n", $output);
    }
}

// Usage
$checker = new UnraidCheckExec();
echo $checker->execute();
