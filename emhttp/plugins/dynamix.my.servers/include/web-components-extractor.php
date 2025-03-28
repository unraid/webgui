<?php
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

class WebComponentsExtractor
{
    private const PREFIXED_PATH = '/plugins/dynamix.my.servers/unraid-components/';
    private const RICH_COMPONENTS_ENTRY = 'unraid-components.client.mjs';
    private const UI_ENTRY = 'src/register.ts';
    private const UI_STYLES_ENTRY = 'style.css';

    public function __construct() {}

    private function findManifestFiles(string $manifestName): array
    {
        $basePath = '/usr/local/emhttp' . self::PREFIXED_PATH;
        $escapedBasePath = escapeshellarg($basePath);
        $escapedManifestName = escapeshellarg($manifestName);
        $command = "find {$escapedBasePath} -name {$escapedManifestName}";
        exec($command, $files);
        return $files;
    }

    public function getAssetPath(string $asset, string $subfolder = ''): string
    {
        return self::PREFIXED_PATH . ($subfolder ? $subfolder . '/' : '') . $asset;
    }

    private function getRelativePath(string $fullPath): string
    {
        $basePath = '/usr/local/emhttp' . self::PREFIXED_PATH;
        $relative = str_replace($basePath, '', $fullPath);
        return dirname($relative);
    }

    public function getManifestContents(string $manifestPath): array
    {
        $contents = @file_get_contents($manifestPath);
        return $contents ? json_decode($contents, true) : [];
    }

    private function getRichComponentsFile(): string
    {
        $manifestFiles = $this->findManifestFiles('manifest.json');
        
        foreach ($manifestFiles as $manifestPath) {
            $manifest = $this->getManifestContents($manifestPath);
            $subfolder = $this->getRelativePath($manifestPath);
            
            foreach ($manifest as $key => $value) {
                if (strpos($key, self::RICH_COMPONENTS_ENTRY) !== false && isset($value["file"])) {
                    return ($subfolder ? $subfolder . '/' : '') . $value["file"];
                }
            }
        }
        return '';
    }

    private function getRichComponentsScript(): string
    {
        $jsFile = $this->getRichComponentsFile();
        if (empty($jsFile)) {
            return '<script>console.error("%cNo matching key containing \'' . self::RICH_COMPONENTS_ENTRY . '\' found.", "font-weight: bold; color: white; background-color: red");</script>';
        }
        return '<script src="' . $this->getAssetPath($jsFile) . '"></script>';
    }

    private function getUnraidUiScriptHtml(): string
    {
        $manifestFiles = $this->findManifestFiles('ui.manifest.json');
        
        if (empty($manifestFiles)) {
            error_log("No ui.manifest.json found");
            return '';
        }

        $manifestPath = $manifestFiles[0]; // Use the first found manifest
        $manifest = $this->getManifestContents($manifestPath);
        $subfolder = $this->getRelativePath($manifestPath);

        if (!isset($manifest[self::UI_ENTRY]) || !isset($manifest[self::UI_STYLES_ENTRY])) {
            error_log("Required entries not found in ui.manifest.json");
            return '';
        }

        $jsFile = ($subfolder ? $subfolder . '/' : '') . $manifest[self::UI_ENTRY]['file'];
        $cssFile = ($subfolder ? $subfolder . '/' : '') . $manifest[self::UI_STYLES_ENTRY]['file'];

        return '<script defer type="module">
            import { registerAllComponents } from "' . $this->getAssetPath($jsFile) . '";
            registerAllComponents({ pathToSharedCss: "' . $this->getAssetPath($cssFile) . '" });
        </script>';
    }

    public function getScriptTagHtml(): string
    {
        try {
            return $this->getRichComponentsScript() . $this->getUnraidUiScriptHtml();
        } catch (\Exception $e) {
            error_log("Error in WebComponentsExtractor::getScriptTagHtml: " . $e->getMessage());
            return "";
        }
    }
}
