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
    private const DEFAULT_OPTIONS = [];
    private static array $_options = [];
    private static $_service;

    /**
     * constructor
     * - load config defaults
     * - overwrite config defaults with actual config
     */
    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_service = Cache::instance();
        self::$_service->load(TRUE);
    }

    /**
     * get service options
     * @return array
     */
    public static function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * get service instance
     * @return Cache|null
     */
    public static function getService(): Cache|NULL
    {
        return self::$_service;
    }
}
