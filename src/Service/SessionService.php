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
        'name' => 'PHPSESSID',
        'table' => 'sessions',
        'key' => '_token',
        'csrf' => [
            'enable' => 0,
            'methods' => ''
        ],
        'cookie' => [
            'options' => [
                'lifetime' => 0,
                'path' => '',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => ''
            ]
        ]
    ];
    static private $_service;
    static private $_f3;
    static private $_db;
    static private $_cache;
    static private array $_options = [];

    static private string $_token = '';

    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        self::$_db = DatabaseService::instance()::getService();
        self::$_cache = CacheService::instance()::getService();
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        session_set_cookie_params(self::$_options['cookie']['options']);
        session_name(self::$_options['name']);
        switch (self::$_options['engine'] ?? '') {
            case 'sql':
                if (!self::$_db)
                    self::$_token = self::generateToken();
                else self::$_service = new SQLSession(self::$_db, self::$_options['table'], TRUE, NULL, self::$_options['key']);
                break;
            case 'mongo':
                if (!self::$_db)
                    self::$_token = self::generateToken();
                else self::$_service = new MongoSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;
            case 'jig':
                if (!self::$_db)
                    self::$_token = self::generateToken();
                else self::$_service = new JigSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;
            case 'cache':
                if (!self::$_cache)
                    self::$_token = self::generateToken();
                self::$_service = new Session(NULL, self::$_options['key'], self::$_cache);
                break;
            default:
                self::$_token = self::generateToken();
                break;
        }
        if (!self::$_token)
            self::$_token = self::$_f3->get(self::$_options['key']);
    }

    /**
     * create random string token
     * @return string
     */
    static private function generateToken(): string
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
     * copy token to session
     * @return void
     */
    static function storeToken(): void
    {
        if ((int)self::$_options['csrf']['enable'] !== 1)
            return;
        self::$_f3->set('SESSION.' . self::$_options['key'], self::$_token);
    }

    /**
     * check csrf client token against server token
     * @return bool
     */
    static function checkToken(): bool
    {
        if ((int)self::$_options['csrf']['enable'] !== 1 || !in_array(self::$_f3->get('VERB'), self::$_options['csrf']['methods']))
            return true;
        $_token_server = self::$_f3->get('SESSION.' . self::$_options['key']);
        $_token_client = (string)(self::$_f3->get('POST.' . self::$_options['key']) ?? self::$_f3->get('PUT.' . self::$_options['key']) ?? self::$_f3->get('GET.' . self::$_options['key']) ?? '');
        if (!$_token_client || !$_token_server || $_token_client !== $_token_server)
            return false;
        return true;
    }

    /**
     * destroy session
     * @return void
     */
    static function destroy(): void
    {
        if (ini_get('session.use_cookies'))
            self::deleteSessionCookie();
        if (session_id())
            session_destroy();
    }

    /**
     * deletes the session cookie
     * @return void
     */
    static private function deleteSessionCookie(): void
    {
        $_params = session_get_cookie_params();
        setcookie(session_name(), '', array_filter([
            'expires' => (string)(time() - 3600 * 24 * 365),
            'domain' => (string)($_params['domain'] ?? '') ?: NULL,
            'httponly' => (string)($_params['httponly'] ?? '') ?: NULL,
            'secure' => (string)($_params['secure'] ?? '') ?: NULL,
            'path' => (string)($_params['path'] ?? '') ?: NULL,
            //'samesite' => (string)($_params['samesite'] ?? '') ?: NULL,
        ]));
        return;
    }

    /**
     * clear session flash messages
     * @return void
     */
    static function clearFlashMessages(): void
    {
        self::$_f3->clear('SESSION._message');
    }

    /**
     * get service instance
     * @return Session|SQLSession|MongoSession|JigSession|null
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
