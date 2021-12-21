<?php

namespace Dduers\F3App\Iface;

interface ServiceInterface
{
    static function __construct(array $options_);
    static function getService();
    static function getOptions();
}
