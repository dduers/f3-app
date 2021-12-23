<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Base;
use Dduers\F3App\Service\ResponseService;
use Dduers\F3App\Service\SessionService;
use Prefab;
use Template;

/**
 * application base controller
 * - route controllers should extend this
 */
class F3App extends Prefab
{
    static private Base $_f3;
    static private array $_service = [];

    /**
     * class constructor
     * - load application configuration
     * - initialization of class properties
     */
    function __construct(string $config_path_ = '../config/')
    {
        self::$_f3 = Base::instance();
        self::register('config', 'Dduers\F3App\Service\ConfigService', ['path' => $config_path_]);
        foreach (self::vars('CONF.services') as $name_ => $class_)
            self::register($name_, $class_, self::vars('CONF.' . $name_));
    }

    /**
     * routing pre processor
     * - init important variables
     * - csrf check
     * @param Base $f3_ f3 framework instance
     * @return void
     */
    static function beforeroute(Base $f3_): void
    {
        $f3_->set('RESPONSE.header', []);
        $f3_->set('RESPONSE.data', []);
        $f3_->set('PARAMS.vers', $f3_->get('PARAMS.vers') ?: 'v1');
        $f3_->set('PARAMS.ctrl', $f3_->get('PARAMS.ctrl') ?: 'home');
        $f3_->set('PARAMS.0', '/' . $f3_->get('PARAMS.ctrl'));
        if (!$f3_->get('PARAMS.lang') || !file_exists($f3_->get('LOCALES') . $f3_->get('PARAMS.lang') . '.ini')) {
            $f3_->set('PARAMS.lang', $f3_->get('FALLBACK'));
            foreach (explode(',', strtolower($f3_->get('LANGUAGE'))) as $lang) {
                if (file_exists($f3_->get('LOCALES') . $lang . '.ini')) {
                    $f3_->set('PARAMS.lang', $lang);
                    break;
                }
            }
        }
        $f3_->set('LANGUAGE', $f3_->get('PARAMS.lang'));
        if (!SessionService::instance()::checkToken()) {
            $f3_->error(401);
            return;
        }
        return;
    }

    /**
     * routing post processor
     * - set response headers
     * - output response data
     * @param Base $f3_ f3 framework instance
     * @return void
     */
    static function afterroute(Base $f3_): void
    {
        $_content_type = self::responseHeaders();
        switch ($_content_type) {
            default:
            case 'application/json':
                echo json_encode($f3_->get('RESPONSE.data') ?? [], ($f3_->get('DEBUG') ? JSON_PRETTY_PRINT : 0));
                break;
            case 'text/html':
                echo Template::instance()->render('template.html');
                break;
        }
        SessionService::instance()::storeToken();
        return;
    }

    /**
     * custom f3 framework error handler
     * @param Base $f3_ f3 framework instance
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
     * output response headers
     * @return string final content type
     */
    static private function responseHeaders(): string
    {
        $_service_response = ResponseService::instance();
        $_service_response->setHeaders(self::vars('RESPONSE.header'));

        $_controller = self::vars('CONF.namespaces.controller') . '\\' . self::vars('PARAMS.ctrl');
        if (class_exists($_controller)) {
            $_t = [];
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'] as $method_)
                if (method_exists($_controller, strtolower($method_)))
                    $_t[] = $method_;
            if (count($_t)) {
                if (self::vars('VERB') === 'OPTIONS')
                    $_service_response::setHeader('Access-Control-Allow-Methods', implode(',', $_t));
                $_service_response::setHeader('Allow', implode(',', $_t));
            }
        } 
        
        /*
        else {
            $_t = implode(',', self::vars('RESPONSE.header.Access-Control-Allow-Methods') ?? []);
            if ($_t) {
                if (self::vars('VERB') === 'OPTIONS')
                    $_service_response::setHeader('Access-Control-Allow-Methods', $_t);
                $_service_response::setHeader('Allow', $_t);
            }
        }
        $_t = implode(',', self::vars('RESPONSE.header.Access-Control-Allow-Headers') ?? []);
        if ($_t)
            $_service_response::setHeader('Access-Control-Allow-Headers', $_t);

        $_t = false;
        if ((self::vars('RESPONSE.header.Access-Control-Allow-Credentials')[0] ?? false) === true)
            $_t = true;
        if ($_t === true)
            $_service_response::setHeader('Access-Control-Allow-Credentials', 'true');
        $_content_type = '';
        if (self::vars('RESPONSE.header.Content-Type'))
            $_content_type = self::vars('RESPONSE.header.Content-Type');
        $_content_type = strtolower($_content_type);
        if ($_content_type)
            $_service_response::setHeader('Content-Type', $_content_type);
        */

        if (self::vars('RESPONSE.filename'))
            $_service_response::setHeader('Content-Disposition', 'attachment; filename="' . self::vars('RESPONSE.filename') . '"');

        $_service_response::dumpHeaders();
        return $_service_response::getHeader('Content-Type');
    }

    /**
     * reroute to version/language/page
     * @param string $vers_ version e.g. 'v1'
     * @param string $ctrl_ name of controller
     * @return void
     */
    static function reroute(string $vers_, string $ctrl_, string $id_ = ''): void
    {
        self::$_f3->reroute($vers_ . '/' . $ctrl_ . ($id_ ? '/' . $id_ : '')(self::$_f3->get('QUERY') ? '?' . self::$_f3->get('QUERY') : ''));
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
     * @param object $instance_
     * @return mixed service instance
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
    }
}
