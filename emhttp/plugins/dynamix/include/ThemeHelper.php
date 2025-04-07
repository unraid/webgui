<?php
class ThemeHelper {
    /**
     * @todo instead of hardcoding the themes, we should get them from the themes directory and extract the text and background colors from the css file
     * Ideally we don't need to be hardcoding any values here, but it's a quick fix to get the themes working with the current codebase
     */
    private const THEME_BLACK = 'black';
    private const THEME_WHITE = 'white';
    private const THEME_GRAY = 'gray';
    private const THEME_AZURE = 'azure';
    private const COLOR_BLACK = '#1c1c1c';
    private const COLOR_WHITE = '#f2f2f2';

    private const TOP_NAV_THEMES = [self::THEME_BLACK, self::THEME_WHITE];
    private const SIDEBAR_THEMES = [self::THEME_GRAY, self::THEME_AZURE];

    private const DARK_THEMES = [self::THEME_BLACK, self::THEME_GRAY];
    private const LIGHT_THEMES = [self::THEME_WHITE, self::THEME_AZURE];

    private const FGCOLORS = [
        self::THEME_BLACK => self::COLOR_BLACK,
        self::THEME_WHITE => self::COLOR_BLACK,
        self::THEME_GRAY => self::COLOR_WHITE,
        self::THEME_AZURE => self::COLOR_WHITE
    ];

    private const INIT_ERROR = 'ThemeHelper not initialized. Call initWithCurrentThemeSetting() first.';

    private string $themeName;
    private bool $topNavTheme;
    private bool $sidebarTheme;
    private bool $darkTheme;
    private bool $lightTheme;
    private string $fgcolor;
    private bool $unlimitedWidth = false;

    /**
     * Constructor for ThemeHelper
     * 
     * @param string|null $theme The theme name (optional)
     * @param '1'|null $width The width of the theme (optional)
     */
    public function __construct(?string $theme = null, ?string $width = null) {
        if ($theme === null) {
            throw new \RuntimeException(self::INIT_ERROR);
        }

        $this->themeName = strtok($theme, '-');

        $this->topNavTheme = in_array($this->themeName, self::TOP_NAV_THEMES);
        $this->sidebarTheme = in_array($this->themeName, self::SIDEBAR_THEMES);
        $this->darkTheme = in_array($this->themeName, self::DARK_THEMES);
        $this->lightTheme = in_array($this->themeName, self::LIGHT_THEMES);
        $this->fgcolor = self::FGCOLORS[$this->themeName] ?? self::COLOR_BLACK;

        if ($width !== null) {
            $this->setWidth($width);
        }
    }

    /**
     * Set the width setting
     * 
     * @param string $width The width setting ('1' for unlimited, empty string for boxed)
     * @return void
     */
    public function setWidth(string $width): void {
        $this->unlimitedWidth = ($width === '1');
    }

    /**
     * Check if unlimited width is enabled
     * 
     * @return bool
     */
    public function isUnlimitedWidth(): bool {
        return $this->unlimitedWidth;
    }

    public function getThemeName(): string {
        return $this->themeName;
    }

    public function isTopNavTheme(): bool {
        return $this->topNavTheme;
    }

    public function isSidebarTheme(): bool {
        return $this->sidebarTheme;
    }

    public function isDarkTheme(): bool {
        return $this->darkTheme;
    }

    public function isLightTheme(): bool {
        return $this->lightTheme;
    }

    /**
     * Get the theme HTML class string
     * 
     * @return string
     */
    public function getThemeHtmlClass(): string {

        $classes = ["Theme--{$this->themeName}"];

        if ($this->sidebarTheme) {
            $classes[] = "Theme--sidebar";
        }

        if ($this->topNavTheme) {
            $classes[] = "Theme--nav-top";
        }

        $classes[] = $this->unlimitedWidth ? "Theme--width-unlimited" : "Theme--width-boxed";

        return implode(' ', $classes);
    }

    public function getFgColor(): string {
        return $this->fgcolor;
    }

    public function updateDockerLogColor(string $docroot): void {
        exec("sed -ri 's/^\.logLine\{color:#......;/.logLine{color:{$this->fgcolor};/' $docroot/plugins/dynamix.docker.manager/log.htm >/dev/null &");
    }

    /**
     * Get all available theme names from the themes directory
     * 
     * @param string $docroot The document root path
     * @return array Array of theme names
     */
    public static function getThemesFromFileSystem(string $docroot): array {
        $themes = [];
        $themePath = "$docroot/webGui/styles/themes";
        if (!is_dir($themePath)) {
            error_log("Theme directory not found: $themePath");
            return $themes;
        }

        $themeFiles = glob("$themePath/*.css");

        foreach ($themeFiles as $themeFile) {
            $themeName = basename($themeFile, '.css');
            $themes[] = $themeName;
        }

        return $themes;
    }
}
