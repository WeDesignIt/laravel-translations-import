<?php

namespace Frogeyedman\LaravelTranslationsImport;

use Frogeyedman\LaravelTranslationsImport\Console\Commands\TranslationsImport;
use Illuminate\Support\ServiceProvider;

class TranslationsImportServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TranslationsImport::class
            ]);
        }
    }
}
