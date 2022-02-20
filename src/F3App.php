<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Base;
use Prefab;

/**
 * application base class
 */
class F3App extends Prefab
{
    private const DEFAULT_CONFIG_PATH = '../config/';
    static private Base $_f3;
    static private array $_service = [];

    /**
     * class constructor:
     * load application configuration and init services
     * @param string $config_path_
     */
    function __construct(string $config_path_ = self::DEFAULT_CONFIG_PATH)
    {
        self::$_f3 = Base::instance();
        self::register('config', 'Dduers\F3App\Service\ConfigService', ['path' => $config_path_]);
        foreach (self::$_f3->get('CONF.services') as $name_ => $class_)
            self::register($name_, $class_, self::$_f3->get('CONF.' . $name_));
    }

    /**
     * routing pre processor:
     * init important variables and csrf check
     * @param Base $f3_
     * @return void
     */
    static function beforeroute(Base $f3_): void
    {
        $_session = self::service('session');
        if (!$f3_->get('PARAMS.lang') || !file_exists($f3_->get('LOCALES') . $f3_->get('PARAMS.lang') . '.ini')) {
            $f3_->set('PARAMS.lang', $f3_->get('FALLBACK'));
            foreach (explode(',', strtolower($f3_->get('LANGUAGE'))) as $lang_) {
                if (file_exists($f3_->get('LOCALES') . $lang_ . '.ini')) {
                    $f3_->set('PARAMS.lang', $lang_);
                    break;
                }
            }
        }
        $f3_->set('LANGUAGE', $f3_->get('PARAMS.lang'));
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
    static function afterroute(Base $f3_): void
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
     * @return void
     */
    static function error(int $code_): void
    {
        self::$_f3->error($code_);
        return;
    }

    /**
     * get or set framework variables
     * @param string $name_ name of f3 hive variable
     * @param mixed $value_ (optional) if set, the var is updated with the value
     * @return mixed current value or new value of f3 hive variable
     */
    static function vars(string $name_, $value_ = NULL)
    {
        if (isset($value_))
            return (self::$_f3->set($name_, $value_));
        else return (self::$_f3->get($name_));
    }

    /**
     * set response headers
     * @param string $header_
     * @param string $content_
     * @return void
     */
    static function header(string $header_, string $content_): void
    {
        if ($_response = self::service('response'))
            $_response::setHeader($header_, $content_);
        return;
    }

    /**
     * set response body
     * @param mixed $data_
     * @return void
     */
    static function body($data_): void
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
    static function register(string $name_, string $class_, array $options_ = [])
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
    static function service(string $name_)
    {
        return isset(self::$_service[$name_]) ? self::$_service[$name_]::instance() : NULL;
    }

    /**
     * run application
     * @return void
     */
    static function run(): void
    {
        self::$_f3->run();
        return;
    }
}
