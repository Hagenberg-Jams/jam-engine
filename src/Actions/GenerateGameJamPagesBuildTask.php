<?php

declare(strict_types=1);

namespace App\Actions;

use App\GameJamData;

use function array_filter;
use function array_map;
use function glob;

use Hyde\Framework\Features\BuildTasks\PreBuildTask;
use Hyde\Hyde;
use Hyde\Pages\InMemoryPage;
use Hyde\Support\Models\Route;
use Illuminate\Support\Str;

use function is_array;
use function is_string;
use function preg_match;
use function sort;

/**
 * Pre-build task to generate Game Jam year pages and game detail pages from data files.
 *
 * This makes `php hyde build` behave like the old Jekyll generator:
 * - For every `_data/jams/{year}.yaml`, register an InMemoryPage with identifier `{year}`
 * - For every game in `_data/games/games{year}.yaml`, register an InMemoryPage with identifier `{year}/{slug}`
 */
class GenerateGameJamPagesBuildTask extends PreBuildTask
{
    protected static string $message = 'Generating Game Jam pages from data files';

    public function handle(): void
    {
        $years = $this->discoverYears();

        if ($years === []) {
            $this->skip('No jam data found in _data/jams');
        }

        foreach ($years as $year) {
            $this->registerYearPage($year);
            $this->registerGamePages($year);
        }

        // Generate person pages and people overview page
        $this->registerPeopleOverviewPage($years);
        $this->registerPersonPages($years);
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
            'jam' => GameJamData::getJam($year),
            'games' => GameJamData::getGames($year),
        ], '', 'pages.gamejam');

        Hyde::pages()->addPage($page);
        Hyde::routes()->addRoute(new Route($page));
    }

    protected function registerGamePages(int $year): void
    {
        $games = GameJamData::getGames($year);
        if ($games === []) {
            return;
        }

        foreach ($games as $entry) {
            if (!isset($entry['game']) || !is_array($entry['game'])) {
                continue;
            }

            $game = $entry['game'];
            if (!isset($game['name']) || !is_string($game['name'])) {
                continue;
            }

            $name = $game['name'];
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
                'gameSlug' => $slug,
                'jam' => GameJamData::getJam($year),
                'games' => $games, // pass full list; view will pick by slug
            ], '', 'pages.game');

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
        /** @var array<string, array{name: string, slug: string, totalGames: int, years: array<int>}> */
        $persons = [];

        // Collect all persons from all games
        foreach ($years as $year) {
            $games = GameJamData::getGames($year);
            if ($games === []) {
                continue;
            }

            foreach ($games as $entry) {
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
                            'totalGames' => 0,
                            'years' => [],
                        ];
                    }

                    $persons[$normalizedName]['totalGames']++;
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
     * Collect all persons from all games and register person pages.
     *
     * @param array<int> $years
     */
    protected function registerPersonPages(array $years): void
    {
        /** @var array<string, array<int, array{year: int, gameName: string, gameSlug: string, teamName: string, teamSlug: string}>> */
        $persons = [];

        // Collect all persons from all games
        foreach ($years as $year) {
            $games = GameJamData::getGames($year);
            if ($games === []) {
                continue;
            }

            foreach ($games as $entry) {
                if (!isset($entry['game']) || !isset($entry['team']) || !is_array($entry['game']) || !is_array($entry['team'])) {
                    continue;
                }

                $game = $entry['game'];
                $team = $entry['team'];
                if (!isset($game['name']) || !is_string($game['name'])) {
                    continue;
                }

                $gameName = $game['name'];
                $teamName = isset($team['name']) && is_string($team['name']) ? $team['name'] : '';
                $members = isset($team['members']) && is_array($team['members']) ? $team['members'] : [];

                if ($gameName === '' || empty($members)) {
                    continue;
                }

                $gameSlug = Str::slug($gameName);
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

                    // Add game info for this person
                    $persons[$normalizedName][] = [
                        'year' => $year,
                        'gameName' => $gameName,
                        'gameSlug' => $gameSlug,
                        'teamName' => $teamName,
                        'teamSlug' => $teamSlug,
                    ];
                }
            }
        }

        // Register a page for each person
        foreach ($persons as $personName => $games) {
            $slug = Str::slug((string) $personName);
            if ($slug === '') {
                continue;
            }

            // Sort games by year (newest first)
            usort($games, function (array $a, array $b): int {
                return $b['year'] <=> $a['year'];
            });

            $identifier = "person/{$slug}";

            $page = InMemoryPage::make($identifier, [
                'personName' => $personName,
                'games' => $games,
                'totalGames' => count($games),
                'years' => array_unique(array_column($games, 'year')),
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
