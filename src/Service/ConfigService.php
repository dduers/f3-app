<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Dduers\F3App\Iface\ServiceInterface;
use Prefab;

/**
 * app config loader and defaults
 */
class ConfigService extends Prefab implements ServiceInterface
{
    public const F3APP_DEFAULT_CONFIG_DIR = '../config/';
    static private Base $_f3;
    static private array $_options = [];

    /**
     * constructor
     * - load config defaults
     * - overwrite config defaults with actual config
     */
    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        self::$_options = $options_;

        self::$_f3->config(__DIR__ . '/Config/default.ini');

        $_config_path = (self::$_options['path'] ?? '') ?: self::F3APP_DEFAULT_CONFIG_DIR;
        foreach (glob($_config_path . '*.ini') as $_inifile)
            self::$_f3->config($_inifile);
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
     * @return ConfigService
     */
    static function getService(): ConfigService
    {
        return self::instance();
    }
}
