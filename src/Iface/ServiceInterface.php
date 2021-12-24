<?php

namespace Dduers\F3App\Iface;

interface ServiceInterface
{
    function __construct(array $options_);
    static function getOptions();
}
