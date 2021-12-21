<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Prefab;
use Log;
use Dduers\F3App\Iface\ServiceInterface;

final class LogService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'file' => 'log.txt'
    ];
    static private $_service;
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_service = new Log(self::$_options['file']);
    }

    /**
     * get service instance
     * @return Log|null
     */
    static function getService()
    {
        return self::$_service;
    }

    /**
     * get service options
     * @return array
     */
    static public function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * write log entries
     * @param mixed $content_ the text to log
     * @param string $format_ (optional) e.g. 'r' for rfc 2822 log format
     * @return void
     */
    static function write($content_, string $format_ = 'r'): void
    {
        if (is_string($content_))
            self::$_service->write($content_, $format_);
        else self::$_service->write(print_r($content_, true), $format_);
    }

    /**
     * erase logfile
     * @return void
     */
    static function erase(): void
    {
        self::$_service->erase();
    }
}
