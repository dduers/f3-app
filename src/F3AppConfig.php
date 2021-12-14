<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Base;
use Prefab;

/**
 * app config loader and defaults
 */
class F3AppConfig extends Prefab
{
    public const F3APP_DEFAULT_CONFIG_DIR = '../config/';
    static private Base $_f3;

    /**
     * constructor
     * - load config defaults
     * - overwrite config defaults with actual config
     */
    function __construct()
    {
        self::$_f3 = Base::instance();
        self::$_f3->config(__DIR__ . '/Config/default.ini');
        foreach (glob((defined('F3APP_CONFIG_DIR') ? F3APP_CONFIG_DIR : self::F3APP_DEFAULT_CONFIG_DIR) . '*.ini') as $_inifile)
            self::$_f3->config($_inifile);
    }
}
