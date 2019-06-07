<?php

namespace Frogeyedman\LaravelTranslationsImport\Helpers;

class LangDirectory {


    public static function translatableFiles()
    {
        $directory = resource_path('lang');

        $recursiveIteratorIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $translatableFileNames = [];

        /** @var \RecursiveIteratorIterator $fileInfo */
        foreach ($recursiveIteratorIterator as $fileInfo) {

            $pathInfo = explode('/', $fileInfo->getRealPath());
            $locale = $pathInfo[4];

            // everything after the fifth key is either the translatable file or folder and translatable file
            $translatableFileName = implode(array_splice($pathInfo, 5), '/');
            // set it in the array

            $translatableFileNames[$locale][] = $translatableFileName;

        }

        dd($translatableFileNames);
    }

    public static function directoryTree()
    {
        $directory = resource_path('lang');

        $recursiveIteratorIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $tree = [];

        /** @var \RecursiveIteratorIterator $fileInfo */
        foreach ($recursiveIteratorIterator as $fileInfo) {
            $fileName = $fileInfo->getFilename();

            $path = $fileInfo->isDir() ? [$fileName => []] : [$fileName];

            for ($dirDepth = $recursiveIteratorIterator->getDepth() - 1; $dirDepth >= 0; $dirDepth--) {
                $parentFolder = $recursiveIteratorIterator->getSubIterator($dirDepth)->current()->getFilename();
                $path = [
                    $parentFolder => $path
                ];
            }

            $tree = array_merge_recursive($tree, $path);
        }

        return $tree;
    }
}