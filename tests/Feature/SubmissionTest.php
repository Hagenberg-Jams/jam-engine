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
    $site = buildSite('animation', prepare: withSubmission(['access' => ['team' => 'jams', 'aud' => 'submit-aud']], "- The Awesomes\n- name: 'Pixel Pirates'\n- The Awesomes\n"));
    $page = page($site, 'submit.html');

    // The admin page works without the registration form, with the Access application of the upload.
    expect(page($site, 'admin/submissions.html'))->toContain('Submissions 2026')->toContain('noindex')
        ->and(file_exists("{$site}/functions/api/admin/submissions/index.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/admin/submissions/file.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/admin/_middleware.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/_access.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/admin/registrations.js"))->toBeFalse()
        ->and(json_decode((string) file_get_contents("{$site}/functions/api/_access-config.json"), true))->toBe(['team' => 'jams', 'aud' => 'submit-aud'])
        ->and(file_get_contents("{$site}/_site/sitemap.xml"))->not->toContain('admin/submissions');
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_submission-config.json"), true);

    expect($page)->toContain('Submit your Animation')
        ->toContain('noindex')
        ->toContain('data-sitekey="0xSUBMITKEY"')
        ->toContain('accept=".mp4,.mov"')
        ->toContain('Your animation as a video file')
        ->toContain('a short description in English. Start from our template')
        ->toContain('of at least 1920×1080 pixels (PNG, JPG or WebP).</li>')
        ->not->toContain('@if')->not->toContain('@endif')
        ->toContain('# Animation title')
        ->and(page($site, 'submit/README.md'))->toContain("## Team Members\n\n<!-- One person per line")->toContain('## Credits')->not->toContain('{team}')
        ->and($page)
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
        // Optional sections reach the function as not required, wherever the preset puts them.
        ->and(array_values(array_filter($config['readme']['sections'], fn(array $section): bool => $section['heading'] === 'Credits')))
        ->toBe([['heading' => 'Credits', 'aliases' => [], 'required' => false]]);
});

it('offers a ZIP for each platform for games, and takes the teams from the registration without a list', function (): void {
    $site = buildSite('game', prepare: withSubmission(['deadline' => '2025-12-14T21:00:00+01:00', 'closes' => '2026-01-31T23:59:00+01:00']));
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_submission-config.json"), true);

    // The template follows the READMEs of the Game Jam 2025: title, Team, Team Members, Description, Controls, How to Play.
    expect(page($site, 'submit/README.md'))->toBe(implode("\n", [
        '# Game name',
        '',
        '<!-- Replace "Game name" with the name of your game. Fill in every section in English, keep the headings, and save the file as UTF-8. -->',
        '',
        '## Team',
        '',
        '<!-- Your team name. -->',
        '',
        '## Team Members',
        '',
        '<!-- One person per line, with the name that should appear on the website. -->',
        '- ',
        '',
        '## Description',
        '',
        '<!-- In English: what is the setting or story, and what must the player do to win? -->',
        '',
        '## Controls',
        '',
        '<!-- One control per line, for example: - **Movement:** WASD -->',
        '- ',
        '',
        '## How to Play',
        '',
        '<!-- Optional: anything else players need to know, for example how to start the game or play it in the browser. Delete this section if you do not need it. -->',
        '',
    ]))
        ->and(page($site, 'submit.html'))->toContain('in English, and the controls.')->toContain('One should show the start screen')->not->toContain('@if')->toContain('id="platform"')->toContain('<option value="Linux">')->toContain('href="/submit/README.md" download="README.md"')
        ->and($config)->toMatchArray(['year' => 2025, 'kind' => 'game', 'platforms' => ['Win', 'Mac', 'Linux', 'Web'], 'extensions' => ['zip'], 'teams' => null])
        ->and($config['closes'])->toBe('2026-01-31T23:59:00+01:00')
        // Both functions need the shared helpers, and the registration keeps working next to the upload.
        ->and(file_exists("{$site}/functions/api/registration/index.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/submission/index.js"))->toBeTrue()
        // Without its own Access application, the upload uses the one of the registration form, and both admin pages work.
        ->and(json_decode((string) file_get_contents("{$site}/functions/api/_access-config.json"), true))->toBe(['team' => 'jams', 'aud' => 'aud-tag'])
        ->and(file_exists("{$site}/functions/api/admin/registrations.js"))->toBeTrue()
        ->and(file_exists("{$site}/functions/api/admin/submissions/index.js"))->toBeTrue();
});

