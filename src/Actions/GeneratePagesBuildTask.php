<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Actions;

use function array_filter;
use function array_map;
use function glob;

use HagenbergJams\JamEngine\JamData;
use Hyde\Framework\Features\BuildTasks\PreBuildTask;
use Hyde\Hyde;
use Hyde\Pages\BladePage;
use Hyde\Pages\InMemoryPage;
use Hyde\Support\Models\Route;
use Illuminate\Support\Str;

use function is_array;
use function is_string;
use function preg_match;
use function sort;

/**
 * Pre-build task to generate the jam year pages and project detail pages from data files.
 *
 * This makes `php hyde build` behave like the old Jekyll generator:
 * - For every `_data/jams/{year}.yaml`, register an InMemoryPage with identifier `{year}`
 * - For every project in `_data/projects/{year}.yaml`, register an InMemoryPage with identifier `{year}/{slug}`
 */
class GeneratePagesBuildTask extends PreBuildTask
{
    protected static string $message = 'Generating jam pages from data files';

    public function handle(): void
    {
        $this->registerStaticPages();

        $years = $this->discoverYears();

        if ($years === []) {
            $this->skip('No jam data found in _data/jams');
        }

        foreach ($years as $year) {
            $this->registerYearPage($year);
            $this->registerGamePages($year);
        }

        // Generate person pages and people overview page
        if (config('jam.features.people')) {
            $this->registerPeopleOverviewPage($years);
            $this->registerPersonPages($years);
        }
    }

    /**
     * Registers the home, rules, imprint and 404 pages. A site can replace one with a file in _pages/.
     *
     * These are real Blade pages, so the sitemap treats them like pages from _pages/.
     */
    protected function registerStaticPages(): void
    {
        $pages = ['index', 'imprint', '404'];

        if (config('jam.features.rules') && JamData::getRules() !== []) {
            $pages[] = 'rules';
        }

        foreach ($pages as $identifier) {
            if (file_exists(base_path("_pages/{$identifier}.blade.php"))) {
                continue;
            }

            $page = new BladePage($identifier);

            Hyde::pages()->addPage($page);
            Hyde::routes()->addRoute(new Route($page));
        }
    }

    /** @return array<int> */
    protected function discoverYears(): array
    {
        // Prefer discovering from _data/jams/*.yaml so the data files are the source of truth.
        $files = glob(base_path('_data/jams/*.yaml')) ?: [];

        $years = array_filter(array_map(function (string $file): ?int {
            $name = basename($file, '.yaml');
            if (preg_match('/^\d{4}$/', $name) === 1) {
                return (int) $name;
            }
            return null;
        }, $files));

        sort($years);

        return $years;
    }

    protected function registerYearPage(int $year): void
    {
        $page = InMemoryPage::make((string) $year, [
            'year' => $year,
            'jam' => JamData::getJam($year),
            'projects' => JamData::getProjects($year),
        ], '', 'pages.year');

        Hyde::pages()->addPage($page);
        Hyde::routes()->addRoute(new Route($page));
    }

