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
            self::$_options['header']['Access-Control-Allow-Credentials'][0] = 'true';
    }

    /**
     * set response header
     * @param string $header_
     * @param string $content_
     * @return void
     */
    static function setHeader(string $header_, string $content_): void
    {
        self::$_options['header'][$header_][] = $content_;
        return;
    }

    /**
     * set headers in batch
     * @param array $headers_
     */
    static function setHeaders(array $headers_): void
    {
        foreach ($headers_ as $header_ => $items_)
            foreach ($items_ as $key_ => $content_)
                self::setHeader($header_, $content_);
    }

    /**
     * get header
     * @param string $header_
     * @return string
     */
    static function getHeader(string $header_): string
    {
        return implode(',', self::$_options['header'][$header_]);
    }

    /**
     * output response headers
     * @return void
     */
    static function dumpHeaders(): void
    {
        foreach (self::$_options['header'] as $header_ => $items_) {
            switch ($header_) {
                case 'Access-Control-Allow-Origin':
                    if (in_array($_SERVER['HTTP_ORIGIN'], $items_))
                        header($header_ . ': ' . $_SERVER['HTTP_ORIGIN'], false);
                    break;
                case 'Set-Cookie':
                    foreach ($items_ as $key_ => $value_)
                        header($header_ . ': ' . $value_, false);
                    break;
                default:
                    header($header_ . ': ' . implode(',', $items_), false);
                    break;
            }
        }
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