it('builds nothing of it while the upload is off', function (): void {
    $site = buildSite('animation');

    expect(file_exists("{$site}/_site/submit.html"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/_submission-config.json"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/submission/start.js"))->toBeFalse()
        ->and(file_exists("{$site}/_site/admin/submissions.html"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/admin/_middleware.js"))->toBeFalse()
        ->and(file_exists("{$site}/functions/api/_access-config.json"))->toBeFalse();
});

it('links the upload page in the menu while it is open, and the admin pages quietly in the footer', function (): void {
    $both = page(buildSite('game', prepare: withSubmission()), 'index.html');
    $upload = page(buildSite('animation', prepare: withSubmission()), '2025.html');
    $closed = page(buildSite('animation', prepare: withSubmission(['closes' => '2020-01-01 00:00'])), 'index.html');
    $none = page(buildSite('animation'), 'index.html');

    expect($both)->toMatch('#<a[^>]+href="/?submit\.html"[^>]*>\s*Submit\s*</a>#')
        ->toContain('Admin:')->toContain('href="/admin/registrations"')->toContain('href="/admin/submissions"')
        ->and($upload)->toMatch('#href="/?submit\.html"#')->toContain('href="/admin/submissions"')->not->toContain('href="/admin/registrations"')
        ->and($closed)->not->toMatch('#href="/?submit\.html"#')->toContain('href="/admin/submissions"')
        ->and($none)->not->toContain('submit.html')->not->toContain('Admin:');
});

/** Gives the jam 2025 of the game fixture the AI categories, and the game of the year a category and its award. */
function withCategories(string $category = 'purists'): callable
{
    return function (string $site) use ($category): void {
        withSubmission()($site);
        file_put_contents("{$site}/_data/jams/2025.yaml", implode("\n", [
            'categories:',
            '  - key: purists',
            '    name: Purists',
            '    description: No AI at all.',
            '  - key: occasional',
            '    name: Occasional',
            '  - key: vibe-coders',
            '    name: Vibe Coders',
            '',
        ]), FILE_APPEND);
        $projects = (string) file_get_contents("{$site}/_data/projects/2025.yaml");
        file_put_contents("{$site}/_data/projects/2025.yaml", str_replace('  winner: jury', "  category: {$category}\n  winner: {$category}", $projects));
    };
}

it('asks for the category of the jam when uploading, and shows it with the projects and their awards', function (): void {
    $site = buildSite('game', prepare: withCategories());
    $config = json_decode((string) file_get_contents("{$site}/functions/api/_submission-config.json"), true);

    expect($config['categories'])->toBe([['key' => 'purists', 'name' => 'Purists'], ['key' => 'occasional', 'name' => 'Occasional'], ['key' => 'vibe-coders', 'name' => 'Vibe Coders']])
        ->and(page($site, 'submit.html'))->toContain('name="category" value="purists"')->toContain('No AI at all.')->toContain('Vibe Coders')->toContain('The awards are given in each category.')
        ->and(page($site, '2025.html'))->toContain('data-filter=".category-purists"')->not->toContain('data-filter=".category-occasional"')
        ->toMatch('#class="project-card [^"]*category-purists#')
        ->toMatch('#Winner in the category <strong[^>]*>Purists</strong>#')
        ->and(page($site, '2025/space-game.html'))->toContain('Category: Purists')
        ->and(runHyde($site, 'jam:validate')->getOutput())->not->toContain('unknown');
});

it('builds no choice without categories, and warns about a category the jam does not have', function (): void {
    $plain = buildSite('game', prepare: withSubmission());
    $unknown = buildSite('game', prepare: withCategories('robots'));

    expect(page($plain, 'submit.html'))->not->toContain('type="radio" name="category"')
        ->and(json_decode((string) file_get_contents("{$plain}/functions/api/_submission-config.json"), true)['categories'])->toBe([])
        ->and(page($plain, '2025.html'))->not->toContain('category-')
        ->and(runHyde($unknown, 'jam:validate')->getOutput())->toContain("Space Game has the unknown category \"robots\"")->toContain("unknown award 'robots'");
});
