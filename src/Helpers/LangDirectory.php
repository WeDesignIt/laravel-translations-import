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

            // get the total path from the file
            $pathInfo = explode('/', $fileInfo->getRealPath());
            $locale   = $pathInfo[4];

            // everything after the fifth key is either the translatable file or folder and translatable file
            $translationGroup = array_splice($pathInfo, 5);

            // implode it and remove the file extension so we get the full filename
            $translatableFileName = str_replace('.php', '', implode($translationGroup, '/'));

            // set it in the array
            $translatableFileNames[$translatableFileName][] = $locale;

        }

        return $translatableFileNames;
    }
}