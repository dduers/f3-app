<?php

declare(strict_types=1);

namespace Dduers\F3App\Utility;

use Prefab;

final class CookieUtility extends Prefab
{
    static private array $_options = [];

    function __construct()
    {
    }

    /**
     * issue a cookie
     * @param string $name_
     * @param string $value_
     * @param array $options_ override default options
     * @return array options used to issue the cookie
     */
    static public function setCookie(string $name_, string $value_, array $options_ = []): array
    {
        $_options = array_merge(self::$_options, $options_);

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
     * deletes php session cookie
     */
    static public function deleteSessionCookie()
    {
        if (ini_get('session.use_cookies')) {
            $_params = session_get_cookie_params();
            self::setCookie(
                session_name(),
                '',
                [
                    'lifetime' => -(3600 * 24 * 365 * 10),
                    'path' => $_params['path'],
                    'domain' => $_params['domain'],
                    'secure' => $_params['secure'],
                    'httponly' => $_params['httponly']
                ]
            );
        }
    }

    /**
     * set cookie default options
     * @param array $options_
     * @return void
     */
    static public function setOptions(array $options_): void
    {
        self::$_options = array_filter($options_);
    }
}
