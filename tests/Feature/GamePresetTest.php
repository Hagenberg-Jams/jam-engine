<?php

declare(strict_types=1);

beforeAll(function (): void {
    $GLOBALS['gameSite'] = buildSite('game');
});

it('keeps the public URLs', function (string $file): void {
    page($GLOBALS['gameSite'], $file);
})->with(['index.html', '2025.html', '2024.html', '2025/space-game.html', 'people.html', 'person/ann-example.html', 'rules.html', 'imprint.html', '404.html']);

it('renders the game sections', function (): void {
    $html = page($GLOBALS['gameSite'], '2025/space-game.html');

    expect($html)->toContain('About the Game')
        ->toContain('2–4 Players')
        ->toContain('Move: WASD')
        ->toContain('/games/2025/space-game-Windows.zip');
});

it('renders the filters, cards and awards on the year page', function (): void {
    $html = page($GLOBALS['gameSite'], '2025.html');

    expect($html)->toContain('data-filter=".singleplayer"')
        ->toContain('project-card multiplayer keyboard gamepad')
        ->toContain('Jury Award')
        ->toContain('Details and Download')
        ->toContain('placeholder="Search games or teams…"');
});

it('does not announce a jam when none is upcoming', function (): void {
    expect(page($GLOBALS['gameSite'], 'index.html'))
        ->not->toContain('id="upcoming-jam"')
        ->not->toContain('id="topic-release"');
});

it('creates no functions without a topic release', function (): void {
    expect(is_dir($GLOBALS['gameSite'] . '/functions'))->toBeFalse();
});

it('uses the configured base URL for downloads', function (): void {
    $site = buildSite('game', prepare: function (string $site): void {
        $config = (string) file_get_contents("{$site}/config/jam.php");
        file_put_contents("{$site}/config/jam.php", str_replace("'latest_jam'", "'downloads' => ['base_url' => 'https://files.example.test'],\n    'latest_jam'", $config));
    });

    expect(page($site, '2025/space-game.html'))->toContain('https://files.example.test/games/2025/space-game-Windows.zip');
});

it('lets a site override an engine view', function (): void {
    $site = buildSite('game', prepare: function (string $site): void {
        mkdir("{$site}/resources/views/components/project", 0777, true);
        file_put_contents("{$site}/resources/views/components/project/description.blade.php", 'OVERRIDDEN BY THE SITE');
    });

    expect(page($site, '2025/space-game.html'))->toContain('OVERRIDDEN BY THE SITE');
});

it('lets a site replace a static page', function (): void {
    $site = buildSite('game', prepare: function (string $site): void {
        file_put_contents("{$site}/_pages/imprint.blade.php", 'OWN IMPRINT');
    });

    expect(page($site, 'imprint.html'))->toContain('OWN IMPRINT');
});

it('migrates legacy data', function (): void {
    $site = fixtureCopy('game');
    (new Symfony\Component\Filesystem\Filesystem())->remove("{$site}/_data/projects");
    mkdir("{$site}/_data/games");
    file_put_contents("{$site}/_data/games/games2025.yaml", "-\n  game:\n    name: 'Legacy'\n  team: { name: 'T', members: [] }\n");
    file_put_contents("{$site}/_data/jams/2025.yaml", "title: '2025'\ndata: games2025\n");

    expect(runHyde($site, 'jam:migrate-data')->getExitCode())->toBe(0)
        ->and(file_get_contents("{$site}/_data/projects/2025.yaml"))->toContain('  project:')
        ->and(is_dir("{$site}/_data/games"))->toBeFalse()
        ->and(file_get_contents("{$site}/_data/jams/2025.yaml"))->not->toContain('data:');
});

it('keeps the old command names as aliases', function (): void {
    $site = fixtureCopy('game');
    $output = runHyde($site, 'list jam')->getOutput();

    expect($output)->toContain('jam:add-project')->toContain('gamejam:add-game');
});
