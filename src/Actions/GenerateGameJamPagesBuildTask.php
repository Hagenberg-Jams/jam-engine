<?php

declare(strict_types=1);

namespace App\Actions;

use App\GameJamData;
use Hyde\Hyde;
use Hyde\Framework\Features\BuildTasks\PreBuildTask;
use Hyde\Facades\Filesystem;
use Hyde\Pages\InMemoryPage;
use Hyde\Support\Models\Route;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;

use function array_filter;
use function array_map;
use function glob;
use function is_array;
use function is_string;
use function preg_match;
use function sort;

/**
 * Pre-build task to generate Game Jam year pages and game detail pages from data files.
 *
 * This makes `php hyde build` behave like the old Jekyll generator:
 * - For every `_data/jams/{year}.md`, register an InMemoryPage with identifier `{year}`
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
    }

    /** @return array<int> */
    protected function discoverYears(): array
    {
        // Prefer discovering from _data/jams/*.md so the data files are the source of truth.
        $files = glob(base_path('_data/jams/*.md')) ?: [];

        $years = array_filter(array_map(function (string $file): ?int {
            $name = basename($file, '.md');
            if (preg_match('/^\d{4}$/', $name) === 1) {
                return (int) $name;
            }
            return null;
        }, $files));

        sort($years);

        return array_values($years);
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
        if (!is_array($games) || $games === []) {
            return;
        }

        foreach ($games as $entry) {
            $game = $entry['game'] ?? [];
            $name = is_array($game) ? ($game['name'] ?? null) : null;
            if (!is_string($name) || $name === '') {
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
}


