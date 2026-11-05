<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Commands;

use Carbon\CarbonImmutable;
use HagenbergJams\JamEngine\JamData;
use HagenbergJams\JamEngine\JamSchedule;
use Illuminate\Console\Command;

/**
 * Command to set, change or remove the topic release of a jam.
 *
 * Only the `topic_release` line of `_data/jams/{year}.yaml` changes, so comments and the order of the
 * other fields stay as they are.
 */
class TopicReleaseCommand extends Command
{
    protected $signature = 'jam:topic-release
        {time?* : Release time as YYYY-MM-DD HH:MM in the jam time zone}
        {--year= : Year of the jam, the newest year by default}
        {--remove : Remove the topic release, so the topic is shown without a countdown}';

    protected $description = 'Set, change or remove the topic release of a jam';

    public function handle(): int
    {
        $year = $this->year();
        if ($year === null) {
            return 1;
        }

        $file = base_path("_data/jams/{$year}.yaml");
        $content = file_get_contents($file);
        if ($content === false) {
            $this->error("Failed to read {$file}");
            return 1;
        }

        // Read the file itself, because JamData hides a pending topic.
        $jam = \Symfony\Component\Yaml\Yaml::parse($content);
        $jam = is_array($jam) ? $jam : [];
        $current = JamSchedule::topicRelease($jam);
        $this->info('Current topic release: ' . ($current?->format('Y-m-d H:i T') ?? 'none'));

        if ($this->option('remove')) {
            file_put_contents($file, (string) preg_replace('/^topic_release:.*\R?/m', '', $content));
            $this->info("Removed the topic release from _data/jams/{$year}.yaml.");
            return 0;
        }

        $release = $this->releaseTime();
        if ($release === null) {
            return 1;
        }

        // A topic in the file would be public before the release, see the README of the engine.
        if (!empty($jam['topic']) && CarbonImmutable::now()->lessThan($release)) {
            $this->error('Remove topic from the YAML file first and store it as the JAM_TOPIC secret on GitHub.');
            return 1;
        }

        $line = "topic_release: '{$release->toIso8601String()}'";
        $content = preg_match('/^topic_release:.*$/m', $content) === 1
            ? (string) preg_replace('/^topic_release:.*$/m', $line, $content)
            : rtrim($content) . "\n{$line}\n";
        file_put_contents($file, $content);

        $this->info("Set the topic release of {$year} to {$release->format('Y-m-d H:i T')}.");
        if (CarbonImmutable::now()->greaterThanOrEqualTo($release)) {
            $this->warn('This time is in the past, so the topic is shown right away.');
        }
        $this->line('Commit and push the change to deploy it. The JAM_TOPIC secret stays as it is.');

        return 0;
    }

    protected function year(): ?int
    {
        $years = JamData::getAvailableYears();
        $option = $this->option('year');
        $year = is_numeric($option) ? (int) $option : (empty($years) ? null : max($years));

        if ($year === null || !in_array($year, $years, true)) {
            $this->error('No jam found. Create one with jam:create-jam first.');
            return null;
        }

        return $year;
    }

    protected function releaseTime(): ?CarbonImmutable
    {
        $argument = $this->argument('time');
        $input = is_array($argument) && $argument !== []
            ? implode(' ', $argument)
            : $this->ask('New topic release (YYYY-MM-DD HH:MM)');

        if (!is_string($input) || preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', trim($input)) !== 1) {
            $this->error('The time must be in the format YYYY-MM-DD HH:MM.');
            return null;
        }

        return JamSchedule::parse(trim($input));
    }
}
