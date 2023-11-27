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
        'path' => '../config/',
        'allow' => false,
        'defaultdictionaries' => false,
    ];
    private static Base $_f3;
    private static array $_options = [];

    private static string $_default_configs = __DIR__ . '/../../config/';
    private static string $_default_dictionaries = __DIR__ . '/../../dict/';

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

        $_config_default = glob(self::$_default_configs . '*.ini');
        if ($_config_default !== false)
            foreach ($_config_default as $file_)
                self::$_f3->config($file_, (bool)self::$_options['allow']);

        $_config_user = glob(self::$_options['path'] . '*.ini');
        if ($_config_user !== false)
            foreach ($_config_user as $file_)
                self::$_f3->config($file_, (bool)self::$_options['allow']);

        if (self::$_options['defaultdictionaries'] === true) {
            $_dict_default = glob(self::$_default_dictionaries . '*.ini');
            if ($_dict_default !== false)
                foreach ($_dict_default as $file_) {
                    $_prefix = self::$_f3->get('PREFIX') . implode('.', ['f3app', '.', explode('.', array_pop(explode(DIRECTORY_SEPARATOR, $file_)))[0], '.']);
                    $_ini_file_content = parse_ini_file($file_, true);
                    self::$_f3->mset($_ini_file_content, $_prefix);
                }
        }
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
