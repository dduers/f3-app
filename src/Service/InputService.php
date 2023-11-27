<?php

declare(strict_types=1);

namespace Dduers\F3App\Service;

use Base;
use Prefab;
use Dduers\F3App\Iface\ServiceInterface;

final class InputService extends Prefab implements ServiceInterface
{
    private const DEFAULT_OPTIONS = [
        'sanitizer' => [
            'enable' => 0,
            'method' => 'encode',
            'exclude' => []
        ]
    ];
    private static Base $_f3;
    private static array $_options = [];
    private static array $_request_headers = [];

    function __construct(array $options_)
    {
        self::$_f3 = Base::instance();
        self::$_options = array_merge(self::DEFAULT_OPTIONS, $options_);
        foreach (getallheaders() as $header_ => $value_)
            self::$_request_headers[$header_] = $value_;
        self::parseInput();
        if ((int)self::$_options['sanitizer']['enable'] === 1)
            self::sanitizeInput();
    }

    /**
     * get service options
     * @return array
     */
    public static function getOptions(): array
    {
        return self::$_options;
    }

    /**
     * get request headers
     * @return array
     */
    public static function getRequestHeaders(): array
    {
        return self::$_request_headers;
    }

    /**
     * get bearer token from authorization header
     * @return string
     */
    public static function getBearerToken(): string
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
     * @param string $method_
     * @return array
     */
    public static function sanitize(array $subject_, array $exclude_ = [], string $method_ = ''): array
    {
        $_result = $subject_;
        foreach ($subject_ as $key_ => $value_) {
            if (in_array($key_, $exclude_))
                continue;
            $_result[$key_] = $method_ === 'clean' ? self::$_f3->clean($value_) : self::$_f3->encode($value_);
        }
        return /*array_filter(*/ $_result/*)*/;
    }

    /**
     * sanitize input data
     * @return void
     */
    public static function sanitizeInput(): void
    {
        if (self::$_f3->get('GET'))
            self::$_f3->set('GET', self::sanitize(
                self::$_f3->get('GET'),
                [],
                self::$_options['sanitizer']['method']
            ));
        if (self::$_f3->get('POST'))
            self::$_f3->set('POST', self::sanitize(
                self::$_f3->get('POST'),
                self::$_options['sanitizer']['exclude'] ?? [],
                self::$_options['sanitizer']['method']
            ));
        if (self::$_f3->get('PUT'))
            self::$_f3->set('PUT', self::sanitize(
                self::$_f3->get('PUT'),
                self::$_options['sanitizer']['exclude'] ?? [],
                self::$_options['sanitizer']['method']
            ));
        if (self::$_f3->get('DELETE'))
            self::$_f3->set('DELETE', self::sanitize(
                self::$_f3->get('DELETE'),
                self::$_options['sanitizer']['exclude'] ?? [],
                self::$_options['sanitizer']['method']
            ));
        return;
    }

    /**
     * parse input data from various content types and formats to assoc arrays
     * @return void
     */
    public static function parseInput(): void
    {
        switch (self::$_f3->get('VERB')) {
            case 'POST':
                switch (explode(';', self::$_request_headers['Content-Type'])[0] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set('POST', json_decode(file_get_contents("php://input"), true));
                        break;
                    case 'application/x-www-form-urlencoded':
                        // normal operation, nothing todo
                        break;
                    case 'multipart/form-data':
                        // TODO::
                        break;
                    case 'text/plain':
                        // TODO::
                        break;
                }
                break;
            case 'PUT':
                switch (explode(';', self::$_request_headers['Content-Type'])[0] ?? '') {
                    default:
                    case 'application/json':
                        self::$_f3->set('PUT', json_decode(file_get_contents("php://input"), true));
                        break;
                    case 'application/x-www-form-urlencoded':
                        parse_str(file_get_contents("php://input"), $_vars);
                        self::$_f3->set('PUT', $_vars);
                        break;
                    case 'multipart/form-data':
                        // TODO::
                        break;
                    case 'text/plain':
                        // TODO::
                        break;
                }
            case 'DELETE':
                switch (explode(';', self::$_request_headers['Content-Type'])[0] ?? '') {
                    default:
                    case 'application/json':
                        parse_str(file_get_contents("php://input"), $_vars);
                        self::$_f3->set('DELETE', $_vars);
                        // TODO::crossbrowser proofs
                        // self::$_f3->set('DELETE', json_decode(file_get_contents("php://input"), true));
                        break;
                    case 'application/x-www-form-urlencoded':
                        parse_str(file_get_contents("php://input"), $_vars);
                        self::$_f3->set('DELETE', $_vars);
                        break;
                    case 'multipart/form-data':
                        // nothing todo, delete requests have no multiparts
                        break;
                    case 'text/plain':
                        // TODO::
                        break;
                }
        }
    }
}
