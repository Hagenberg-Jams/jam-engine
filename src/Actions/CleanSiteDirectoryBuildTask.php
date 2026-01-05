<?php

declare(strict_types=1);

namespace App\Actions;

use Hyde\Hyde;
use Hyde\Framework\Features\BuildTasks\PreBuildTask;
use Hyde\Facades\Filesystem;

use function is_dir;
use function rmdir;
use function unlink;
use function scandir;

/**
 * Pre-build task to completely empty the _site directory before building.
 *
 * This ensures that old files are removed and don't persist between builds.
 * This is more thorough than HydePHP's default CleanSiteDirectory task which
 * only removes HTML and JSON files.
 */
class CleanSiteDirectoryBuildTask extends PreBuildTask
{
    protected static string $message = 'Completely emptying build directory';

    public function handle(): void
    {
        $sitePath = Hyde::sitePath();

        if (!is_dir($sitePath)) {
            $this->skip('Build directory does not exist');
            return;
        }

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

            if (is_dir($path)) {
                $this->deleteDirectoryContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
