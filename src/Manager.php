<?php

namespace WeDesignIt\LaravelTranslationsImport;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;

// TODO: Export, Clean and Reset (Not doing Find)

class Manager
{
    const JSON_GROUP = '_json';

    const LOGGING = [
        'info' => "\033[32m%s\033[0m",
        'error' => "\033[41m%s\033[0m",
    ];

    /** @var \Illuminate\Contracts\Foundation\Application */
    protected $app;
    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;
    /** @var \Illuminate\Contracts\Events\Dispatcher */
    protected $events;

    protected $locales;

    /** @var array $databaseData */
    protected $databaseData;

    /** @var array $options */
    protected array $options;

    public function __construct(Application $app, Filesystem $files, Dispatcher $events)
    {
        $this->app = $app;
        $this->files = $files;
        $this->events = $events;
        $this->locales = [];

        $databaseData = [
            'table' => config('translations-import.table'),
            'groupColumn' => config('translations-import.group'),
            'keyColumn' => config('translations-import.key'),
            'translationColumn' => config('translations-import.translations'),
        ];

        if (array_search('', $databaseData) !== false) {
            $error = 'Table and column values cannot be null/empty! Ensure table, group, key and translations are set in config/translations-import.php!';
            error_log(sprintf(self::LOGGING['error'], $error));
            die;
        }

        $this->databaseData = $databaseData;
    }

    public function importTranslations($options = [], $vendorPath = '')
    {
        $logInfo = '';
        $vendor = false;

        // Set options
        $this->options = $options;

        // Set the lang path
        $base = config('translations-import.lang_path');
        if (empty($base)) {
            $base = $this->app['path.lang'];
        }

        // Change lang path if it's a vendor directory
        if (!empty($vendorPath)) {
            $base = $vendorPath;
            $logInfo = ' for vendor package ' . basename($base);
            $vendor = true;
        }

        $counter = 0;

        // Loop through all directories in the base path
        foreach ($this->files->directories($base) as $langPath)
        {
            // Get locale from path
            $locale = basename($langPath);

            // If the locale can be imported (and is not in the ignore-locales)
            if ($this->localeCanBeImported($locale) || $locale == 'vendor')
            {
                if ($locale == 'vendor')
                {
                    if ($this->options['allow-vendor'])
                    {
                        // Pass all packages as new langpath and rerun this function
                        foreach ($this->files->directories($langPath) as $vendorPath) {
                            $counter += $this->importTranslations($this->options, $vendorPath);
                        }
                    }
                }
                else
                {
                    error_log(sprintf(self::LOGGING['info'], "Processing locale '{$locale}'{$logInfo}"));

                    // Get the directory route of the locale, then the name of the directory (which is the package)
                    $packageName = $this->files->name($this->files->dirname($langPath));

                    // Loop through all files in the locale
                    foreach ($this->files->allfiles($langPath) as $file)
                    {
                        $info = pathinfo($file);
                        $group = $info['filename'];
                        if ($this->groupCanBeImported($group))
                        {
                            // Ensure separator consistency
                            $subLangPath = str_replace($langPath.DIRECTORY_SEPARATOR, '', $info['dirname']);
                            $subLangPath = str_replace(DIRECTORY_SEPARATOR, '/', $subLangPath);
                            $langPath = str_replace(DIRECTORY_SEPARATOR, '/', $langPath);

                            if ($subLangPath != $langPath) {
                                $group = $subLangPath.'/'.$group;
                            }


                            if ($vendor) {
                                // We can't use the loader here, so we just grab the whole file
                                $translations = include $file;
                                $group = "vendor/{$packageName}/{$group}";
                            }
                            else {
                                // Load all translations in an associative array
                                $translations = \Lang::getLoader()->load($locale, $group);
                            }

                            // Loop through all translations
                            if ($translations && is_array($translations)) {
                                // Convert nested array keys to dots ('auth' => [ 'login' => 'Login', ], to auth.login
                                foreach (Arr::dot($translations) as $key => $value) {
                                    // Import the translation
                                    $importedTranslation = $this->importTranslation($key, $value, $locale, $group);
                                    // Add to the counter if the translation was successful
                                    $counter += $importedTranslation ? 1 : 0;
                                }
                            }
                        }
                    }
                }
            }
            else {
                error_log(sprintf(self::LOGGING['info'], "Skipping locale '{$locale}'{$logInfo}"));
            }
        }

        // Loop through all JSON files
        foreach ($this->files->files($this->app['path.lang']) as $jsonTranslationFile) {
            // Only continue if it's a valid .json
            if (strpos($jsonTranslationFile, '.json') === false) {
                continue;
            }
            $locale = basename($jsonTranslationFile, '.json');
            if ($this->localeCanBeImported($locale))
            {
                error_log(sprintf(self::LOGGING['info'], "Processing JSON locale '{$locale}'"));

                $group = self::JSON_GROUP;
                if ($this->groupCanBeImported($group))
                {
                    // Retrieves JSON entries of the given locale only
                    $translations = \Lang::getLoader()->load($locale, '*', '*');

                    // Import all translations from the JSON
                    if ($translations && is_array($translations)) {
                        foreach ($translations as $key => $value) {
                            $importedTranslation = $this->importTranslation($key, $value, $locale, $group);
                            $counter += $importedTranslation ? 1 : 0;
                        }
                    }
                }
            }
            else {
                error_log(sprintf(self::LOGGING['info'], "Skipping JSON locale '{$locale}'"));
            }
        }

        return $counter;
    }

