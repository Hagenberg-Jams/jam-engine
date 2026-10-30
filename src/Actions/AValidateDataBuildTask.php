<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Actions;

use HagenbergJams\JamEngine\JamValidator;
use Hyde\Framework\Features\BuildTasks\PreBuildTask;

/**
 * Stops the build when the site data is invalid, for example when a topic would be public too early.
 *
 * Hyde catches exceptions from build tasks and keeps building, so this ends the process itself.
 */
class AValidateDataBuildTask extends PreBuildTask
{
    protected static string $message = 'Validating site data';

    public function handle(): void
    {
        $errors = (new JamValidator())->run()->errors;

        if ($errors === []) {
            return;
        }

        fwrite(STDERR, "\nThe build was stopped because the site data is invalid:\n");

        foreach ($errors as $error) {
            fwrite(STDERR, "  - {$error}\n");
        }

        exit(1);
    }
}
