# Import your language files to the database

This package provides you with a command to import the translation files to your database.
It's particularly useful in the case you're using a package like Spatie's 
[Laravel Translation Loader](https://github.com/spatie/laravel-translation-loader). 
In this case the lang files can be used as 'defaults' to import into a project.
When you want to add new defaults, just add them to your lang file(s), rerun the 
import command and the newly added translations will be added for usage, without 
the existing translations being modified.

## Basic usage

This command will import all translation files located in the resources/lang folder.
```
php artisan translations:import
```

There is a way to only import certain groups
```bash 
php artisan translations:import --only-groups=admin/companies/user,frontend/login,home
```

Its also possible to ignore certain groups or locales

```bash
php artisan translations:import --ignore-groups=routes,defaults --ignore-locales=en,fr
```

While it's turned of by default, we offer a way of overwriting all the existing translations
```bash
php artisan translations:import --overwrite-existing-translations
```

## Installation

You can install the package via composer: 

``` bash
composer require wedesignit/laravel-translations-import
```

Optionally you could publish the config file to change table and column names.

```bash
php artisan vendor:publish --provider="WeDesignIt\LaravelTranslationsImport\TranslationsImportServiceProvider" --tag="config"
```

This is the content of the published config file:
```php
<?php

return [
    /**
     * The table where the translations are stored
     */
    'table' => 'language_lines',

    /**
     * The column where the translations group should be stored
     */
    'group' => 'group',

    /**
     * The column where the translation key should be stored
     */
    'key' => 'key',

    /**
     * The column where the translation text itself should be stored
     */
    'translations' => 'text'
];
```
