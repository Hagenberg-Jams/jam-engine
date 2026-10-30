<?php

declare(strict_types=1);

it('announces the next jam with its dates and registration', function (): void {
    $html = page(buildSite('animation'), 'index.html');

    expect($html)->toContain('id="upcoming-jam"')
        ->toContain('21–22 November 2026, 09:00–21:00')
        ->toContain('Register for 2026')
        ->toContain('15 November 2026, 23:59');
});

it('removes the announcement once the jam has started', function (): void {
    $html = page(buildSite('animation', now: '2026-11-21T09:00:01+01:00'), 'index.html');

    expect($html)->not->toContain('id="upcoming-jam"');
});

it('can be turned off', function (): void {
    $site = buildSite('animation', prepare: function (string $site): void {
        $config = (string) file_get_contents("{$site}/config/jam.php");
        file_put_contents("{$site}/config/jam.php", str_replace("'latest_jam'", "'features' => ['upcoming_jam' => false],\n    'latest_jam'", $config));
    });

    expect(page($site, 'index.html'))->not->toContain('id="upcoming-jam"');
});
