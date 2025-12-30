<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

use function glob;
use function file_exists;
use function hash_file;
use function is_array;
use function is_string;
use function preg_match;
use function file_put_contents;

/**
 * Command to calculate and update SHA256 checksums for game download files in YAML data files.
 *
 * Run this command after adding or updating game ZIP files to update the checksums in the YAML files.
 * This allows the build task to skip copying unchanged files by comparing checksums.
 */
class UpdateGameChecksumsCommand extends Command
{
    protected $signature = 'gamejam:update-checksums {--year= : Only update checksums for a specific year}';

    protected $description = 'Calculate and update SHA256 checksums for game download files in YAML data files';

    public function handle(): int
    {
        $years = $this->getYears();

        if (empty($years)) {
            $this->error('No game data files found');
            return 1;
        }

        $this->info('Updating checksums for game download files...');
        $this->newLine();

        $totalUpdated = 0;

        foreach ($years as $year) {
            $updated = $this->updateChecksumsForYear($year);
            $totalUpdated += $updated;

            if ($updated > 0) {
                $this->info("Updated {$updated} checksum(s) for year {$year}");
            }
        }

        $this->newLine();

        if ($totalUpdated > 0) {
            $this->info("Successfully updated {$totalUpdated} checksum(s) total");
        } else {
            $this->info('All checksums are up to date');
        }

        return 0;
    }

    /**
     * @return array<int>
     */
    protected function getYears(): array
    {
        if ($this->option('year')) {
            $year = (int) $this->option('year');
            $yamlFile = base_path("_data/games/games{$year}.yaml");
            if (file_exists($yamlFile)) {
                return [$year];
            }
            $this->error("No game data file found for year {$year}");
            return [];
        }

        $files = glob(base_path('_data/games/games*.yaml')) ?: [];
        $years = [];

        foreach ($files as $file) {
            if (preg_match('/games(\d{4})\.yaml$/', $file, $matches)) {
                $years[] = (int) $matches[1];
            }
        }

        sort($years);
        return $years;
    }

    protected function updateChecksumsForYear(int $year): int
    {
        $yamlFile = base_path("_data/games/games{$year}.yaml");

        if (!file_exists($yamlFile)) {
            return 0;
        }

        $data = Yaml::parseFile($yamlFile) ?? [];

        if (!is_array($data)) {
            return 0;
        }

        $updated = 0;
        $gamesDir = base_path("games/{$year}");

        foreach ($data as $index => $entry) {
            if (!is_array($entry) || !isset($entry['game']) || !isset($entry['download'])) {
                continue;
            }

            $downloads = $entry['download'] ?? [];
            if (!is_array($downloads)) {
                continue;
            }

            $hasChanges = false;

            foreach ($downloads as $downloadIndex => $download) {
                if (!is_array($download) || !isset($download['file'])) {
                    continue;
                }

                $fileName = $download['file'] ?? '';
                if (!is_string($fileName) || $fileName === '') {
                    continue;
                }

                // Skip URLs
                if (str_starts_with($fileName, 'http://') || str_starts_with($fileName, 'https://')) {
                    continue;
                }

                $filePath = $gamesDir . '/' . $fileName;

                if (!file_exists($filePath)) {
                    continue;
                }

                $checksum = hash_file('sha256', $filePath);

                if ($checksum === false) {
                    $this->warn("Failed to calculate checksum for {$filePath}");
                    continue;
                }

                // Update checksum if it's missing or different
                if (!isset($download['checksum']) || $download['checksum'] !== $checksum) {
                    $data[$index]['download'][$downloadIndex]['checksum'] = $checksum;
                    $hasChanges = true;
                    $updated++;
                }
            }
        }

        if ($hasChanges) {
            // Write back to YAML file with proper formatting
            $yamlContent = Yaml::dump($data, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            file_put_contents($yamlFile, $yamlContent);
        }

        return $updated;
    }
}

