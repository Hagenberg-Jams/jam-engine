<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Actions;

use HagenbergJams\JamEngine\Registration;
use HagenbergJams\JamEngine\Submission;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;

/**
 * Adds the Pages Functions of the upload page and its admin page to functions/, with the settings they need.
 *
 * The upload page and the admin page are left out of the sitemap, because they are of no use to search engines.
 * It runs after the registration task, which removes the admin files they share when the form is off.
 */
class PrepareSubmissionBuildTask extends PostBuildTask
{
    protected static string $message = 'Preparing the submission functions';

    /** Function files, relative to resources/functions/api. */
    protected const FILES = [
        '_shared.js',
        '_submission-logic.js',
        'submission/index.js',
        'submission/team.js',
        'submission/check.js',
        'submission/start.js',
        'submission/part.js',
        'submission/screenshot.js',
        'submission/complete.js',
        'submission/abort.js',
        'admin/submissions/index.js',
        'admin/submissions/file.js',
    ];

    /** Files the admin pages of the registration and the upload share. */
    protected const ADMIN_FILES = ['_access.js', 'admin/_middleware.js'];

    public function handle(): void
    {
        $target = base_path('functions/api');

        if (!Submission::enabled()) {
            // Remove the files of an earlier build, so a disabled upload cannot be reached.
            // The shared helpers stay, and the admin files stay while the registration needs them.
            $files = [...array_diff(self::FILES, ['_shared.js']), '_submission-config.json'];
            if (!Registration::enabled()) {
                $files = [...$files, ...self::ADMIN_FILES, '_access-config.json'];
            }

            foreach ($files as $file) {
                if (is_file("{$target}/{$file}")) {
                    unlink("{$target}/{$file}");
                }
            }

            $this->skip('The submission is disabled');

            return;
        }

        $source = dirname(__DIR__, 2) . '/resources/functions/api';

        foreach ([...self::FILES, ...self::ADMIN_FILES] as $file) {
            if (!is_dir(dirname("{$target}/{$file}"))) {
                mkdir(dirname("{$target}/{$file}"), 0755, true);
            }

            copy("{$source}/{$file}", "{$target}/{$file}");
        }

        file_put_contents("{$target}/_access-config.json", json_encode(Submission::access(), JSON_PRETTY_PRINT) . "\n");
        file_put_contents(
            "{$target}/_submission-config.json",
            json_encode(Submission::functionConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );

        // The README template as a file of its own, so that it can be linked, for example in an announcement.
        if (!is_dir(base_path('_site/submit'))) {
            mkdir(base_path('_site/submit'), 0755, true);
        }
        file_put_contents(base_path('_site/submit/README.md'), Submission::readmeTemplate(''));

        $sitemap = base_path('_site/sitemap.xml');
        $content = is_file($sitemap) ? file_get_contents($sitemap) : false;
        if ($content !== false) {
            foreach (['submit', 'admin/submissions'] as $path) {
                $content = (string) preg_replace('#<url>\s*<loc>[^<]*/' . preg_quote($path, '#') . '(\.html)?</loc>.*?</url>\s*#s', '', $content);
            }
            file_put_contents($sitemap, $content);
        }
    }
}
