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

it('refuses a future topic release while a topic description is in the file', function (): void {
    $site = fixtureCopy('animation');
    file_put_contents("{$site}/_data/jams/2027.yaml", "title: '2027'\ntopic_description: 'Too early'\n");

    $process = runHyde($site, 'jam:topic-release 2027-11-19 18:00 --year=2027');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput() . $process->getErrorOutput())->toContain('topic_description')->toContain('JAM_TOPIC_DESCRIPTION')
        ->and((string) file_get_contents("{$site}/_data/jams/2027.yaml"))->not->toContain('topic_release');
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

/** A folder laid out like the submission bucket, with two versions for Windows and one for Linux. */
function submissionMirror(bool $images = false): string
{
    $mirror = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jam-submissions-' . uniqid();
    $team = "{$mirror}/2025/the-awesomes";
    mkdir("{$team}/uploads", 0777, true);
    mkdir("{$team}/screenshots/s1", 0777, true);

    foreach (['Win_1.0.0', 'Win_1.0.1', 'Linux_1.0.0'] as $build) {
        $zip = new ZipArchive();
        $zip->open("{$team}/uploads/TheAwesomes_CatQuest_{$build}.zip", ZipArchive::CREATE);
        $zip->addFromString('CatQuest.exe', "build {$build}");
        $zip->addFromString('README.md', 'an old README');
        $zip->close();
    }

    file_put_contents("{$team}/README.md", implode("\n", [
        '# Cat Quest', '', '<!-- Replace "Game name" with the name of your game. -->', '',
        '## Team', '', 'The Awesomes', '',
        '## Team Members', '', '- Ann Example', '- Bo Tester', '',
        '## Description', '', 'You are a cat in space. Collect all fish to win.', '',
        '## Controls', '', '- **Movement:** WASD', '',
        '## How to Play', '', 'Start CatQuest.exe.', '',
    ]));

    $files = [];
    for ($i = 1; $i <= 4; $i++) {
        $path = "{$team}/screenshots/s1/{$i}.png";
        $images ? exec('magick -size 1920x1080 xc:blue ' . escapeshellarg($path)) : file_put_contents($path, "png {$i}");
        $files[] = "s1/{$i}.png";
    }
    file_put_contents("{$team}/screenshots/current.json", json_encode(['stamp' => 's1', 'files' => $files]));

    return $mirror;
}

it('fetches the latest build of each platform and puts the README into the ZIP files', function (): void {
    $site = fixtureCopy('game');
    $mirror = submissionMirror();

    $process = runHyde($site, "jam:fetch-submissions --year=2025 --from={$mirror}");
    $target = "{$site}/submissions/2025/the-awesomes";
    $uploads = array_map('basename', glob("{$target}/uploads/*.zip") ?: []);
    sort($uploads);
    $zip = new ZipArchive();
    $zip->open("{$target}/uploads/TheAwesomes_CatQuest_Win_1.0.1.zip");

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and($process->getOutput())->toContain('Cat Quest')
        ->and($uploads)->toBe(['TheAwesomes_CatQuest_Linux_1.0.0.zip', 'TheAwesomes_CatQuest_Win_1.0.1.zip'])
        ->and($zip->getFromName('README.md'))->toBe(file_get_contents("{$mirror}/2025/the-awesomes/README.md"))
        ->and($zip->getFromName('CatQuest.exe'))->toBe('build Win_1.0.1')
        ->and(glob("{$target}/screenshots/*.png"))->toHaveCount(4)
        ->and(file_exists("{$target}/screenshots/01.png"))->toBeTrue();
    $zip->close();

    // A second run keeps the files it fetched before, with their README.
    touch("{$target}/uploads/TheAwesomes_CatQuest_Win_1.0.1.zip", 1000000000);
    expect(runHyde($site, "jam:fetch-submissions --year=2025 --from={$mirror}")->getExitCode())->toBe(0)
        ->and(filemtime("{$target}/uploads/TheAwesomes_CatQuest_Win_1.0.1.zip"))->toBe(1000000000);
});

it('fetches every version on request, and only the chosen teams', function (): void {
    $site = fixtureCopy('game');
    $mirror = submissionMirror();

    runHyde($site, "jam:fetch-submissions --year=2025 --all --team=the-awesomes --from={$mirror}");
    $other = runHyde($site, "jam:fetch-submissions --year=2025 --team=someone-else --from={$mirror}");

    expect(glob("{$site}/submissions/2025/the-awesomes/uploads/*.zip"))->toHaveCount(3)
        ->and($other->getOutput())->toContain('No submissions found');
});

it('adds a project from a submission with the data of its README', function (): void {
    $site = fixtureCopy('game');
    $mirror = submissionMirror(images: true);
    // The jam has categories, and the team chose one when uploading.
    file_put_contents("{$site}/_data/jams/2025.yaml", "categories:
  - { key: purists, name: Purists }
  - { key: occasional, name: Occasional }
", FILE_APPEND);
    file_put_contents("{$mirror}/2025/the-awesomes/category.json", json_encode(['category' => 'occasional']));
    runHyde($site, "jam:fetch-submissions --year=2025 --from={$mirror}");

    // Name from the README (Enter), players, control types, the category of the team (Enter), not a winner.
    $process = runWithInput($site, 'jam:add-project --year=2025 --submission=the-awesomes', ['', '1', '1,2', '', 'no']);
    $data = (string) file_get_contents("{$site}/_data/projects/2025.yaml");

    expect($process->getExitCode())->toBe(0, $process->getOutput() . $process->getErrorOutput())
        ->and($data)->toContain('Cat Quest')->toContain('The Awesomes')->toContain('Ann Example')->toContain('Bo Tester')
        ->toContain('Collect all fish to win.')->toContain('**Movement:** WASD')->toContain('Start CatQuest.exe.')
        ->toContain('platform: Windows')->toContain('platform: Linux')->toContain('category: occasional')
        ->and(file_exists("{$site}/submissions/2025/the-awesomes/category.json"))->toBeTrue()
        ->and(file_exists("{$site}/games/2025/cat-quest-Windows.zip"))->toBeTrue()
        ->and(file_exists("{$site}/_media/2025/cat-quest_header.webp"))->toBeTrue()
        ->and(glob("{$site}/_media/2025/cat-quest_image*_full.webp"))->toHaveCount(4)
        ->and(runHyde($site, 'jam:validate')->getExitCode())->toBe(0);
})->skip(fn(): bool => !magickAvailable(), 'ImageMagick (magick) is not installed');
