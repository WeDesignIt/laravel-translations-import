# Import your language files to the database (and much more)

This package provides you with commands to deal with your lang files and your
database. Instead of manually adding translations to your database,
this commandset gives you the tools needed to import and export translations,
find unimported translations in your php/twig files, clean empty translations from 
your database and to delete all translations in one go!

## Usefulness and credit
It's particularly useful in the case you're using a package like  
[Spatie's Laravel Translation Loader](https://github.com/spatie/laravel-translation-loader). 
In this case the lang files can be used as 'defaults' to import into a project.
When you want to add new defaults, just add them to your lang file(s), rerun the 
import command and the newly added translations will be added for usage, without 
the existing translations being modified. You can export the files if you want the updated 
translations to be used as default. 

Credit to [Barryvdh's Translation Manager](https://github.com/barryvdh/laravel-translation-manager) for 
creating this package which we could build on.


## Usage

### The import command
```
php artisan translations:import
```
This command will import all translation files located in the set lang folder.

The command offers 6 options:
 * `ignore-locales`: Allows you to set locales that should **not** be imported. 
 <br>Example: `--ignore-locales=fr,de`.    
 * `ignore-groups`: Allows you to set groups that should **not** be imported.
 <br>Example: `--ignore-groups=routes,admin/dashboard,frontend/employer`.
 * `only-groups`: Allows you to set groups that should **only** be imported. No
 other group will be imported. Works like `ignore-groups`.
 * `overwrite`: Option to enable overwriting existing translations in the database.
 Shortcut `o`.
 * `allow-vendor`: Option to allow vendor translation overrides to be imported
 (The lang files at `lang/vendor/{package}/`). Shortcut `a`.
 * `allow-json`: Option to allow JSON translations to be imported. Shortcut `j`.

<small>Command options should be split by commas.<br>
For groups, a wildcard (*) can be used at the end to allow all subdirectories
to be processed as well (for either `ignore` or `only`). 
Example: `--only-groups=admin/*`.</small>


### The export command
```
php artisan translations:export
```
This command will take all translations in the database, and write them to the lang folder.
Existing translations will be overwritten (except for files that don't exist in the database).

The command offers 4 options:
 * `ignore-groups`
 * `only-groups`
 * `allow-vendor`
 * `allow-json`
 
<small>These options work the same as explained with the import command</small> 


### The find command
```
php artisan translations:find
```
This command will find all translations in your php/twig files, and import them
if they do not exist in the database.

The command offers 1 option:
 * `path`: By default, the find command starts in the root directory. Using this
 option, you can point the command to only search in a subdirectory. 
 <br>Example: `--path=resources/lang`.


### The clean command
```
php artisan translations:clean
```
This command will remove all translations which do not have a stored value.

The command has no options. 


### The delete command
```
php artisan translations:nuke
```
This command will remove all translations.

The command has 1 option: 
 * `only-groups`

<small>This options works the same as explained with the import command</small>
 
 
## Installation

You can install the package via composer: 

``` bash
composer require wedesignit/laravel-translations-import
```


## Config
By default, the config sets the tables and columns following the `language_lines`
table from the [Spatie's Laravel Translation Loader](https://github.com/spatie/laravel-translation-loader)
migration, with the following structure:
 * `group`: Stores the group (`string`).
 * `key`: Stores the key (`string`).
 * `text`: Stores the translation (in JSON format) (`text`).

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
    'translations' => 'text',

    /**
     * Array of functions which are used to get translations
     */
    'trans_functions' => [
        'trans',
        'trans_choice',
        'Lang::get',
        'Lang::choice',
        'Lang::trans',
        'Lang::transChoice',
        '@lang',
        '@choice',
        '__',
        '$trans.get',
    ],
];
```
