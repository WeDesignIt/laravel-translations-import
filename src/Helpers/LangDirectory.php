<?php

namespace WeDesignIt\LaravelTranslationsImport\Helpers;

class LangDirectory
{
    /**
     * Get the language groups available in the application
     *
     * @return array
     */
    public static function getLanguageGroupsWithLocale(): array
    {
        $directory = resource_path('lang');

        $recursiveIteratorIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $translatableFileNames = [];

        /** @var \RecursiveIteratorIterator $fileInfo */
        foreach ($recursiveIteratorIterator as $fileInfo) {

            $relativeFile = str_replace($directory .'/', "", $fileInfo->getRealPath());
            $translationGroup = explode("/", $relativeFile);
            $locale = array_shift($translationGroup);

            // implode it and remove the file extension so we get the full filename
            $translatableFileName = str_replace('.php', '', implode($translationGroup, '/'));

            // set it in the array
            $translatableFileNames[$translatableFileName][] = $locale;

        }

        return $translatableFileNames;
    }
}