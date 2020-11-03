<?php

namespace WeDesignIt\LaravelTranslationsImport\Console\Commands;

use Illuminate\Console\Command;
use WeDesignIt\LaravelTranslationsImport\Manager;

class TranslationsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:import

        { --ignore-locales=             : Locales that should be ignored during the importing process (split,by,commas), ex: --ignore-locales=fr,de }
        { --ignore-groups=              : Groups that should not be imported (split,by,commas), ex: --ignore-groups=routes,admin/non-editable-stuff }
        { --only-groups=                : Only import given groups (split,by,commas), ex: admin/employer,frontend/* }
        { --o|overwrite                 : Whether the existing translations should be overwritten or not }
        { --a|allow-vendor              : Whether to import vendor lang files or not }
        { --j|allow-json                : Whether to import JSON lang files or not }';

//  Potentially: Only supported locales? Config max locale length?

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import translations from the lang files.';

    /** @var \WeDesignIt\LaravelTranslationsImport\Manager */
    protected $manager;

    protected $overwrite = false;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        parent::__construct();
    }

    public function handle()
    {
        if ($this->option('overwrite')) {
            if ($this->confirm('Are you really sure you want to overwrite all translations in the database ? This action cannot be undone.')) {
                $this->overwrite = true;
            }
        }

        // Set options from the command context
        $options = [
            'overwrite' => $this->overwrite,
            'allow-vendor' => $this->option('allow-vendor'),
            'allow-json' => $this->option('allow-json'),
            'ignore-locales' => $this->option('ignore-locales'),
            'ignore-groups' => $this->option('ignore-groups'),
            'only-groups' => $this->option('only-groups'),
        ];

        // Let the manager do his job
        $counter = $this->manager->importTranslations($options);
        $this->info("A total of {$counter} translations have been updated/created.");
    }
}
