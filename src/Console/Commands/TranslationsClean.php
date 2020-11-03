<?php

namespace WeDesignIt\LaravelTranslationsImport\Console\Commands;

use Illuminate\Console\Command;
use WeDesignIt\LaravelTranslationsImport\Manager;

class TranslationsClean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean empty translations';

    /** @var \WeDesignIt\LaravelTranslationsImport\Manager */
    protected $manager;

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
        $counter = $this->manager->cleanTranslations();
        $this->info("A total of {$counter} translations have been removed.");
    }
}
