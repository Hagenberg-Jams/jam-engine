<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use Carbon\CarbonImmutable;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Checks a site's data. Errors stop the build, warnings do not.
 */
class JamValidator
{
    public const SECTIONS = ['info', 'header-image', 'video', 'description', 'controls', 'team', 'gallery', 'downloads'];

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<int, string> */
    public array $warnings = [];

    public function run(): self
    {
        $this->errors = [];
        $this->warnings = [];

        $this->validateConfig();

        foreach (glob(base_path('_data/jams/*.yaml')) ?: [] as $file) {
            $this->validateJam($file);
        }

        foreach (glob(base_path('_data/projects/*.yaml')) ?: [] as $file) {
            $this->validateProjects($file);
        }

        return $this;
    }

    protected function validateConfig(): void
    {
        $sections = config('jam.project.sections', []);

        foreach (is_array($sections) ? $sections : [] as $section) {
            if (!in_array($section, self::SECTIONS, true)) {
                $this->errors[] = 'config jam.project.sections: unknown section ' . json_encode($section);
            }
        }
    }

    protected function validateJam(string $file): void
    {
        $name = basename($file);
        $jam = $this->parse($file);

        if ($jam === null) {
            return;
        }

        foreach (['startdate', 'enddate', 'topic_release'] as $field) {
            if (array_key_exists($field, $jam) && $this->invalidDate($jam[$field])) {
                $this->errors[] = "{$name}: {$field} is not a valid date";
            }
        }

        $release = $this->invalidDate($jam['topic_release'] ?? null) ? null : JamSchedule::parse($jam['topic_release'] ?? null);

        if ($release !== null && CarbonImmutable::now()->lessThan($release) && ($jam['topic'] ?? '') !== '') {
            $this->errors[] = "{$name}: remove topic, it would be public before topic_release. Set it as the JAM_TOPIC secret instead.";
        }
    }

    protected function validateProjects(string $file): void
    {
        $name = basename($file);
        $entries = $this->parse($file);
        $awards = config('jam.awards', []);

        foreach ($entries ?? [] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $project = is_array($entry['project'] ?? null) ? $entry['project'] : [];

            if (!is_string($project['name'] ?? null) || $project['name'] === '') {
                $this->errors[] = "{$name}: entry {$index} has no project name";
                continue;
            }

            foreach (is_array($project['videos'] ?? null) ? $project['videos'] : [] as $video) {
                $id = is_array($video) ? ($video['youtube'] ?? null) : null;
                if (!is_string($id) || preg_match('/^[\w-]{6,20}$/', $id) !== 1) {
                    $this->errors[] = "{$name}: {$project['name']} has an invalid YouTube ID, use only the ID and not the URL";
                }
            }

            $winner = is_string($entry['winner'] ?? null) ? $entry['winner'] : '';
            foreach (array_filter(array_map('trim', explode(',', $winner))) as $category) {
                if ($category !== 'no' && is_array($awards) && !array_key_exists($category, $awards)) {
                    $this->warnings[] = "{$name}: {$project['name']} has the unknown award '{$category}', which is not displayed";
                }
            }
        }
    }

    /**
     * @return array<mixed>|null
     */
    protected function parse(string $file): ?array
    {
        try {
            $data = Yaml::parseFile($file);
        } catch (Throwable $exception) {
            $this->errors[] = basename($file) . ': ' . $exception->getMessage();

            return null;
        }

        return is_array($data) ? $data : [];
    }

    protected function invalidDate(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        try {
            return JamSchedule::parse($value) === null;
        } catch (Throwable) {
            return true;
        }
    }
}