    protected function registerGamePages(int $year): void
    {
        $projects = JamData::getProjects($year);
        if ($projects === []) {
            return;
        }

        foreach ($projects as $entry) {
            if (!isset($entry['project']) || !is_array($entry['project'])) {
                continue;
            }

            $project = $entry['project'];
            if (!isset($project['name']) || !is_string($project['name'])) {
                continue;
            }

            $name = $project['name'];
            if ($name === '') {
                continue;
            }

            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }

            $identifier = "{$year}/{$slug}";

            $page = InMemoryPage::make($identifier, [
                'year' => $year,
                'projectSlug' => $slug,
                'jam' => JamData::getJam($year),
                'projects' => $projects, // pass full list; view will pick by slug
            ], '', 'pages.project');

            Hyde::pages()->addPage($page);
            Hyde::routes()->addRoute(new Route($page));
        }
    }

    /**
     * Register the people overview page listing all participants.
     *
     * @param array<int> $years
     */
    protected function registerPeopleOverviewPage(array $years): void
    {
        /** @var array<string, array{name: string, slug: string, totalProjects: int, years: array<int>}> */
        $persons = [];

        // Collect all persons from all projects
        foreach ($years as $year) {
            $projects = JamData::getProjects($year);
            if ($projects === []) {
                continue;
            }

            foreach ($projects as $entry) {
                if (!isset($entry['team']) || !is_array($entry['team'])) {
                    continue;
                }

                $team = $entry['team'];
                if (!isset($team['members']) || !is_array($team['members'])) {
                    continue;
                }

                $members = $team['members'];

                foreach ($members as $member) {
                    if (!is_string($member) || $member === '') {
                        continue;
                    }

                    // Normalize person name
                    $normalizedName = $this->normalizePersonName($member);
                    $slug = Str::slug($normalizedName);

                    if ($slug === '') {
                        continue;
                    }

                    if (!isset($persons[$normalizedName])) {
                        $persons[$normalizedName] = [
                            'name' => $normalizedName,
                            'slug' => $slug,
                            'totalProjects' => 0,
                            'years' => [],
                        ];
                    }

                    $persons[$normalizedName]['totalProjects']++;
                    if (!in_array($year, $persons[$normalizedName]['years'], true)) {
                        $persons[$normalizedName]['years'][] = $year;
                    }
                }
            }
        }

        // Sort alphabetically by name
        usort($persons, function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        // Register the people overview page
        $page = InMemoryPage::make('people', [
            'persons' => $persons,
        ], '', 'pages.people');

        Hyde::pages()->addPage($page);
        Hyde::routes()->addRoute(new Route($page));
    }

    /**
     * Collect all persons from all projects and register person pages.
     *
     * @param array<int> $years
     */
    protected function registerPersonPages(array $years): void
    {
        /** @var array<string, array<int, array{year: int, projectName: string, projectSlug: string, teamName: string, teamSlug: string}>> */
        $persons = [];

        // Collect all persons from all projects
        foreach ($years as $year) {
            $projects = JamData::getProjects($year);
            if ($projects === []) {
                continue;
            }

            foreach ($projects as $entry) {
                if (!isset($entry['project']) || !isset($entry['team']) || !is_array($entry['project']) || !is_array($entry['team'])) {
                    continue;
                }

                $project = $entry['project'];
                $team = $entry['team'];
                if (!isset($project['name']) || !is_string($project['name'])) {
                    continue;
                }

                $projectName = $project['name'];
                $teamName = isset($team['name']) && is_string($team['name']) ? $team['name'] : '';
                $members = isset($team['members']) && is_array($team['members']) ? $team['members'] : [];

                if ($projectName === '' || empty($members)) {
                    continue;
                }

                $projectSlug = Str::slug($projectName);
                $teamSlug = Str::slug($teamName);

                foreach ($members as $member) {
                    if (!is_string($member) || $member === '') {
                        continue;
                    }

                    // Normalize person name (trim, remove extra spaces)
                    $normalizedName = $this->normalizePersonName($member);

                    if (!isset($persons[$normalizedName])) {
                        $persons[$normalizedName] = [];
                    }

                    // Add project info for this person
                    $persons[$normalizedName][] = [
                        'year' => $year,
                        'projectName' => $projectName,
                        'projectSlug' => $projectSlug,
                        'teamName' => $teamName,
                        'teamSlug' => $teamSlug,
                    ];
                }
            }
        }

        // Register a page for each person
        foreach ($persons as $personName => $projects) {
            $slug = Str::slug((string) $personName);
            if ($slug === '') {
                continue;
            }

            // Sort projects by year (newest first)
            usort($projects, function (array $a, array $b): int {
                return $b['year'] <=> $a['year'];
            });

            $identifier = "person/{$slug}";

            $page = InMemoryPage::make($identifier, [
                'personName' => $personName,
                'projects' => $projects,
                'totalProjects' => count($projects),
                'years' => array_unique(array_column($projects, 'year')),
            ], '', 'pages.person');

            Hyde::pages()->addPage($page);
            Hyde::routes()->addRoute(new Route($page));
        }
    }

    /**
     * Normalize person name for consistent grouping.
     */
    protected function normalizePersonName(string $name): string
    {
        // Trim and normalize whitespace
        $normalized = trim($name);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
