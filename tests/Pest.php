<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function fixtureCopy(string $fixture): string
{
    $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jam-fixture-' . uniqid();

    foreach ([__DIR__ . '/Fixtures/skeleton', __DIR__ . "/Fixtures/{$fixture}"] as $source) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $path = $target . substr($file->getPathname(), strlen($source));
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            copy($file->getPathname(), $path);
        }
    }

    foreach (['_pages', '_media', '_posts', '_docs', 'resources/views', 'app/storage/framework/views', 'app/storage/framework/cache/data', 'app/storage/app'] as $dir) {
        if (!is_dir("{$target}/{$dir}")) {
            mkdir("{$target}/{$dir}", 0777, true);
        }
    }

    file_put_contents("{$target}/_media/app.css", '/* test */');

    return $target;
}

/** @param array<string, string> $env */
function runHyde(string $site, string $command, array $env = [], string $now = '2026-11-10T12:00:00+01:00'): Process
{
    $process = new Process(
        ['php', '-d', 'display_errors=0', 'hyde', ...explode(' ', $command)],
        $site,
        ['JAM_ENGINE_ROOT' => dirname(__DIR__), 'JAM_TEST_NOW' => $now, ...$env],
        null,
        180,
    );
    $process->run();

    return $process;
}

/** @param array<string, string> $env */
function buildSite(string $fixture, array $env = [], string $now = '2026-11-10T12:00:00+01:00', ?callable $prepare = null): string
{
    $site = fixtureCopy($fixture);

    if ($prepare !== null) {
        $prepare($site);
    }

    $process = runHyde($site, 'build --no-interaction', $env, $now);

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput());

    return $site;
}

function page(string $site, string $file): string
{
    expect(file_exists("{$site}/_site/{$file}"))->toBeTrue("{$file} was not built");

    return (string) file_get_contents("{$site}/_site/{$file}");
}

/** @return array<int, string> Every file in the directories that get deployed */
function deployedFiles(string $site): array
{
    $found = [];
    foreach (['_site', 'functions'] as $dir) {
        if (!is_dir("{$site}/{$dir}")) {
            continue;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$site}/{$dir}", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $found[] = $file->getPathname();
        }
    }

    return $found;
}

function deployedContains(string $site, string $needle): bool
{
    foreach (deployedFiles($site) as $file) {
        if (str_contains((string) file_get_contents($file), $needle)) {
            return true;
        }
    }

    return false;
}
