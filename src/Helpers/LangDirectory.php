<?php

namespace Frogeyedman\LaravelTranslationsImport\Helpers;

class LangDirectory {

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