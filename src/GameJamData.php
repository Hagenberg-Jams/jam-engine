<?php

namespace App;

/**
 * Helper class to load Game Jam data from YAML and Markdown files
 */
class GameJamData
{
    protected static array $cache = [];

    /**
     * Load homepage data (about/video/sponsors).
     */
    public static function getHomepage(): array
    {
        $key = 'homepage';

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $yamlFile = base_path('_data/homepage.yaml');
        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile) ?? [];
        self::$cache[$key] = is_array($data) ? $data : [];

        return self::$cache[$key];
    }

    /**
     * Load games data for a specific year
     */
    public static function getGames(int $year): array
    {
        $key = "games{$year}";
        
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $yamlFile = base_path("_data/games/games{$year}.yaml");
        
        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);
        
        self::$cache[$key] = $data ?? [];
        
        return self::$cache[$key];
    }

    /**
     * Load jam data for a specific year
     */
    public static function getJam(int $year): ?array
    {
        $markdownFile = base_path("_data/jams/{$year}.md");
        
        if (!file_exists($markdownFile)) {
            return null;
        }

        $content = file_get_contents($markdownFile);
        $frontMatter = [];
        
        // Match front matter with optional newline after closing ---
        if (preg_match('/^---\s*\n(.*?)\n---\s*(?:\n|$)/s', $content, $matches)) {
            $frontMatter = \Symfony\Component\Yaml\Yaml::parse($matches[1]) ?? [];
        }
        
        return $frontMatter;
    }

    /**
     * Get all available jam years
     */
    public static function getAvailableYears(): array
    {
        // Discover years from data files so we don't need to hardcode them.
        $files = glob(base_path('_data/jams/*.md')) ?: [];

        $years = [];
        foreach ($files as $file) {
            $name = basename($file, '.md');
            if (preg_match('/^\d{4}$/', $name)) {
                $years[] = (int) $name;
            }
        }

        sort($years);

        return array_values(array_unique($years));
    }

    /**
     * Get rules data
     */
    public static function getRules(): array
    {
        $yamlFile = base_path("_data/rules.yaml");
        
        if (!file_exists($yamlFile)) {
            return [];
        }

        return \Symfony\Component\Yaml\Yaml::parseFile($yamlFile) ?? [];
    }

    /**
     * Site configuration is stored in config/gamejam.php.
     * Use config('gamejam.*') directly in templates and build tasks.
     */
}

