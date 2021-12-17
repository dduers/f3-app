<?php

declare(strict_types=1);

namespace Dduers\F3App\Utility;

use Prefab;

final class CookieUtility extends Prefab
{
    static private array $_options = [];

    function __construct(array $options_ = [])
    {
        self::$_options = $options_;
    }

    static public function setCookie(string $name_, string $value_): array
    {
        $_options = array_filter([
            'expires' => (string)(self::$_options['lifetime'] ?? '') ? (string)(time() + (int)self::$_options['lifetime']) : NULL,
            'domain' => (string)(self::$_options['domain'] ?? '') ?: NULL,
            'httponly' => (string)(self::$_options['httponly'] ?? '') ?: NULL,
            'secure' => (string)(self::$_options['secure'] ?? '') ?: NULL,
            'path' => (string)(self::$_options['path'] ?? '') ?: NULL,
            'samesite' => (string)(self::$_options['samesite'] ?? '') ?: NULL,
        ]);

        setcookie($name_, $value_, $_options);
        return $_options;
    }

    /**
     * set cookie options
     */
    static public function setOptions(array $options_)
    {
        self::$_options = array_filter($options_);
    }
    





}