<?php

namespace Spatie\Translatable\Test;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use WeDesignIt\LaravelTranslationsImport\TranslationsImportServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [TranslationsImportServiceProvider::class];
    }
}