<?php

declare(strict_types=1);

namespace Dduers\F3App\Utility;

use Base;

/**
 * dictionary utility
 * @author Wildtier Schweiz
 */
class DictionaryUtility
{
    private array $_dictionary_data = [];

    /**
     * initialization
     * @param array $dictdata_
     */
    function __construct(array $dictionary_data_ = [])
    {
        $this->_dictionary_data = $dictionary_data_;
    }

}
