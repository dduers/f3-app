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
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);

        if ((self::$_options['header']['Access-Control-Allow-Credentials'][0] ?? false))
            self::$_options['header']['Access-Control-Allow-Credentials'][0] = true;
    }

    static function setHeader(string $header_, $content_)
    {
        if (is_string($content_))
            self::$_options['header'][$header_][] = $content_;
        elseif (is_array($content_))
            foreach ($content_ as $key_ => $value_)
                self::$_options['header'][$header_][] = $value_;
        return;
    }

    static function setHeaders(array $headers_): void
    {
        foreach ($headers_ as $header_ => $items_)
            foreach ($items_ as $key_ => $content_)
                self::setHeader($header_, $content_);
    }

    static function getHeader(string $header_): array
    {
        return self::$_options['header'][$header_] ?? [];
    }

    static function dumpHeaders(): void
    {
        foreach (self::$_options['header'] as $header_ => $items_)
            if (is_array($items_))
                foreach ($items_ as $key_ => $content_)
                    header($header_ . ': ' . $content_, false);
            else header($header_ . ': ' . $items_, false);
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
