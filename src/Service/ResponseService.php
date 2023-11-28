<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Dduers\F3App\Iface\ServiceInterface;
use Prefab;
use Template;

final class ResponseService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'header' => [],
        'body' => '',
        'template' => 'template.html',
    ];
    private static $_service;
    private static array $_options = [];

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
    public static function setHeader(string $header_, string $content_): void
    {
        self::$_options['header'][$header_][] = $content_;
        return;
    }

    /**
     * remove all headers of a specific type
     * @param string $header_
     * @return bool
     */
    public static function removeHeader(string $header_): bool
    {
        if (!isset(self::$_options['header'][$header_]))
            return false;
        else unset(self::$_options['header'][$header_]);
        return true;
    }

    /**
     * set response body
     * @param mixed $body_
     * @return void
     */
    public static function setBody($body_): void
    {
        self::$_options['body'] = $body_;
    }

    /**
     * get body
     * @return mixed
     */
    public static function getBody()
    {
        return self::$_options['body'];
    }

    /**
     * set headers in batch
     * @param array $headers_
     * @return void
     */
    public static function setHeaders(array $headers_): void
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
    public static function getHeader(string $header_): string
    {
        return implode(',', self::$_options['header'][$header_] ?? []);
    }

    /**
     * output response headers
     * @return void
     */
    public static function dumpHeaders(): void
    {
        if (self::getHeader('Content-Type') === '')
            self::setHeader('Content-Type', 'application/json');
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
     * output response body
     * @return void
     */
    public static function dumpBody(): void
    {
        switch (self::getHeader('Content-Type')) {
            default:
            case 'application/json':
                if (is_array(self::$_options['body']))
                    echo json_encode(self::$_options['body']);
                if (is_string(self::$_options['body']))
                    echo self::$_options['body'];
                break;
            case 'application/xml':
                if (is_string(self::$_options['body']))
                    echo self::$_options['body'];
                break;
            case 'text/html':
                if (!empty(self::$_options['body']))
                    echo self::$_options['body'];
                else echo Template::instance()->render(self::$_options['template']);
                break;
        }
    }

    /**
     * get service instance
     * @return null
     */
    public static function getService(): NULL
    {
        return self::$_service;
    }

    /**
     * get service options
     * @return array
     */
    public static function getOptions(): array
    {
        return self::$_options;
    }
}
