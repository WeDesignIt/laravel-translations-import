<?php

namespace WeDesignIt\LaravelTranslationsImport\Console\Commands;

use Illuminate\Console\Command;
use WeDesignIt\LaravelTranslationsImport\Manager;

class TranslationsExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:export

        { --ignore-groups=              : Groups that should not be imported (split,by,commas), ex: --ignore-groups=routes,admin/non-editable-stuff }
        { --only-groups=                : Only export given groups (split,by,commas), ex: admin/employer,frontend/general/setting }
        { --a|allow-vendor              : Whether to export to vendor lang files or not }
        { --j|allow-json                : Whether to export to JSON lang files or not }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translations back to the lang files.';

    /** @var \WeDesignIt\LaravelTranslationsImport\Manager */
    protected $manager;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $error = <<<EOT
        Are you really sure you want to export translations from the database to the lang files?
         Existing translations will be overwritten, and translations that have not been imported will be lost.
        EOT;

        if ($this->confirm($error))
        {
            // Set options from the command context
            $options = [
                'allow-vendor' => $this->option('allow-vendor'),
                'allow-json' => $this->option('allow-json'),
                'ignore-groups' => $this->option('ignore-groups'),
                'only-groups' => $this->option('only-groups'),
            ];
            $this->manager->exportTranslations($options);

            $this->info('All translations have been exported.');
        }
        else {
            $this->warn('Exporting cancelled.');
        }
    }
}
