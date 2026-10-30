<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use Carbon\CarbonImmutable;

/**
 * Time-based logic for jams: the next jam and the topic release.
 *
 * YAML parses unquoted dates into timestamps, so values can be integers or strings.
 */
class JamSchedule
{
    public static function timezone(): string
    {
        $timezone = config('jam.timezone', 'Europe/Vienna');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Europe/Vienna';
    }

    public static function parse(mixed $value): ?CarbonImmutable
    {
        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value, self::timezone());
        }

        if (is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value, self::timezone())->setTimezone(self::timezone());
        }

        return null;
    }

    /**
     * @param array<mixed> $jam
     */
    public static function topicRelease(array $jam): ?CarbonImmutable
    {
        return self::parse($jam['topic_release'] ?? null);
    }

    /**
     * True while the topic must stay secret.
     *
     * @param array<mixed> $jam
     */
    public static function isTopicPending(array $jam): bool
    {
        $release = self::topicRelease($jam);

        return $release !== null && CarbonImmutable::now()->lessThan($release);
    }

    /**
     * The next jam that has not started yet.
     *
     * @return array{year: int, jam: array<string, mixed>, start: CarbonImmutable}|null
     */
    public static function upcoming(): ?array
    {
        foreach (JamData::getAvailableYears() as $year) {
            $jam = JamData::getJam($year);
            $start = self::parse($jam['startdate'] ?? null);

            if ($jam !== null && $start !== null && CarbonImmutable::now()->lessThan($start)) {
                return ['year' => $year, 'jam' => $jam, 'start' => $start];
            }
        }

        return null;
    }

    /**
     * The jam whose topic release should be shown: released or not, until the jam has ended.
     *
     * @return array{year: int, jam: array<string, mixed>, release: CarbonImmutable}|null
     */
    public static function currentTopicJam(): ?array
    {
        $year = JamData::latestYearWithTopicRelease();
        $jam = $year !== null ? JamData::getJam($year) : null;

        if ($year === null || $jam === null) {
            return null;
        }

        $release = self::topicRelease($jam);
        $end = self::parse($jam['enddate'] ?? null) ?? self::parse($jam['startdate'] ?? null);

        if ($release === null || ($end !== null && CarbonImmutable::now()->greaterThan($end->endOfDay()))) {
            return null;
        }

        return ['year' => $year, 'jam' => $jam, 'release' => $release];
    }

    /**
     * YAML turns an unquoted date without a time into midnight UTC.
     */
    protected static function hasTimeOfDay(mixed $value): bool
    {
        if (is_int($value)) {
            return $value % 86400 !== 0;
        }

        return is_string($value) && str_contains($value, ':');
    }

    /**
     * For example "21–22 November 2026, 09:00–21:00" or "30 November – 1 December 2026".
     *
     * @param array<mixed> $jam
     */
    public static function formatDates(array $jam): string
    {
        $start = self::parse($jam['startdate'] ?? null);
        $end = self::parse($jam['enddate'] ?? null);

        if ($start === null) {
            return '';
        }

        if ($end === null || $start->isSameDay($end)) {
            $dates = $start->format('j F Y');
        } elseif ($start->isSameMonth($end)) {
            $dates = $start->format('j') . '–' . $end->format('j F Y');
        } else {
            $dates = $start->format('j F') . ' – ' . $end->format('j F Y');
        }

        if ($end !== null && self::hasTimeOfDay($jam['startdate'] ?? null)) {
            $dates .= ', ' . $start->format('H:i') . '–' . $end->format('H:i');
        }

        return $dates;
    }
}
