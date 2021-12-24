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
    static private Base $_f3;
    static private array $_service = [];

    /**
     * class constructor
     * - load application configuration
     * - initialization of class properties
     * @param string $config_path_
     */
    function __construct(string $config_path_ = '../config/')
    {
        self::$_f3 = Base::instance();
        self::register('config', 'Dduers\F3App\Service\ConfigService', ['path' => $config_path_]);
        foreach (self::$_f3->get('CONF.services') as $name_ => $class_)
            self::register($name_, $class_, self::$_f3->get('CONF.' . $name_));
    }

    /**
     * routing pre processor
     * - init important variables
     * - csrf check
     * @param Base $f3_
     * @return void
     */
    static function beforeroute(Base $f3_): void
    {
        $_session = self::service('session');
        $f3_->set('RESPONSE.header', []);
        $f3_->set('RESPONSE.data', []);
        //$f3_->set('PARAMS.vers', $f3_->get('PARAMS.vers') ?: 'v1');
        //$f3_->set('PARAMS.ctrl', $f3_->get('PARAMS.ctrl') ?: 'home');
        //$f3_->set('PARAMS.0', '/' . $f3_->get('PARAMS.ctrl'));
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
     * routing post processor
     * - set response headers
     * - output response data
     * @param Base $f3_
     * @return void
     */
    static function afterroute(Base $f3_): void
    {
        $_response = self::service('response');
        $_session = self::service('session');
        $_response::setHeaders($f3_->get('RESPONSE.header'));
        $_controller = $f3_->get('CONF.namespaces.controller') . '\\' . $f3_->get('PARAMS.ctrl');
        if (class_exists($_controller)) {
            $_t = [];
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'] as $method_)
                if (method_exists($_controller, strtolower($method_)))
                    $_t[] = $method_;
            if (count($_t)) {
                if ($f3_->get('VERB') === 'OPTIONS')
                    $_response::setHeader('Access-Control-Allow-Methods', implode(',', $_t));
                $_response::setHeader('Allow', implode(',', $_t));
            }
        }
        if ($f3_->get('RESPONSE.filename'))
            $_response::setHeader('Content-Disposition', 'attachment; filename="' . $f3_->get('RESPONSE.filename') . '"');
        $_response::setBody($f3_->get('RESPONSE.data'));
        $_response::dumpHeaders();
        $_response::dumpBody();
        $_session::storeToken();
        return;
    }

    /**
     * custom f3 framework error handler
     * @param Base $f3_
     * @return bool true = error handled, false = fallback to default f3 error handler
     */
    static function onerror(Base $f3_): bool
    {
        $f3_->set('RESPONSE.data', [
            'result' => 'error',
            'code' => $f3_->get('ERROR.code'),
            'message' => (int)$f3_->get('ERROR.code') === 500
                ? ($f3_->get('DEBUG')
                    ? $f3_->get('ERROR.text')
                    : 'Internal Server Error (500)'
                )
                : $f3_->get('ERROR.text'),
        ]);
        return true;
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
     * register a service
     * @param string $name_
     * @param string $class_
     * @param array $options_
     * @return mixed initialized service instance
     */
    static function register(string $name_, string $class_, array $options_ = [])
    {
        return self::$_service[$name_] = $class_::instance($options_);
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
