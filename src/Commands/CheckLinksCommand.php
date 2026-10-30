<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use FilesystemIterator;
use Hyde\Hyde;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class CheckLinksCommand extends Command
{
    /** @var string */
    protected $signature = 'jam:check-links';

    /** @var string */
    protected $description = 'Check that the internal links of the built site lead somewhere';

    public function handle(): int
    {
        $site = Hyde::sitePath();
        $broken = [];
        $checked = 0;

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }

            preg_match_all('/(?:href|src)="(\/[^"\/][^"]*|\/)"/', (string) file_get_contents($file->getPathname()), $matches);

            foreach (array_unique($matches[1]) as $link) {
                $checked++;
                if (!$this->exists($site, $link)) {
                    $broken[] = substr($file->getPathname(), strlen($site) + 1) . ": {$link}";
                }
            }
        }

        foreach ($broken as $link) {
            $this->error($link);
        }

        if ($broken !== []) {
            return self::FAILURE;
        }

        $this->info("All {$checked} internal links are valid.");

        return self::SUCCESS;
    }

    protected function exists(string $site, string $link): bool
    {
        // Browsers resolve "/../2025" to "/2025", and the navigation links of nested pages look like that.
        $linkPath = (string) preg_replace('#(/\.\.)+(?=/)#', '', (string) strtok($link, '?#'));
        $path = rtrim($site . $linkPath, '/');

        return is_file($path) || is_file("{$path}.html") || is_file("{$path}/index.html");
    }
}
