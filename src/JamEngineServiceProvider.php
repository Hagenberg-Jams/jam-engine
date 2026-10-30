<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use HagenbergJams\JamEngine\Actions\ACleanSiteDirectoryBuildTask;
use HagenbergJams\JamEngine\Actions\CopyGamesBuildTask;
use HagenbergJams\JamEngine\Actions\CopyHtaccessBuildTask;
use HagenbergJams\JamEngine\Actions\CopyRobotsTxtBuildTask;
use HagenbergJams\JamEngine\Actions\GenerateGameJamPagesBuildTask;
use HagenbergJams\JamEngine\Actions\PreBuildTasks\TransferMediaAssets;
use HagenbergJams\JamEngine\Commands\AddGameCommand;
use HagenbergJams\JamEngine\Commands\AddSponsorCommand;
use HagenbergJams\JamEngine\Commands\ConvertImagesCommand;
use HagenbergJams\JamEngine\Commands\CreateGameJamCommand;
use HagenbergJams\JamEngine\Commands\PrintGameSheetsCommand;
use HagenbergJams\JamEngine\Commands\RemoveSponsorCommand;
use HagenbergJams\JamEngine\Commands\SyncR2Command;
use HagenbergJams\JamEngine\Commands\UpdateGameChecksumsCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class JamEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The order matters: the clean task must run before the tasks that write into _site.
        $engineTasks = [
            ACleanSiteDirectoryBuildTask::class,
            TransferMediaAssets::class,
            CopyGamesBuildTask::class,
            CopyHtaccessBuildTask::class,
            CopyRobotsTxtBuildTask::class,
            GenerateGameJamPagesBuildTask::class,
        ];

        /** @var array<int, string> $siteTasks */
        $siteTasks = (array) config('hyde.build_tasks', []);

        config(['hyde.build_tasks' => array_merge($engineTasks, $siteTasks)]);
    }

    public function boot(): void
    {
        $views = __DIR__ . '/../resources/views';

        // Added after the site's own view path, so a site can override any view by using the same path.
        View::addLocation($views);
        View::prependNamespace('hyde', $views . '/vendor/hyde');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AddGameCommand::class,
                AddSponsorCommand::class,
                ConvertImagesCommand::class,
                CreateGameJamCommand::class,
                PrintGameSheetsCommand::class,
                RemoveSponsorCommand::class,
                SyncR2Command::class,
                UpdateGameChecksumsCommand::class,
            ]);
        }
    }
}
