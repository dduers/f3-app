<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Dduers\F3App\Iface\ServiceInterface;
use Prefab;
use Cache;

/**
 * app config loader and defaults
 */
final class CacheService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'enable' => 0
    ];
    static private $_service;
    static private array $_options = [];

    /**
     * constructor
     * - load config defaults
     * - overwrite config defaults with actual config
     */
    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        if ((int)self::$_options['enable'] === 1) {
            self::$_service = Cache::instance();
            self::$_service->load(TRUE);
        }
    }

    /**
     * get service options
     * @return array
     */
    static function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * get service instance
     * @return Cache|null
     */
    static function getService()
    {
        return self::$_service;
    }
}
