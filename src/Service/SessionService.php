<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use Session;
use DB\SQL\Session as SQLSession;
use DB\Mongo\Session as MongoSession;
use DB\Jig\Session as JigSession;
use Dduers\F3App\Iface\ServiceInterface;

final class SessionService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'engine' => '',
        'table' => 'sessions',
        'key' => '_token',
        'cookie' => [
            'options' => [
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => ''
            ]
        ]
    ];
    static private $_service;
    static private array $_options = [];
    static private $_db;
    static private $_cache;
    static private $_f3;
    static private string $_token = '';

    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        self::$_db = DatabaseService::instance()::getService();
        self::$_cache = CacheService::instance()::getService();
        self::$_f3 = Base::instance();

        foreach (self::$_options['cookie']['options'] as $option_ => $value_) {
            if (isset($value_))
                ini_set('session.cookie_' . $option_, (string)$value_);
        }

        switch (strtolower(self::$_options['engine'] ?? '')) {

            case 'sql':
                if (!self::$_db)
                    self::$_token = self::createToken();
                else self::$_service = new SQLSession(self::$_db, self::$_options['table'], TRUE, NULL, self::$_options['key']);
                break;

            case 'mongo':
                if (!self::$_db)
                    self::$_token = self::createToken();
                else self::$_service = new MongoSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;

            case 'jig':
                if (!self::$_db)
                    self::$_token = self::createToken();
                else self::$_service = new JigSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;

            case 'cache':
                if (!self::$_cache)
                    self::$_token = self::createToken();
                self::$_service = new Session(NULL, self::$_options['key'], self::$_cache);
                break;

            default:
                self::$_token = self::createToken();
                break;
        }

        if (!self::$_token)
            self::$_token = self::$_f3->get(self::$_options['key']);
    }

    /**
     * create random token
     * @return string
     */
    static private function createToken(): string
    {
        return bin2hex(random_bytes(7));
    }

    /**
     * get current session token
     * @return string
     */
    static function getToken(): string
    {
        return self::$_token;
    }

    /**
     * get token from the session (previous request)
     * @return string
     */
    static private function getServerToken(): string
    {
        return self::$_f3->get('SESSION.' . self::$_options['key']);
    }

    /**
     * get token received from client
     * @return string
     */
    static private function getClientToken(): string
    {
        return (string)(self::$_f3->get('POST.' . self::$_options['key']) ?? self::$_f3->get('PUT.' . self::$_options['key']) ?? self::$_f3->get('GET.' . self::$_options['key']) ?? '');
    }

    /**
     * check csrf token
     * @return bool
     */
    static function checkToken(): bool
    {
        $_token_server = self::getServerToken();
        $_token_client = self::getClientToken();
        if (!$_token_client || !$_token_server || $_token_client !== $_token_server)
            return false;
        return true;
    }

    /**
     * copy token to session
     */
    static function copyTokenToSession(): void
    {
        self::$_f3->set('SESSION.' . self::$_options['key'], self::$_token);
    }

    /**
     * get service instance
     * @return Session|SQLSession|MongoSession|JigSession
     */
    static function getService()
    {
        return self::$_service;
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
