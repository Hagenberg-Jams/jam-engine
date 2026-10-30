<?php

declare(strict_types=1);

namespace HagenbergJams\JamEngine;

use HagenbergJams\JamEngine\Actions\ACleanSiteDirectoryBuildTask;
use HagenbergJams\JamEngine\Actions\AValidateDataBuildTask;
use HagenbergJams\JamEngine\Actions\CopyGamesBuildTask;
use HagenbergJams\JamEngine\Actions\CopyHtaccessBuildTask;
use HagenbergJams\JamEngine\Actions\CopyRobotsTxtBuildTask;
use HagenbergJams\JamEngine\Actions\GeneratePagesBuildTask;
use HagenbergJams\JamEngine\Actions\PreBuildTasks\TransferMediaAssets;
use HagenbergJams\JamEngine\Actions\PrepareFunctionsBuildTask;
use HagenbergJams\JamEngine\Commands\AddProjectCommand;
use HagenbergJams\JamEngine\Commands\AddSponsorCommand;
use HagenbergJams\JamEngine\Commands\ConvertImagesCommand;
use HagenbergJams\JamEngine\Commands\CreateJamCommand;
use HagenbergJams\JamEngine\Commands\MigrateDataCommand;
use HagenbergJams\JamEngine\Commands\PrintProjectSheetsCommand;
use HagenbergJams\JamEngine\Commands\RemoveSponsorCommand;
use HagenbergJams\JamEngine\Commands\SyncR2Command;
use HagenbergJams\JamEngine\Commands\UpdateChecksumsCommand;
use HagenbergJams\JamEngine\Commands\ValidateCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class JamEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var array<mixed> $site */
        $site = (array) config('jam', []);
        config(['jam' => JamConfig::resolve($site)]);

        // The order matters: the clean task must run before the tasks that write into _site.
        $engineTasks = [
            AValidateDataBuildTask::class,
            ACleanSiteDirectoryBuildTask::class,
            TransferMediaAssets::class,
            CopyGamesBuildTask::class,
            CopyHtaccessBuildTask::class,
            CopyRobotsTxtBuildTask::class,
            GeneratePagesBuildTask::class,
            PrepareFunctionsBuildTask::class,
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
                AddProjectCommand::class,
                AddSponsorCommand::class,
                ConvertImagesCommand::class,
                CreateJamCommand::class,
                MigrateDataCommand::class,
                PrintProjectSheetsCommand::class,
                RemoveSponsorCommand::class,
                SyncR2Command::class,
                UpdateChecksumsCommand::class,
                ValidateCommand::class,
            ]);
        }
    }
}
