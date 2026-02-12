<?php

namespace App;

/**
 * Helper class to load Game Jam data from YAML files
 */
class GameJamData
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    /**
     * Load homepage data (about/video/sponsors).
     *
     * @return array<string, mixed>
     */
    public static function getHomepage(): array
    {
        $key = 'homepage';

        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            return is_array($cached) ? $cached : [];
        }

        $yamlFile = base_path('_data/homepage.yaml');
        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile) ?? [];
        $result = is_array($data) ? $data : [];
        self::$cache[$key] = $result;

        return $result;
    }

    /**
     * Load games data for a specific year
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getGames(int $year): array
    {
        $key = "games{$year}";

        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            if (!is_array($cached)) {
                return [];
            }
            /** @var array<int, array<string, mixed>> $result */
            $result = array_values($cached);
            return $result;
        }

        $yamlFile = base_path("_data/games/games{$year}.yaml");

        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);

        $games = is_array($data) ? $data : [];
        // Ensure numeric keys for return type
        $result = array_values($games);
        self::$cache[$key] = $result;

        /** @var array<int, array<string, mixed>> $result */
        return $result;
    }

    /**
     * Load jam data for a specific year
     *
     * @return array<string, mixed>|null
     */
    public static function getJam(int $year): ?array
    {
        $yamlFile = base_path("_data/jams/{$year}.yaml");

        if (!file_exists($yamlFile)) {
            return null;
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile) ?? [];

        if (!is_array($data)) {
            return null;
        }

        // Ensure string keys for return type
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * Get all available jam years
     *
     * @return array<int>
     */
    public static function getAvailableYears(): array
    {
        // Discover years from data files so we don't need to hardcode them.
        $files = glob(base_path('_data/jams/*.yaml')) ?: [];

        $years = [];
        foreach ($files as $file) {
            $name = basename($file, '.yaml');
            if (preg_match('/^\d{4}$/', $name)) {
                $years[] = (int) $name;
            }
        }

        sort($years);

        return array_values(array_unique($years));
    }

    /**
     * Get rules data
     *
     * @return array<string, mixed>
     */
    public static function getRules(): array
    {
        $yamlFile = base_path("_data/rules.yaml");

        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);

        return is_array($data) ? $data : [];
    }

    /**
     * Site configuration is stored in config/gamejam.php.
     * Use config('gamejam.*') directly in templates and build tasks.
     */
}
