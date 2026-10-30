<?php

declare(strict_types=1);

it('passes for a complete site', function (): void {
    $site = buildSite('animation');
    $process = runHyde($site, 'jam:check-links');

    expect($process->getExitCode())->toBe(0, $process->getOutput());
});

it('reports a broken internal link', function (): void {
    $site = buildSite('animation');
    file_put_contents("{$site}/_site/broken.html", '<a href="/does-not-exist">x</a><a href="/2025">ok</a><a href="https://example.test/x">external</a>');
    $process = runHyde($site, 'jam:check-links');

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('/does-not-exist')
        ->not->toContain('broken.html: /2025');
});
