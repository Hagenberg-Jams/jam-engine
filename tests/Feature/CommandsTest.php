<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function runWithInput(string $site, string $command, array $lines): Process
{
    $process = new Process(
        ['php', '-d', 'display_errors=0', 'hyde', ...explode(' ', $command)],
        $site,
        ['JAM_ENGINE_ROOT' => dirname(__DIR__, 2), 'JAM_TEST_NOW' => '2026-11-10T12:00:00+01:00'],
        implode("\n", $lines) . "\n",
        120,
    );
    $process->run();

    return $process;
}

function magickAvailable(): bool
{
    exec('magick -version', $output, $code);

    return $code === 0;
}

it('creates a jam with a known topic', function (): void {
    $site = fixtureCopy('game');
    $process = runWithInput($site, 'jam:create-jam', ['2027', '2027', '', 'Open Topic', '2027-12-11', '2027-12-12', '36']);

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and(file_get_contents("{$site}/_data/jams/2027.yaml"))->toContain('Open Topic')->toContain('2027-12-11')
        ->and(file_exists("{$site}/_data/projects/2027.yaml"))->toBeTrue();
});

it('never writes the topic of a jam with a topic release', function (): void {
    $site = fixtureCopy('animation');
    $process = runWithInput($site, 'jam:create-jam', ['2027', '2027', '2027-11-19 18:00', '2027-11-20 09:00', '2027-11-21 21:00', '36']);
    $jam = (string) file_get_contents("{$site}/_data/jams/2027.yaml");

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and($jam)->not->toContain('topic:')
        ->toContain('topic_release: \'2027-11-19T18:00:00+01:00\'')
        ->toContain('startdate: \'2027-11-20T09:00:00+01:00\'')
        ->and(runHyde($site, 'jam:validate')->getExitCode())->toBe(0);
});

it('adds an animation with videos and without game fields', function (): void {
    $site = fixtureCopy('animation');
    mkdir("{$site}/_input/header", 0777, true);
    mkdir("{$site}/_input/screenshots", 0777, true);
    exec('magick -size 2000x1000 xc:red ' . escapeshellarg("{$site}/_input/header/h.png"));

    $process = runWithInput($site, 'jam:add-project --year=2025', [
        'New Film',
        'https://www.youtube.com/watch?v=abcdefghijk',
        'https://youtu.be/lmnopqrstuv',
        '',
        'A description.',
        '---END---',
        'The Team',
        'Ann Example, Bo Tester',
        '---END---',
        'yes',
        'yes',
        'sound',
    ]);
    $data = (string) file_get_contents("{$site}/_data/projects/2025.yaml");

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and($data)->toContain('New Film')->toContain('abcdefghijk')->toContain('lmnopqrstuv')->toContain('winner: sound')
        ->not->toContain('players')->not->toContain('controls')
        ->and(file_exists("{$site}/_media/2025/new-film_header.webp"))->toBeTrue()
        ->and(runHyde($site, 'jam:validate')->getExitCode())->toBe(0);
})->skip(fn(): bool => !magickAvailable(), 'ImageMagick (magick) is not installed');
