<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Base;
use Prefab;
use Template;

use Dduers\F3App\Service\ConfigService;
use Dduers\F3App\Service\DatabaseService;
use Dduers\F3App\Service\InputService;
use Dduers\F3App\Service\MailService;
use Dduers\F3App\Service\SessionService;
use Dduers\F3App\Service\LogService;
use Dduers\F3App\Service\CacheService;

/**
 * application base controller
 * - route controllers should extend this
 */
class F3App extends Prefab
{
    static private Base $_f3;
    //static private $_cache;
    static private array $_service = [];

    /**
     * class constructor
     * - load application configuration
     * - initialization of class properties
     */
    function __construct(string $config_path_ = '../config/')
    {
        self::register('config', ConfigService::class, ['path' => $config_path_]);
        self::$_f3 = Base::instance();
        self::register('cache', CacheService::class, self::vars('CONF.cache'));
        self::register('database', DatabaseService::class, self::vars('CONF.database'));
        self::register('mail', MailService::class, self::vars('CONF.mail'));
        self::register('log', LogService::class, self::vars('CONF.log'));
        self::register('input', InputService::class, self::vars('CONF.input'));
        
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
        self::register('session', SessionService::class, self::vars('CONF.session'));
        
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

        if (
            (int)$f3_->get('CONF.csrf.enable') === 1
            && in_array($f3_->get('VERB'), $f3_->get('CONF.csrf.methods'))
            && !self::checkCsrfToken()
        ) {
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
        if ($_SERVER['HTTP_ORIGIN'] ?? '') {
            if (is_array($f3_->get('CONF.header.accesscontrolalloworigin')) && in_array($_SERVER['HTTP_ORIGIN'], $f3_->get('CONF.header.accesscontrolalloworigin')))
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            elseif (is_string($f3_->get('CONF.header.accesscontrolalloworigin')) && $_SERVER['HTTP_ORIGIN'] === $f3_->get('CONF.header.accesscontrolalloworigin'))
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        }

        $_t = '';
        if (is_array($f3_->get('RESPONSE.header.accesscontrolallowmethods')))
            $_t = implode(',', $f3_->get('RESPONSE.header.accesscontrolallowmethods'));
        elseif (is_string($f3_->get('RESPONSE.header.accesscontrolallowmethods')))
            $_t = $f3_->get('RESPONSE.header.accesscontrolallowmethods');
        if ($_t === '') {
            if (is_array($f3_->get('CONF.header.accesscontrolallowmethods')))
                $_t = implode(',', $f3_->get('CONF.header.accesscontrolallowmethods'));
            elseif (is_string($f3_->get('CONF.header.accesscontrolallowmethods')))
                $_t = $f3_->get('CONF.header.accesscontrolallowmethods');
        }
        if ($_t)
            header('Access-Control-Allow-Methods: ' . $_t);

        $_t = '';
        if (is_array($f3_->get('RESPONSE.header.accesscontrolallowheaders')))
            $_t = implode(',', $f3_->get('RESPONSE.header.accesscontrolallowheaders'));
        elseif (is_string($f3_->get('RESPONSE.header.accesscontrolallowheaders')))
            $_t = $f3_->get('RESPONSE.header.accesscontrolallowheaders');
        if ($_t === '') {
            if (is_array($f3_->get('CONF.header.accesscontrolallowheaders')))
                $_t = implode(',', $f3_->get('CONF.header.accesscontrolallowheaders'));
            elseif (is_string($f3_->get('CONF.header.accesscontrolallowheaders')))
                $_t = $f3_->get('CONF.header.accesscontrolallowheaders');
        }
        if ($_t)
            header('Access-Control-Allow-Headers: ' . $_t);

        $_t = false;
        if ($f3_->get('CONF.header.accesscontrolallowcredentials') === true)
            $_t = true;
        if ($f3_->get('RESPONSE.header.accesscontrolallowcredentials') === true)
            $_t = true;
        elseif ($f3_->get('RESPONSE.header.accesscontrolallowcredentials') === false)
            $_t = false;
        if ($_t === true)
            header('Access-Control-Allow-Credentials: true');

        $_content_type = '';
        if ($f3_->get('RESPONSE.header.contenttype'))
            $_content_type = $f3_->get('RESPONSE.header.contenttype');
        if ($_content_type === '') {
            if ($f3_->get('CONF.header.contenttype'))
                $_content_type = $f3_->get('CONF.header.contenttype');
        }
        $_content_type = strtolower($_content_type);
        if ($_content_type)
            header('Content-Type: ' . $_content_type);

        if ($f3_->get('RESPONSE.filename'))
            header('Content-Disposition: attachment; filename="' . $f3_->get('RESPONSE.filename') . '"');

        switch ($_content_type ?: '') {
            default:
            case 'application/json':
                echo json_encode($f3_->get('RESPONSE.data') ?? [], ($f3_->get('DEBUG') ? JSON_PRETTY_PRINT : 0));
                break;
            case 'text/html':
                echo Template::instance()->render('template.html');
                break;
        }



        if ((int)$f3_->get('CONF.csrf.enable') === 1)
            $f3_->copy('CSRF', 'SESSION.csrf');

        $_logger = self::service('log');
        $_logger->write('CSRF: ' . $f3_->get('CSRF'));
        $_logger->write('CSRF_SESSION: ' . $f3_->get('SESSION.csrf'));

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
    static function register(string $name_, $class_, array $options_ = [])
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
     * get framework instance
     * @return Base
     */
    static function getFw(): Base
    {
        return self::$_f3;
    }

    /**
     * check csrf token
     * @return bool
     */
    static private function checkCsrfToken(): bool
    {
        $_token = self::vars('POST._token') ?? self::vars('PUT._token') ?? self::vars('GET._token') ?? '';
        $_logger = self::service('log');
        $_logger->write([
            'post' => self::vars('POST._token'),
            'put' => self::vars('PUT._token'),
            'session' => self::vars('SESSION.csrf'),
            'token' => $_token
        ]);

        if (!$_token || !self::vars('SESSION.csrf') || $_token !== self::vars('SESSION.csrf'))
            return false;
        return true;
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
