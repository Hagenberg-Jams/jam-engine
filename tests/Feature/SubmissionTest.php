<?php

declare(strict_types=1);

/** Turns on the upload page of a fixture, optionally with a list of teams. */
function withSubmission(array $settings = [], ?string $teams = null): callable
{
    return function (string $site) use ($settings, $teams): void {
        $config = (string) file_get_contents("{$site}/config/jam.php");
        $submission = var_export(array_replace_recursive(['enabled' => true, 'turnstile_site_key' => '0xSUBMITKEY', 'notify' => ['orga@example.test'], 'from' => 'noreply@example.test'], $settings), true);
        file_put_contents("{$site}/config/jam.php", str_replace("'website' =>", "'submission' => {$submission},\n    'website' =>", $config));

        if ($teams !== null) {
            mkdir("{$site}/_data/teams", 0777, true);
            file_put_contents("{$site}/_data/teams/2026.yaml", $teams);
        }
    };
}

it('builds the upload page and the functions for videos, with the teams of the site', function (): void {
    $site = buildSite('animation', prepare: withSubmission(teams: "- The Awesomes\n- name: 'Pixel Pirates'\n- The Awesomes\n"));
    $page = page($site, 'submit.html');
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_submission-config.json"), true);

    expect($page)->toContain('Submit your Animation')
        ->toContain('noindex')
        ->toContain('data-sitekey="0xSUBMITKEY"')
        ->toContain('accept=".mp4,.mov"')
        ->toContain('Your animation as a video file')
        ->toContain('# Animation title')
        ->not->toContain('id="platform"')
        ->and(file_exists("{$site}/functions/api/submission/start.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/_shared.js"))->toBeTrue()
        ->and(file_get_contents("{$site}/_site/sitemap.xml"))->not->toContain('/submit')
        ->and($config)->toMatchArray([
            'year' => 2026,
            'kind' => 'video',
            'platforms' => [],
            'extensions' => ['mp4', 'mov'],
            'deadline' => '2026-11-22T21:00:00+01:00',
            'closes' => null,
            'notify' => ['orga@example.test'],
            'from' => 'noreply@example.test',
            'teams' => [['name' => 'The Awesomes', 'slug' => 'the-awesomes'], ['name' => 'Pixel Pirates', 'slug' => 'pixel-pirates']],
        ])
        ->and($config['readme']['sections'][3])->toBe(['heading' => 'Credits', 'aliases' => [], 'required' => false]);
});

it('offers a ZIP for each platform for games, and takes the teams from the registration without a list', function (): void {
    $site = buildSite('game', prepare: withSubmission(['deadline' => '2025-12-14T21:00:00+01:00', 'closes' => '2026-01-31T23:59:00+01:00']));
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_submission-config.json"), true);

    expect(page($site, 'submit.html'))->toContain('id="platform"')->toContain('<option value="Linux">')->toContain('# Game name')->toContain('## How to play')
        ->and($config)->toMatchArray(['year' => 2025, 'kind' => 'game', 'platforms' => ['Win', 'Mac', 'Linux', 'Web'], 'extensions' => ['zip'], 'teams' => []])
        ->and($config['closes'])->toBe('2026-01-31T23:59:00+01:00')
        // Both functions need the shared helpers, and the registration keeps working next to the upload.
        ->and(file_exists("{$site}/functions/api/registration/index.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/submission/index.js"))->toBeTrue();
});

it('builds nothing of it while the upload is off', function (): void {
    $site = buildSite('animation');

    expect(file_exists("{$site}/_site/submit.html"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/_submission-config.json"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/submission/start.js"))->toBeFalse();
});
