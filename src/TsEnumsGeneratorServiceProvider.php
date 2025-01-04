<?php

namespace XiDanko\TsEnumsGenerator;

use Illuminate\Support\ServiceProvider;
use XiDanko\TsEnumsGenerator\Console\Commands\Generate;

class TsEnumsGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'ts-enums-generator');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/config.php' => config_path('ts-enums-generator.php')]);
        if ($this->app->runningInConsole()) {
            $this->commands([
                Generate::class,
            ]);
        }
    }
}
