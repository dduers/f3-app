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
    private static $_service;
    private static $_f3;
    private static $_db;
    private static $_cache;
    private static array $_options = [];
    private static string $_token = '';

    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        // TODO::better solution for this mess
        if ((int)self::$_f3->get('CONF.database.enable') === 1)
            self::$_db = DatabaseService::instance()::getService();
        // TODO::better solution for this mess
        if ((int)self::$_f3->get('CONF.cache.enable') === 1)
            self::$_cache = CacheService::instance()::getService();
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        session_set_cookie_params(self::$_options['cookie']['options']);
        session_name(self::$_options['name']);
        switch (self::$_options['engine'] ?? '') {
            case 'sql':
                !self::$_db ? self::$_token = self::generateToken() : self::$_service = new SQLSession(self::$_db, self::$_options['table'], TRUE, NULL, self::$_options['key']);
                break;
            case 'mongo':
                !self::$_db ? self::$_token = self::generateToken() : self::$_service = new MongoSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;
            case 'jig':
                !self::$_db ? self::$_token = self::generateToken() : self::$_service = new JigSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;
            case 'cache':
                !self::$_cache ? self::$_token = self::generateToken() : self::$_service = new Session(NULL, self::$_options['key'], self::$_cache);
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
    private static function generateToken(): string
    {
        return bin2hex(random_bytes(7));
    }

    /**
     * get current session token
     * @return string
     */
    public static function getToken(): string
    {
        return self::$_token;
    }

    /**
     * copy token to session
     * @return void
     */
    public static function storeToken(): void
    {
        if ((int)self::$_options['csrf']['enable'] !== 1)
            return;
        self::$_f3->set('SESSION.' . self::$_options['key'], self::$_token);
    }

    /**
     * check csrf client token against server token
     * @return bool
     */
    public static function checkToken(): bool
    {
        if ((int)self::$_options['csrf']['enable'] !== 1 || !in_array(self::$_f3->get('VERB'), (array)self::$_options['csrf']['methods']))
            return true;
        $_token_server = self::$_f3->get('SESSION.' . self::$_options['key']);
        $_token_client = (string)(self::$_f3->get(self::$_f3->get('VERB') . '.' . self::$_options['key']) ?? '');
        if (!$_token_client || !$_token_server || $_token_client !== $_token_server)
            return false;
        return true;
    }

    /**
     * destroy session
     * @return void
     */
    public static function destroy(): void
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
    private static function deleteSessionCookie(): void
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
    public static function clearFlashMessages(): void
    {
        self::$_f3->clear('SESSION._message');
    }

    /**
     * add a session based flash message
     * @param string $text_
     * @param string $type_ info | warning | danger
     * @return void
     */
    public static function addFlashMessage(string $text_, string $type_ = 'info'): void
    {
        self::$_f3->push('SESSION._message', [
            'type' => $type_,
            'text' => $text_,
        ]);
    }

    /**
     * add a session based flash message
     * @return array 
     */
    public static function getFlashMessages(): array
    {
        return (array)self::$_f3->get('SESSION._message');
    }

    /**
     * get service instance
     * @return Session|SQLSession|MongoSession|JigSession|null
     */
    public static function getService(): Session|SQLSession|MongoSession|JigSession|NULL
    {
        return self::$_service;
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
