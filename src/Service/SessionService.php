<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Cache;
use Prefab;
use Session;
use DB\SQL\Session as SQLSession;
use DB\Mongo\Session as MongoSession;
use DB\Jig\Session as JigSession;
use Dduers\F3App\ServiceInterface;

final class SessionService extends Prefab implements ServiceInterface
{
    static private $_service;
    static private array $_options = [];
    static private $_db;

    function __construct(array $options_)
    {
        self::$_options = $options_;
        self::$_db = DatabaseService::instance()::getService();
        self::init();
    }

    /**
     * init service instance
     * @return void
     */
    static function init(): void
    {
        foreach (self::$_options['cookie']['options'] as $option_ => $value_) {
            if (isset($value_))
                ini_set('session.cookie_' . $option_, (string)$value_);
        }

        switch (strtolower((string)self::$_options['engine'])) {

            case 'sql':
                if (!self::$_db)
                    self::$_service = new Session(NULL, self::$_options['key']);
                else self::$_service = new SQLSession(self::$_db, self::$_options['table'], TRUE, NULL, self::$_options['key']);
                break;

            case 'mongo':
                if (!self::$_db)
                    self::$_service = new Session(NULL, self::$_options['key']);
                else self::$_service = new MongoSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;

            case 'jig':
                if (!self::$_db)
                    self::$_service = new Session(NULL, self::$_options['key']);
                else self::$_service = new JigSession(self::$_db, self::$_options['table'], NULL, self::$_options['key']);
                break;

            case 'cache':
                $_cache = new Cache('folder=' . self::$_options['folder'] . self::$_options['table'] . '/');
                self::$_service = new Session(NULL, self::$_options['key'], $_cache);
                break;

            default:
                self::$_service = new Session(NULL, self::$_options['key']);
                break;
        }
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
