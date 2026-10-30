<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use Illuminate\Console\Command;

/**
 * Moves project data from the legacy Game Jam layout to the neutral layout.
 *
 * `_data/games/gamesYYYY.yaml` becomes `_data/projects/YYYY.yaml`, and each entry's `game:` key
 * becomes `project:`. The files are edited as text to keep their formatting. Safe to re-run.
 */
class MigrateDataCommand extends Command
{
    /** @var string */
    protected $signature = 'jam:migrate-data';

    /** @var string */
    protected $description = 'Move project data from the legacy _data/games layout to _data/projects';

    public function handle(): int
    {
        $files = glob(base_path('_data/games/games*.yaml')) ?: [];
        $target = base_path('_data/projects');

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        foreach ($files as $file) {
            if (preg_match('/games(\d{4})\.yaml$/', $file, $matches) !== 1) {
                continue;
            }

            $destination = "{$target}/{$matches[1]}.yaml";
            if (file_exists($destination)) {
                $this->error("{$destination} already exists, skipping {$file}");

                return self::FAILURE;
            }

            $content = (string) file_get_contents($file);
            file_put_contents($destination, (string) preg_replace('/^(\s*)game:/m', '$1project:', $content));
            unlink($file);
            $this->line("Migrated {$matches[1]}");
        }

        if (is_dir(base_path('_data/games')) && (glob(base_path('_data/games/*')) ?: []) === []) {
            rmdir(base_path('_data/games'));
        }

        foreach (glob(base_path('_data/jams/*.yaml')) ?: [] as $file) {
            $content = (string) file_get_contents($file);
            file_put_contents($file, (string) preg_replace('/^data: games\d{4}\R/m', '', $content));
        }

        $this->info('Project data is up to date.');

        return self::SUCCESS;
    }
}
