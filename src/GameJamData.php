<?php

namespace App;

/**
 * Helper class to load Game Jam data from YAML files
 */
class GameJamData
{
    protected static array $cache = [];

    /**
     * Load games data for a specific year
     */
    public static function getGames(int $year): array
    {
        $key = "games{$year}";
        
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $yamlFile = base_path("jekyll-site/_data/games{$year}.yml");
        
        if (!file_exists($yamlFile)) {
            return [];
        }

        // Use Symfony YAML to parse the file
        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);
        
        self::$cache[$key] = $data ?? [];
        
        return self::$cache[$key];
    }

    /**
     * Load jam data for a specific year
     */
    public static function getJam(int $year): ?array
    {
        $markdownFile = base_path("jekyll-site/_jams/{$year}.md");
        
        if (!file_exists($markdownFile)) {
            return null;
        }

        $content = file_get_contents($markdownFile);
        $frontMatter = [];
        
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            $frontMatter = \Symfony\Component\Yaml\Yaml::parse($matches[1]) ?? [];
        }
        
        return $frontMatter;
    }

    /**
     * Get all available jam years
     */
    public static function getAvailableYears(): array
    {
        return [2015, 2016, 2017, 2018, 2019, 2020, 2022, 2023, 2024];
    }

    /**
     * Get rules data
     */
    public static function getRules(): array
    {
        $yamlFile = base_path("jekyll-site/_data/rules.yml");
        
        if (!file_exists($yamlFile)) {
            return [];
        }

        return \Symfony\Component\Yaml\Yaml::parseFile($yamlFile) ?? [];
    }

    /**
     * Get site configuration from Jekyll config
     */
    public static function getSiteConfig(): array
    {
        $configFile = base_path("jekyll-site/_config.yml");
        
        if (!file_exists($configFile)) {
            return [];
        }

        return \Symfony\Component\Yaml\Yaml::parseFile($configFile) ?? [];
    }
}

