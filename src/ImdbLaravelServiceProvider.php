<?php

namespace KadeGray\ImdbLaravel;

use Illuminate\Support\ServiceProvider;
use KadeGray\ImdbLaravel\Console\Commands\ImdbImport;
use KadeGray\ImdbLaravel\Console\Commands\ImdbDump;

class ImdbLaravelServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register the command if we are using the application via the CLI
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImdbImport::class,
                ImdbDump::class,
            ]);
        }
    }
}
