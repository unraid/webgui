<?php
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

class ActivationCodeExtractor {
    public const DIR = '/boot/config/activation';
    public const FILE_PATTERN = '/activation_code_([A-Za-z0-9]+)\.activationcode/';

    public const DOCROOT = '/usr/local/emhttp';
    public const WEBGUI_IMAGES_BASE_DIR = '/webGui/images';
    public const PARTNER_LOGO_FILE_NAME = 'partner-logo.svg';
    public const DEFAULT_LOGO = self::DOCROOT . self::WEBGUI_IMAGES_BASE_DIR . '/UN-logotype-gradient.svg';

    /** @var array{
     *     code: string,
     *     partnerName: string,
     *     partnerUrl?: string,
     *     sysModel?: string,
     *     comment?: string,
     *     caseIcon: string,
     *     partnerLogo?: boolean,
     *     header?: string,
     *     headermetacolor?: string,
     *     background?: string,
     *     showBannerGradient?: string,
     *     theme?: "azure" | "black" | "gray" | "white
     * }
     */
    private array $data = [];
    private string $partnerName = '';
    private string $partnerUrl = 'https://unraid.net';
    private string $partnerLogoPath = '';

    /**
     * Constructor to automatically fetch JSON data from all matching files.
     */
    public function __construct() {
        $this->data = $this->fetchJsonData();
    }

    /**
     * Fetch JSON data from all files matching the pattern.
     * 
     * @return array Array of extracted JSON data.
     */
    private function fetchJsonData(): array {
        $data = [];

        if (!is_dir(self::DIR)) {
            return $data;
        }

        $files = scandir(self::DIR);

        if ($files === false || count($files) === 0) {
            return $data;
        }

        foreach ($files as $file) {
            $filePath = self::DIR . DIRECTORY_SEPARATOR . $file;

            if (preg_match(self::FILE_PATTERN, $file, $matches)) {
                // $activationCode = $matches[1];
                $fileContent = file_get_contents($filePath);
                $jsonData = json_decode($fileContent, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $jsonData;
                } else {
                    $data = ['error' => 'Invalid JSON format'];
                }

                break; // Stop after the first match
            }
        }

        if (isset($data['partnerName'])) {
            $this->partnerName = $data['partnerName'];
        }

        if (isset($data['partnerUrl'])) {
            $this->partnerUrl = $data['partnerUrl'];
        }

        /**
         * During the plg install, the partner logo asset is copied to the webgui images dir.
         */
        $logo = self::DOCROOT . self::WEBGUI_IMAGES_BASE_DIR . '/' . self::PARTNER_LOGO_FILE_NAME;
        if (file_exists($logo)) {
            $this->partnerLogoPath = $logo;
        }

        return $data;
    }

    /**
     * Get the partner logo path.
     * 
     * @return string
     */
    public function getPartnerLogoPath(): string {
        return $this->partnerLogoPath;
    }

    /**
     * Get the extracted data.
     * 
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Retrieve the activation code data as JSON string with converted special characters to HTML entities
     *
     * @return string
     */
    public function getDataForHtmlAttr(): string {
        $json = json_encode($this->getData());
        return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the partner logo render string.
     * 
     * @return string
     */
    public function getPartnerLogoRenderString(): string {
        if (empty($this->partnerLogoPath)) { // default logo
            return file_get_contents(self::DEFAULT_LOGO);
        }

        return file_get_contents($this->partnerLogoPath);
    }

    /**
     * Get the partner name.
     * 
     * @return string
     */
    public function getPartnerName(): string {
        return $this->partnerName;
    }

    /**
     * Get the partner URL.
     * 
     * @return string
     */
    public function getPartnerUrl(): string {
        return $this->partnerUrl;
    }

    /**
     * Output for debugging
     * @return void
     */
    public function debug(): void {
        echo "data: "; var_dump($this->data);
        echo "partnerName: "; var_dump($this->partnerName);
        echo "partnerUrl: "; var_dump($this->partnerUrl);
        echo "partnerLogoPath: "; var_dump($this->partnerLogoPath);

        echo $this->getPartnerLogoRenderString();
    }
}
