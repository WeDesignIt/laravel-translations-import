<?php

namespace WeDesignIt\LaravelTranslationsImport;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;

// TODO: Export, Find, Clean and Reset

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

    /**
     * Manager constructor.
     * @param Application $app
     * @param Filesystem $files
     * @param Dispatcher $events
     */
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

    /**
     * Process to import all translations.
     * @param array $options
     * @param string $vendorPath
     * @return int|mixed
     */
    public function importTranslations($options = [], $vendorPath = '')
    {
        $logInfo = '';
        $vendor = false;

        // Set options
        $this->options = $options;

        // Set the lang path
        $base = $this->app['path.lang'];

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
                        if ($this->groupCanBeProcessed($group))
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
                if ($this->groupCanBeProcessed($group))
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

    /**
     * Process the import a singular translation.
     * @param $key
     * @param $value
     * @param $locale
     * @param $group
     * @return bool
     */
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
            $text = json_decode($translation->{$translationColumn}, true);

            // If the locale is not set, or if replace is true, or if the translation is empty
            if (!isset($text[$locale]) || $this->options['overwrite'] || empty($text[$locale]))
            {
                // Update the translation
                $text[$locale] = $value;
                $translation->{$translationColumn} = json_encode($text);
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

    /**
     * Process to export all translations.
     * @param $options
     */
    public function exportTranslations($options)
    {
        // Set options
        $this->options = $options;

        // Get all groups
        $groupObjects = DB::table($this->databaseData['table'])
            ->select('group')
            ->groupBy('group')
            ->get();

        foreach ($groupObjects as $groupObject)
        {
            $group = $groupObject->group;

            $vendor = false;
            $json = false;
            if (Str::startsWith($group, 'vendor')) {
                $vendor = true;
            }
            else if ($group == self::JSON_GROUP) {
                $json = true;
            }

            // Process separately if it's a vendor group or JSON
            if ($vendor && $this->options['allow-vendor'])
            {
                $fullGroup = $group;
                // Get an array of each nesting
                $subfolders = explode(DIRECTORY_SEPARATOR, $group);
                // Set the actual group
                $group = implode(DIRECTORY_SEPARATOR, array_slice($subfolders, 2));

                $this->processExportForGroup($group, $fullGroup);
            }
            else if ($json && $this->options['allow-json'])
            {

            }
            else
            {
                 $this->processExportForGroup($group);
            }
        }
    }

    public function processExportForGroup($group, $fullGroup = null)
    {
        if ($this->groupCanBeProcessed($group))
        {
            // Get all translations by group
            $translations = DB::table($this->databaseData['table'])
                ->where($this->databaseData['groupColumn'], $fullGroup ?? $group)
                ->get();

            // Make a tree for this group
            $tree = $this->makeTree($translations);

            $this->exportTranslationGroup($tree, $group, $fullGroup);
        }
    }


    public function exportTranslationGroup($tree, $group, $fullGroup = null)
    {
        // Loop through all groups
        foreach ($tree as $locale => $groups)
        {
            // Only process if the current group is present
            if (isset($groups[$fullGroup ?? $group])) {
                // Get the translations for this group
                $translations = $groups[$fullGroup ?? $group];

                // Set the lang path
                $base = $this->app['path.lang'];

                // If the full group exists, and is a vendor group
                if (isset($fullGroup) && Str::startsWith($fullGroup, 'vendor'))
                {
                    // Construct the proper path to the locale
                    $vendorGroup = str_replace("/{$group}", '', $fullGroup);

                    $localePath = $vendorGroup.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group;
                }
                else {
                    // Define the localePath, based of locale and group
                    $localePath = $locale.DIRECTORY_SEPARATOR.$group;
                }

                // Get an array of each nesting
                $subfolders = explode(DIRECTORY_SEPARATOR, $localePath);
                // Remove the last item (which is the actual .php file)
                array_pop($subfolders);

                $subfolder_level = '';
                // Loop through each subfolder to validate the full path
                foreach ($subfolders as $subfolder) {
                    // Define the path to the current subfolder
                    $subfolder_level = $subfolder_level.$subfolder.DIRECTORY_SEPARATOR;
                    // Build a path
                    $temp_path = rtrim($base.DIRECTORY_SEPARATOR.$subfolder_level, DIRECTORY_SEPARATOR);

                    // If the directory doesn't exist, ensure to make it
                    if (! is_dir($temp_path)) {
                        mkdir($temp_path, 0775, true);
                    }
                }
                // The path is now fully validated

                // Define the path of the
                $filePath = $base.DIRECTORY_SEPARATOR.$localePath.'.php';

                // Convert the translations into valid PHP code to be written to the file
                $output = "<?php\n\nreturn ".var_export($translations, true).';'.\PHP_EOL;
                // Write the translations to the file
                $this->files->put($filePath, $output);
            }
        }
    }

    /**
     * Build a nested tree array.
     * @param $translations
     * @param bool $json
     * @return array
     */
    protected function makeTree($translations, $json = false)
    {
        $array = [];
        foreach ($translations as $translation) {
            if ($json) {
                /** WIP */
                $this->jsonSet($array[$translation->locale][$translation->group], $translation->key,
                    $translation->value);
            } else {
                // Retrieve the translation values
                $text = json_decode($translation->{$this->databaseData['translationColumn']}, true);
                // Loop through all locales
                foreach ($text as $locale => $value)
                {
                    // Build a tree nested array, shaped as locale => groups => keys => value
                    Arr::set($array[$locale][$translation->{$this->databaseData['groupColumn']}],
                        $translation->{$this->databaseData['keyColumn']}, $value);
                }
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

    public function groupCanBeProcessed($group)
    {
        $groupsToProcess = explode(',', $this->options['only-groups']);
        $groupsToIgnore = explode(',', $this->options['ignore-groups']);

        if (!is_null($this->options['only-groups']) && !in_array($group, $groupsToProcess)) {
            return false;
        }

        if (!is_null($this->options['ignore-groups']) && in_array($group, $groupsToIgnore)) {
            return false;
        }

        return true;
    }
}
