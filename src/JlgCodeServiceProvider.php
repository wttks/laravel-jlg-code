<?php

declare(strict_types=1);

namespace Wttks\JlgCode;

use Illuminate\Support\ServiceProvider;
use Wttks\JlgCode\Console\Commands\ImportMunicipalitiesCommand;
use Wttks\JlgCode\Console\Commands\UpdateMunicipalitiesCommand;

class JlgCodeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
            __DIR__.'/../database/seeders' => database_path('seeders'),
            __DIR__.'/../resources/data' => storage_path('app/data'),
        ], 'jlg-code-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportMunicipalitiesCommand::class,
                UpdateMunicipalitiesCommand::class,
            ]);
        }
    }
}
