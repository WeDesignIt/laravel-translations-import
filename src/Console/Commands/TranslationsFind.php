<?php

namespace WeDesignIt\LaravelTranslationsImport\Console\Commands;

use Illuminate\Console\Command;
use WeDesignIt\LaravelTranslationsImport\Manager;

class TranslationsFind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:find

        { --path=                       : A specific directory path to find translations in }
        { --c|force-confirm             : Whether to skip confirming found translations }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find unimported translations in php/twig files';

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
        $path = $this->option('path');
        // Ensure the path is valid
        if (isset($path) && !(file_exists($path) && is_dir($path))) {
            $this->error('This is not a valid directoy path! Ensure the path exists.');
            die;
        }
        $options = [
            'path' => $path,
            'force-confirm' => $this->option('force-confirm'),
        ];
        $counter = $this->manager->findTranslations($this, $options);
        $this->info("A total of {$counter} unimported translations have been found and imported.");
    }
}