    public function importTranslation($key, $value, $locale, $group)
    {
        // Process only string values
        if (is_array($value)) {
            return false;
        }

        $table = $this->databaseData['table'];
        $groupColumn = $this->databaseData['groupColumn'];
        $keyColumn = $this->databaseData['keyColumn'];
        $translationColumn = $this->databaseData['translationColumn'];

        // See if a translation already exists
        $translation = DB::table($table)
            ->where($groupColumn, $group)
            ->where($keyColumn, $key)
            ->first();

        // If a translation does exist
        if (isset($translation))
        {
            $text = json_decode($translation->text, true);

            // If the locale is not set, or if replace is true, or if the translation is empty
            if (!isset($text[$locale]) || $this->options['overwrite'] || empty($text[$locale]))
            {
                // Update the translation
                $text[$locale] = $value;
                $translation->text = json_encode($text);
                DB::table($table)
                    ->where($groupColumn, $group)
                    ->where($keyColumn, $key)
                    ->update((array) $translation);

                return true;
            }
        }
        else
        {
            $text = [
                $locale => $value,
            ];

            // Insert the translation into the database from the config
            DB::table($table)
                ->insert([
                    $groupColumn => $group,
                    $keyColumn => $key,
                    $translationColumn => json_encode($text),
                ]);

            return true;
        }

        return false;
    }

