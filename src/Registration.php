<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use Carbon\CarbonImmutable;

/**
 * Settings of the registration form, shared by the build tasks, the views and the Pages Functions.
 */
class Registration
{
    public static function enabled(): bool
    {
        return (bool) config('jam.registration.form.enabled', false) && self::year() !== null;
    }

    /**
     * The jam the form registers for: the next one that has not started yet, or else the latest one.
     */
    public static function year(): ?int
    {
        $upcoming = JamSchedule::upcoming();
        if ($upcoming !== null) {
            return $upcoming['year'];
        }

        $years = JamData::getAvailableYears();

        return $years === [] ? null : max($years);
    }

    public static function deadline(): ?CarbonImmutable
    {
        return JamSchedule::parse(config('jam.registration.deadline'));
    }

    public static function isOpen(): bool
    {
        $deadline = self::deadline();

        return (bool) config('jam.registration.active', false) && ($deadline === null || CarbonImmutable::now()->lessThanOrEqualTo($deadline));
    }

    /**
     * When the registrations of a jam are deleted: a number of days after its end.
     */
    public static function purgeAfter(int $year): CarbonImmutable
    {
        $jam = JamData::getJam($year) ?? [];
        $end = JamSchedule::parse($jam['enddate'] ?? null) ?? JamSchedule::parse($jam['startdate'] ?? null) ?? CarbonImmutable::now();
        $days = config('jam.registration.form.retention_days', 90);

        return $end->endOfDay()->addDays(is_numeric($days) ? (int) $days : 90);
    }

    /**
     * The settings the Pages Functions need. They are bundled into the function, not published.
     *
     * @return array<string, mixed>
     */
    public static function functionConfig(): array
    {
        $year = (int) self::year();
        $form = (array) config('jam.registration.form', []);
        $size = array_values((array) ($form['team_size'] ?? [1, 6]));
        $capacity = $form['onsite_capacity'] ?? null;
        $access = (array) ($form['access'] ?? []);

        return [
            'year' => $year,
            'siteName' => self::string(config('hyde.name')),
            'siteUrl' => rtrim(self::string(config('jam.website')), '/'),
            'active' => (bool) config('jam.registration.active', false),
            'deadline' => self::deadline()?->toIso8601String(),
            'teamSize' => [self::int($size[0] ?? 1, 1), self::int($size[1] ?? 6, 6)],
            'capacity' => is_numeric($capacity) ? (int) $capacity : null,
            'fields' => array_map(fn(mixed $value): bool => (bool) $value, (array) ($form['fields'] ?? [])),
            'programs' => array_values((array) ($form['programs'] ?? [])),
            'notify' => self::notify((array) ($form['notify'] ?? [])),
            'from' => self::string($form['from'] ?? ''),
            'replyTo' => self::string(config('jam.email')),
            'purgeAfter' => self::purgeAfter($year)->toIso8601String(),
            'access' => [
                'team' => self::string($access['team'] ?? ''),
                'aud' => self::string($access['aud'] ?? ''),
            ],
            'texts' => (array) ($form['texts'] ?? []),
        ];
    }

    /**
     * Organisers to notify: from the config, and from the secret JAM_REGISTRATION_NOTIFY, so that
     * private addresses do not have to be in the public repository. Comma-separated.
     *
     * @param array<mixed> $configured
     * @return array<int, string>
     */
    protected static function notify(array $configured): array
    {
        $secret = explode(',', self::string(env('JAM_REGISTRATION_NOTIFY', '')));
        $addresses = array_map(fn(mixed $address): string => trim(self::string($address)), [...$configured, ...$secret]);

        return array_values(array_unique(array_filter($addresses, fn(string $address): bool => $address !== '')));
    }

    protected static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    protected static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
