<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Dduers\F3App\Iface\ServiceInterface;
use Prefab;
use Base;

/**
 * app config loader and defaults
 */
final class ConfigService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'path' => '../config/'
    ];
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
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_f3->config(__DIR__ . '/../Config/default.ini');
        $_config_path = (self::$_options['path'] ?? '') ?: self::DEFAULT_OPTIONS['path'];
        $_config_files = glob($_config_path . '*.ini');
        if ($_config_files !== false)
            foreach ($_config_files as $_inifile)
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
}
