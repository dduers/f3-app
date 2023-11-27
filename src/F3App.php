<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Base;
use Prefab;

/**
 * application base class
 * individual page controllers should extend this class
 */
class F3App extends Prefab
{
    /**
     * local constants
     */
    private const DEFAULT_OPTIONS = [
        'config_path' => '../config/',
        'config_allow' => false,
        'config_defaultdictionaries' => true,
        'config_service_class' => __NAMESPACE__ . '\Service\ConfigService',
    ];

    /**
     * f3 instance
     * @var Base
     */
    private static Base $_f3;

    /**
     * service registry
     * @var array
     */
    private static array $_service;

    /**
     * app configuration options
     * @var array
     */
    private static array $_options;

    /**
     * class constructor:
     * load application configuration and init services
     * @param string $config_path_
     */
    function __construct(array $options_ = [])
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_f3 = Base::instance();
        self::register('config', self::$_options['config_service_class'], [
            'path' => self::$_options['config_path'],
            'allow' => self::$_options['config_allow'],
            'defaultdictionaries' => self::$_options['config_defaultdictionaries'],
        ]);
        self::serviceBootloader(0);
    }

    /**
     * boot load service classes
     * @param int $stage_id_ 0: on app construction / 1: before route
     */
    private static function serviceBootloader(int $stage_ = 0): void
    {
        foreach (self::$_f3->get('CONF._services') as $name_ => $class_) {
            if (is_array($class_)) {
                $_class = $class_[0];
                $_stage = (int)($class_[1] ?? 0);
            } else {
                $_class = $class_;
                $_stage = 0;
            }
            if ($_stage === $stage_ && (int)self::$_f3->get('CONF.' . $name_ . '.enable') === 1)
                self::register($name_, $_class, self::$_f3->get('CONF.' . $name_));
        }
    }

    /**
     * routing pre processor:
     * init important variables and csrf check
     * @param Base $f3_
     * @return void
     */
    public static function beforeroute(Base $f3_): void
    {
        self::serviceBootloader(1);
        $_language = self::service('language');
        if ($_language)
            $_language::frameworkLanguagePreparation();
        $_session = self::service('session');
        if ($_session && !$_session::checkToken())
            $f3_->error(401);
        return;
    }

    /**
     * routing post processor:
     * output response headers and data, store new csrf token to session
     * @param Base $f3_
     * @return void
     */
    public static function afterroute(Base $f3_): void
    {
        if ($_response = self::service('response')) {
            $_response::dumpHeaders();
            $_response::dumpBody();
        }
        if ($_session = self::service('session')) {
            $_session::storeToken();
            $_session::clearFlashMessages();
        }
        return;
    }

    /**
     * issue http error
     * @param int $code_
     * @param string $text_ (optional)
     * @return void
     */
    public static function error(int $code_, string $text_ = ''): void
    {
        self::$_f3->error($code_, $text_);
        return;
    }

    /**
     * get or set framework variables
     * @param string $name_ name of f3 hive variable
     * @param mixed $value_ (optional) if set, the var is updated with the value
     * @return mixed current value or new value of f3 hive variable
     */
    public static function vars(string $name_, $value_ = NULL)
    {
        if ($value_ !== NULL)
            return (self::$_f3->set($name_, $value_));
        else return (self::$_f3->get($name_));
    }

    /**
     * set response headers
     * @param string $header_
     * @param string $content_
     * @return void
     */
    public static function header(string $header_, string $content_, bool $replace_ = false): void
    {
        if ($_response = self::service('response')) {
            if ($replace_ === true)
                $_response::removeHeader($header_);
            $_response::setHeader($header_, $content_);
        }
        return;
    }

    /**
     * set response body
     * @param mixed $data_
     * @return void
     */
    public static function body($data_): void
    {
        if ($_response = self::service('response'))
            $_response::setBody($data_);
        return;
    }

    /**
     * register a service
     * @param string $name_
     * @param string $class_
     * @param array $options_
     * @return mixed initialized service instance
     */
    public static function register(string $name_, string $class_, array $options_ = [])
    {
        if (class_exists($class_))
            return self::$_service[$name_] = $class_::instance($options_);
        else return NULL;
    }

    /**
     * get a service instance by name
     * @param string $name_
     * @return mixed service instance
     */
    public static function service(string $name_)
    {
        return (self::$_service[$name_] ?? NULL) ? self::$_service[$name_]::instance() : NULL;
    }

    /**
     * add a route
     * @param string $pattern_
     * @param callable $handler_
     */
    public static function addRoute(string $pattern_, callable $handler_): void
    {
        self::$_f3->route($pattern_, $handler_);
        return;
    }

    /**
     * run application
     * @return void
     */
    public static function run(): void
    {
        self::$_f3->run();
        return;
    }
}
