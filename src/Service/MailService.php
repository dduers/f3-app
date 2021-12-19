<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Prefab;
use SMTP;

final class MailService extends Prefab
{
    static private $_mail;
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = $options_;
    }

    /**
     * get service instance
     * @return SMTP|null
     */
    static public function getService()
    {
        if ((int)self::$_options['enable'] === 1) {
            if (!self::$_mail)
                self::$_mail = new SMTP(
                    self::$_options['host'],
                    self::$_options['port'],
                    self::$_options['scheme'],
                    self::$_options['user'],
                    self::$_options['pass']
                );
            return self::$_mail;
        }
        return NULL;
    }
}
