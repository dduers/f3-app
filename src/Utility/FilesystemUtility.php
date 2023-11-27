<?php

declare(strict_types=1);

namespace Dduers\F3App\Utility;

use Prefab;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * frontend controls utility
 */
class FilesystemUtility extends Prefab
{
    function __construct()
    {
    }

    /**
     * crawl a folder and subfolders for filename pattern
     * @author https://stackoverflow.com/questions/17160696/php-glob-scan-in-subfolders-for-a-file
     * @param string $folder_
     * @param string $regex_pattern_
     * @return array
     */
    public static function recursiveDirectorySearch(string $directory_, string $regex_pattern_): array
    {
        $_directory = new RecursiveDirectoryIterator($directory_);
        $_iterator = new RecursiveIteratorIterator($_directory);
        $_files = new RegexIterator($_iterator, $regex_pattern_, RegexIterator::GET_MATCH);
        $_result = [];
        foreach ($_files as $file_)
            $_result = array_merge($_result, $file_);
        return array_values(array_filter($_result, fn ($item_) => (is_file($item_))));
    }
}
