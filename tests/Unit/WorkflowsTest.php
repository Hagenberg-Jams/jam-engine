<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('has valid YAML in its workflow files', function (string $file): void {
    expect(Yaml::parseFile($file))->toHaveKey('jobs');
})->with(glob(dirname(__DIR__, 2) . '/.github/workflows/*.yml') ?: []);
