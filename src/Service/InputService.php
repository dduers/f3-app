<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use Dduers\F3App\Iface\ServiceInterface;

final class InputService extends Prefab implements ServiceInterface
{
    static private Base $_f3;
    static private array $_options = [];
    static private array $_request_headers = [];

    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        self::$_options = $options_;
        
        foreach (getallheaders() as $header_ => $value_)
            self::$_request_headers[$header_] = $value_;

        if ((int)self::$_options['parser']['enable'] === 1)
            self::parseInput();

        if ((int)self::$_options['sanitizer']['enable'] === 1)
            self::sanitizeInput();
    }

    /**
     * get service instance
     * @return SanitizerService
     */
    static function getService()
    {
        return self::instance();
    }

    /**
     * get service options
     * @return array
     */
    static function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * get request headers
     * @return array
     */
    static function getRequestHeaders(): array
    {
        return self::$_request_headers;
    }

    /**
     * get bearer token from authorization header
     * @return string
     */
    static function getBearerToken(): string
    {
        $_auth_header_prefix = 'Bearer ';
        $_auth_header = self::$_request_headers['Authorization'] ?? '';
        if (strpos($_auth_header, $_auth_header_prefix) === 0)
            return substr($_auth_header, strlen($_auth_header_prefix));
        return '';
    }

    /**
     * sanitize array of user inputs
     * @param array $subject_ key => pairs to sanitize
     * @param string $exclude_ exclude keys from sanitation
     */
    static function sanitize(array $subject_, array $exclude_ = [])
    {
        $_result = $subject_;
        foreach ($subject_ as $key_ => $value_) {
            if (in_array($key_, $exclude_))
                continue;
            $_result[$key_] = self::$_f3->clean($value_);
        }
        return array_filter($_result);
    }

    /**
     * sanitize input data (strip tags)
     * @return void
     */
    static function sanitizeInput(): void
    {
        switch (self::$_f3->get('VERB')) {
            case 'POST':
                self::$_f3->set('POST', self::sanitize(self::$_f3->get('POST'), self::$_options['sanitizer']['exclude'] ?? []));
                break;
            case 'PUT':
                self::$_f3->set('PUT', self::sanitize(self::$_f3->get('PUT'), self::$_options['sanitizer']['exclude'] ?? []));
                break;
        }
        return;
    }

    /**
     * parse input data from various content types and formats to assoc arrays
     * @return void
     */
    static function parseInput(): void
    {
        switch (self::$_f3->get('VERB')) {
            case 'POST':
                switch (self::$_request_headers['Content-Type'] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set('POST', json_decode(file_get_contents("php://input"), true));
                        break;
                }
                break;
            case 'PUT':
                switch (self::$_request_headers['Content-Type'] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set('PUT', json_decode(file_get_contents("php://input"), true));
                        break;
                }
                /*
                $_body = file_get_contents("php://input");
                parse_str($_body, $_parsed);
                self::vars('PUT', $_parsed);
                */
        }
    }
}
