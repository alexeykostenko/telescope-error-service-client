<?php

namespace PDFfiller\TelescopeClient;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as SupportServiceProvider;
use PDFfiller\TelescopeClient\Contracts\EntriesRepository;
use PDFfiller\TelescopeClient\Contracts\PrunableRepository;
use PDFfiller\TelescopeClient\Storage\TelescopeServerEntriesRepository;

use PDFfiller\TelescopeClient\Http\Client;

class ServiceProvider extends SupportServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPublishing();

        Telescope::start($this->app);
        Telescope::listenForStorageOpportunities($this->app);
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    private function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/telescope-client.php' => config_path('telescope.php'),
            ], 'telescope-config');
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telescope-client.php', 'telescope'
        );

        $this->registerStorageDriver();

        $this->commands([
            Console\InstallCommand::class,
            Console\PublishCommand::class,
        ]);
    }

    /**
     * Register the package storage driver.
     *
     * @return void
     */
    protected function registerStorageDriver()
    {
        $driver = config('telescope-client.driver');

        if (method_exists($this, $method = 'register'.ucfirst($driver).'Driver')) {
            $this->$method();
        }
    }

    /**
     * Register the package database storage driver.
     *
     * @return void
     */
    protected function registerDatabaseDriver()
    {
        $this->app->singleton(
            EntriesRepository::class, TelescopeServerEntriesRepository::class
        );

/*        $this->app->when(TelescopeServerEntriesRepository::class)
            ->needs('$httpClient')
            ->give(new Client(config('telescope-client.server')));*/
    }
}