<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Settings of the upload page at /submit, shared by the build task, the view and the Pages Functions.
 */
class Submission
{
    public static function enabled(): bool
    {
        return (bool) config('jam.submission.enabled', false) && self::year() !== null;
    }

    /** Uploads are for the latest jam. */
    public static function year(): ?int
    {
        $years = JamData::getAvailableYears();

        return $years === [] ? null : max($years);
    }

    /** Uploads after this time are marked as late: the setting, or else the end of the jam. */
    public static function deadline(): ?CarbonImmutable
    {
        $deadline = JamSchedule::parse(config('jam.submission.deadline'));
        if ($deadline !== null) {
            return $deadline;
        }

        $jam = JamData::getJam((int) self::year()) ?? [];

        return JamSchedule::parse($jam['enddate'] ?? null);
    }

    public static function closes(): ?CarbonImmutable
    {
        return JamSchedule::parse(config('jam.submission.closes'));
    }

    public static function isOpen(): bool
    {
        $closes = self::closes();

        return self::enabled() && ($closes === null || CarbonImmutable::now()->lessThanOrEqualTo($closes));
    }

    public static function kind(): string
    {
        return config('jam.submission.kind') === 'video' ? 'video' : 'game';
    }

    /**
     * The Cloudflare Access application of the admin page: the one of the upload, or else the one of the registration form.
     *
     * @return array{team: string, aud: string}
     */
    public static function access(): array
    {
        foreach (['jam.submission.access', 'jam.registration.form.access'] as $key) {
            $access = (array) config($key, []);
            $team = self::string($access['team'] ?? '');
            $aud = self::string($access['aud'] ?? '');
            if ($team !== '' && $aud !== '') {
                return ['team' => $team, 'aud' => $aud];
            }
        }

        return ['team' => '', 'aud' => ''];
    }

    public static function turnstileSiteKey(): string
    {
        return self::string(config('jam.submission.turnstile_site_key')) ?: self::string(config('jam.registration.form.turnstile_site_key'));
    }

    /**
     * Teams from `_data/teams/{year}.yaml`, a list of names. Without the file, the function offers the
     * confirmed registrations of the jam instead.
     *
     * @return array<int, array{name: string, slug: string}>
     */
    public static function teams(): array
    {
        $file = base_path('_data/teams/' . self::year() . '.yaml');
        if (!is_file($file)) {
            return [];
        }

        $data = Yaml::parseFile($file);
        $entries = is_array($data) ? ($data['teams'] ?? $data) : [];
        $teams = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            $name = trim(self::string(is_array($entry) ? ($entry['name'] ?? '') : $entry));
            $slug = Str::slug($name);
            if ($name !== '' && $slug !== '' && !isset($teams[$slug])) {
                $teams[$slug] = ['name' => $name, 'slug' => $slug];
            }
        }

        return array_values($teams);
    }

    /**
     * The README.md template, laid out like the READMEs of earlier jams: the title, then one section per heading.
     * Hints are HTML comments, so they do not count as content. `{team}` is replaced by the team, or else left empty.
     */
    public static function readmeTemplate(?string $team = null): string
    {
        $readme = (array) config('jam.submission.readme', []);
        $hint = self::string($readme['title_hint'] ?? '');
        $lines = [
            '# ' . self::string($readme['title'] ?? 'Project name'),
            '',
            '<!-- ' . ($hint !== '' ? "{$hint} " : '') . 'Fill in every section in English, keep the headings, and save the file as UTF-8. -->',
        ];

        foreach (self::sections() as $section) {
            $lines[] = '';
            $lines[] = '## ' . $section['heading'];
            $lines[] = '';
            if ($section['hint'] !== '') {
                $lines[] = '<!-- ' . $section['hint'] . ' -->';
            }
            if (strtolower($section['heading']) === 'team') {
                if (($team ?? '{team}') !== '') {
                    $lines[] = $team ?? '{team}';
                }
            } elseif ($section['list']) {
                $lines[] = '- ';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<int, array{heading: string, aliases: array<int, string>, required: bool, hint: string, list: bool}>
     */
    public static function sections(): array
    {
        $sections = (array) config('jam.submission.readme.sections', []);

        return array_values(array_map(fn(mixed $section): array => [
            'heading' => self::string(is_array($section) ? ($section['heading'] ?? '') : $section),
            'aliases' => array_values(array_map(fn(mixed $alias): string => self::string($alias), (array) (is_array($section) ? ($section['aliases'] ?? []) : []))),
            'required' => !is_array($section) || (bool) ($section['required'] ?? true),
            'hint' => self::string(is_array($section) ? ($section['hint'] ?? '') : ''),
            // The template starts the section with an empty list item.
            'list' => is_array($section) && (bool) ($section['list'] ?? false),
        ], $sections));
    }

    /**
     * The settings the Pages Functions need. They are bundled into the function, not published.
     *
     * @return array<string, mixed>
     */
    public static function functionConfig(): array
    {
        $config = (array) config('jam.submission', []);
        $screenshots = (array) ($config['screenshots'] ?? []);
        $size = $config['max_size_mb'] ?? 4096;
        $notify = array_values(array_filter((array) ($config['notify'] ?? [])));
        $from = self::string($config['from'] ?? '');

        return [
            'year' => (int) self::year(),
            'siteName' => self::string(config('hyde.name')),
            'siteUrl' => rtrim(self::string(config('jam.website')), '/'),
            'active' => (bool) ($config['enabled'] ?? false),
            'kind' => self::kind(),
            'platforms' => self::kind() === 'game' ? array_values(array_map(fn(mixed $p): string => self::string($p), (array) ($config['platforms'] ?? []))) : [],
            'extensions' => array_values(array_map(fn(mixed $e): string => strtolower(ltrim(self::string($e), '.')), (array) ($config['extensions'] ?? []))),
            'maxSize' => (is_numeric($size) ? (int) $size : 4096) * 1024 * 1024,
            'deadline' => self::deadline()?->toIso8601String(),
            'closes' => self::closes()?->toIso8601String(),
            'screenshots' => [
                'min' => self::int($screenshots['min'] ?? 0, 0),
                'max' => self::int($screenshots['max'] ?? 8, 8),
                'width' => self::int($screenshots['width'] ?? 0, 0),
                'height' => self::int($screenshots['height'] ?? 0, 0),
            ],
            'readme' => [
                'title' => self::string(config('jam.submission.readme.title', 'Project name')),
                'sections' => array_map(fn(array $section): array => [
                    'heading' => $section['heading'],
                    'aliases' => $section['aliases'],
                    'required' => $section['required'],
                ], self::sections()),
            ],
            'teams' => self::teams(),
            // Organisers and sender: the ones of the submission, or else the ones of the registration form.
            'notify' => Registration::notify($notify !== [] ? $notify : (array) config('jam.registration.form.notify', [])),
            'from' => $from !== '' ? $from : self::string(config('jam.registration.form.from')),
            'replyTo' => self::string(config('jam.email')),
        ];
    }

    protected static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    protected static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
