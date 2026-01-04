<?php

declare(strict_types=1);

namespace App\Actions;

use Hyde\Hyde;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;

/**
 * Post-build task to copy robots.txt to the output directory.
 */
class CopyRobotsTxtBuildTask extends PostBuildTask
{
    protected static string $message = 'Copying robots.txt';

    public function handle(): void
    {
        $sourceFile = base_path('robots.txt');
        $targetFile = Hyde::sitePath('robots.txt');

        if (!file_exists($sourceFile)) {
            $this->skip('robots.txt does not exist');
            return;
        }

        if (!copy($sourceFile, $targetFile)) {
            $this->error('Failed to copy robots.txt');
            return;
        }

        $this->info('Copied robots.txt');
    }
}
