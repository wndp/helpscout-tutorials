<?php

namespace Wndp\HelpScoutTutorials;

use Illuminate\Support\ServiceProvider;
use Wndp\HelpScoutTutorials\Console\CategorizeArticle;
use Wndp\HelpScoutTutorials\Console\CreateTutorial;
use Wndp\HelpScoutTutorials\Console\DownloadTutorials;
use Wndp\HelpScoutTutorials\Console\InstallCommand;
use Wndp\HelpScoutTutorials\Console\ListCategories;
use Wndp\HelpScoutTutorials\Console\ManageCategory;
use Wndp\HelpScoutTutorials\Console\PublishTutorial;

class HelpScoutTutorialsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/helpscout-tutorials.php',
            'helpscout-tutorials',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DownloadTutorials::class,
                PublishTutorial::class,
                CreateTutorial::class,
                ListCategories::class,
                ManageCategory::class,
                CategorizeArticle::class,
                InstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/helpscout-tutorials.php' => config_path('helpscout-tutorials.php'),
            ], 'helpscout-tutorials-config');

            $this->publishes([
                __DIR__.'/../resources/claude-commands' => base_path('.claude/commands'),
            ], 'helpscout-tutorials-claude-commands');
        }
    }
}
