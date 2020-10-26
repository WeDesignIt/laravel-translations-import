<?php

namespace WeDesignIt\LaravelTranslationsImport\Console\Commands;

use WeDesignIt\LaravelTranslationsImport\Helpers\LangDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class TranslationsImportOld extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:import_old

        {--only-groups=                     : Only import given groups, ex: admin/employer,frontend/general/setting}
        {--ignore-locales=                  : Locales that should be ignored during the importing process, ex: --ignore-locales=fr,de }
        {--ignore-groups=                   : Groups that should not be imported, ex: --ignore-groups=routes,admin/non-editable-stuff }
        {--overwrite-existing-translations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import translations from the resources/lang folder.';

    /**
     * To track the total updates
     *
     * @var int
     */
    protected $updateCounter = 0;

    /**
     * To track the total creates
     *
     * @var int
     */
    protected $createCounter = 0;

    /**
     * Track the total overwrites
     *
     * @var int
     */
    protected $overwriteCounter = 0;

    /**
     * To determine if the existing translations should be overwritten
     *
     * @var bool
     */
    protected $shouldOverWriteExistingTranslations = false;

    /*
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get the existing translation from the database
     *
     * @param $group
     * @param $key
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getExistingTranslation($group, $key)
    {
        return DB::table(config('translations-import.table'))
                 ->where(config('translations-import.key'), $key)
                 ->where(config('translations-import.group'), $group)
                 ->first();
    }

    /**
     * Create a new translation row in the database
     *
     * @param $group
     * @param $key
     * @param $locale
     * @param $translation
     */
    public function createNewTranslation($group, $key, $locale, $translation)
    {
        if (!empty($translation)) {
            DB::table(config('translations-import.table'))
              ->insert([
                  config('translations-import.group')        => $group,
                  config('translations-import.key')          => $key,
                  config('translations-import.translations') => json_encode([$locale => $translation]),
              ]);
            $this->createCounter++;
        }
    }

    /**
     * Check if a translation exists
     *
     * @param $group
     * @param $key
     *
     * @return bool
     */
    public function translationExists($group, $key)
    {
        return DB::table(config('translations-import.table'))
                 ->where(config('translations-import.key'), $key)
                 ->where(config('translations-import.group'), $group)->first() instanceof \stdClass;
    }

    /**
     * Update an existing translation row.
     *
     * @param $group
     * @param $key
     * @param $locale
     * @param $translation
     */
    public function updateExistingTranslationRow($group, $key, $locale, $translation)
    {
        $translationColumn = config('translations-import.translations');

        $existingTranslation = $this->getExistingTranslation($group, $key);

        $translations = json_decode($existingTranslation->$translationColumn, true);


        // check if a translation should be overwritten.
        // else if the locale does not exist in the current translation, then we will update it and add it to the translation.
        if ($this->shouldOverWriteExistingTranslations) {

            // update the translation for the locale in the array.
            $translations[$locale] = $translation;

            $this->updateTranslation($group, $key, $translations);

            $this->overwriteCounter++;
        } else if (!array_key_exists($locale, $translations)) {

            // update the translation for the locale in the array.
            $translations[$locale] = $translation;

            $this->updateTranslation($group, $key, $translations);

            $this->updateCounter++;
        }

    }

    /**
     * Update a existing translation
     *
     * @param $group
     * @param $key
     * @param $translations
     */
    public function updateTranslation($group, $key, $translations)
    {
        DB::table(config('translations-import.table'))
          ->where(config('translations-import.key'), $key)
          ->where(config('translations-import.group'), $group)
          ->update([
              config('translations-import.translations') => json_encode($translations),
          ]);
    }

    public function localeCanBeImported($locale)
    {
        return ! in_array($locale, explode(',', $this->option('ignore-locales')));
    }

    public function groupCanBeImported($group)
    {

        $groupsToImport = explode(',', $this->option('only-groups'));
        $groupsToIgnore = explode(',', $this->option('ignore-groups'));

        if (!is_null($this->option('only-groups')) && !in_array($group, $groupsToImport)) {
            return false;
        }

        if (!is_null($this->option('ignore-groups')) && in_array($group, $groupsToIgnore)) {
            return false;
        }

        return true;
    }

    public function handle()
    {
        $languageGroupsByLocale = LangDirectory::getLanguageGroupsWithLocale();

        if ($this->option('overwrite-existing-translations')) {
            if ($this->confirm('Are you really sure you want to overwrite all translations in the database ? This action cannot be undone.')) {
                $this->shouldOverWriteExistingTranslations = true;
            }
        }


        foreach ($languageGroupsByLocale as $group => $locales) {

            // only proceed if the group should be imported.
            if ($this->groupCanBeImported($group)) {
                foreach ($locales as $locale) {

                    if ($this->localeCanBeImported($locale)) {
                        $this->line("Importing group: {$group} for locale: {$locale}");
                        // we only have the translation group, so we will get all the translations for the current locale.
                        $translationsForGivenGroupAndLocale = Lang::get($group, [], $locale);

                        // dot the array so we can get the keys the easy way
                        $dottedTranslationsForGivenGroupAndLocale = Arr::dot($translationsForGivenGroupAndLocale);

                        foreach ($dottedTranslationsForGivenGroupAndLocale as $key => $translation) {
                            // here we will check if the given group + key exists
                            if ($this->translationExists($group, $key)) {
                                // check we can overwrite the existing translations.
                                $this->updateExistingTranslationRow($group, $key, $locale, $translation);
                            } else {
                                $this->createNewTranslation($group, $key, $locale, $translation);
                            }
                        }
                    } else {
                        $this->info("Skipping locale: {$locale}");
                    }
                }
            } else {
                $this->info("Skipping group: {$group}");
            }
        }

        if ($this->shouldOverWriteExistingTranslations) {
            $this->info("A total of {$this->overwriteCounter} translations have been overwritten");
        } else {
            $this->info("A total of {$this->createCounter} translations have been created and {$this->updateCounter} translations have been updated");
        }

    }
}
