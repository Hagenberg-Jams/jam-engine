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

it('makes the new jam the latest one, unless asked to keep the latest jam', function (): void {
    $site = fixtureCopy('game');
    $input = ['2027', '2027', '2027-12-10 18:00', '2027-12-11 09:00', '2027-12-12 21:00', '36'];

    expect(runWithInput($site, 'jam:create-jam --keep-latest', $input)->getExitCode())->toBe(0)
        ->and(file_get_contents("{$site}/config/jam.php"))->toContain("'latest_jam' => '2025'");

    unlink("{$site}/_data/jams/2027.yaml");
    expect(runWithInput($site, 'jam:create-jam', $input)->getExitCode())->toBe(0)
        ->and(file_get_contents("{$site}/config/jam.php"))->toContain("'latest_jam' => '2027'");
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

it('changes the topic release and keeps the other fields', function (): void {
    $site = fixtureCopy('animation');
    $process = runHyde($site, 'jam:topic-release 2026-11-20 19:00');
    $jam = (string) file_get_contents("{$site}/_data/jams/2026.yaml");

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and($jam)->toContain("topic_release: '2026-11-20T19:00:00+01:00'")
        ->toContain("startdate: '2026-11-21T09:00:00+01:00'")
        ->and(substr_count($jam, 'topic_release'))->toBe(1)
        ->and(runHyde($site, 'jam:validate')->getExitCode())->toBe(0);
});

it('adds and removes a topic release', function (): void {
    $site = fixtureCopy('animation');
    file_put_contents("{$site}/_data/jams/2027.yaml", "title: '2027'\nstartdate: '2027-11-20T09:00:00+01:00'\n");

    $added = runWithInput($site, 'jam:topic-release', ['2027-11-19 18:00']);
    $jam = (string) file_get_contents("{$site}/_data/jams/2027.yaml");

    expect($added->getExitCode())->toBe(0, $added->getOutput() . $added->getErrorOutput())
        ->and($jam)->toContain("topic_release: '2027-11-19T18:00:00+01:00'");

    $removed = runHyde($site, 'jam:topic-release --year=2027 --remove');

    expect($removed->getExitCode())->toBe(0)
        ->and((string) file_get_contents("{$site}/_data/jams/2027.yaml"))->not->toContain('topic_release');
});

it('refuses a future topic release while the topic is in the file', function (): void {
    $site = fixtureCopy('animation');
    $process = runHyde($site, 'jam:topic-release 2026-12-01 18:00 --year=2025');

    expect($process->getExitCode())->toBe(1)
        ->and((string) file_get_contents("{$site}/_data/jams/2025.yaml"))->not->toContain('topic_release');
});

it('rejects a malformed topic release', function (): void {
    $site = fixtureCopy('animation');

    expect(runHyde($site, 'jam:topic-release 20.11.2026')->getExitCode())->toBe(1)
        ->and((string) file_get_contents("{$site}/_data/jams/2026.yaml"))->toContain("topic_release: '2026-11-20T18:00:00+01:00'");
});

it('takes team and shown names from the registration export', function (): void {
    $site = fixtureCopy('animation');
    mkdir("{$site}/_input/header", 0777, true);
    mkdir("{$site}/_input/screenshots", 0777, true);
    exec('magick -size 2000x1000 xc:red ' . escapeshellarg("{$site}/_input/header/h.png"));
    file_put_contents("{$site}/_input/teams-2025.json", json_encode(['year' => 2025, 'teams' => [['name' => 'Night Owls', 'members' => ['Annie', 'Bo Real']]]]));

    $process = runWithInput($site, 'jam:add-project --year=2025', [
        'Owl Film',
        '',
        'A description.',
        '---END---',
        'Night Owls',
        'no',
    ]);
    $data = (string) file_get_contents("{$site}/_data/projects/2025.yaml");

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and($data)->toContain('Night Owls')->toContain('Annie')->toContain('Bo Real');
})->skip(fn(): bool => !magickAvailable(), 'ImageMagick (magick) is not installed');
