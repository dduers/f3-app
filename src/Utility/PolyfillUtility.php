<?php

declare(strict_types=1);

namespace Dduers\F3App\Utility;

use Prefab;

final class PolyfillUtility extends Prefab
{
    function __construct()
    {
    }

    /**
     * polyfill for php 8.1 array_is_list
     * author: divinity76+spam at gmail dot com
     * @param array $array_
     * @return bool
     */
    public static function array_is_list(array $array_): bool
    {
        $i = -1;
        foreach ($array_ as $k => $v) {
            ++$i;
            if ($k !== $i)
                return false;
        }
        return true;
    }
}
