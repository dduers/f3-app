<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use DB\Jig;
use DB\Mongo;
use DB\SQL;
use Prefab;

final class DatabaseService extends Prefab
{
    static private $_db;
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = $options_;
    }

    /**
     * get service instance
     * @return SQL|Mongo|Jig
     */
    static public function getService()
    {
        if ((int)self::$_options['enable'] === 1) {

            if (!self::$_db)
                switch (strtolower(self::$_options['type'])) {

                    case 'sql':
                        self::$_db = new SQL(
                            self::$_options['type']
                                . ':host=' . self::$_options['host']
                                . ';port=' . self::$_options['port']
                                . ';dbname=' . self::$_options['data'],
                            self::$_options['user'],
                            self::$_options['pass']
                        );
                        break;

                    case 'jig':
                        self::$_db = new Jig(self::$_options['folder'] . self::$_options['data'] . '/', Jig::FORMAT_JSON);
                        break;

                    case 'mongo':
                        self::$_db = new Mongo('mongodb://' . self::$_options['host'] . ':' . self::$_options['port'], self::$_options['data'], NULL);
                        break;
                }

            return self::$_db;
        }

        return NULL;
    }

    static public function getOptions(): array
    {
        return self::$_options;
    }
}
