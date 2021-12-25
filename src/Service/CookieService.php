<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Prefab;

final class CookieService extends Prefab
{
    private const DEFAULT_OPTIONS = [
        'options' => [
            'lifetime' => 0,
            'path' => '',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => ''
        ]
    ];
    static private array $_options = [];

    function __construct(array $options_)
    {
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
    }

    /**
     * issue a cookie
     * @param string $name_
     * @param string $value_
     * @param array $options_ override default options
     * @return array options used to issue the cookie
     */
    static function setCookie(string $name_, string $value_, array $options_ = []): array
    {
        $_options = array_merge(self::$_options['options'], $options_);
        setcookie($name_, $value_, array_filter([
            'expires' => (string)($_options['lifetime'] ?? '') ? (string)(time() + (int)$_options['lifetime']) : NULL,
            'domain' => (string)($_options['domain'] ?? '') ?: NULL,
            'httponly' => (string)($_options['httponly'] ?? '') ?: NULL,
            'secure' => (string)($_options['secure'] ?? '') ?: NULL,
            'path' => (string)($_options['path'] ?? '') ?: NULL,
            'samesite' => (string)($_options['samesite'] ?? '') ?: NULL,
        ]));
        return $_options;
    }

    /**
     * set cookie options (will be merged with defaults)
     * @param array $options_
     * @return array final cookie options
     */
    static function setOptions(array $options_): array
    {
        return self::$_options = array_merge(self::$_options, $options_);
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