    public function exportTranslations($group = null, $json = false)
    {
        $basePath = $this->app['path.lang'];

        if (! is_null($group) && ! $json) {
            if (! in_array($group, $this->config['exclude_groups'])) {
                $vendor = false;
                if ($group == '*') {
                    return $this->exportAllTranslations();
                } else {
                    if (Str::startsWith($group, 'vendor')) {
                        $vendor = true;
                    }
                }

                $tree = $this->makeTree(Translation::ofTranslatedGroup($group)
                    ->orderByGroupKeys(Arr::get($this->config, 'sort_keys', false))
                    ->get());

                foreach ($tree as $locale => $groups) {
                    if (isset($groups[$group])) {
                        $translations = $groups[$group];
                        $path = $this->app['path.lang'];

                        $locale_path = $locale.DIRECTORY_SEPARATOR.$group;
                        if ($vendor) {
                            $path = $basePath.'/'.$group.'/'.$locale;
                            $locale_path = Str::after($group, '/');
                        }
                        $subfolders = explode(DIRECTORY_SEPARATOR, $locale_path);
                        array_pop($subfolders);

                        $subfolder_level = '';
                        foreach ($subfolders as $subfolder) {
                            $subfolder_level = $subfolder_level.$subfolder.DIRECTORY_SEPARATOR;

                            $temp_path = rtrim($path.DIRECTORY_SEPARATOR.$subfolder_level, DIRECTORY_SEPARATOR);
                            if (! is_dir($temp_path)) {
                                mkdir($temp_path, 0777, true);
                            }
                        }

                        $path = $path.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group.'.php';

                        $output = "<?php\n\nreturn ".var_export($translations, true).';'.\PHP_EOL;
                        $this->files->put($path, $output);
                    }
                }
                Translation::ofTranslatedGroup($group)->update(['status' => Translation::STATUS_SAVED]);
            }
        }

        if ($json) {
            $tree = $this->makeTree(Translation::ofTranslatedGroup(self::JSON_GROUP)
                ->orderByGroupKeys(Arr::get($this->config, 'sort_keys', false))
                ->get(), true);

            foreach ($tree as $locale => $groups) {
                if (isset($groups[self::JSON_GROUP])) {
                    $translations = $groups[self::JSON_GROUP];
                    $path = $this->app['path.lang'].'/'.$locale.'.json';
                    $output = json_encode($translations, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
                    $this->files->put($path, $output);
                }
            }

            Translation::ofTranslatedGroup(self::JSON_GROUP)->update(['status' => Translation::STATUS_SAVED]);
        }

        $this->events->dispatch(new TranslationsExportedEvent());
    }

    public function exportAllTranslations()
    {
        $groups = Translation::whereNotNull('value')->selectDistinctGroup()->get('group');

        foreach ($groups as $group) {
            if ($group->group == self::JSON_GROUP) {
                $this->exportTranslations(null, true);
            } else {
                $this->exportTranslations($group->group);
            }
        }

        $this->events->dispatch(new TranslationsExportedEvent());
    }

    protected function makeTree($translations, $json = false)
    {
        $array = [];
        foreach ($translations as $translation) {
            if ($json) {
                $this->jsonSet($array[$translation->locale][$translation->group], $translation->key,
                    $translation->value);
            } else {
                Arr::set($array[$translation->locale][$translation->group], $translation->key,
                    $translation->value);
            }
        }

        return $array;
    }

    public function jsonSet(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }
        $array[$key] = $value;

        return $array;
    }

    public function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    public function truncateTranslations()
    {
        Translation::truncate();
    }

    public function getLocales()
    {
        if (empty($this->locales)) {
            $locales = array_merge([config('app.locale')],
                Translation::groupBy('locale')->pluck('locale')->toArray());
            foreach ($this->files->directories($this->app->langPath()) as $localeDir) {
                if (($name = $this->files->name($localeDir)) != 'vendor') {
                    $locales[] = $name;
                }
            }

            $this->locales = array_unique($locales);
            sort($this->locales);
        }

        return array_diff($this->locales, $this->ignoreLocales);
    }

    public function addLocale($locale)
    {
        $localeDir = $this->app->langPath().'/'.$locale;

        $this->ignoreLocales = array_diff($this->ignoreLocales, [$locale]);
        $this->saveIgnoredLocales();
        $this->ignoreLocales = $this->getIgnoredLocales();

        if (! $this->files->exists($localeDir) || ! $this->files->isDirectory($localeDir)) {
            return $this->files->makeDirectory($localeDir);
        }

        return true;
    }

    protected function saveIgnoredLocales()
    {
        return $this->files->put($this->ignoreFilePath, json_encode($this->ignoreLocales));
    }

    public function removeLocale($locale)
    {
        if (! $locale) {
            return false;
        }
        $this->ignoreLocales = array_merge($this->ignoreLocales, [$locale]);
        $this->saveIgnoredLocales();
        $this->ignoreLocales = $this->getIgnoredLocales();

        Translation::where('locale', $locale)->delete();
    }

    public function getConfig($key = null)
    {
        if ($key == null) {
            return $this->config;
        } else {
            return $this->config[$key];
        }
    }




    public function localeCanBeImported($locale)
    {
        return ! in_array($locale, explode(',', $this->options['ignore-locales']));
    }

    public function groupCanBeImported($group)
    {
//        $groupsToImport = explode(',', $this->option('only-groups'));
        $groupsToIgnore = explode(',', $this->options['ignore-groups']);

//        if (!is_null($this->option('only-groups')) && !in_array($group, $groupsToImport)) {
//            return false;
//        }

        if (!is_null($this->options['ignore-groups']) && in_array($group, $groupsToIgnore)) {
            return false;
        }

        return true;
    }
}
