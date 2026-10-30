<?php

namespace HagenbergJams\JamEngine;

/**
 * Helper class to load jam data from YAML files
 */
class JamData
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
            unset($jam['topic'], $jam['topic_video']);

            return $jam;
        }

        if (JamSchedule::topicRelease($jam) !== null && $year === self::latestYearWithTopicRelease()) {
            $topic = env('JAM_TOPIC');
            $video = env('JAM_TOPIC_VIDEO');

            if (empty($jam['topic']) && is_string($topic) && $topic !== '') {
                $jam['topic'] = $topic;
            }

            if (empty($jam['topic_video']) && is_string($video) && $video !== '') {
                $jam['topic_video'] = $video;
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
