<?php

declare(strict_types=1);

beforeAll(function (): void {
    $GLOBALS['animationSite'] = buildSite('animation');
});

it('renders videos without loading anything from YouTube', function (): void {
    $html = page($GLOBALS['animationSite'], '2025/short-film.html');

    expect($html)->toContain('data-youtube-id="aaaaaaaaaaa"')
        ->toContain('data-youtube-id="bbbbbbbbbbb"')
        ->toContain('About the Animation')
        ->not->toMatch('/<img[^>]+src="[^"]*youtube/')
        ->not->toMatch('/<iframe[^>]+src=/');
});

it('hides what only games need', function (): void {
    $project = page($GLOBALS['animationSite'], '2025/short-film.html');
    $year = page($GLOBALS['animationSite'], '2025.html');

    expect($project)->not->toContain('Players')->not->toContain('>Download<')
        ->and($year)->not->toContain('id="filter-buttons"')->not->toContain('singleplayer')->not->toContain('Player');
});

it('uses its own labels and awards', function (): void {
    expect(page($GLOBALS['animationSite'], '2025.html'))
        ->toContain('Best Sound')
        ->toContain('Watch →')
        ->not->toContain('Jury Award');
});

it('shows a clean page for a year without projects', function (): void {
    expect(page($GLOBALS['animationSite'], '2026.html'))
        ->toContain('No animations have been published for this year yet.')
        ->not->toContain('id="project-search-input"');
});

it('does not offer projects of a year without any', function (): void {
    expect(page($GLOBALS['animationSite'], 'index.html'))->not->toContain('View the animations of 2026');
});

it('skips the rules page when there are no rules', function (): void {
    expect(file_exists($GLOBALS['animationSite'] . '/_site/rules.html'))->toBeFalse()
        ->and(page($GLOBALS['animationSite'], 'index.html'))->not->toMatch('/>\s*Rules\s*</');
});

it('shows the site name when there is no logo', function (): void {
    expect(page($GLOBALS['animationSite'], 'index.html'))->toContain('<span class="text-lg">Test Jam</span>');
});

it('shows a sponsor without a URL as a plain logo', function (): void {
    $site = buildSite('animation', prepare: function (string $site): void {
        file_put_contents("{$site}/_data/homepage.yaml", "sponsors:\n  items:\n    - { name: 'Plain Sponsor', logo: plain.svg }\n    - { name: 'Linked Sponsor', logo: linked.svg, url: 'https://sponsor.example.test' }\n", FILE_APPEND);
    });
    $html = page($site, 'index.html');

    expect($html)->toContain('alt="Plain Sponsor"')
        ->toContain('href="https://sponsor.example.test"')
        ->not->toContain('href="#" target="_blank"');
});

it('can turn off the people pages', function (): void {
    $site = buildSite('animation', prepare: function (string $site): void {
        $config = (string) file_get_contents("{$site}/config/jam.php");
        file_put_contents("{$site}/config/jam.php", str_replace("'latest_jam'", "'features' => ['people' => false],\n    'latest_jam'", $config));
    });

    expect(file_exists("{$site}/_site/people.html"))->toBeFalse()
        ->and(page($site, 'index.html'))->not->toContain('People</a>');
});
