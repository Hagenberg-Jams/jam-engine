<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use InvalidArgumentException;

/**
 * Builds the final jam configuration from the engine defaults, a preset and the site's settings.
 */
class JamConfig
{
    /**
     * @param array<mixed> $site The site's config/jam.php
     * @return array<mixed>
     */
    public static function resolve(array $site): array
    {
        $configDir = dirname(__DIR__) . '/config';

        /** @var array<mixed> $defaults */
        $defaults = require $configDir . '/jam.php';

        $type = is_string($site['type'] ?? null) ? $site['type'] : 'generic';
        if (preg_match('/^[a-z-]+$/', $type) !== 1 || !file_exists("{$configDir}/presets/{$type}.php")) {
            throw new InvalidArgumentException("Unknown jam type '{$type}'. Add config/presets/{$type}.php to the engine.");
        }

        /** @var array<mixed> $preset */
        $preset = require "{$configDir}/presets/{$type}.php";

        $merged = self::merge(self::merge($defaults, $preset), $site);

        // Awards replace each other, because the key order is the display order.
        $merged['awards'] = $site['awards'] ?? $preset['awards'] ?? [];
        $merged['type'] = $type;

        return $merged;
    }

    /**
     * Merges associative arrays recursively. Lists and scalar values are replaced.
     *
     * @param array<mixed> $base
     * @param array<mixed> $override
     * @return array<mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null) && !array_is_list($value) && !array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
