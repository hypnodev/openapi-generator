<?php

namespace Hypnodev\OpenapiGenerator;

use Hypnodev\OpenapiGenerator\Commands\OpenApiGenerate;
use Illuminate\Support\ServiceProvider;

class OpenapiGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/openapi-generator.php' => config_path('openapi-generator.php'),
            ], 'config');

            // Registering package commands.
            $this->commands([
                OpenApiGenerate::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/openapi-generator.php', 'openapi-generator');

        // Register the main class to use with the facade
        $this->app->singleton('openapi-generator', function () {
            return new OpenapiGenerator(
                config('openapi-generator.definition')
            );
        });
    }
}
