<?php

declare(strict_types=1);

use HagenbergJams\JamEngine\JamConfig;

it('merges associative arrays and replaces lists', function (): void {
    $merged = JamConfig::merge(
        ['a' => ['x' => 1, 'y' => 2], 'list' => [1, 2, 3]],
        ['a' => ['y' => 3], 'list' => [9]],
    );

    expect($merged)->toBe(['a' => ['x' => 1, 'y' => 3], 'list' => [9]]);
});

it('applies the preset below the site settings', function (): void {
    $config = JamConfig::resolve(['type' => 'animation', 'labels' => ['card_cta' => 'See it']]);

    expect($config['labels']['project'])->toBe('Animation')
        ->and($config['labels']['card_cta'])->toBe('See it')
        ->and($config['project']['sections'])->toContain('video')
        ->and($config['features']['downloads'])->toBeFalse();
});

it('lets a site replace the awards as a whole', function (): void {
    $config = JamConfig::resolve(['type' => 'game', 'awards' => ['best' => 'Best']]);

    expect($config['awards'])->toBe(['best' => 'Best']);
});

it('rejects unknown jam types', function (): void {
    JamConfig::resolve(['type' => 'nope']);
})->throws(InvalidArgumentException::class);

it('rejects jam types that could reach outside the presets folder', function (): void {
    JamConfig::resolve(['type' => '../jam']);
})->throws(InvalidArgumentException::class);
