<?php

namespace Frogeyedman\LaravelTranslationsImport\Console\Commands;

use Frogeyedman\LaravelTranslationsImport\Helpers\LangDirectory;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class TranslationsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:import {--ignore-languages=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import translations from the resources/lang folder.';

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
        DB::table(config('translations-import.table'))
          ->insert([
              config('translations-import.group') => $group,
              config('translations-import.key') => $key,
              config('translations-import.translations') => json_encode([$locale => $translation]),
          ]);
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
     * Update existing translation row
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

        if (!array_key_exists($locale, $translations)) {
            $translations[$locale] = $translation;
        }

        DB::table(config('translations-import.table'))
          ->where(config('translations-import.key'), $key)
          ->where(config('translations-import.group'), $group)
          ->update([
              config('translations-import.translations') => json_encode($translations),
          ]);
    }

    public function localeCanBeImported($locale)
    {
        return !in_array($locale, $this->option('ignore-languages'));
    }

    public function handle()
    {
        $languageGroupsByLocale = LangDirectory::getLanguageGroupsWithLocale();

        foreach ($languageGroupsByLocale as $group => $locales) {

            foreach ($locales as $locale) {

                if ($this->localeCanBeImported($locale)) {
                    $this->line("Importing group: {$group} for locale: {$locale}");
                    // we only have the translation group, so we will get all the translations for the current locale.
                    $translationsForGivenGroupAndLocale = Lang::get($group, [], $locale);

                    // dot the array so we can get the keys the easy way
                    $dottedTranslationsForGivenGroupAndLocale = Arr::dot($translationsForGivenGroupAndLocale);

                    foreach ($dottedTranslationsForGivenGroupAndLocale as $key => $translation) {

                        // here we will check if the given group + key exists.
                        if ($this->translationExists($group, $key)) {
                            $this->updateExistingTranslationRow($group, $key, $locale, $translation);
                        } else {
                            $this->createNewTranslation($group, $key, $locale, $translation);
                        }
                    }
                } else {
                    $this->line("Skipping locale: {$locale}");
                }
            }
        }
    }
}
