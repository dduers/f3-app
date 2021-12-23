<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Dduers\F3App\Iface\ServiceInterface;
use Prefab;

final class ResponseService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'header' => []
    ];
    static private $_service;
    static private $_f3;
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
    }

    static function setHeader(string $header_, string $content_)
    {
        self::$_options['header'][$header_] = $content_;
        return;
    }

    static function getHeader($header_): string
    {
        return self::$_options['header'][$header_] ?? '';
    }

    static function dumpHeaders(): void
    {
        foreach (self::$_options['header'] as $header_ => $content_)
            header($header_, $content_);
    }

    /**
     * get service instance
     * @return 
     */
    static function getService()
    {
        return self::$_service;
    }

    /**
     * get service options
     * @return array
     */
    static function getOptions(): array
    {
        return self::$_options;
    }
}
