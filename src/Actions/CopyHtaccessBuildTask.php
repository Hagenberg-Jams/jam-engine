<?php

declare(strict_types=1);

namespace App\Actions;

use Hyde\Framework\Features\BuildTasks\PostBuildTask;
use Hyde\Hyde;

/**
 * Post-build task to copy .htaccess to the output directory.
 */
class CopyHtaccessBuildTask extends PostBuildTask
{
    protected static string $message = 'Copying .htaccess';

    public function handle(): void
    {
        $sourceFile = base_path('.htaccess');
        $targetFile = Hyde::sitePath('.htaccess');

        if (!file_exists($sourceFile)) {
            $this->skip('.htaccess does not exist');
            return;
        }

        if (!copy($sourceFile, $targetFile)) {
            $this->error('Failed to copy .htaccess');
            return;
        }

        $this->info('Copied .htaccess');
    }
}
