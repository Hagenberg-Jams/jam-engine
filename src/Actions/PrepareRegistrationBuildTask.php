<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Actions;

use HagenbergJams\JamEngine\Registration;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;

/**
 * Adds the Pages Functions of the registration form to functions/, with the settings they need.
 *
 * The admin and edit pages are left out of the sitemap, because they are of no use to search engines.
 */
class PrepareRegistrationBuildTask extends PostBuildTask
{
    protected static string $message = 'Preparing the registration functions';

    /** Function files, relative to resources/functions/api. */
    protected const FILES = [
        '_registration-logic.js',
        '_access.js',
        'registration/index.js',
        'registration/self.js',
        'admin/_middleware.js',
        'admin/registrations.js',
        'admin/export.js',
    ];

    public function handle(): void
    {
        $target = base_path('functions/api');

        if (!Registration::enabled()) {
            // Remove the files of an earlier build, so a disabled form cannot be reached.
            foreach ([...self::FILES, '_registration-config.json'] as $file) {
                if (is_file("{$target}/{$file}")) {
                    unlink("{$target}/{$file}");
                }
            }

            $this->skip('The registration form is disabled');

            return;
        }

        $source = dirname(__DIR__, 2) . '/resources/functions/api';

        foreach (self::FILES as $file) {
            if (!is_dir(dirname("{$target}/{$file}"))) {
                mkdir(dirname("{$target}/{$file}"), 0755, true);
            }

            copy("{$source}/{$file}", "{$target}/{$file}");
        }

        file_put_contents(
            "{$target}/_registration-config.json",
            json_encode(Registration::functionConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );

        $this->removeFromSitemap(['admin/registrations', 'register/edit']);
    }

    /** @param array<string> $paths */
    protected function removeFromSitemap(array $paths): void
    {
        $sitemap = base_path('_site/sitemap.xml');
        $content = is_file($sitemap) ? file_get_contents($sitemap) : false;

        if ($content === false) {
            return;
        }

        foreach ($paths as $path) {
            $content = (string) preg_replace('#<url>\s*<loc>[^<]*/' . preg_quote($path, '#') . '(\.html)?</loc>.*?</url>\s*#s', '', $content);
        }

        file_put_contents($sitemap, $content);
    }
}
