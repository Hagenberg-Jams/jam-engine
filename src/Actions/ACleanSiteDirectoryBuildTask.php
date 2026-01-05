<?php

declare(strict_types=1);

namespace App\Actions;

use Hyde\Framework\Features\BuildTasks\PreBuildTask;
use Hyde\Hyde;

use function is_dir;
use function rmdir;
use function scandir;
use function unlink;

/**
 * Pre-build task to completely empty the _site directory before building.
 *
 * This ensures that old files are removed and don't persist between builds.
 * This is more thorough than HydePHP's default CleanSiteDirectory task which
 * only removes HTML and JSON files.
 */
/**
 * IMPORTANT: This class name starts with "A" to ensure it runs before
 * TransferMediaAssets (which starts with "T") when tasks are auto-discovered.
 */
class ACleanSiteDirectoryBuildTask extends PreBuildTask
{
    protected static string $message = 'Completely emptying build directory';

    public function handle(): void
    {
        $sitePath = Hyde::sitePath();

        if (!is_dir($sitePath)) {
            $this->skip('Build directory does not exist');
            return;
        }

        // Only delete contents, but preserve the directory structure
        // This ensures media files can be copied after this task runs
        $this->deleteDirectoryContents($sitePath);
        $this->info('Build directory emptied.');
    }

    /**
     * Recursively delete all contents of a directory, but keep the directory itself.
     */
    protected function deleteDirectoryContents(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $file;

            // Skip the media directory - it will be populated by TransferMediaAssets
            // This prevents deleting media files that were just copied
            if ($file === 'media' && is_dir($path)) {
                continue;
            }

            if (is_dir($path)) {
                $this->deleteDirectoryContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
