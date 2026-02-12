<?php

declare(strict_types=1);

namespace App\Actions;

use App\GameJamData;

use function copy;
use function file_exists;
use function glob;
use function hash_file;

use Hyde\Framework\Concerns\InteractsWithDirectories;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;
use Hyde\Hyde;

use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function preg_match;
use function scandir;

/**
 * Post-build task to copy the games directory to the output directory.
 *
 * This ensures that game ZIP files are available in the built site at /games/{year}/{file}.
 * Uses SHA256 checksums from YAML files to skip copying unchanged files.
 */
class CopyGamesBuildTask extends PostBuildTask
{
    use InteractsWithDirectories;

    protected static string $message = 'Copying game downloads';

    protected int $copiedCount = 0;
    protected int $skippedCount = 0;

    /** @var array<string, string> Cache of expected checksums by file path */
    protected array $checksumCache = [];

    public function handle(): void
    {
        $gamesBaseUrl = config('gamejam.games_base_url');
        if (!empty($gamesBaseUrl)) {
            $this->skip('Game downloads served from external URL (GAMES_BASE_URL), skipping copy');
            return;
        }

        $sourceDir = base_path('games');
        $targetDir = Hyde::sitePath('games');

        if (!is_dir($sourceDir)) {
            $this->skip('Games directory does not exist');
            return;
        }

        $this->needsParentDirectory($targetDir);

        // Load checksums from YAML files
        $this->loadChecksumsFromYaml();

        $files = $this->collectFiles($sourceDir, $targetDir);

        if (empty($files)) {
            $this->skip('No game files to copy');
            return;
        }

        $this->newLine();

        $this->withProgressBar($files, function (array $file): void {
            $this->copyFileIfNeeded($file['source'], $file['target'], $file['expectedChecksum'] ?? null);
        });

        $this->newLine();
    }

    /**
     * Load expected checksums from YAML game data files.
     */
    protected function loadChecksumsFromYaml(): void
    {
        $yamlFiles = glob(base_path('_data/games/games*.yaml')) ?: [];

        foreach ($yamlFiles as $yamlFile) {
            if (preg_match('/games(\d{4})\.yaml$/', $yamlFile, $matches)) {
                $year = (int) $matches[1];
                $games = GameJamData::getGames($year);

                if ($games === []) {
                    continue;
                }

                foreach ($games as $entry) {
                    if (!isset($entry['download']) || !is_array($entry['download'])) {
                        continue;
                    }

                    $downloads = $entry['download'];

                    foreach ($downloads as $download) {
                        if (!is_array($download) || !isset($download['file'])) {
                            continue;
                        }

                        $fileName = $download['file'];
                        if (!is_string($fileName) || $fileName === '') {
                            continue;
                        }

                        // Skip URLs
                        if (str_starts_with($fileName, 'http://') || str_starts_with($fileName, 'https://')) {
                            continue;
                        }

                        // Store checksum with relative path as key (e.g., "2024/game.zip")
                        $relativePath = "{$year}/{$fileName}";
                        if (isset($download['checksum']) && is_string($download['checksum'])) {
                            $this->checksumCache[$relativePath] = $download['checksum'];
                        }
                    }
                }
            }
        }
    }

    /**
     * Collect all files that need to be checked/copied.
     *
     * @return array<array{source: string, target: string, expectedChecksum: string|null}>
     */
    protected function collectFiles(string $source, string $target): array
    {
        $files = [];

        if (!is_dir($source)) {
            return $files;
        }

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore' || $item === '.gitkeep') {
                continue;
            }

            $sourcePath = $source . '/' . $item;
            $targetPath = $target . '/' . $item;

            if (is_file($sourcePath)) {
                // Only process ZIP files (all other files including .gitignore and .gitkeep are ignored)
                if (str_ends_with(strtolower($item), '.zip')) {
                    // Extract relative path for checksum lookup (e.g., "2024/game.zip")
                    $relativePath = str_replace(base_path('games') . '/', '', $sourcePath);
                    $expectedChecksum = $this->checksumCache[$relativePath] ?? null;

                    $files[] = [
                        'source' => $sourcePath,
                        'target' => $targetPath,
                        'expectedChecksum' => $expectedChecksum,
                    ];
                }
            } elseif (is_dir($sourcePath)) {
                // Recursively collect files from subdirectories
                $files = array_merge($files, $this->collectFiles($sourcePath, $targetPath));
            }
        }

        return $files;
    }

    protected function copyFileIfNeeded(string $source, string $target, ?string $expectedChecksum): void
    {
        // If we have an expected checksum and the target file exists, compare checksums
        if ($expectedChecksum !== null && file_exists($target)) {
            $targetChecksum = hash_file('sha256', $target);

            if ($targetChecksum !== false && $targetChecksum === $expectedChecksum) {
                $this->skippedCount++;
                return;
            }
        }

        // Ensure target directory exists
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Copy the file
        if (copy($source, $target)) {
            $this->copiedCount++;
        }
    }

    public function printFinishMessage(): void
    {
        if ($this->copiedCount > 0 || $this->skippedCount > 0) {
            $message = "Copied {$this->copiedCount} file(s)";
            if ($this->skippedCount > 0) {
                $message .= ", skipped {$this->skippedCount} file(s) (unchanged)";
            }
            $this->write("\n > <info>{$message}</info>");
            $this->withExecutionTime();
        } else {
            $this->createdSiteFile('games/')->withExecutionTime();
        }
    }
}
