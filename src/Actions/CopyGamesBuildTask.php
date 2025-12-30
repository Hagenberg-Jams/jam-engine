<?php

declare(strict_types=1);

namespace App\Actions;

use Hyde\Hyde;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;
use Hyde\Framework\Concerns\InteractsWithDirectories;

use function is_dir;
use function mkdir;
use function copy;
use function scandir;
use function is_file;

/**
 * Post-build task to copy the games directory to the output directory.
 *
 * This ensures that game ZIP files are available in the built site at /games/{year}/{file}.
 */
class CopyGamesBuildTask extends PostBuildTask
{
    use InteractsWithDirectories;

    protected static string $message = 'Copying game downloads';

    public function handle(): void
    {
        $sourceDir = base_path('games');
        $targetDir = Hyde::sitePath('games');

        if (!is_dir($sourceDir)) {
            $this->skip('Games directory does not exist');
            return;
        }

        $this->needsParentDirectory($targetDir);

        $this->copyDirectory($sourceDir, $targetDir);
    }

    protected function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $item;
            $targetPath = $target . '/' . $item;

            if (is_file($sourcePath)) {
                copy($sourcePath, $targetPath);
            } elseif (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $targetPath);
            }
        }
    }

    public function printFinishMessage(): void
    {
        $this->createdSiteFile('games/')->withExecutionTime();
    }
}

