<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use DB\Jig;
use DB\Mongo;
use DB\SQL;
use Prefab;

final class DatabaseService extends Prefab
{
    static private $_service;
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = $options_;
        self::init();
    }

    /**
     * init service instance
     * @return void
     */
    static private function init(): void
    {
        if ((int)self::$_options['enable'] === 1) {

            switch (strtolower(self::$_options['engine'])) {

                default:
                    self::$_service = NULL;
                    break;

                case 'sql':
                    self::$_service = new SQL(
                        self::$_options['type']
                            . ':host=' . self::$_options['host']
                            . ';port=' . self::$_options['port']
                            . ';dbname=' . self::$_options['data'],
                        self::$_options['user'],
                        self::$_options['pass']
                    );
                    break;

                case 'jig':
                    self::$_service = new Jig(
                        self::$_options['folder'] . self::$_options['data'] . '/',
                        Jig::FORMAT_JSON
                    );
                    break;

                case 'mongo':
                    self::$_service = new Mongo(
                        'mongodb://'
                            . self::$_options['host']
                            . ':'
                            . self::$_options['port'],
                        self::$_options['data'],
                        NULL
                    );
                    break;
            }
        }
    }

    /**
     * get service instance
     * @return SQL|Mongo|Jig|null
     */
    static public function getService()
    {
        return self::$_service;
    }

    /**
     * get service options
     * @return array
     */
    static public function getOptions(): array
    {
        return self::$_options;
    }
}
