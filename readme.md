# Import your language files to the database


## Basic usage
```
php artisan translations:import
```

The command will import all existing translations located in the resources/lang folder.


## Installation

You can install the package via composer:

``` bash
composer require wedesingit/laravel-translation-import
```

Optionally you could publish the config file to change table and column names.

```
php artisan vendor:publish --provider="Wedesignit\LaravelTranslationsImport\TranslationsImportServiceProvider" --tag="config"
```

This is the contents of the published config file:
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
