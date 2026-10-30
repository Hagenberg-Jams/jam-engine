<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine\Actions;

use HagenbergJams\JamEngine\JamData;
use HagenbergJams\JamEngine\JamSchedule;
use Hyde\Framework\Features\BuildTasks\PostBuildTask;

/**
 * Generates functions/ for Cloudflare Pages when a jam has a topic release.
 *
 * The release time comes from the jam's YAML file, so there is only one place to change it.
 * The topic itself only exists as a secret in the Cloudflare project.
 */
class PrepareFunctionsBuildTask extends PostBuildTask
{
    protected static string $message = 'Preparing Cloudflare Pages functions';

    public function handle(): void
    {
        $year = JamData::latestYearWithTopicRelease();
        $jam = $year !== null ? JamData::getJam($year) : null;
        $release = $jam !== null ? JamSchedule::topicRelease($jam) : null;

        if (!config('jam.features.topic_release') || $release === null) {
            $this->skip('No topic release configured');

            return;
        }

        $source = dirname(__DIR__, 2) . '/resources/functions/api';
        $target = base_path('functions/api');

        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        copy("{$source}/topic.js", "{$target}/topic.js");
        copy("{$source}/_topic-logic.js", "{$target}/_topic-logic.js");
        file_put_contents(
            "{$target}/_topic-config.json",
            json_encode(['year' => $year, 'release' => $release->toIso8601String()], JSON_PRETTY_PRINT) . "\n",
        );
    }
}
