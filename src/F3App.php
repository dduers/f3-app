<?php

declare(strict_types=1);

namespace Dduers\F3App;

use Base;
use Cache;
use Prefab;
use Template;

use Dduers\F3App\F3AppConfig;
use Dduers\F3App\Service\DatabaseService;
use Dduers\F3App\Service\MailService;
use Dduers\F3App\Service\SessionService;
use Dduers\F3App\Service\LogService;
use Dduers\F3App\Service\SanitizerService;

/**
 * application base controller
 * - route controllers should extend this
 */
class F3App extends Prefab
{
    static private F3AppConfig $_config;
    static private Base $_f3;
    static private $_cache;
    static private array $_request_headers = [];
    static private array $_service = [];

    /**
     * class constructor
     * - load application configuration
     * - initialization of class properties
     */
    function __construct()
    {
        self::$_config = F3AppConfig::instance();
        self::$_f3 = self::getFw();
        self::$_cache = self::getCache();
        self::registerService('database', DatabaseService::class);
        self::registerService('mail', MailService::class);
        self::registerService('log', LogService::class);
        
    }

    /**
     * routing pre processor
     * - csrf check for post and put requests
     * - set / init very important variables
     * - read request headers for later use
     * - parse input streams to arrays
     * @param Base $f3_ f3 framework instance
     * @return void
     */
    static public function beforeroute(Base $f3_): void
    {
        self::registerService('session', SessionService::class);

        /*
        if (!count(glob($f3_->get('LOCALES') . '*.ini')))
            throw new \Exception('DICTIONARY check failed');
        */

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

        foreach (getallheaders() as $header_ => $value_)
            self::$_request_headers[$header_] = $value_;

        self::registerService('sanitizer', SanitizerService::class);

        if ((int)self::$_f3->get('CONF.security.csrf.enable') === 1) {
            if (in_array($f3_->get('VERB'), self::$_f3->get('CONF.security.csrf.methods'))) {
                $_token = $f3_->get('POST._token') ?? $f3_->get('PUT._token') ?? $f3_->get('GET._token');
                if (!$_token || !$f3_->get('SESSION.csrf') || $_token !== $f3_->get('SESSION.csrf')) {
                    $f3_->error(401);
                    return;
                }
            }
        }

        return;
    }


    /**
     * routing post processor
     * - set response headers from RESPONSE.header
     * - output RESPONSE.data, recarding header RESPONSE.header.mime 
     * @param Base $f3_ f3 framework instance
     * @return void
     */
    static public function afterroute(Base $f3_): void
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
        if ($_content_type)
            header('Content-Type: ' . strtolower($_content_type));

        if ($f3_->get('RESPONSE.filename'))
            header('Content-Disposition: attachment; filename="' . $f3_->get('RESPONSE.filename') . '"');

        switch (strtolower($_content_type ?? '')) {
            default:
            case 'application/json':
                echo json_encode($f3_->get('RESPONSE.data') ?? [], ($f3_->get('DEBUG') ? JSON_PRETTY_PRINT : 0));
                break;
            case 'text/html':
                echo Template::instance()->render('template.html');
                break;
        }

        $f3_->copy('CSRF', 'SESSION.csrf');
        return;
    }

    /**
     * custom f3 framework error handler
     * @param Base $f3_ f3 framework instance
     * @return bool true = error handled, false = fallback to default f3 error handler
     */
    static public function onerror(Base $f3_): bool
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
    static public function reroute(string $vers_, string $ctrl_, string $id_ = ''): void
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
    static public function vars(string $name_, $value_ = NULL)
    {
        if (isset($value_))
            return (self::$_f3->set($name_, $value_));
        else return (self::$_f3->get($name_));
    }

    static public function vars_cache(string $name_, $value_ = NULL)
    {
        if (isset($value_))
            return (self::$_cache->set($name_, $value_));
        else return (self::$_cache->get($name_));
    }

    /**
     * get bearer token from authorization header
     * @return string
     */
    static public function getBearerToken(): string
    {
        $_auth_header_prefix = 'Bearer ';
        $_auth_header = self::$_request_headers['Authorization'] ?? '';
        if (strpos($_auth_header, $_auth_header_prefix) === 0)
            return substr($_auth_header, strlen($_auth_header_prefix));
        return '';
    }

    /**
     * register a service
     * @param string $name_
     * @param object $instance_
     * @return mixed service instance
     */
    static private function registerService(string $name_, $class_)
    {
        return self::$_service[$name_] = $class_::instance(self::vars('CONF.' . $name_));
    }

    /**
     * get a service instance by name
     * @param string $name_
     * @return mixed service instance
     */
    static public function getService(string $name_)
    {
        return isset(self::$_service[$name_]) ? self::$_service[$name_]::getService() : NULL;
    }

    /**
     * get framework instance
     * @return Base
     */
    static public function getFw(): Base
    {
        return Base::instance();
    }

    static public function getCache()
    {
        if (!self::$_cache) {
            self::$_cache = Cache::instance();
            self::$_cache->load(TRUE);
        }
        return self::$_cache;
    }

    /**
     * run application
     * @return void
     */
    static public function run(): void
    {
        self::$_f3->run();
    }
}
