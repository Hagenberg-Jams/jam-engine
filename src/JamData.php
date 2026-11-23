<?php

namespace HagenbergJams\JamEngine;

/**
 * Helper class to load jam data from YAML files
 */
class JamData
{
    /**
     * The fields of a jam that stay secret until the topic release, each with the secret that holds it.
     */
    public const TOPIC_SECRETS = [
        'topic' => 'JAM_TOPIC',
        'topic_video' => 'JAM_TOPIC_VIDEO',
        'topic_description' => 'JAM_TOPIC_DESCRIPTION',
    ];

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
     * Load project data for a specific year
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getProjects(int $year): array
    {
        $key = "projects{$year}";

        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            if (!is_array($cached)) {
                return [];
            }
            /** @var array<int, array<string, mixed>> $result */
            $result = array_values($cached);
            return $result;
        }

        $yamlFile = base_path("_data/projects/{$year}.yaml");

        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);

        $projects = is_array($data) ? $data : [];
        // Ensure numeric keys for return type
        $result = array_values($projects);
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

        return self::applyTopic($year, $result);
    }

    /**
     * The categories of a jam from `categories` in its YAML file, in their order, for example how much AI a team used.
     * Teams choose one when they upload, and a project with `winner: <key>` won the award of its category.
     *
     * @return array<string, array{name: string, description: string}>
     */
    public static function categories(int $year): array
    {
        $entries = self::getJam($year)['categories'] ?? [];
        $categories = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            $key = is_array($entry) && is_scalar($entry['key'] ?? null) ? strtolower(trim((string) $entry['key'])) : '';
            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $key) !== 1 || isset($categories[$key])) {
                continue;
            }

            $name = is_scalar($entry['name'] ?? null) ? trim((string) $entry['name']) : '';
            $categories[$key] = [
                'name' => $name !== '' ? $name : ucfirst($key),
                'description' => is_scalar($entry['description'] ?? null) ? trim((string) $entry['description']) : '',
            ];
        }

        return $categories;
    }

    /**
     * The names of the awards of a jam: those of the site, then one for each category of the jam.
     *
     * @return array<string, string>
     */
    public static function awards(int $year): array
    {
        $awards = [];
        foreach ((array) config('jam.awards', []) as $key => $name) {
            $awards[(string) $key] = is_scalar($name) ? (string) $name : (string) $key;
        }
        foreach (self::categories($year) as $key => $category) {
            $awards[$key] ??= $category['name'];
        }

        return $awards;
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
     * Get imprint data
     *
     * @return array<string, mixed>
     */
    public static function getImprint(): array
    {
        $yamlFile = base_path('_data/imprint.yaml');

        if (!file_exists($yamlFile)) {
            return [];
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);

        return is_array($data) ? $data : [];
    }

    /**
     * The newest jam that has a topic release.
     */
    public static function latestYearWithTopicRelease(): ?int
    {
        foreach (array_reverse(self::getAvailableYears()) as $year) {
            $jam = \Symfony\Component\Yaml\Yaml::parseFile(base_path("_data/jams/{$year}.yaml"));

            if (is_array($jam) && !empty($jam['topic_release'])) {
                return $year;
            }
        }

        return null;
    }

    /**
     * Keeps the topic secret until its release, and fills it in from the secret afterwards.
     *
     * @param array<string, mixed> $jam
     * @return array<string, mixed>
     */
    protected static function applyTopic(int $year, array $jam): array
    {
        if (JamSchedule::isTopicPending($jam)) {
            foreach (array_keys(self::TOPIC_SECRETS) as $field) {
                unset($jam[$field]);
            }

            return $jam;
        }

        if (JamSchedule::topicRelease($jam) !== null && $year === self::latestYearWithTopicRelease()) {
            foreach (self::TOPIC_SECRETS as $field => $secret) {
                $value = env($secret);

                if (empty($jam[$field]) && is_string($value) && $value !== '') {
                    $jam[$field] = $value;
                }
            }
        }

        return $jam;
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
     * Site configuration is stored in config/jam.php.
     * Use config('jam.*') directly in templates and build tasks.
     */
}
